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
     * @param list<string> $productCodes
     */
    #[DataProvider('exampleBaskets')]
    public function test_it_totals_the_documented_baskets(array $productCodes, float $expectedTotal): void
    {
        $basket = AcmeShop::basket();

        foreach ($productCodes as $code) {
            $basket->add($code);
        }

        self::assertSame($expectedTotal, $basket->total());
    }

    /**
     * Straight from the specification's table.
     *
     * @return array<string, array{list<string>, float}>
     */
    public static function exampleBaskets(): array
    {
        return [
            'B01, G01' => [['B01', 'G01'], 37.85],
            'R01, R01' => [['R01', 'R01'], 54.37],
            'R01, G01' => [['R01', 'G01'], 60.85],
            'B01, B01, R01, R01, R01' => [['B01', 'B01', 'R01', 'R01', 'R01'], 98.27],
        ];
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
