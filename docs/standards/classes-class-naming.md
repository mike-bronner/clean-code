# Classes: Class Naming

## Standard

A class name must not repeat the folder it is filed under. `App\Services\
BillingService` says "service" twice: the namespace already files the type
under `Services`, so the suffix adds nothing at the declaration and lengthens
every reference to it. The name is `App\Services\Billing`.

Where a call site genuinely reads better with the fuller name, the import
carries it:

```php
use App\Services\Billing as BillingService;
```

That is where a suffix belongs — in the alias it disambiguates, not in the
declaration it duplicates.

**Takeaway:** name the class for what it *is*, not for the folder it is in;
alias at the call site when the folder's word adds clarity there.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

> **This standard was inverted on 2026-08-21.** It previously *required* the
> folder-derived suffix (`app/Services/BillingService`). The rule is now its
> opposite, decided in
> [#27](https://github.com/mike-bronner/phpcs-rules/issues/27): no
> folder-derived suffix, anywhere in `app/`. The source page at
> mikebronner.dev/clean-code still documents the superseded rule and needs the
> same correction.

## Scope — global inside `app/`

The rule carries no per-folder exemption:

| Declaration | Verdict |
| --- | --- |
| `App\Services\BillingService` | flagged — name it `Billing` |
| `App\Http\Controllers\UserController` | flagged — name it `User` |
| `App\Http\Requests\StoreUserRequest` | flagged — name it `StoreUser` |
| `App\Livewire\Forms\LoginForm` | flagged — name it `Login` |
| `App\Models\User` | compliant |
| `App\Services\Billing` | compliant |

Laravel's generators produce `UserController`, `StoreUserRequest`,
`LoginForm` and the rest. This standard asks for the rename anyway, and the
import alias is what carries the framework's vocabulary to the call sites that
want it.

**Every ancestor counts, not only the immediate parent.**
`App\Http\Controllers\Api\TokenController` is measured against `Http`,
`Controllers` *and* `Api`, and is flagged for repeating `Controllers` two
levels up.

## Enforceability — Tier 2, custom sniff

Enforced by the custom `CleanCode.Naming.RedundantNamespaceSuffix` sniff
([#27](https://github.com/mike-bronner/phpcs-rules/issues/27)), at **error**
severity, on classes, interfaces, traits and enums alike — a redundantly named
interface is the identical violation under a different keyword.

**No existing sniff covers this.** Slevomat's five `Superfluous*Naming` sniffs
(`SuperfluousInterfaceNaming`, `SuperfluousTraitNaming`,
`SuperfluousAbstractClassNaming`, `SuperfluousExceptionNaming`,
`SuperfluousErrorNaming`) are the closest available rules, and each compares a
name against one hardcoded word for a *type kind*; none of them reads the
enclosing namespace, so none can say that `Service` is redundant under
`App\Services` but fine under `App\Billing`. That claim is measured, not
asserted: `tests/Standards/RedundantNamespaceSuffixTest.php` runs those five
sniffs over `vendor-superfluous-naming.php`, a fixture holding identical
declarations under two namespaces, and pins their verdict as the same in both —
a Slevomat version that started reading the namespace reddens the suite rather
than leaving this paragraph stale. `Squiz.Classes.ValidClassName`,
PSR-1's naming sniffs and `Files.TypeNameMatchesFileName` check casing or the
file name, not the folder.

**Detection only, no fixer.** Renaming a type means rewriting every reference
to it across the project, which no single-file, token-based fixer can see, let
alone do.

## How the redundancy is detected

The sniff reads the **declared namespace**, never the file path. Under PSR-4
the two say the same thing for any autoloadable class, and the namespace is the
half a checkout location cannot corrupt — PHP_CodeSniffer hands a sniff a fully
resolved absolute path, so a package checked out under `/home/app/project/`
reads as living in `app/` when the path is trusted.

A namespace is in scope when its **first** segment is `App`, which is the
prefix PSR-4 maps onto the `app/` directory. `Tests\Unit\…`,
`Vendor\App\Repositories\…` and this package's own
`MikeBronner\CleanCode\Sniffs\…` are all out of scope. That gate is
load-bearing: PHPUnit discovers a test class by its `Test` suffix and
PHP_CodeSniffer discovers a sniff by its `Sniff` suffix, so a rule reaching
those namespaces would demand a rename the tooling forbids.

A segment counts as repeated when the declared name ends in that segment, or in
a singular form of it, at a **PascalCase word boundary** — the matched suffix
has to start at an upper-case letter, or be the whole name. Both sides are
compared case-insensitively, as PHP resolves names.

Plurals are handled as a set of candidate stems rather than one guess, because
the endings are genuinely ambiguous and no token stream carries a dictionary:
`-es` splits two ways (`Statuses` → `Status`, `Cases` → `Case`) and `-ies`
three (`Categories` → `Category`, `Movies` → `Movie`). Every ending that
applies contributes a stem, and the declared name decides which one it wrote; a
handful of irregulars (`Analyses`, `Children`, `Criteria`, `Indices`,
`Matrices`, `People`) are listed outright.
An unrecognised plural therefore costs a missed report rather than a false one.

## Deliberate limits

- **A prefix echo is not a violation.** `App\Services\Billing\BillingGateway`
  opens with an ancestor's name rather than ending in it. The standard is about
  a redundant *suffix*.
- **A suffix run on in lower case escapes.** `App\Notifications\
  Ordernotification` carries the suffix, but no word boundary marks it.
  Matching inside a word is the same change that would flag
  `App\Ads\Squad`, and a false report on an ordinary name costs more than a
  missed one on a misspelled suffix.
- **A class in the global namespace is left alone** — nothing says where it is
  filed.
- **The class name is read, never its behaviour.** A hit is a naming
  observation, not a verdict on the design.
