# Acme Widget Co — Basket

A proof of concept for Acme Widget Co's new sales system: a basket that knows a
product catalogue, a set of delivery charge rules and any number of special
offers, and can tell you what the customer pays.

## Requirements

- PHP 8.2 or newer
- [Composer](https://getcomposer.org/)

## Getting started

```bash
composer install
composer test     # run the test suite
composer stan     # run static analysis
composer check    # both of the above
```

## Usage

The basket is initialised with the catalogue, the delivery rules and the offers.
Products are declared in dollars, exactly as they appear on the price list.

```php
use Acme\Basket;
use Acme\Delivery\DeliveryBand;
use Acme\Delivery\ThresholdDeliveryRules;
use Acme\Offer\BuyOneGetSecondHalfPriceOffer;
use Acme\Product;
use Acme\ProductCatalogue;

$catalogue = new ProductCatalogue(
    new Product('R01', 'Red Widget', 32.95),
    new Product('G01', 'Green Widget', 24.95),
    new Product('B01', 'Blue Widget', 7.95),
);

$delivery = new ThresholdDeliveryRules(
    new DeliveryBand(spendAtLeast: 0.00, cost: 4.95),
    new DeliveryBand(spendAtLeast: 50.00, cost: 2.95),
    new DeliveryBand(spendAtLeast: 90.00, cost: 0.00),
);

$basket = new Basket($catalogue, $delivery, new BuyOneGetSecondHalfPriceOffer('R01'));

$basket->add('B01');
$basket->add('G01');

$basket->total();        // 37.85
$basket->totalInCents(); // 3785
```

The examples from the specification, all covered by
[`tests/ExampleBasketsTest.php`](tests/ExampleBasketsTest.php):

| Products                | Total    |
| ----------------------- | -------- |
| B01, G01                | $37.85   |
| R01, R01                | $54.37   |
| R01, G01                | $60.85   |
| B01, B01, R01, R01, R01 | $98.27   |

## How it works

```
src/
├── Basket.php                            the interface the brief asks for
├── Cents.php                             dollars in, whole cents out
├── Product.php                           a code, a name and a price
├── ProductCatalogue.php                  products indexed by code
├── Delivery/
│   ├── DeliveryChargeRules.php           interface: subtotal in, charge out
│   ├── DeliveryBand.php                  "spend at least X, pay Y"
│   └── ThresholdDeliveryRules.php        picks the best band an order reaches
├── Exception/
│   └── UnknownProductException.php
└── Offer/
    ├── Offer.php                         interface: basket in, discount out
    └── BuyOneGetSecondHalfPriceOffer.php
```

Working out a total is three steps:

1. **Add up the products.** `add()` looks each code up in the catalogue as it
   goes, so an unknown code fails at the `add()` that caused it rather than
   later during checkout.
2. **Take the offers off.** Each offer is handed the whole basket and returns a
   discount. It does not rewrite prices, so several offers can apply to the same
   basket without fighting over who owns a line.
3. **Add delivery.** The delivery rules are given the subtotal *after* discounts
   and return the charge for the band it reaches.

`Basket` depends on the `Offer` and `DeliveryChargeRules` interfaces, never on a
particular implementation, so a new promotion or a new delivery scheme is a new
class and a change to how the basket is constructed — nothing inside `Basket`
changes.

### Adding an offer

Implement `Offer` and pass it in:

```php
final readonly class ThreeForTwoOffer implements Offer
{
    public function __construct(public string $productCode) {}

    public function discountFor(array $products): int { /* discount in cents */ }
}

$basket = new Basket($catalogue, $delivery, new BuyOneGetSecondHalfPriceOffer('R01'), new ThreeForTwoOffer('G01'));
```

`BuyOneGetSecondHalfPriceOffer` takes the product code it applies to, so moving
the promotion from red widgets to green ones is a constructor argument, not a
code change.

## Decisions and assumptions

The specification leaves some things open. These are the calls I made and why.

**Money is held in whole cents, never a float.** Prices are declared in dollars
because that is how a price list reads, and converted once on the way in. Every
calculation after that is integer arithmetic, so no total drifts.

**The conversion rounds rather than casts.** `1.15 * 100` is
`114.99999999999999` in binary floating point, so `(int) ($dollars * 100)` would
silently lose a cent. `Cents::fromDollars()` uses `round()`, and refuses
negative amounts, values that are not real numbers, and amounts so large that
the cast to `int` would return a wrong number instead of failing.

**A price with more than two decimal places is rounded to the nearest cent.**
`32.955` becomes `3296` without complaint. Acceptable for a price list; if Acme
would rather that be an error, it is one guard in `Cents::fromDollars()`.

**The half-price widget rounds down, so the shop keeps the odd half cent.**
Half of $32.95 is $16.475, which cannot be paid. The second widget costs $16.47
and the discount is $16.48, making the pair $49.42. This is not arbitrary — it
is what the specification's own totals require. Rounding the other way gives
$54.38 and $98.28 instead of the documented $54.37 and $98.27.

**Offers are applied before delivery is worked out.** Two red widgets come to
$49.42 after the offer, which is *under* $50, so delivery costs $4.95 — banding
on the undiscounted $65.90 would have charged $2.95 and produced the wrong
total. It cuts the other way too: three red widgets are $98.85 before the offer
and $82.37 after, so the discount costs the customer their free delivery.

**Delivery thresholds are inclusive, and free delivery is a configured band.**
"Orders of $90 or more" means exactly $90.00 ships free, and exactly $50.00 pays
$2.95. Rather than hard-coding "everything else is free", every tier is stated
as "spend at least X, pay Y", including the free one. A set of bands must
include one starting at $0.00, so no order can fall through without a charge
being defined.

**An empty basket costs nothing.** It returns $0.00 rather than charging $4.95
to deliver nothing.

**Product codes are case sensitive.** `r01` is not `R01` and throws
`UnknownProductException`. Normalising the case silently would hide a caller's
typo.

**A misconfigured offer fails loudly.** If an offer ever discounted more than
the basket holds, the subtotal would go negative and the delivery rules throw
rather than clamping to zero, so the misconfiguration surfaces instead of
quietly producing a plausible total.

**`total()` returns dollars as a float, for display.** `totalInCents()` returns
the same figure as an exact integer for anything that cannot afford a float —
that is also what the tests assert against, so no test compares floats.

**Out of scope for a proof of concept:** tax, currencies other than dollars,
persistence, quantities on `add()` (call it twice), removing products, and
offers that depend on anything outside the basket.

## Questions I would ask Acme

The assumptions above are my reading of the brief and its example totals. These
are the points I would want confirmed before this went near production, with
what the code does in the meantime.

**How should two offers that touch the same product combine?** This is the one
that matters most. Each offer is asked independently for a discount, so two
promotions on red widgets both discount the same pair — two "second half price"
offers would take $32.96 off a $65.90 pair and total $37.89. With today's single
offer that cannot happen, but it is a silent wrong answer waiting for the second
promotion. The fix depends on Acme's rule: best offer wins, offers apply in a
priority order, or an offer consumes the products it has already discounted.

**Does an offer repeat within one basket?** Assumed yes: four red widgets means
two of them are half price. "Get the second half price" could equally mean once
per customer or once per order.

**Is delivery banded on the discounted total?** Assumed yes — the documented
$54.37 for two red widgets only works if it is. Worth confirming it is policy
rather than a coincidence of the example, because the two readings differ by
$2.00 on that basket.

**Which way does the odd half cent go?** Same reasoning: the examples require
the shop to keep it. A rounding rule inferred from four data points deserves an
explicit yes.

**Is delivery charged per order?** Assumed yes, with no weight, size, zone or
per-item component, and no products that ship free regardless of order value.

**Should an empty basket really cost nothing?** Assumed yes, rather than
charging $4.95 to deliver an empty box.

**Is tax in scope?** Not mentioned in the brief. If it arrives, we need to know
whether listed prices include it, whether delivery is taxed, and whether the
$50/$90 thresholds are measured gross or net — each changes the arithmetic.

**Do offers have start and end dates, or usage limits?** Promotions usually do,
and "the initial offer" suggests more are coming.

## Where this would go next

- **Return a breakdown, not just a number.** A receipt object carrying subtotal,
  each discount with the offer that produced it, delivery and total. Any real
  checkout page needs to show the customer why they are paying what they pay,
  and `total()` cannot tell them.
- **An offer resolution strategy**, once Acme answers the first question above.
  Letting offers claim the products they have discounted would make
  double-counting structurally impossible rather than merely unlikely.
- **Line items with quantities.** `add('R01', 3)` and a basket that stores counts
  rather than repeated objects — better for display, and offers could work from
  counts instead of scanning the whole basket.
- **Load the catalogue from configuration** rather than constructing it in code,
  once prices live somewhere other than a developer's editor.
- **Mutation testing in CI**, which needs a coverage driver installed on the
  runner. It has already proved its worth here (see below).

## Testing

```bash
composer check
```

73 tests covering the specification's four example baskets end to end, every
delivery band boundary, the rounding rule, and the validation each class
performs. [`tests/Fixtures/AcmeShop.php`](tests/Fixtures/AcmeShop.php) holds
Acme's configuration once so no test restates the price list.

Quality is enforced by more than the suite passing:

- **PHPStan at level max** over `src` *and* `tests`, run in CI. It caught a real
  typing error during development: a variadic parameter is not guaranteed to be
  a `list`, because a named argument gives the collected array a string key.
- **PHPUnit strict settings** — the suite fails on warnings and on risky tests.
- **Mutation testing** (Infection) was used during development and reached an
  MSI of 91%. It found that `round()` could be replaced with `floor()` or
  `ceil()` without a single test failing, because PHPUnit attributes coverage
  only to the classes a test declares with `#[CoversClass]`, and the conversion
  cases lived in the wrong test class. The surviving mutants are documented
  equivalents, such as `array_values()` calls that exist to satisfy the type
  system rather than to change behaviour.
- **CI** runs the tests, static analysis and `composer validate --strict` on PHP
  8.2, 8.3 and 8.4.
