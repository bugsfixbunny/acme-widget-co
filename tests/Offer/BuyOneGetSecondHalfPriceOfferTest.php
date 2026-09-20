<?php

declare(strict_types=1);

namespace Acme\Tests\Offer;

use Acme\Offer\BuyOneGetSecondHalfPriceOffer;
use Acme\Product;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuyOneGetSecondHalfPriceOffer::class)]
final class BuyOneGetSecondHalfPriceOfferTest extends TestCase
{
    private const RED_WIDGET_PRICE = 32.95;

    private BuyOneGetSecondHalfPriceOffer $offer;

    protected function setUp(): void
    {
        $this->offer = new BuyOneGetSecondHalfPriceOffer('R01');
    }

    #[DataProvider('redWidgetCounts')]
    public function test_it_discounts_one_widget_for_every_pair(int $redWidgets, int $expectedDiscount): void
    {
        self::assertSame($expectedDiscount, $this->offer->discountFor(self::redWidgets($redWidgets)));
    }

    /** @return array<string, array{int, int}> */
    public static function redWidgetCounts(): array
    {
        return [
            'none'  => [0, 0],
            'one'   => [1, 0],
            'two'   => [2, 1648],
            'three' => [3, 1648],
            'four'  => [4, 3296],
            'five'  => [5, 3296],
            'six'   => [6, 4944],
        ];
    }

    /**
     * Half of $32.95 is $16.475, which cannot be paid. The half-price widget is
     * rounded down to $16.47, so the discount is $16.48 and the pair costs
     * $49.42. Rounding the other way would make the documented basket totals
     * $54.38 and $98.28 instead of $54.37 and $98.27.
     */
    public function test_the_odd_half_cent_is_kept_by_the_shop(): void
    {
        $pair = self::redWidgets(2);
        $discount = $this->offer->discountFor($pair);
        $pairCosts = array_sum(array_map(static fn (Product $p): int => $p->priceInCents, $pair)) - $discount;

        self::assertSame(1648, $discount, 'The discount rounds up, not down.');
        self::assertSame(4942, $pairCosts, 'Two red widgets cost $49.42.');
    }

    public function test_an_evenly_halved_price_loses_no_cents(): void
    {
        $offer = new BuyOneGetSecondHalfPriceOffer('E01');
        $evenlyPriced = [new Product('E01', 'Even Widget', 10.00), new Product('E01', 'Even Widget', 10.00)];

        self::assertSame(500, $offer->discountFor($evenlyPriced));
    }

    public function test_it_ignores_products_the_offer_does_not_apply_to(): void
    {
        $basket = [
            new Product('G01', 'Green Widget', 24.95),
            new Product('G01', 'Green Widget', 24.95),
            new Product('B01', 'Blue Widget', 7.95),
            ...self::redWidgets(2),
        ];

        self::assertSame(1648, $this->offer->discountFor($basket));
    }

    public function test_it_applies_to_whichever_product_it_is_configured_for(): void
    {
        $greenOffer = new BuyOneGetSecondHalfPriceOffer('G01');
        $greens = [new Product('G01', 'Green Widget', 24.95), new Product('G01', 'Green Widget', 24.95)];

        self::assertSame(1248, $greenOffer->discountFor($greens), 'Half of $24.95 rounds down to $12.47.');
        self::assertSame(0, $this->offer->discountFor($greens), 'The red widget offer leaves green widgets alone.');
    }

    public function test_an_empty_basket_earns_no_discount(): void
    {
        self::assertSame(0, $this->offer->discountFor([]));
    }

    public function test_it_requires_a_product_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An offer needs a product code to apply to.');

        new BuyOneGetSecondHalfPriceOffer('  ');
    }

    /** @return list<Product> */
    private static function redWidgets(int $quantity): array
    {
        return array_fill(0, $quantity, new Product('R01', 'Red Widget', self::RED_WIDGET_PRICE));
    }
}
