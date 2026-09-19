<?php

declare(strict_types=1);

namespace Acme;

use InvalidArgumentException;

/**
 * Converts the dollar amounts used to configure the system into the whole
 * cents everything is calculated in.
 */
final class Cents
{
    private function __construct()
    {
    }

    /**
     * @param string $description names the amount, so a bad value reports where it came from
     *
     * @throws InvalidArgumentException when the amount is negative or not a real number
     */
    public static function fromDollars(float $dollars, string $description): int
    {
        if (!is_finite($dollars) || $dollars < 0) {
            throw new InvalidArgumentException(
                sprintf('%s must be a non-negative amount, got %s.', $description, var_export($dollars, true)),
            );
        }

        // round(), never a plain (int) cast: (int) (1.15 * 100) is 114, not 115.
        return (int) round($dollars * 100);
    }
}
