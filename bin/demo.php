#!/usr/bin/env php
<?php

declare(strict_types=1);

use Acme\Basket;
use Acme\Delivery\DeliveryBand;
use Acme\Delivery\ThresholdDeliveryRules;
use Acme\Offer\BuyOneGetSecondHalfPriceOffer;
use Acme\Product;
use Acme\ProductCatalogue;

require __DIR__ . '/../vendor/autoload.php';

$catalogue = new ProductCatalogue(
    new Product('R01', 'Red Widget', 32.95),
    new Product('G01', 'Green Widget', 24.95),
    new Product('B01', 'Blue Widget', 7.95),
);

$deliveryRules = new ThresholdDeliveryRules(
    new DeliveryBand(spendAtLeast: 0.00, cost: 4.95),
    new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
    new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
);

/** @var list<array{products: list<string>, expected: float}> the baskets given in the specification */
$examples = [
    ['products' => ['B01', 'G01'], 'expected' => 37.85],
    ['products' => ['R01', 'R01'], 'expected' => 54.37],
    ['products' => ['R01', 'G01'], 'expected' => 60.85],
    ['products' => ['B01', 'B01', 'R01', 'R01', 'R01'], 'expected' => 98.27],
];

printf("%-28s %10s %10s  %s\n", 'Basket', 'Expected', 'Total', '');
echo str_repeat('-', 58), "\n";

$allMatched = true;

foreach ($examples as $example) {
    $basket = new Basket($catalogue, $deliveryRules, new BuyOneGetSecondHalfPriceOffer('R01'));

    foreach ($example['products'] as $code) {
        $basket->add($code);
    }

    $total = $basket->total();
    $matched = $total === $example['expected'];
    $allMatched = $allMatched && $matched;

    printf(
        "%-28s %10s %10s  %s\n",
        implode(', ', $example['products']),
        '$' . number_format($example['expected'], 2),
        '$' . number_format($total, 2),
        $matched ? 'ok' : 'MISMATCH',
    );
}

echo str_repeat('-', 58), "\n";
echo $allMatched ? "All baskets match the specification.\n" : "Some baskets do not match.\n";

exit($allMatched ? 0 : 1);
