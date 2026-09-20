<?php

declare(strict_types=1);

namespace Acme\Tests;

use Acme\Basket;
use Acme\Delivery\DeliveryBand;
use Acme\Delivery\DeliveryChargeRules;
use Acme\Delivery\ThresholdDeliveryRules;
use Acme\Exception\UnknownProductException;
use Acme\Offer\BuyOneGetSecondHalfPriceOffer;
use Acme\Offer\Offer;
use Acme\Product;
use Acme\ProductCatalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Basket::class)]
final class BasketTest extends TestCase
{
    public function test_it_totals_the_products_it_was_given(): void
    {
        $basket = $this->basket();
        $basket->add('B01');
        $basket->add('B01');

        // $7.95 x 2, plus $4.95 delivery.
        self::assertSame(2085, $basket->totalInCents());
    }

    public function test_it_returns_the_total_in_dollars(): void
    {
        $basket = $this->basket();
        $basket->add('G01');

        self::assertSame(29.90, $basket->total());
    }

    public function test_an_empty_basket_costs_nothing(): void
    {
        self::assertSame(0.0, $this->basket()->total());
        self::assertSame(0, $this->basket()->totalInCents());
    }

    public function test_the_same_product_can_be_added_repeatedly(): void
    {
        $basket = $this->basket();

        foreach (['B01', 'B01', 'B01'] as $code) {
            $basket->add($code);
        }

        // $7.95 x 3, plus $4.95 delivery.
        self::assertSame(2880, $basket->totalInCents());
    }

    public function test_it_rejects_an_unknown_product_code_when_it_is_added(): void
    {
        $this->expectException(UnknownProductException::class);
        $this->expectExceptionMessage('Unknown product code "Z99".');

        $this->basket()->add('Z99');
    }

    public function test_offers_are_applied_before_delivery_is_worked_out(): void
    {
        $basket = $this->basket();
        $basket->add('R01');
        $basket->add('R01');

        // $49.42 after the offer, which is under $50, so delivery is $4.95.
        // Banding on the undiscounted $65.90 would have charged $2.95.
        self::assertSame(4942 + 495, $basket->totalInCents());
    }

    public function test_it_works_without_any_offers(): void
    {
        $basket = new Basket($this->catalogue(), $this->deliveryRules());
        $basket->add('R01');
        $basket->add('R01');

        self::assertSame(6590 + 295, $basket->totalInCents());
    }

    public function test_every_offer_it_was_given_is_applied(): void
    {
        $basket = new Basket(
            $this->catalogue(),
            $this->deliveryRules(),
            new BuyOneGetSecondHalfPriceOffer('R01'),
            new BuyOneGetSecondHalfPriceOffer('G01'),
        );

        foreach (['R01', 'R01', 'G01', 'G01'] as $code) {
            $basket->add($code);
        }

        // $65.90 + $49.90, less $16.48 and $12.48, is $86.84 — delivery $2.95.
        self::assertSame(8684 + 295, $basket->totalInCents());
    }

    public function test_it_asks_the_delivery_rules_for_the_discounted_subtotal(): void
    {
        $spy = new class implements DeliveryChargeRules {
            public ?int $subtotalSeen = null;

            public function chargeFor(int $subtotalInCents): int
            {
                $this->subtotalSeen = $subtotalInCents;

                return 0;
            }
        };

        $basket = new Basket($this->catalogue(), $spy, new BuyOneGetSecondHalfPriceOffer('R01'));
        $basket->add('R01');
        $basket->add('R01');
        $basket->totalInCents();

        self::assertSame(4942, $spy->subtotalSeen);
    }

    public function test_an_offer_that_never_applies_changes_nothing(): void
    {
        $noOp = new class implements Offer {
            public function discountFor(array $products): int
            {
                return 0;
            }
        };

        $basket = new Basket($this->catalogue(), $this->deliveryRules(), $noOp);
        $basket->add('G01');

        self::assertSame(2495 + 495, $basket->totalInCents());
    }

    private function basket(): Basket
    {
        return new Basket(
            $this->catalogue(),
            $this->deliveryRules(),
            new BuyOneGetSecondHalfPriceOffer('R01'),
        );
    }

    private function catalogue(): ProductCatalogue
    {
        return new ProductCatalogue(
            new Product('R01', 'Red Widget', 32.95),
            new Product('G01', 'Green Widget', 24.95),
            new Product('B01', 'Blue Widget', 7.95),
        );
    }

    private function deliveryRules(): ThresholdDeliveryRules
    {
        return new ThresholdDeliveryRules(
            new DeliveryBand(spendAtLeast: 0.00, cost: 4.95),
            new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
            new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
        );
    }
}
