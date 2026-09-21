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

php bin/demo.php  # print the specification's example baskets and their totals
```

`bin/demo.php` exits non-zero if any total does not match the specification, so
it doubles as a smoke test:

```
Basket                         Expected      Total
----------------------------------------------------------
B01, G01                         $37.85     $37.85  ok
R01, R01                         $54.37     $54.37  ok
R01, G01                         $60.85     $60.85  ok
B01, B01, R01, R01, R01          $98.27     $98.27  ok
----------------------------------------------------------
All baskets match the specification.
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

Delivery charges can also be read from a file rather than written out like
this — see [Configuration](#configuration) below.

The examples from the specification, all covered by
[`tests/ExampleBasketsTest.php`](tests/ExampleBasketsTest.php):

| Products                | Total    |
| ----------------------- | -------- |
| B01, G01                | $37.85   |
| R01, R01                | $54.37   |
| R01, G01                | $60.85   |
| B01, B01, R01, R01, R01 | $98.27   |

## Configuration

The brief leaves the format of these rules open. Delivery charges are the ones
that change most often and carry no code with them, so they are defined as data
in [`config/delivery.json`](config/delivery.json):

```json
{
    "delivery": [
        { "spendAtLeast": 0.00,  "cost": 4.95 },
        { "spendAtLeast": 50.00, "cost": 2.95 },
        { "spendAtLeast": 90.00, "cost": 0.00 }
    ]
}
```

```php
$deliveryRules = DeliveryConfiguration::fromJsonFile('config/delivery.json');
```

The tests read the same file rather than restating the bands, so a wrong edit
to it fails the documented example baskets and not merely the tests that look
at delivery directly.

Changing what delivery costs, or adding a band, is an edit to that file. It is
read once at start-up and validated there — a missing section, a cost written
as text, an entry that is not an object — so a mistake fails immediately with a
message naming what is wrong, rather than at a customer's checkout. What makes
a *usable* set of bands is still `ThresholdDeliveryRules`' own business:
configuration cannot sneak past the rule that the bands must cover every order.

The catalogue and the offers are still declared in PHP. They could follow the
same pattern, and the section below says what that would take.

## How it works

```
src/
├── Basket.php                            the interface the brief asks for
├── Cents.php                             dollars in, whole cents out
├── Product.php                           a code, a name and a price
├── ProductCatalogue.php                  products indexed by code
├── Configuration/
│   └── DeliveryConfiguration.php         delivery bands from a file or an array
├── Delivery/
│   ├── DeliveryChargeRules.php           interface: subtotal in, charge out
│   ├── DeliveryBand.php                  "spend at least X, pay Y"
│   └── ThresholdDeliveryRules.php        picks the best band an order reaches
├── Exception/
│   ├── DiscountExceedsBasketException.php
│   ├── InvalidConfigurationException.php
│   ├── NegativeAmountException.php
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
negative amounts and values that are not real numbers.

**A price with more than two decimal places is rounded to the nearest cent.**
`32.955` becomes `3296` without complaint. Acceptable for a price list; if Acme
would rather that be an error, it is one guard in `Cents::fromDollars()`.

**The half-price widget rounds down, so the customer keeps the odd half cent.**
Half of $32.95 is $16.475, which cannot be paid. The second widget costs $16.47
rather than $16.48, making the discount $16.48 and the pair $49.42 — half a
cent in the customer's favour. This is not arbitrary: it is what the
specification's own totals require. Rounding the other way gives $54.38 and
$98.28 instead of the documented $54.37 and $98.27.

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

**Offers stack, and that is the current policy.** Every offer is asked for a
discount and all of them are applied, so two promotions covering the same
product both come off. The bundled configuration runs one offer, so this cannot
happen today; the *Questions* section below explains why it needs an answer
from Acme before a second promotion is switched on.

**Nonsense results from offers and delivery are refused.** The interfaces
promise only an `int`, so an implementation could return a negative discount —
which would quietly add to the bill — or a negative delivery charge. Both throw
`NegativeAmountException`, naming the class at fault.

**Discounting more than the basket is worth fails loudly.** Two overlapping
offers can do it — half off a $7.95 widget plus $5.00 off the order is $8.97 of
discount on $7.95 of goods — and so can a single faulty one, since nothing in
the `Offer` interface bounds what it returns. The basket throws
`DiscountExceedsBasketException` naming both figures rather than clamping to
zero, so a promotion configured to give stock away surfaces instead of quietly
producing a plausible total. Discounting a basket to exactly nothing is allowed:
that is a giveaway, and the customer still pays delivery.

**Amounts are capped at $1,000,000.** Bounding what the system handles is
simpler than defending the integer limit, where exactly `PHP_INT_MAX / 100`
converted to a *negative* number of cents and sums of large amounts could
overflow. At a million dollars apiece a basket would need some 92 billion
products before its total could overflow, so one bound closes both.

**`total()` returns dollars as a float, for display.** `totalInCents()` returns
the same figure as an exact integer, which is what most tests assert against.
The example baskets deliberately assert both: the dollar figures the
specification documents, and the same totals in cents, so the headline
assertions do not rest on floating-point comparison alone.

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
- **Move the catalogue into configuration too.** Delivery bands already live in
  a file; products would follow the same shape — a `products` section read and
  validated by a loader like `DeliveryConfiguration`. Prices then change without
  a deploy. It needs two decisions first that delivery did not: what happens to
  an order already in progress when a price changes, and whether a product code
  that disappears from the file should break baskets that still hold it.
- **Define which offers run in configuration.** This one is more than a loader.
  Offers carry behaviour, so a file can only say *which* promotions are on and
  with what parameters — something like
  `{ "type": "buy_one_get_second_half_price", "product": "R01" }` resolved
  through a registry of offer types, with each new kind of promotion still a
  class implementing `Offer`. The blocker is not the loading: the moment two
  offers can be switched on from a file, the question above about how
  overlapping offers combine has to be answered first, because today they
  would both discount the same products.
- **Mutation testing in CI**, which needs a coverage driver installed on the
  runner. It has already proved its worth here (see below).

## Testing

```bash
composer check
```

102 tests covering the specification's four example baskets end to end, every
delivery band boundary, the rounding rule, the basket lifecycle, and the
validation each class performs. [`tests/Fixtures/AcmeShop.php`](tests/Fixtures/AcmeShop.php) holds
Acme's configuration once so no test restates the price list.

Quality is enforced by more than the suite passing:

- **PHPStan at level max** over `src` *and* `tests`, run in CI. It caught a real
  typing error during development: a variadic parameter is not guaranteed to be
  a `list`, because a named argument gives the collected array a string key.
- **PHPUnit strict settings** — the suite fails on warnings and on risky tests.
- **Mutation testing** (Infection) was run during development, reaching a
  mutation score of 95%. It is not part of the committed tooling and needs a
  coverage driver, so that figure is a development-time measurement rather than
  something this repository reproduces. It found that `round()` could be
  replaced with `floor()` or
  `ceil()` without a single test failing, because PHPUnit attributes coverage
  only to the classes a test declares with `#[CoversClass]`, and the conversion
  cases lived in the wrong test class. The surviving mutants are documented
  equivalents, such as `array_values()` calls that exist to satisfy the type
  system rather than to change behaviour.
- **CI** runs the tests, static analysis and `composer validate --strict` on PHP
  8.2, 8.3 and 8.4.
