<?php

declare(strict_types=1);

namespace Acme\Delivery;

use InvalidArgumentException;
use LogicException;

/**
 * Delivery priced in bands, where the highest threshold an order reaches wins.
 *
 * Acme's current rules read as three bands:
 *
 *     new ThresholdDeliveryRules(
 *         new DeliveryBand(spendAtLeast: 0.00,  cost: 4.95),
 *         new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
 *         new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
 *     );
 *
 * Every band states the spend it applies from, so there is no implicit
 * "and everything else is free" rule hiding in the implementation.
 */
final readonly class ThresholdDeliveryRules implements DeliveryChargeRules
{
    /** @var list<DeliveryBand> ordered by threshold, highest first */
    private array $bands;

    public function __construct(DeliveryBand ...$bands)
    {
        if ($bands === []) {
            throw new InvalidArgumentException('At least one delivery band is required.');
        }

        $byThreshold = [];

        foreach ($bands as $band) {
            if (isset($byThreshold[$band->spendAtLeastInCents])) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate delivery band for a spend of %d cents.', $band->spendAtLeastInCents),
                );
            }

            $byThreshold[$band->spendAtLeastInCents] = $band;
        }

        if (!isset($byThreshold[0])) {
            throw new InvalidArgumentException(
                'Delivery bands must include one starting at $0.00, otherwise small orders have no charge.',
            );
        }

        // Highest threshold first, so the first band an order reaches is the
        // best one it qualifies for.
        krsort($byThreshold);

        $this->bands = array_values($byThreshold);
    }

    public function chargeFor(int $subtotalInCents): int
    {
        if ($subtotalInCents < 0) {
            throw new InvalidArgumentException(
                sprintf('Cannot charge delivery on a negative subtotal of %d cents.', $subtotalInCents),
            );
        }

        foreach ($this->bands as $band) {
            if ($subtotalInCents >= $band->spendAtLeastInCents) {
                return $band->costInCents;
            }
        }

        // The constructor guarantees a band starting at $0.00, so this is unreachable.
        throw new LogicException(sprintf('No delivery band covers a subtotal of %d cents.', $subtotalInCents));
    }
}
