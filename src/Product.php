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

        $this->priceInCents = Cents::fromDollars($price, sprintf('Price for product "%s"', $code));
    }
}
