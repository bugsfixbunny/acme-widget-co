<?php

declare(strict_types=1);

namespace Acme\Tests;

use Acme\Exception\UnknownProductException;
use Acme\Product;
use Acme\ProductCatalogue;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductCatalogue::class)]
#[CoversClass(UnknownProductException::class)]
final class ProductCatalogueTest extends TestCase
{
    private ProductCatalogue $catalogue;

    protected function setUp(): void
    {
        $this->catalogue = new ProductCatalogue(
            new Product('R01', 'Red Widget', 32.95),
            new Product('G01', 'Green Widget', 24.95),
            new Product('B01', 'Blue Widget', 7.95),
        );
    }

    public function test_it_returns_a_product_by_code(): void
    {
        $product = $this->catalogue->get('G01');

        self::assertSame('Green Widget', $product->name);
        self::assertSame(2495, $product->priceInCents);
    }

    public function test_it_reports_whether_a_code_is_stocked(): void
    {
        self::assertTrue($this->catalogue->has('R01'));
        self::assertFalse($this->catalogue->has('Z99'));
    }

    public function test_it_throws_for_an_unknown_code(): void
    {
        $this->expectException(UnknownProductException::class);
        $this->expectExceptionMessage('Unknown product code "Z99".');

        $this->catalogue->get('Z99');
    }

    public function test_product_codes_are_case_sensitive(): void
    {
        $this->expectException(UnknownProductException::class);

        $this->catalogue->get('r01');
    }

    public function test_it_rejects_duplicate_product_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate product code "R01"');

        new ProductCatalogue(
            new Product('R01', 'Red Widget', 32.95),
            new Product('R01', 'Red Widget Redux', 31.95),
        );
    }

    public function test_it_can_be_empty(): void
    {
        self::assertFalse((new ProductCatalogue())->has('R01'));
    }
}
