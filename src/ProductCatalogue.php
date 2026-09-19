<?php

declare(strict_types=1);

namespace Acme;

use Acme\Exception\UnknownProductException;
use InvalidArgumentException;

/**
 * The products Acme sells, indexed by product code.
 */
final readonly class ProductCatalogue
{
    /** @var array<string, Product> */
    private array $products;

    public function __construct(Product ...$products)
    {
        $indexed = [];

        foreach ($products as $product) {
            if (isset($indexed[$product->code])) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate product code "%s" in catalogue.', $product->code),
                );
            }

            $indexed[$product->code] = $product;
        }

        $this->products = $indexed;
    }

    /**
     * @throws UnknownProductException when the code is not in the catalogue
     */
    public function get(string $code): Product
    {
        return $this->products[$code] ?? throw UnknownProductException::forCode($code);
    }

    public function has(string $code): bool
    {
        return isset($this->products[$code]);
    }
}
