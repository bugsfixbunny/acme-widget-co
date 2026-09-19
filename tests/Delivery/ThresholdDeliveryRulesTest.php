<?php

declare(strict_types=1);

namespace Acme\Tests\Delivery;

use Acme\Cents;
use Acme\Delivery\DeliveryBand;
use Acme\Delivery\ThresholdDeliveryRules;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThresholdDeliveryRules::class)]
#[CoversClass(DeliveryBand::class)]
#[CoversClass(Cents::class)]
final class ThresholdDeliveryRulesTest extends TestCase
{
    private static function acmeRules(): ThresholdDeliveryRules
    {
        return new ThresholdDeliveryRules(
            new DeliveryBand(spendAtLeast: 0.00, cost: 4.95),
            new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
            new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
        );
    }

    /**
     * The boundaries are the whole point: $50 and $90 belong to the cheaper
     * band, because the spec says "under $50" and "$90 or more".
     */
    #[DataProvider('acmeSubtotals')]
    public function test_it_charges_the_band_the_order_reaches(int $subtotalInCents, int $expectedCharge): void
    {
        self::assertSame($expectedCharge, self::acmeRules()->chargeFor($subtotalInCents));
    }

    /** @return array<string, array{int, int}> */
    public static function acmeSubtotals(): array
    {
        return [
            'empty order'          => [0, 495],
            'one cent'             => [1, 495],
            'a cent under $50'     => [4999, 495],
            'exactly $50'          => [5000, 295],
            'a cent over $50'      => [5001, 295],
            'a cent under $90'     => [8999, 295],
            'exactly $90'          => [9000, 0],
            'a cent over $90'      => [9001, 0],
            'a very large order'   => [1000000, 0],
        ];
    }

    public function test_band_order_does_not_matter(): void
    {
        $shuffled = new ThresholdDeliveryRules(
            new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
            new DeliveryBand(spendAtLeast: 0.00, cost: 4.95),
            new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
        );

        self::assertSame(495, $shuffled->chargeFor(4999));
        self::assertSame(295, $shuffled->chargeFor(5000));
        self::assertSame(0, $shuffled->chargeFor(9000));
    }

    public function test_a_single_band_charges_every_order_the_same(): void
    {
        $flatRate = new ThresholdDeliveryRules(new DeliveryBand(spendAtLeast: 0.00, cost: 3.50));

        self::assertSame(350, $flatRate->chargeFor(0));
        self::assertSame(350, $flatRate->chargeFor(1000000));
    }

    public function test_it_requires_at_least_one_band(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one delivery band is required.');

        new ThresholdDeliveryRules();
    }

    public function test_it_requires_a_band_starting_at_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must include one starting at $0.00');

        new ThresholdDeliveryRules(
            new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
            new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
        );
    }

    public function test_it_rejects_two_bands_with_the_same_threshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate delivery band for a spend of 5000 cents.');

        new ThresholdDeliveryRules(
            new DeliveryBand(spendAtLeast: 0.00, cost: 4.95),
            new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
            new DeliveryBand(spendAtLeast: 50.00, cost: 1.95),
        );
    }

    public function test_it_rejects_a_negative_subtotal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('negative subtotal');

        self::acmeRules()->chargeFor(-1);
    }

    public function test_it_rejects_a_negative_threshold_or_cost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery cost must be a non-negative amount');

        new DeliveryBand(spendAtLeast: 0.00, cost: -1.00);
    }
}
