<?php

declare(strict_types=1);

namespace Acme\Delivery;

/**
 * Works out what delivery costs for a given order value.
 *
 * The basket depends on this interface rather than on any particular pricing
 * scheme, so Acme can change how delivery is charged without touching it.
 */
interface DeliveryChargeRules
{
    /**
     * @param int $subtotalInCents the order value after any offers have been applied
     *
     * @return int the delivery charge in whole cents
     */
    public function chargeFor(int $subtotalInCents): int;
}
