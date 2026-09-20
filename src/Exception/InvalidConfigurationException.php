<?php

declare(strict_types=1);

namespace Acme\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * Thrown when a configuration file cannot be turned into working rules.
 *
 * Configuration is read once at start-up, so a mistake in it fails there
 * rather than at a customer's checkout.
 */
final class InvalidConfigurationException extends InvalidArgumentException
{
    public static function unreadableFile(string $path): self
    {
        return new self(sprintf('Cannot read configuration file "%s".', $path));
    }

    public static function invalidJson(string $path, Throwable $previous): self
    {
        return new self(
            sprintf('Configuration file "%s" is not valid JSON: %s', $path, $previous->getMessage()),
            previous: $previous,
        );
    }

    public static function missingSection(string $section): self
    {
        return new self(sprintf('Configuration has no "%s" section.', $section));
    }

    public static function sectionIsNotAList(string $section): self
    {
        return new self(sprintf('Configuration section "%s" must be a list of entries.', $section));
    }

    public static function entryIsNotAnObject(string $section, string|int $index): self
    {
        return new self(sprintf('Entry %s of configuration section "%s" must be an object.', $index, $section));
    }

    public static function missingOption(string $context, string $option): self
    {
        return new self(sprintf('A %s in the configuration is missing the "%s" option.', $context, $option));
    }

    public static function optionHasWrongType(string $context, string $option, string $expected): self
    {
        return new self(sprintf('Option "%s" of a %s in the configuration must be %s.', $option, $context, $expected));
    }
}
