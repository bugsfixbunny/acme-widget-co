<?php

declare(strict_types=1);

namespace Acme\Offer;

use Acme\Product;

/**
 * A special offer, expressed as money off the basket.
 *
 * Offers return a discount rather than rewriting prices, so several can apply
 * to the same basket and the basket itself stays unaware of what each one does.
 */
interface Offer
{
    /**
     * @param list<Product> $products everything currently in the basket
     *
     * @return int the discount in whole cents, zero when the offer does not apply
     */
    public function discountFor(array $products): int;
}
