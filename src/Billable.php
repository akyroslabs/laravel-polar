<?php

namespace AkyrosLabs\Polar;

use AkyrosLabs\Polar\Concerns\ManagesCustomer;
use AkyrosLabs\Polar\Concerns\ManagesCheckouts;
use AkyrosLabs\Polar\Concerns\ManagesSubscription;
use AkyrosLabs\Polar\Concerns\ManagesOrders;
use AkyrosLabs\Polar\Concerns\ManagesBenefits;
use AkyrosLabs\Polar\Concerns\ManagesUsage;
use AkyrosLabs\Polar\Concerns\ManagesPlanLimits;

trait Billable
{
    use ManagesCustomer;
    use ManagesCheckouts;
    use ManagesSubscription;
    use ManagesOrders;
    use ManagesBenefits;
    use ManagesUsage;
    use ManagesPlanLimits;
}
