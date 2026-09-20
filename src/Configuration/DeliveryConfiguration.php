<?php

declare(strict_types=1);

namespace Acme\Configuration;

use Acme\Delivery\DeliveryBand;
use Acme\Delivery\ThresholdDeliveryRules;
use Acme\Exception\InvalidConfigurationException;
use JsonException;

/**
 * Builds delivery charge rules from plain data, so changing what delivery
 * costs is an edit to a file rather than a code change.
 *
 * Reading and validating happen here, at the edge, so the rules themselves
 * only ever see values they can work with. A different source — a database,
 * say — would be another class producing the same rules; nothing in the domain
 * would change.
 */
final class DeliveryConfiguration
{
    private function __construct()
    {
    }

    public static function fromJsonFile(string $path): ThresholdDeliveryRules
    {
        // Suppressed because the failure is reported as an exception instead,
        // and one check covers missing, unreadable and not-a-file alike.
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw InvalidConfigurationException::unreadableFile($path);
        }

        try {
            $decoded = json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidConfigurationException::invalidJson($path, $e);
        }

        if (!is_array($decoded)) {
            throw InvalidConfigurationException::invalidJson(
                $path,
                new JsonException('the file does not describe an object'),
            );
        }

        return self::fromArray($decoded);
    }

    /**
     * @param array<mixed, mixed> $configuration
     */
    public static function fromArray(array $configuration): ThresholdDeliveryRules
    {
        $entries = $configuration['delivery'] ?? throw InvalidConfigurationException::missingSection('delivery');

        if (!is_array($entries)) {
            throw InvalidConfigurationException::sectionIsNotAList('delivery');
        }

        $bands = [];

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                throw InvalidConfigurationException::entryIsNotAnObject('delivery', $index);
            }

            $bands[] = new DeliveryBand(
                self::amount($entry, 'spendAtLeast'),
                self::amount($entry, 'cost'),
            );
        }

        return new ThresholdDeliveryRules(...$bands);
    }

    /**
     * @param array<mixed, mixed> $entry
     */
    private static function amount(array $entry, string $option): float
    {
        $value = $entry[$option] ?? throw InvalidConfigurationException::missingOption('delivery band', $option);

        if (!is_int($value) && !is_float($value)) {
            throw InvalidConfigurationException::optionHasWrongType('delivery band', $option, 'an amount in dollars');
        }

        return (float) $value;
    }
}
