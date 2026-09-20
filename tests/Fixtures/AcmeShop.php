<?php

declare(strict_types=1);

namespace Acme\Tests\Fixtures;

use Acme\Basket;
use Acme\Configuration\DeliveryConfiguration;
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
     * Read from the configuration file that ships with the project, rather
     * than restated here, so every test measures the delivery charges Acme
     * actually runs. A wrong edit to that file fails the documented example
     * baskets, not just the tests that look at it directly.
     */
    public static function deliveryRules(): ThresholdDeliveryRules
    {
        return DeliveryConfiguration::fromJsonFile(__DIR__ . '/../../config/delivery.json');
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
