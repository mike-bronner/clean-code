# PHPMD Design: GotoStatement

## Rule

- Do not use `goto`.

**Why:** a `goto` obscures control flow — the reader has to find the target
label to know what runs next, and no tool can follow the jump. Use standard
control structures and separate methods instead.

```php
// PHPMD (and this ruleset) flags this:
A:
if ($param === 42) {
    goto X;
}
X:
return 42;
```

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 1.1.0)_

## Mapping — Tier 1 (existing sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/GotoStatement` | `Generic.PHP.DiscourageGoto` (message code `.Found`) |

Enforced by `Generic.PHP.DiscourageGoto`, wired into the master ruleset
(`rules.xml`) — no custom sniff needed
([#109](https://github.com/mike-bronner/phpcs-rules/issues/109)). No Slevomat
sniff disallows `goto` — it appears there only as a cognitive-complexity
increment (`Complexity.Cognitive`) and in control-structure spacing. Several
other PHPCS sniffs read `T_GOTO` for their own purposes (language-construct
spacing, unreachable code, where a `switch` case ends), but
`Generic.PHP.DiscourageGoto` is the only sniff in either standard that reports
the construct itself. Running `phpcs` with `rules.xml` therefore covers this
rule, and `phpmd` does not have to run separately for it.

- **Detection** — the sniff listens for `T_GOTO` and `T_GOTO_LABEL` and reports
  each occurrence at its own line and column, wherever it appears: at file
  scope, inside a function, inside a method, or nested in loops. PHPMD has no
  threshold or configurable property for this rule, so there is nothing to tune.
- **Severity raised to error** — the sniff reports a *warning* out of the box.
  `rules.xml` overrides it with `<type>error</type>` so that `goto` fails a
  `phpcs` run the way it fails a `phpmd` run. Left as a warning, `phpcs` would
  exit `0` on `goto` usage and the mapping would not actually replace `phpmd`.
- **Message replaced** — the stock text is "Use of the GOTO language construct
  is discouraged", which contradicts the error severity above and names no
  remedy. `rules.xml` overrides it with "Use of the goto construct is
  disallowed; replace it with standard control structures and separate
  methods." The sniff emits a single code, `Found`, and PHPCS honours a
  `<message>` override only on a rule referenced *by code* — which is why
  `rules.xml` refers to `Generic.PHP.DiscourageGoto.Found` rather than to the
  sniff name.
- **Not auto-fixable** — matching PHPMD. Rewriting a jump as a loop, an early
  return, or an extracted method depends on what the jump means, so there is no
  mechanical replacement.

## Divergences from PHPMD

Both differences make this ruleset **stricter**. Every violation PHPMD reports
is also reported here; the sniff adds two shapes PHPMD cannot see, because
PHPMD's rule class implements `MethodAware` and `FunctionAware` only and walks
for `GotoStatement` nodes alone.

| Shape | PHPMD | This ruleset |
| --- | --- | --- |
| `goto` inside a function or method | flagged | flagged |
| Target label (`x:`) | not flagged | flagged |
| `goto` at file scope, outside any function | not flagged | flagged |

Both extra reports are true positives. A label in PHP is reachable only by
`goto`, so it is part of the same construct — and removing the jump while
leaving the label behind leaves dead syntax. A `goto` at file scope obscures
control flow exactly as much as one inside a method.

Measured on `tests/fixtures/DiscourageGotoSniff/failing.php`, which holds five
`goto` statements and four labels: the sniff reports all nine, PHPMD 2.15.0
reports four of them. `tests/Ruleset/GotoStatementTest.php` pins the full set,
the PHPMD subset, and each divergence separately.

## Not flagged

PHPCS has no native goto-label token — the tokenizer rewrites any `T_STRING`
followed by a single colon into `T_GOTO_LABEL` unless the nearest preceding
`case`, `?`, or `enum` says otherwise. Every `identifier :` sequence below is
therefore a chance for a false positive, and none of them fires:

- `switch` cases, including `case Foo::BAR:`
- ternary arms — `$a ? B : C` and `$a ?: C`
- PHP 8 named arguments, including one literally named `goto:`
- alternative syntax — `if (…):` / `else:` / `endif;`, `foreach (…):` / `endforeach;`
- enum backing types — `enum Suit: string`
- `::` constant and static access

Methods *named* `goto` are not flagged either. `goto` is a reserved word, but
PHP 7.0 onwards allows it as a method name, so `$object->goto()`,
`$object?->goto()`, `self::goto()`, `static::goto()`, and `Class::goto()` are
ordinary method calls rather than the language construct. The word appearing in
a comment, a single- or double-quoted string, a heredoc, an array key, or an
identifier such as `$gotoCount` is not flagged either.

`tests/fixtures/DiscourageGotoSniff/boundaries.php` pins every shape in this
section; `passing.php` pins the compliant rewrites of what `failing.php` spells
with `goto`.
