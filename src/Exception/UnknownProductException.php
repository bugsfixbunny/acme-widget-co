<?php

declare(strict_types=1);

namespace Acme\Exception;

use InvalidArgumentException;

final class UnknownProductException extends InvalidArgumentException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('Unknown product code "%s".', $code));
    }
}
