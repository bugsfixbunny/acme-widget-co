<?php

declare(strict_types=1);

namespace Acme\Tests;

use Acme\Product;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Product::class)]
final class ProductTest extends TestCase
{
    public function test_it_exposes_its_code_and_name(): void
    {
        $product = new Product('R01', 'Red Widget', 32.95);

        self::assertSame('R01', $product->code);
        self::assertSame('Red Widget', $product->name);
    }

    /**
     * A plain (int) cast would lose a cent on prices such as $1.15, because
     * 1.15 * 100 is 114.99999999999999 in binary floating point.
     */
    #[DataProvider('dollarPrices')]
    public function test_it_converts_a_dollar_price_to_whole_cents(float $dollars, int $expectedCents): void
    {
        self::assertSame($expectedCents, (new Product('X01', 'Widget', $dollars))->priceInCents);
    }

    /** @return array<string, array{float, int}> */
    public static function dollarPrices(): array
    {
        return [
            'red widget'            => [32.95, 3295],
            'green widget'          => [24.95, 2495],
            'blue widget'           => [7.95, 795],
            'lands below the cent'  => [1.15, 115],
            'also lands below'      => [8.20, 820],
            'accumulated float'     => [0.1 + 0.2, 30],
            'whole dollars'         => [12.0, 1200],
            'free'                  => [0.0, 0],
        ];
    }

    public function test_it_rejects_an_empty_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product code cannot be empty.');

        new Product('   ', 'Nameless Widget', 1.00);
    }

    public function test_it_rejects_a_negative_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-negative amount');

        new Product('R01', 'Red Widget', -0.01);
    }

    public function test_it_rejects_a_price_that_is_not_a_real_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-negative amount');

        new Product('R01', 'Red Widget', NAN);
    }
}
