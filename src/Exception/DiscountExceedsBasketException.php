<?php

declare(strict_types=1);

namespace Acme\Exception;

use DomainException;

/**
 * Thrown when the offers applied to a basket discount more than it is worth.
 *
 * A single offer cannot do this. Two that overlap can: half off a $7.95 widget
 * plus $5.00 off the order is $8.97 of discount on $7.95 of goods.
 */
final class DiscountExceedsBasketException extends DomainException
{
    public static function of(int $subtotalInCents, int $discountInCents): self
    {
        return new self(sprintf(
            'Offers discounted $%s from a basket worth $%s. Check for offers that apply to the same products.',
            number_format($discountInCents / 100, 2),
            number_format($subtotalInCents / 100, 2),
        ));
    }
}
