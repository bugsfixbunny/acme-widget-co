<?php

declare(strict_types=1);

namespace Acme;

use InvalidArgumentException;

/**
 * A single product in the catalogue.
 *
 * Prices are supplied in dollars, as they appear on the price list, and held
 * internally as whole cents so that no later arithmetic touches a float.
 */
final readonly class Product
{
    /** The price in whole cents, e.g. 3295 for $32.95. */
    public int $priceInCents;

    public function __construct(
        public string $code,
        public string $name,
        float $price,
    ) {
        if (trim($code) === '') {
            throw new InvalidArgumentException('Product code cannot be empty.');
        }

        if (!is_finite($price) || $price < 0) {
            throw new InvalidArgumentException(
                sprintf('Price for product "%s" must be a non-negative amount, got %s.', $code, var_export($price, true)),
            );
        }

        // round(), never a plain (int) cast: (int) (1.15 * 100) is 114, not 115.
        $this->priceInCents = (int) round($price * 100);
    }
}
