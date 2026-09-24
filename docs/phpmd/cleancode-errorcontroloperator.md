# PHPMD CleanCode: ErrorControlOperator

## Rule

- Do not use the error-control operator `@`.

**Why:** suppression does not just hide the error you meant to stop, it hides
every error you did not predict — and it costs time on every call, because PHP
still raises the diagnostic before discarding it. Change the
`error_reporting()` level, install an error handler, or ask the question
directly (`??`, `isset()`, a readability guard) instead.

```php
// PHPMD (and this ruleset) flags this:
function foo($filePath) {
    $file = @fopen($filePath); // hides exceptions
    $key = @$array[$notExistingKey]; // assigns null to $key
}
```

_Source: [phpmd.org/rules/cleancode.html](https://phpmd.org/rules/cleancode.html#errorcontroloperator)
(PHPMD Clean Code ruleset, since PHPMD 2.9.0)_

## Mapping — Tier 1 (existing sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CleanCode/ErrorControlOperator` | `Generic.PHP.NoSilencedErrors` (message code `.Forbidden`) |

Enforced by `Generic.PHP.NoSilencedErrors`, wired into the master ruleset
(`CleanCode/ruleset.xml`) — no custom sniff needed
([#82](https://github.com/mike-bronner/clean-code/issues/82)). Running `phpcs`
with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not have to run
separately for it.

The issue's Tier-2 estimate ("expect a custom sniff") did not survive contact
with the sniff catalogue: the bundled `Generic` standard already ships the exact
detection, so this landed as Tier 1.

- **Detection** — the sniff registers on the `T_ASPERAND` token and reports
  every error-control operator, whatever expression it prefixes: a function
  call, an array read, a property read, a method call, a variable function, a
  parenthesised expression, `include`, or a bare statement. PHPMD has no
  threshold or configurable property for this rule, so there is nothing to tune.
- **No false positives to exclude** — the tokenizer emits `T_ASPERAND` only for
  the operator. An `@` inside a single-quoted, double-quoted, heredoc or nowdoc
  string, an `@param`/`@throws` docblock tag, an address written in a comment,
  and a PHP 8 attribute (`#[Deprecated]`) are all other token types, so the
  sniff never sees them. `tests/fixtures/NoSilencedErrorsSniff/passing.php`
  carries every one of those shapes and asserts silence on all of them.
- **Severity raised to error** — the sniff reports a *warning* out of the box.
  `CleanCode/ruleset.xml` sets the sniff's own `error` property to `true`, which reports an
  error under the `.Forbidden` code instead of a warning under `.Discouraged`,
  so suppression fails a `phpcs` run the way it fails a `phpmd` run. Left as a
  warning, `phpcs` would exit `0` on suppressed errors and the mapping would not
  actually replace `phpmd`. (`Squiz.PHP.Eval` uses a `<type>error</type>`
  override to say the same thing only because that sniff has no such property.)
- **Not auto-fixable** — matching PHPMD. The sniff registers no fixer, and it
  should not: removing an `@` lets the suppressed diagnostic surface, which is a
  behaviour change for the running application, not a formatting change.

## Divergence from PHPMD

**One shape, and it is stricter — never looser.**

| Shape | PHPMD | This ruleset |
| --- | --- | --- |
| `@` at a file's top level, outside any function or method | silent | reported |

PHPMD's rule class implements `MethodAware` and `FunctionAware`, so PHPMD only
ever hands it a method or function node; an `@` written at file scope is never
inspected. The sniff works from the token, so it reports there too. Suppression
hides the same errors wherever it is written, so the extra report is kept, and
`tests/fixtures/NoSilencedErrorsSniff/divergences.php` pins it.

The two tools also *place* their reports differently, which matters when reading
output rather than when deciding what is a defect. PHPMD attributes each
violation to the enclosing method or function's declaration line and names the
operator's line in the message text (`Remove error control operator '@' on line
25.`); the sniff reports at the operator's own line and column. Verified against
PHPMD 2.15.0: on
`tests/fixtures/NoSilencedErrorsSniff/failing.php` both tools report the same
eleven operators, PHPMD from two declaration lines and the sniff from the eleven
lines the operators sit on.
