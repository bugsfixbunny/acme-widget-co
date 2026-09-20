<?php

declare(strict_types=1);

namespace Acme\Tests\Configuration;

use Acme\Configuration\DeliveryConfiguration;
use Acme\Exception\InvalidConfigurationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryConfiguration::class)]
#[CoversClass(InvalidConfigurationException::class)]
final class DeliveryConfigurationTest extends TestCase
{
    private const DELIVERY_CONFIGURATION = __DIR__ . '/../../config/delivery.json';

    /**
     * Covers the file that ships with the project, so the rules Acme actually
     * runs stay in step with the specification.
     */
    #[DataProvider('acmeSubtotals')]
    public function test_the_shipped_file_charges_what_the_specification_says(int $subtotalInCents, int $expectedCharge): void
    {
        $rules = DeliveryConfiguration::fromJsonFile(self::DELIVERY_CONFIGURATION);

        self::assertSame($expectedCharge, $rules->chargeFor($subtotalInCents));
    }

    /** @return array<string, array{int, int}> */
    public static function acmeSubtotals(): array
    {
        return [
            'a cent under $50' => [4999, 495],
            'exactly $50'      => [5000, 295],
            'a cent under $90' => [8999, 295],
            'exactly $90'      => [9000, 0],
        ];
    }

    public function test_it_builds_rules_from_plain_data(): void
    {
        $rules = DeliveryConfiguration::fromArray([
            'delivery' => [
                ['spendAtLeast' => 0.00, 'cost' => 3.50],
                ['spendAtLeast' => 25.00, 'cost' => 1.50],
            ],
        ]);

        self::assertSame(350, $rules->chargeFor(2499));
        self::assertSame(150, $rules->chargeFor(2500));
    }

    public function test_whole_dollar_amounts_may_be_written_as_integers(): void
    {
        $rules = DeliveryConfiguration::fromArray(['delivery' => [['spendAtLeast' => 0, 'cost' => 5]]]);

        self::assertSame(500, $rules->chargeFor(1000));
    }

    public function test_it_reports_a_file_that_is_not_there(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot read configuration file');

        DeliveryConfiguration::fromJsonFile(__DIR__ . '/no-such-file.json');
    }

    #[DataProvider('unusableFiles')]
    public function test_it_reports_a_file_it_cannot_use(string $contents, string $expectedMessage): void
    {
        $path = tempnam(sys_get_temp_dir(), 'delivery');

        if ($path === false) {
            self::fail('Could not create a temporary file.');
        }

        file_put_contents($path, $contents);

        try {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage($expectedMessage);

            DeliveryConfiguration::fromJsonFile($path);
        } finally {
            @unlink($path);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function unusableFiles(): array
    {
        return [
            'truncated json' => ['{ "delivery": [', 'is not valid JSON'],
            'not an object'  => ['"free delivery please"', 'does not describe an object'],
        ];
    }

    public function test_it_reports_a_missing_delivery_section(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Configuration has no "delivery" section.');

        DeliveryConfiguration::fromArray(['products' => []]);
    }

    public function test_it_reports_a_delivery_section_that_is_not_a_list(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('section "delivery" must be a list of entries');

        DeliveryConfiguration::fromArray(['delivery' => 'free']);
    }

    public function test_it_reports_an_entry_that_is_not_an_object(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Entry 0 of configuration section "delivery" must be an object.');

        DeliveryConfiguration::fromArray(['delivery' => ['free']]);
    }

    public function test_it_reports_a_band_with_no_cost(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A delivery band in the configuration is missing the "cost" option.');

        DeliveryConfiguration::fromArray(['delivery' => [['spendAtLeast' => 0.00]]]);
    }

    public function test_it_reports_an_amount_that_is_not_a_number(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Option "cost" of a delivery band in the configuration must be an amount in dollars.');

        DeliveryConfiguration::fromArray(['delivery' => [['spendAtLeast' => 0.00, 'cost' => '4.95']]]);
    }

    /**
     * The loader checks the shape of the data; what makes a usable set of bands
     * is still the rules' own business, and configuration cannot sneak past it.
     */
    public function test_the_rules_still_reject_bands_that_do_not_cover_every_order(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must include one starting at $0.00');

        DeliveryConfiguration::fromArray(['delivery' => [['spendAtLeast' => 50.00, 'cost' => 2.95]]]);
    }
}
