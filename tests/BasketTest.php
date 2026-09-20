<?php

declare(strict_types=1);

namespace Acme\Tests;

use Acme\Basket;
use Acme\Delivery\DeliveryChargeRules;
use Acme\Exception\UnknownProductException;
use Acme\Offer\BuyOneGetSecondHalfPriceOffer;
use Acme\Offer\Offer;
use Acme\Product;
use Acme\ProductCatalogue;
use Acme\Tests\Fixtures\AcmeShop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Basket::class)]
final class BasketTest extends TestCase
{
    public function test_it_totals_the_products_it_was_given(): void
    {
        $basket = AcmeShop::basket();
        $basket->add('B01');
        $basket->add('B01');

        // $7.95 x 2, plus $4.95 delivery.
        self::assertSame(2085, $basket->totalInCents());
    }

    public function test_it_returns_the_total_in_dollars(): void
    {
        $basket = AcmeShop::basket();
        $basket->add('G01');

        self::assertSame(29.90, $basket->total());
    }

    public function test_an_empty_basket_costs_nothing(): void
    {
        self::assertSame(0.0, AcmeShop::basket()->total());
        self::assertSame(0, AcmeShop::basket()->totalInCents());
    }

    public function test_the_same_product_can_be_added_repeatedly(): void
    {
        $basket = AcmeShop::basket();

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

        AcmeShop::basket()->add('Z99');
    }

    public function test_offers_are_applied_before_delivery_is_worked_out(): void
    {
        $basket = AcmeShop::basket();
        $basket->add('R01');
        $basket->add('R01');

        // $49.42 after the offer, which is under $50, so delivery is $4.95.
        // Banding on the undiscounted $65.90 would have charged $2.95.
        self::assertSame(4942 + 495, $basket->totalInCents());
    }

    /**
     * Three red widgets come to $98.85, which would qualify for free delivery,
     * but the offer brings the order down to $82.37 and delivery is charged.
     */
    public function test_a_discount_can_drop_an_order_out_of_free_delivery(): void
    {
        $basket = AcmeShop::basket();

        foreach (['R01', 'R01', 'R01'] as $code) {
            $basket->add($code);
        }

        self::assertSame(8237 + 295, $basket->totalInCents());
    }

    /**
     * Acme's own prices cannot add up to exactly $50 or $90, so the boundaries
     * are exercised here with a catalogue that can reach them.
     */
    public function test_an_order_landing_exactly_on_a_band_boundary_takes_the_cheaper_band(): void
    {
        $catalogue = new ProductCatalogue(
            new Product('H01', 'Half Century Widget', 25.00),
            new Product('N01', 'Ninety Widget', 45.00),
        );

        $exactlyFifty = new Basket($catalogue, AcmeShop::deliveryRules());
        $exactlyFifty->add('H01');
        $exactlyFifty->add('H01');

        $exactlyNinety = new Basket($catalogue, AcmeShop::deliveryRules());
        $exactlyNinety->add('N01');
        $exactlyNinety->add('N01');

        self::assertSame(5000 + 295, $exactlyFifty->totalInCents(), '$50.00 exactly pays $2.95.');
        self::assertSame(9000, $exactlyNinety->totalInCents(), '$90.00 exactly ships free.');
    }

    public function test_it_works_without_any_offers(): void
    {
        $basket = new Basket(AcmeShop::catalogue(), AcmeShop::deliveryRules());
        $basket->add('R01');
        $basket->add('R01');

        self::assertSame(6590 + 295, $basket->totalInCents());
    }

    public function test_every_offer_it_was_given_is_applied(): void
    {
        $basket = new Basket(
            AcmeShop::catalogue(),
            AcmeShop::deliveryRules(),
            AcmeShop::redWidgetOffer(),
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

        $basket = new Basket(AcmeShop::catalogue(), $spy, AcmeShop::redWidgetOffer());
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

        $basket = new Basket(AcmeShop::catalogue(), AcmeShop::deliveryRules(), $noOp);
        $basket->add('G01');

        self::assertSame(2495 + 495, $basket->totalInCents());
    }
}
