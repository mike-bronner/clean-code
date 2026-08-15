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

No existing PHPCS or Slevomat sniff enforces operator *line placement*. PHPCS's
`Squiz.WhiteSpace.OperatorSpacing` and PSR-12's operator rules govern *spacing*
around operators, not whether a wrapped operator leads or trails its line;
PSR-12 even permits boolean operators at either end of the line. None matched
this standard's test suite, so the standard is enforced by **three cooperating
CleanCode sniffs, each owning a disjoint slice of the operator list** — two of
which were already wired into `rules.xml` for earlier standards. Only the math
and bitwise groups needed a new sniff
([#59](https://github.com/mike-bronner/phpcs-rules/issues/59)).

| Operators | Sniff | Wired in for |
|---|---|---|
| `+`, `-`, `*`, `/`, `%`, `**`, `&`, `\|`, `^`, `<<`, `>>` | `CleanCode.Operators.ManipulationOperatorPlacement` | **#59** |
| `.`, `&&`, `\|\|` | `CleanCode.Operators.OperatorLineBreak` | [#35](https://github.com/mike-bronner/phpcs-rules/issues/35) |
| any operator inside an `if`/`elseif`/`while`/`for` condition, where that sniff back-stops it | `CleanCode.Conditionals.OneConditionPerLine` | [#17](https://github.com/mike-bronner/phpcs-rules/issues/17) |

### Why the split is load-bearing

`CleanCode.Operators.OperatorLineBreak` already reports the *identical* rule —
an operator must lead the continuation line, never trail the previous one — over
the assignment, comparison, logical, and concatenation operators. Registering
`.`, `&&`, or `||` on the new sniff as well would report every wrapped
concatenation and boolean **twice**: PHP_CodeSniffer collapses duplicate
*configuration* of the same sniff, but it cannot collapse diagnostics from two
*different* sniffs. Narrowing the new sniff to the math and bitwise groups —
the one part of the list nothing else covers — is what keeps exactly one
diagnostic per wrap.

The same reasoning runs one level further in. Inside an `if`/`elseif`/`while`/
`for` condition, `CleanCode.Conditionals.OneConditionPerLine` already polices a
top-level boolean operator's placement, and collapses a *single* condition (one
carrying no top-level boolean) onto one line wholesale — so both line-break
sniffs stand down there, on exactly the same terms. A non-boolean operator
inside a *multi*-condition is covered by neither, so it stays with the sniff
that owns its token. That decision has one implementation, shared by both
sniffs: `CleanCode\Support\ConditionOperatorOwnership`.

`tests/Standards/ManipulationOperatorPlacementTest.php` pins the split directly,
by intersecting this sniff's `register()` against `OperatorLineBreak`'s — no
fixture involved, so it holds for every token either side claims rather than for
whichever ones an example happens to use.
`tests/Integration/OperatorRulesIntegrationTest.php` pins the same property
end-to-end over the whole master ruleset, line by line.

- **Detection** — a manipulation operator that joins two operands across a line
  break is flagged when it *trails* the previous line (the left-hand operand ends
  the operator's line and the right-hand operand sits on a later line) instead of
  *leading* the continuation line. Reported as
  `CleanCode.Operators.ManipulationOperatorPlacement.OperatorNotLeading` at the
  operator's own line and column. Operators covered *by this sniff*: `+ - * / %
  **` (math) and `& | ^ << >>` (bitwise). The standard's remaining operators —
  `.` (string concatenation) and `&& ||` (logical) — are reported by
  `CleanCode.Operators.OperatorLineBreak` under the same rule, per the table
  above.
- **Not flagged** — cases the standard leaves alone:
  - **Inline usage** — both operands on one line, including operators acting as
    call parameters (`floor(4 + 4.1)`).
  - **Already-leading operators** — a wrapped operator that starts its
    continuation line is compliant.
  - **Operators another sniff already owns** — `.`, `&&`, and `||` anywhere,
    and any registered operator inside a condition `OneConditionPerLine` backs
    up. Not silence about the standard: the wrap is still reported, by the sniff
    that owns it.
  - **Unary and reference forms** — a sign (`-5`, `+5`), reference (`&$ref`), and
    bitwise NOT (`~$bits`) carry no left-hand operand, so they are not
    manipulation operators — and `~` is not registered at all, having no binary
    form. `+` and `-` count as operators only when a real
    operand ends the previous line — including a magic constant (`__LINE__`), an
    interpolated string, or a heredoc/nowdoc body, not just literals and
    variables. A `&` is told apart from bitwise-AND by PHP_CodeSniffer's own
    reference detection, so a by-reference parameter, return, assignment,
    `foreach`, or array element is exempt regardless of what precedes it.
  - **Catch-clause type unions** — a `|` (or `&`) separating exception types in a
    `catch (TypeA | TypeB $e)` clause is a type-union separator, not a bitwise
    operator, so it is never flagged even across a line break. PHP_CodeSniffer
    retokenises union/intersection types to `T_TYPE_UNION`/`T_TYPE_INTERSECTION`
    in parameter, return, and property positions but leaves the catch-clause
    separator as `T_BITWISE_OR`/`T_BITWISE_AND`, so it is exempted by its
    enclosing `catch` parenthesis.
- **Auto-fixable — Yes.** The fixer moves the trailing operator down to lead the
  continuation line, indented one level past the statement's *root* line (escaping
  any enclosing parentheses, brackets, or array literal so a wrapped operator
  inside an `if (...)` condition, call-argument list, or array lands level with
  its operand rather than one level deeper), with a single space before its
  right-hand operand — the whitespace-only rewrite that preserves behaviour and
  indentation. The one exception is a comment sitting between the two operands:
  moving the operator would reorder the comment, so that violation is reported but
  left for the developer.

Tests covering the token-level split, compliant inline and multi-line code,
per-line/column violation reporting for every operator category, operand-boundary
disambiguation (magic constants, interpolated strings, heredoc/nowdoc bodies, and
reference `&`), the catch-clause exemption, and the fixer's continuation indent
inside brackets live at `tests/Standards/ManipulationOperatorPlacementTest.php`,
over the fixtures in `tests/fixtures/ManipulationOperatorPlacementSniff/`. The
byte-for-byte auto-fix output, its idempotency, and the passing/failing floor
come from the generic sweep in `tests/Contract/SniffContractTest.php`.

## What remains code review

Nothing about *placement* — that is fully machine-enforced. The math and bitwise
groups are auto-fixable; a wrapped `.`, `&&`, or `||` is reported by
`CleanCode.Operators.OperatorLineBreak`, which is deliberately report-only
because where the operator lands on the rewritten line is a layout judgement it
does not make (see
[Arrays: Operator Spacing & Line Breaks](arrays-operator-spacing-and-line-breaks.md)).
Whether a long manipulation expression should be *broken up at all* (extracting
intermediate variables, simplifying the arithmetic) is a judgement call the
sniff does not make.
