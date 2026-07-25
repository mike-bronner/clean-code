# Classes: Class Naming

## Standard

- All classes should be suffixed based on the base folder within the `app`
  folder, with exception of the following:
  - `app/Models`: no suffix
  - `app/Http`: file suffix is based on the folder within `app/Http`
  - `app/Livewire`: no suffix
  - `app/Livewire/Forms`: `Form` suffix; only Livewire Form classes here

**Why:**

- A class name announces its role without the reader opening the file
  (readability).
- The suffix and the folder cannot drift apart, so where a class lives and what
  it is stay one fact instead of two (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff derives a required class suffix from the
file's folder. The candidates were evaluated against this standard's test suite
and none satisfies it:

- [`Squiz.Classes.ValidClassName`](https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/src/Standards/Squiz/Sniffs/Classes/ValidClassNameSniff.php)
  (already wired in for [#22](https://github.com/mike-bronner/phpcs-rules/issues/22))
  checks PascalCase only — it is indifferent to suffixes.
- `Generic.NamingConventions.InterfaceNameSuffix`,
  `Generic.NamingConventions.TraitNameSuffix` and
  `Generic.NamingConventions.AbstractClassNamePrefix` require a *fixed* affix
  chosen by the **language construct** (interface, trait, abstract class), not by
  the folder. They cannot express "`Service` under `app/Services`, `Controller`
  under `app/Http/Controllers`", and they do not apply to plain classes.
- Slevomat's `Classes.SuperfluousInterfaceNaming`, `SuperfluousTraitNaming`,
  `SuperfluousAbstractClassNaming`, `SuperfluousExceptionNaming` and
  `SuperfluousErrorNaming` are the inverse rule — they **forbid** a fixed suffix
  rather than requiring a folder-derived one.
- [`SlevomatCodingStandard.Files.TypeNameMatchesFileName`](https://github.com/slevomat/coding-standard/blob/master/doc/files.md#slevomatcodingstandardfilestypenamematchesfilename)
  is the only path-aware candidate, but it checks that the type name matches the
  *file* name and that the namespace maps to a configured root directory. It
  never derives a *suffix* requirement from a folder, and configuring it does not
  make it do so.

The standard is therefore enforced by the custom
`CleanCode.Classes.ClassSuffixByFolder` sniff, wired into the master `rules.xml`
via the CleanCode standard
([#27](https://github.com/mike-bronner/phpcs-rules/issues/27)).

### How the required suffix is derived

The sniff reads the folder segments between the application's `app` directory
and the file, then resolves the suffix in this order:

| Folder | Required suffix | Example |
| --- | --- | --- |
| `app/` itself (no subfolder) | none | `app/Bootstrapper` |
| `app/Models/…` | none | `app/Models/User`, never `UserModel` |
| `app/Livewire/` (outside `Forms`) | none | `app/Livewire/Counter` |
| `app/Livewire/Forms/…` | `Form` | `app/Livewire/Forms/LoginForm` |
| `app/Http/` itself | none | `app/Http/Kernel` |
| `app/Http/<Segment>/…` | `<Segment>`, singularised | `app/Http/Controllers/Api/TokenController` |
| any other `app/<Base>/…` | `<Base>`, singularised | `app/Services/Billing/StripeService` |

Nested folders inherit their category's suffix: below `app/Http` the suffix comes
from the segment directly under `Http`, and everywhere else from the base folder
under `app`. Singularisation trims a trailing `s` (`Services` → `Service`), maps
`ies` → `y` (`Policies` → `Policy`), and leaves already-singular names untouched,
including those ending in a double `s` (`Middleware` → `Middleware`, `Access` →
`Access`).

Two violation codes are reported, independently — a class can trip both:

- `CleanCode.Classes.ClassSuffixByFolder.MissingSuffix` — the class name does not
  end with the suffix its folder requires.
- `CleanCode.Classes.ClassSuffixByFolder.MisplacedFormClass` — the class carries
  the `Form` suffix but does not live in a folder that derives it, so it is not a
  Livewire form class sitting where it belongs.

### Deliberate limits

- **"No suffix" means no suffix is *required*, not that a suffix is forbidden.**
  In `app/Models` and `app/Livewire` the suffix requirement is simply vacuous, so
  any name passes. The one rule that still reaches into those folders is the
  misplaced-`Form` check above, because the standard says Livewire form classes
  belong in `app/Livewire/Forms`. Detecting an *arbitrary* unwanted suffix
  (`app/Models/UserModel`) is not decidable from tokens — there is no way to know
  which trailing word the author meant as a role suffix — so it stays code review.
- **A `Form`-suffixed class is allowed wherever the folder derives `Form`** (for
  example `app/Forms/ContactForm`). Flagging it there would leave the class
  unnameable: required to end in `Form` by its folder and forbidden to by the
  Livewire rule.
- **Only classes are checked.** Interfaces, traits and enums are left alone; the
  standard is written about classes, and common interface naming
  (`app/Contracts/PaymentGatewayInterface`) would collide with a folder-derived
  suffix.
- **Files outside an `app` folder are skipped entirely**, so the sniff is inert in
  a project that does not use the Laravel layout. Where a path contains more than
  one `app` segment the *last* one is treated as the application folder, so a
  checkout nested inside a directory called `app` (`/app/app/Models/User.php`, a
  common container layout) still resolves correctly.
- **Auto-fixable — No (detection only).** Renaming a class means rewriting every
  reference to it: imports, type hints, container bindings, string class names,
  config and route files. A token-based fixer sees only the current file and
  cannot follow those, so a "fix" would leave the project broken. The rename is
  left to the developer or an IDE refactor.

Ruleset-integration tests covering a compliant class in every folder category, a
misnamed class in every folder category, the nested/bare `app/Http` and nested
generic-folder boundaries, singularisation, the nameless- and anonymous-class
edges, and the non-fixable (detection-only) guarantee live at
`tests/Standards/ClassSuffixByFolderTest.php`.

## What remains code review

- Whether a class's *role* is the one its folder implies — the sniff checks the
  suffix, not that a `PaymentService` actually behaves like a service.
- Suffix-shaped names in the no-suffix folders (`app/Models/UserModel`), which
  tokens cannot distinguish from a legitimate name ending in the same word.
