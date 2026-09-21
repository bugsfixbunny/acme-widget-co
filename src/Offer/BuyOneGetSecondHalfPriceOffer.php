<?php

declare(strict_types=1);

namespace Acme\Offer;

use Acme\Product;
use InvalidArgumentException;

/**
 * Buy one of a product, get the second half price.
 *
 * The offer repeats: four red widgets means two of them are half price.
 */
final readonly class BuyOneGetSecondHalfPriceOffer implements Offer
{
    public function __construct(public string $productCode)
    {
        if (trim($productCode) === '') {
            throw new InvalidArgumentException('An offer needs a product code to apply to.');
        }
    }

    public function discountFor(array $products): int
    {
        $matching = array_values(
            array_filter($products, fn (Product $product): bool => $product->code === $this->productCode),
        );

        $pairs = intdiv(count($matching), 2);

        if ($pairs === 0) {
            return 0;
        }

        $fullPrice = $matching[0]->priceInCents;

        // Half of an odd number of cents cannot be paid, so the half-price item
        // is rounded down to the cent and the customer keeps the odd half cent:
        // $32.95 becomes $16.47, making the discount $16.48 rather than
        // $16.475. That is what makes two red widgets come to $49.42 rather
        // than $49.43.
        $halfPrice = intdiv($fullPrice, 2);

        return $pairs * ($fullPrice - $halfPrice);
    }
}
