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
    /**
     * The largest amount this system handles.
     *
     * Bounding amounts is simpler and safer than defending the integer limit.
     * The old guard let exactly PHP_INT_MAX / 100 through, where the
     * multiplication tipped the cast over into a negative number, and even
     * individually valid amounts could overflow once a basket summed them. At
     * a million dollars apiece a basket would need some 92 billion products
     * before its total in cents could overflow, which no basket can hold.
     */
    public const MAXIMUM_DOLLARS = 1_000_000.0;

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

        if ($dollars > self::MAXIMUM_DOLLARS) {
            throw new InvalidArgumentException(sprintf(
                '%s must not be more than $%s, got %s.',
                $description,
                number_format(self::MAXIMUM_DOLLARS, 2),
                var_export($dollars, true),
            ));
        }

        // round(), never a plain (int) cast: (int) (1.15 * 100) is 114, not 115.
        return (int) round($dollars * 100);
    }
}
