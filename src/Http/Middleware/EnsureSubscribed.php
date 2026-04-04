<?php

namespace AkyrosLabs\Polar\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next, ?string $plan = null): mixed
    {
        $billable = $this->getBillable($request);

        if (!$billable) {
            abort(403, 'No billable account found.');
        }

        if ($plan) {
            if (!$billable->onPlan($plan)) {
                abort(403, "This feature requires the {$plan} plan.");
            }
        } else {
            if (!$billable->subscribed()) {
                return redirect()->route('polar.checkout');
            }
        }

        return $next($request);
    }

    private function getBillable(Request $request): mixed
    {
        $user = $request->user();
        if (!$user) return null;

        // If the billable model is the user itself
        $billableModel = config('polar.billable_model');
        if ($user instanceof $billableModel) {
            return $user;
        }

        // Otherwise try tenant relationship
        if (method_exists($user, 'tenant')) {
            return $user->tenant;
        }

        // Try billable_model from user's property
        if (property_exists($user, 'tenant_id') && $user->tenant_id) {
            return $billableModel::find($user->tenant_id);
        }

        return null;
    }
}
