# Naming: Casing Conventions

## Standard

- All variables, properties, and methods should be in **camelCase**
- All SQL fields should be in **snake_case**
- All SQL keywords should be in **UPPERCASE**
- All class names should be in **PascalCase**
- All URLs, query strings, and config keys should be in **snake_case**
- All environment variables should be in **UPPER_SNAKE_CASE**

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (existing sniffs)

Only the PHP-identifier cases are statically checkable. Three existing sniffs
enforce them under the master `CleanCode/ruleset.xml` ([#22](https://github.com/mike-bronner/phpcs-rules/issues/22)).
One is referenced explicitly; the other two arrive with the ruleset's `PSR12`
reference, which includes `PSR1` wholesale
([#217](https://github.com/mike-bronner/phpcs-rules/issues/217)):

- **`Squiz.NamingConventions.ValidVariableName`** (explicit ref) — camelCase variables and
  properties. Its `PrivateNoUnderscore` code is excluded: Squiz demands a
  leading underscore on private properties, while this standard wants plain
  camelCase for every property regardless of visibility.
- **`PSR1.Methods.CamelCapsMethodName`** (via `PSR12` → `PSR1`) — camelCase method names. Magic
  methods (`__construct`, `__get`, …) are exempt, closures are ignored, and
  global functions are outside this rule's scope.
- **`Squiz.Classes.ValidClassName`** (via `PSR12` → `PSR1`) — PascalCase names for `class`,
  `interface`, `trait`, and `enum` declarations (mirroring PHPMD's
  `CamelCaseClassName` scope). Abstract classes are covered — they carry the
  same class token.

None of the checks are auto-fixable: renaming identifiers is never safe for a
fixer, so the rules are reporting-only.

### Behavior notes (asserted by the integration test)

- **Acronym runs pass.** All three sniffs use non-strict camelCaps, so
  consecutive capitals are accepted: `$userId` and `$userID`, `getUserId()`
  and `getUserID()`, `HttpClient` and `HTTPClient` are all valid.
- **Single-letter identifiers** (`$x`) pass.
- **Leading underscores** are flagged on public properties
  (`PublicHasUnderscore`) and on top-level locals (`$_foo`), but the Squiz
  sniff strips a leading underscore from private properties and from locals
  inside class scope before the camelCaps check, and the PSR1 sniff strips
  leading underscores from method names — so `private $_legacy`, `$_inClass`,
  and `_legacyHelper()` slip through. A documented limitation of the existing
  sniffs.
- **Class constants** are conventionally `UPPER_SNAKE_CASE` and are
  explicitly excluded from this rule; no constant-casing sniff is wired in by
  this standard.

## Out of scope for the linter

SQL field casing (snake_case), SQL keyword casing (UPPERCASE),
URL/query-string/config-key casing (snake_case), and environment-variable
casing (UPPER_SNAKE_CASE) all live inside strings and external systems — a
token-based sniff cannot see them, and no test asserts on them. Those stay
with code review.
