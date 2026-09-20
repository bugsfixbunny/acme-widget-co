<?php

declare(strict_types=1);

namespace Acme;

use Acme\Delivery\DeliveryChargeRules;
use Acme\Exception\DiscountExceedsBasketException;
use Acme\Exception\NegativeAmountException;
use Acme\Exception\UnknownProductException;
use Acme\Offer\Offer;

/**
 * A customer's basket.
 *
 * Initialised with the catalogue, the delivery charge rules and any offers;
 * products go in by code and the total accounts for both.
 */
final class Basket
{
    /** @var list<Product> */
    private array $products = [];

    /** @var list<Offer> */
    private readonly array $offers;

    public function __construct(
        private readonly ProductCatalogue $catalogue,
        private readonly DeliveryChargeRules $deliveryRules,
        Offer ...$offers,
    ) {
        // array_values(), because a named argument would give the spread a
        // string key and this is declared as a list.
        $this->offers = array_values($offers);
    }

    /**
     * @throws UnknownProductException when the code is not in the catalogue
     */
    public function add(string $productCode): void
    {
        // Resolved now rather than at checkout, so a bad code fails where the
        // caller can still see which add() caused it.
        $this->products[] = $this->catalogue->get($productCode);
    }

    /**
     * The total in dollars, including delivery and any offers.
     */
    public function total(): float
    {
        return $this->totalInCents() / 100;
    }

    /**
     * The same total in whole cents, for callers that cannot afford a float.
     */
    public function totalInCents(): int
    {
        if ($this->products === []) {
            // Nothing has been bought, so there is nothing to deliver.
            return 0;
        }

        $subtotal = array_sum(array_map(
            static fn (Product $product): int => $product->priceInCents,
            $this->products,
        ));

        $discount = $this->totalDiscount();

        if ($discount > $subtotal) {
            // Caught here rather than left to the delivery rules, which would
            // otherwise report a negative subtotal and blame the wrong thing.
            throw DiscountExceedsBasketException::of($subtotal, $discount);
        }

        $subtotal -= $discount;

        return $subtotal + $this->deliveryCharge($subtotal);
    }

    private function totalDiscount(): int
    {
        $discount = 0;

        foreach ($this->offers as $offer) {
            $offered = $offer->discountFor($this->products);

            if ($offered < 0) {
                throw NegativeAmountException::discountFrom($offer::class, $offered);
            }

            $discount += $offered;
        }

        return $discount;
    }

    private function deliveryCharge(int $subtotalInCents): int
    {
        $charge = $this->deliveryRules->chargeFor($subtotalInCents);

        if ($charge < 0) {
            throw NegativeAmountException::deliveryChargeFrom($this->deliveryRules::class, $charge);
        }

        return $charge;
    }
}
