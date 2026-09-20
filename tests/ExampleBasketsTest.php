<?php

declare(strict_types=1);

namespace Acme\Tests;

use Acme\Basket;
use Acme\Delivery\ThresholdDeliveryRules;
use Acme\Offer\BuyOneGetSecondHalfPriceOffer;
use Acme\ProductCatalogue;
use Acme\Tests\Fixtures\AcmeShop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The example baskets given in the specification, end to end: real catalogue,
 * real delivery rules, real offer.
 */
#[CoversClass(Basket::class)]
#[CoversClass(ProductCatalogue::class)]
#[CoversClass(ThresholdDeliveryRules::class)]
#[CoversClass(BuyOneGetSecondHalfPriceOffer::class)]
final class ExampleBasketsTest extends TestCase
{
    /**
     * The interface the specification asks for: a total in dollars.
     *
     * @param list<string> $productCodes
     */
    #[DataProvider('exampleBaskets')]
    public function test_it_totals_the_documented_baskets(array $productCodes, float $expectedTotal): void
    {
        self::assertSame($expectedTotal, self::basketOf($productCodes)->total());
    }

    /**
     * The same totals in whole cents, which cannot be affected by how floating
     * point compares.
     *
     * @param list<string> $productCodes
     */
    #[DataProvider('exampleBaskets')]
    public function test_it_totals_the_documented_baskets_to_the_cent(
        array $productCodes,
        float $expectedTotal,
        int $expectedCents,
    ): void {
        self::assertSame($expectedCents, self::basketOf($productCodes)->totalInCents());
    }

    /**
     * Straight from the specification's table.
     *
     * @return array<string, array{list<string>, float, int}>
     */
    public static function exampleBaskets(): array
    {
        return [
            'B01, G01' => [['B01', 'G01'], 37.85, 3785],
            'R01, R01' => [['R01', 'R01'], 54.37, 5437],
            'R01, G01' => [['R01', 'G01'], 60.85, 6085],
            'B01, B01, R01, R01, R01' => [['B01', 'B01', 'R01', 'R01', 'R01'], 98.27, 9827],
        ];
    }

    /**
     * @param list<string> $productCodes
     */
    private static function basketOf(array $productCodes): Basket
    {
        $basket = AcmeShop::basket();

        foreach ($productCodes as $code) {
            $basket->add($code);
        }

        return $basket;
    }

    /**
     * The order of the codes should not change what the customer pays.
     */
    public function test_the_order_products_are_added_does_not_matter(): void
    {
        $asListed = AcmeShop::basket();
        $shuffled = AcmeShop::basket();

        foreach (['B01', 'B01', 'R01', 'R01', 'R01'] as $code) {
            $asListed->add($code);
        }

        foreach (['R01', 'B01', 'R01', 'B01', 'R01'] as $code) {
            $shuffled->add($code);
        }

        self::assertSame(98.27, $asListed->total());
        self::assertSame($asListed->total(), $shuffled->total());
    }
}
