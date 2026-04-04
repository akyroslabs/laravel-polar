<?php

namespace AkyrosLabs\Polar\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   ->middleware('subscribed')           // any valid subscription
     *   ->middleware('subscribed:pro')        // must be on 'pro' plan
     */
    public function handle(Request $request, Closure $next, ?string $plan = null): Response
    {
        $billable = $this->resolveBillable($request);

        if (!$billable) {
            return $this->deny($request);
        }

        if ($plan) {
            // Check if the billable is on the specified plan
            $currentPlan = method_exists($billable, 'planName') ? $billable->planName() : null;
            if ($currentPlan !== $plan) {
                return $this->deny($request);
            }
        }

        // Check for any valid subscription
        if (!method_exists($billable, 'subscribed') || !$billable->subscribed()) {
            // Also allow generic trial
            $onTrial = method_exists($billable, 'onTrial') && $billable->onTrial();
            if (!$onTrial) {
                return $this->deny($request);
            }
        }

        return $next($request);
    }

    /**
     * Resolve the billable model from the authenticated user.
     * Supports direct billable (User) or tenant relationship.
     */
    private function resolveBillable(Request $request): ?object
    {
        $user = $request->user();
        if (!$user) return null;

        // If user itself is billable (has subscriptions relationship)
        if (method_exists($user, 'subscriptions')) {
            return $user;
        }

        // Try tenant / team relationship
        if (method_exists($user, 'currentTeam') && $user->currentTeam) {
            return $user->currentTeam;
        }

        if (method_exists($user, 'tenant') && $user->tenant) {
            return $user->tenant;
        }

        return null;
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Subscription required.'], 403);
        }

        return redirect()->route('billing')->with('error', 'An active subscription is required.');
    }
}
