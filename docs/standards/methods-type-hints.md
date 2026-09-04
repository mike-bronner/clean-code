# Methods: Type Hints

## Standard

Methods should have type-hinted parameters as well as a return type.

**Why:**

- Helps maintainability and readability, and is self-documenting (mental debt).
- Traps logic errors close to the source (technical debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (Slevomat, configured)

Enforced by two existing Slevomat sniffs wired into the master `rules.xml`:

- `SlevomatCodingStandard.TypeHints.ParameterTypeHint` — every parameter must
  carry a native type hint. Reported as `MissingAnyTypeHint` when neither a
  hint nor a `@param` annotation exists, or as the auto-fixable
  `MissingNativeTypeHint` when a `@param` annotation can be promoted to a
  native hint.
- `SlevomatCodingStandard.TypeHints.ReturnTypeHint` — every declaration must
  declare a native return type. Declarations that return nothing are
  auto-fixed to `: void`; a promotable `@return` annotation is auto-fixed to
  the native hint; one that returns a value with no hint and no annotation is
  reported (not fixable — that sniff infers nothing from a body).
- `CleanCode.TypeHints.InferredReturnType` — carries the fixer the Slevomat
  sniff cannot. It runs **alongside** it and writes a return type wherever one
  is *provable from the source*:

  | Shape | Written |
  | --- | --- |
  | A resolvable ancestor already declares the type (read by reflection) | that type |
  | Every return is a literal | the literal, `true`/`false` collapsing to `bool` |
  | Returns `$this` | `static` |
  | Returns a parameter that carries a declared type | that type |
  | A lone `null` alongside one other type | `?T` shorthand |

  It stays silent everywhere else — a return whose value comes from a call, or
  from an operation rather than a single literal token. **A guessed return type
  is not a lint finding in the consumer's code, it is a `TypeError` thrown at
  runtime**, so silence is the only safe default. It also cedes three cases
  outright: any magic method (PHP refuses to load a class whose `__construct()`
  or `__destruct()` declares a return type at all), any declaration carrying a
  `@return` annotation, and any body that returns nothing — the Slevomat sniff
  already owns those and reports them under a more specific code.

  A declaration it can fix therefore carries **two** reports until `phpcbf`
  runs: the Slevomat one and this one. The fix clears both.

  **Why it does not extend the Slevomat sniff.** That was the first design and
  it silently dropped reports. `ReturnTypeHintSniff` builds every message code
  from a `private const NAME` through a `private` method using `self::`, so a
  subclass can override neither, and the branches that resolve severity through
  the hardcoded name vanish once the parent is unregistered. Measured: two
  reports disappeared from the ruleset fixture under a subclass, with pure
  delegation and no interception at all.

  The reflection path answers nothing against an ancestor that declares no
  types of its own — PHP_CodeSniffer's own `File` class declares none on any of
  its 42 methods — and nothing against an ancestor that cannot be resolved
  during a lint run, the same limit `InheritedMembers` already carries.

  A return inside a nested closure, arrow function, or anonymous class belongs
  to that declaration and never joins the enclosing method's union.

### Scope — all callables, not just methods

The Slevomat sniffs register on every `T_FUNCTION`, so this standard covers
**class methods and free (top-level) functions alike** — a free function with
an unhinted parameter or a missing return type is flagged the same as a
method. This is the deliberate scope for #70: it owns parameter and return
type hints for every callable. The general Type Hints standard
([#45](https://github.com/mike-bronner/phpcs-rules/issues/45)) keeps the
non-overlapping remainder — `PropertyTypeHint` and docblock hygiene.

### Configuration decisions

- **`enable*` properties are pinned** to the package's PHP 8.1 floor
  (`object`, `mixed`, `union`, `intersection`, `static`, `never` enabled;
  standalone `null`/`true`/`false` hints disabled — those are PHP 8.2+).
  Left unset, Slevomat resolves them from the PHP version running the check,
  so pinning keeps behaviour identical across environments. With the
  standalone hints disabled, a docblock `@param false`/`@return false` is
  widened to the native `bool` (not the 8.2 standalone `false`), and a
  docblock `@param null` — which has no native 8.1 spelling — is left
  unhinted rather than flagged.
- **`MissingTraversableTypeHintSpecification`, `UselessAnnotation`, and
  `LessSpecificNativeTypeHint` are excluded** — each acts on what a docblock
  says rather than on a missing native hint: item-type specifications
  (`@param array<int, string>`), annotations duplicating the native hint, and
  narrowing a declared `: void` to `: never` on the strength of a
  `@return never` annotation. Docblock and property coverage belongs to the
  general Type Hints standard
  ([#45](https://github.com/mike-bronner/phpcs-rules/issues/45)).

### Edge-case behaviour

- `__construct`, `__destruct`, and `__clone` are exempt from return type
  hints (PHP forbids them); their parameters — including promoted
  constructor properties — are still checked.
- Other magic methods (`__toString`, etc.) are fully checked.
- Closures are not checked for parameter hints; a closure that returns no
  value must declare `: void` (auto-fixed), while one returning a value is
  not checked. Arrow functions are not checked at all.
- Free functions are checked exactly like methods — an unhinted parameter or
  a missing return type is flagged and, where a `@param`/`@return` annotation
  can be promoted, auto-fixed.
- Methods carrying an `@inheritDoc` annotation are skipped — their signature
  is the parent's.

## What remains code review

Whether the chosen types are the *right* types — an overly wide `mixed` or a
sloppy union satisfies the sniff but not the standard's intent. Property type
hints and docblock quality are covered by the general Type Hints standard
(#45), not this rule.
