<?php

declare(strict_types=1);

namespace Acme\Tests;

use Acme\Product;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Converting dollars to cents is Cents' job and is tested there; what matters
 * here is that a product hands its price over and reports where a bad one came
 * from.
 */
#[CoversClass(Product::class)]
final class ProductTest extends TestCase
{
    public function test_it_exposes_its_code_name_and_price_in_cents(): void
    {
        $product = new Product('R01', 'Red Widget', 32.95);

        self::assertSame('R01', $product->code);
        self::assertSame('Red Widget', $product->name);
        self::assertSame(3295, $product->priceInCents);
    }

    public function test_it_allows_a_free_product(): void
    {
        self::assertSame(0, (new Product('F01', 'Free Widget', 0.0))->priceInCents);
    }

    public function test_it_rejects_an_empty_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product code cannot be empty.');

        new Product('   ', 'Nameless Widget', 1.00);
    }

    public function test_it_reports_which_product_had_an_impossible_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price for product "R01" must be a non-negative amount, got -0.01.');

        new Product('R01', 'Red Widget', -0.01);
    }
}
