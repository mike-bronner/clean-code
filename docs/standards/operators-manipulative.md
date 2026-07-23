# Operators: Manipulative

## Standard

Manipulation operators should start on a new line in standalone statements, but
may be written on a single line when acting as parameters:

```php
$result = 4
    + 4;
$result = floor(4 + 4.1);
$string = "Hello"
    . Str::lower(", world!");

if (
    $isTrue
    && $isAlsoTrue
) {
    //
}
```

Common manipulation operators:

- Strings: `.`
- Math: `+`, `-`, `/`, `*`, `%`, `**`
- Logical: `&&`, `||`
- Bitwise: `&`, `|`, `^`, `~`, `<<`, `>>`

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff enforces operator *line placement* for this
full operator set. PHPCS's `Squiz.WhiteSpace.OperatorSpacing` and PSR-12's
operator rules govern *spacing* around operators, not whether a wrapped operator
leads or trails its line; PSR-12 even permits boolean operators at either end of
the line. The sibling `CleanCode.Conditionals.OneConditionPerLine` sniff enforces
leading logical operators inside `if`/`while`/`for` *conditions* only. None
matched this standard's test suite, so it is enforced by the custom
`CleanCode.Operators.ManipulationOperatorPlacement` sniff, wired into the master
`rules.xml` via the CleanCode standard
([#59](https://github.com/mike-bronner/phpcs-rules/issues/59)).

- **Detection** — a manipulation operator that joins two operands across a line
  break is flagged when it *trails* the previous line (the left-hand operand ends
  the operator's line and the right-hand operand sits on a later line) instead of
  *leading* the continuation line. Reported as
  `CleanCode.Operators.ManipulationOperatorPlacement.OperatorNotLeading` at the
  operator's own line and column. Operators covered: `.` (string concatenation);
  `+ - * / % **` (math); `&& ||` (logical); `& | ^ << >>` (bitwise).
- **Not flagged** — cases the standard leaves alone:
  - **Inline usage** — both operands on one line, including operators acting as
    call parameters (`floor(4 + 4.1)`).
  - **Already-leading operators** — a wrapped operator that starts its
    continuation line is compliant.
  - **Unary and reference forms** — a sign (`-5`, `+5`), reference (`&$ref`), and
    bitwise NOT (`~$bits`) carry no left-hand operand, so they are not
    manipulation operators. `+`, `-`, and `&` are only treated as manipulation
    operators when a real operand ends the previous line.
- **Auto-fixable — Yes.** The fixer moves the trailing operator down to lead the
  continuation line, indented one level past the statement's first line, with a
  single space before its right-hand operand — the whitespace-only rewrite that
  preserves behaviour and indentation. The one exception is a comment sitting
  between the two operands: moving the operator would reorder the comment, so that
  violation is reported but left for the developer.

Ruleset-integration tests covering compliant inline and multi-line code,
per-line/column violation reporting for every operator category, the auto-fix
output, fixed-output idempotency, and the non-fixable comment guard live at
`tests/Ruleset/ManipulationOperatorPlacementTest.php`.

## What remains code review

Nothing about *placement* — that is fully machine-enforced and auto-fixable.
Whether a long manipulation expression should be *broken up at all* (extracting
intermediate variables, simplifying the arithmetic) is a judgement call the
sniff does not make.
