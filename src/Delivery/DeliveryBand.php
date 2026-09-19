<?php

declare(strict_types=1);

namespace Acme\Delivery;

use Acme\Cents;

/**
 * One rung of the delivery pricing ladder: spend this much, pay this to ship.
 *
 * The threshold is inclusive, so a band of $90 applies to an order of exactly
 * $90.00 — "orders of $90 or more have free delivery".
 */
final readonly class DeliveryBand
{
    public int $spendAtLeastInCents;

    public int $costInCents;

    public function __construct(float $spendAtLeast, float $cost)
    {
        $this->spendAtLeastInCents = Cents::fromDollars($spendAtLeast, 'Delivery band threshold');
        $this->costInCents = Cents::fromDollars($cost, 'Delivery cost');
    }
}
