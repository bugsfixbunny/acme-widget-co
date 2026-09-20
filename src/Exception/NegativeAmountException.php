<?php

declare(strict_types=1);

namespace Acme\Exception;

use DomainException;

/**
 * Thrown when an offer or a delivery scheme returns an amount that cannot mean
 * anything: a discount that adds to the bill, or a delivery charge that pays
 * the customer.
 *
 * The implementations here cannot produce either. This guards the extension
 * points, where the interfaces promise a plain int and nothing else enforces
 * what a sensible one looks like.
 */
final class NegativeAmountException extends DomainException
{
    public static function discountFrom(string $offerClass, int $discountInCents): self
    {
        return new self(sprintf(
            'Offer %s returned a discount of %s. A discount cannot be negative.',
            $offerClass,
            self::dollars($discountInCents),
        ));
    }

    public static function deliveryChargeFrom(string $rulesClass, int $chargeInCents): self
    {
        return new self(sprintf(
            'Delivery rules %s returned a charge of %s. Delivery cannot cost less than nothing.',
            $rulesClass,
            self::dollars($chargeInCents),
        ));
    }

    private static function dollars(int $cents): string
    {
        return sprintf('%s$%s', $cents < 0 ? '-' : '', number_format(abs($cents) / 100, 2));
    }
}
