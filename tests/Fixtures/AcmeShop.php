<?php

declare(strict_types=1);

namespace Acme\Tests\Fixtures;

use Acme\Basket;
use Acme\Delivery\DeliveryBand;
use Acme\Delivery\ThresholdDeliveryRules;
use Acme\Offer\BuyOneGetSecondHalfPriceOffer;
use Acme\Product;
use Acme\ProductCatalogue;

/**
 * Acme's shop exactly as the specification describes it, so that every test
 * measures the same configuration and there is one place to change when the
 * price list does.
 */
final class AcmeShop
{
    private function __construct()
    {
    }

    public static function catalogue(): ProductCatalogue
    {
        return new ProductCatalogue(
            new Product('R01', 'Red Widget', 32.95),
            new Product('G01', 'Green Widget', 24.95),
            new Product('B01', 'Blue Widget', 7.95),
        );
    }

    /**
     * Under $50 costs $4.95, under $90 costs $2.95, $90 or more is free.
     */
    public static function deliveryRules(): ThresholdDeliveryRules
    {
        return new ThresholdDeliveryRules(
            new DeliveryBand(spendAtLeast: 0.00, cost: 4.95),
            new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
            new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
        );
    }

    public static function redWidgetOffer(): BuyOneGetSecondHalfPriceOffer
    {
        return new BuyOneGetSecondHalfPriceOffer('R01');
    }

    /**
     * A basket wired up the way Acme currently sells.
     */
    public static function basket(): Basket
    {
        return new Basket(self::catalogue(), self::deliveryRules(), self::redWidgetOffer());
    }
}
