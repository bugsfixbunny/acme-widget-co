<?php

declare(strict_types=1);

namespace Acme\Tests;

use Acme\Cents;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cents::class)]
final class CentsTest extends TestCase
{
    #[DataProvider('dollarAmounts')]
    public function test_it_converts_dollars_to_whole_cents(float $dollars, int $expectedCents): void
    {
        self::assertSame($expectedCents, Cents::fromDollars($dollars, 'Amount'));
    }

    /**
     * Binary floating point cannot hold most prices exactly, so the conversion
     * has to round: 1.15 * 100 is 114.99999999999999 and 32.95 * 100 is
     * 3295.0000000000005. Flooring would lose a cent on the first, ceiling
     * would add one to the second.
     *
     * @return array<string, array{float, int}>
     */
    public static function dollarAmounts(): array
    {
        return [
            'lands just below the cent' => [1.15, 115],
            'also lands just below'     => [8.20, 820],
            'lands just above the cent' => [32.95, 3295],
            'green widget'              => [24.95, 2495],
            'blue widget'               => [7.95, 795],
            'accumulated float error'   => [0.1 + 0.2, 30],
            'whole dollars'             => [12.0, 1200],
            'nothing'                   => [0.0, 0],
            'a safely large amount'     => [1.0e12, 100000000000000],
        ];
    }

    public function test_it_rejects_a_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a non-negative amount, got -0.01.');

        Cents::fromDollars(-0.01, 'Amount');
    }

    #[DataProvider('impossibleNumbers')]
    public function test_it_rejects_a_value_that_is_not_a_real_number(float $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a non-negative amount');

        Cents::fromDollars($value, 'Amount');
    }

    /** @return array<string, array{float}> */
    public static function impossibleNumbers(): array
    {
        return ['not a number' => [NAN], 'infinity' => [INF], 'negative infinity' => [-INF]];
    }

    /**
     * Casting a float above PHP_INT_MAX to int returns a wrong number instead
     * of failing, so such an amount is refused before the cast happens.
     */
    public function test_it_rejects_an_amount_too_large_to_count_in_cents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount is too large to count in cents, got 1.0E+20.');

        Cents::fromDollars(1.0e20, 'Amount');
    }

    public function test_it_names_the_amount_that_was_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price for product "R01" must be a non-negative amount');

        Cents::fromDollars(-1.0, 'Price for product "R01"');
    }
}
