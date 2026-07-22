# Arrays: Operator Spacing & Line Breaks

## Standard

- All operators are surrounded by **1 space**, with the exception of an
  operator at the beginning of a statement — such as the not operator (`!`),
  which has no preceding space:

  ```php
  if (! $test) {
      // ...
  }
  ```

- When an expression wraps, operators to the right of the assignment operator
  **start the new line** rather than trailing the previous one.
- Don't line-break **after** a comparison or assignment operator — the operator
  may not dangle at the end of a line.

**Why:** makes code easier to parse (reduces mental load).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (configured rules + custom sniffs)

The standard is enforced by a combination of two configured bundled sniffs and
two custom CleanCode sniffs, wired into the master `rules.xml`
([#35](https://github.com/mike-bronner/phpcs-rules/issues/35)).

### Binary-operator spacing — `Squiz.WhiteSpace.OperatorSpacing`

Enforces **exactly** one space on each side of binary operators (assignment,
comparison, arithmetic, logical). PSR-12 (already in the ruleset) requires *at
least* one space; this sniff tightens that to exactly one, so over-padding used
for alignment (`$a  -  $b`) is also flagged. Configured with:

- `ignoreSpacingBeforeAssignments="false"` — a space before `=` is required
  too (the standard wants one space each side, not alignment padding).
- `ignoreNewlines="true"` — spacing is only policed within a line, so an
  operator that leads a wrapped continuation line is left to the line-break
  sniff below. Auto-fixable.

### Concatenation spacing — `Squiz.Strings.ConcatenationSpacing`

Enforces one space each side of the `.` concatenation operator
(`spacing="1"`, `ignoreNewlines="true"`). Auto-fixable.

### Not-operator spacing — `CleanCode.Operators.NotOperatorSpacing`

Custom sniff. Requires exactly one space **after** `!`, and no space between an
opening `(` / `[` and the `!` that opens the enclosed expression:

- `if (! $test)` — compliant.
- `if (!$test)` — flagged (`NoSpaceAfter`).
- `if ( ! $test)` — flagged (`SpaceBefore`).

A `!` that follows a binary operator (`$a = ! $b`, `return ! $c`, `$a && ! $b`)
keeps the space that belongs to the preceding operator, so it is left alone.
Auto-fixable.

### Operator line breaks — `CleanCode.Operators.OperatorLineBreak`

Custom sniff. Flags an assignment, comparison, logical, or concatenation
operator left dangling at the end of a wrapped line — the operator must lead
the continuation line instead (arithmetic operators such as `+`/`-` are out of
scope):

```php
// compliant
$message = $greeting
    . $name;

// flagged (OperatorAtLineEnd)
$message = $greeting .
    $name;
```

**Reporting only** — where the operator lands on the rewritten line (re-indent,
merge, or split) is a layout judgement, so this rule has no auto-fixer.

Operators inside an `if`/`elseif`/`while`/`for` condition are left to
`CleanCode.Conditionals.OneConditionPerLine`, which owns condition layout and
can auto-fix it; this sniff defers there so a dangling operator in a wrapped
condition is reported once, not twice.

### Tests

- `tests/Standards/NotOperatorSpacingTest.php` and
  `tests/Standards/OperatorLineBreakTest.php` pin the custom sniffs against
  hand-rolled `phpcs` / `phpcbf` runs (compliant, violation, and — for the
  fixable sniff — autofix-before/autofix-after fixtures).
- `tests/Rules/OperatorSpacingRulesTest.php` pins the two configured Squiz
  sniffs and confirms both custom sniffs are reachable through the master
  ruleset.
