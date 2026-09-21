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
which were already wired into `CleanCode/ruleset.xml` for earlier standards. Only the math
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

Standing down is bounded by what the other sniff actually reads. A `for`
header's init and increment clauses sit inside the condition's parentheses, but
`OneConditionPerLine` confines every check it makes to the clause between the
two semicolons. A wrapped operator in either of the other two clauses is
therefore reported here, on both readings of the condition — deferring it would
drop the violation rather than hand it over. The same class defines that span
for both sniffs, so the boundary deferred across is the boundary walked.

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
    `foreach`, or array element is exempt regardless of what precedes it. The
    operand test admits every construct that can end a value — a short-array
    literal (`[1, 2] + [3]`), a postfix `++`/`--` (`$count++ + $step`), a
    backtick shell execution, and the closing brace of a `match`, an anonymous
    class, a closure, or a brace dereference — `${$name}`, `$object->{$name}`,
    `$object?->{$name}`, `Thing::{$name}`, `Thing::${$name}`. A dynamic
    static *call* (`Thing::{$name}()`) ends on the call's `)` instead, so
    there it is the parenthesis that answers, not the brace.
  - **A sign after a statement's closing brace** — a `}` that ends an `if`,
    `while`, `for`, `foreach`, `switch`, `try`, function, or class body ends a
    *statement*, so the `-` in `} -5;` opens a new (discarded) statement rather
    than continuing the previous expression, and is left alone. The same holds
    for a **bare compound-statement block** — `{ … }` written standalone, with
    no owning keyword — whose `}` also ends a statement, so `{ … } - 5;` is a
    block followed by an independent discarded statement, not a subtraction.
    The brace token is identical in every case above and below; what separates
    them is what owns the brace. PHP_CodeSniffer names that owner for a brace
    that opens a scope, and leaves it unset for both a bare block and a
    dereference — so an absent owner is read as a value only when a `$`, `->`,
    `?->`, or `::` opened the brace.
  - **Catch-clause type unions** — a `|` (or `&`) separating exception types in a
    `catch (TypeA | TypeB $e)` clause is a type-union separator, not a bitwise
    operator, so it is never flagged even across a line break. PHP_CodeSniffer
    retokenises union/intersection types to `T_TYPE_UNION`/`T_TYPE_INTERSECTION`
    in parameter, return, and property positions but leaves the catch-clause
    separator as `T_BITWISE_OR`/`T_BITWISE_AND`, so it is exempted by its
    enclosing `catch` parenthesis.
- **Auto-fixable — the math and bitwise groups, yes; `.`, `&&` and `||`, no.**
  The wrapped `.`, `&&` and `||` this standard also covers belong to
  `CleanCode.Operators.OperatorLineBreak`, which reports them and deliberately
  does not rewrite them: where the operator lands on the rewritten line is a
  layout judgement, a boundary the "Arrays: Operator Spacing & Line Breaks"
  standard (#35) set and this one does not reopen. So the standard is fully
  *detected* and partly *fixed*. That split is a decision, not an oversight —
  Mike settled it on [#59](https://github.com/mike-bronner/phpcs-rules/issues/59)
  rather than force a fixer onto another standard's sniff.

  For the groups this sniff owns, the fixer moves the trailing operator down to lead the
  continuation line, indented one level past the statement's *root* line, with a
  single space before its right-hand operand — the whitespace-only rewrite that
  preserves behaviour and indentation. The one exception is a comment sitting
  between the two operands: moving the operator would reorder the comment, so that
  violation is reported but left for the developer.

  The *root* line is the point of that rule. Every continuation operator of one
  statement lands on the same indent, however deep in brackets it sits, so a
  multi-line expression never stair-steps. Reaching it means escaping outward past
  everything that merely divides an expression — the grouping openers `(` and `[`
  (short array included), the argument/element separator `,`, an array key's `=>`,
  a named argument's `:`, and a `for` header's `;` — and stopping at everything
  that ends a statement, chiefly `{` and every other `;`. A `for` header's two
  semicolons divide one header into clauses rather than closing a statement, so
  its init and increment clauses wrap to the same indent as each other and as an
  `if` condition's; read as terminators, the two clauses of one header would
  stair-step. Only those two count: a closure or an anonymous class written
  directly in a clause carries its own statements inside the same parentheses,
  and each `;` in that body terminates a statement exactly as a top-level one
  does — the header's dividers are the two the shared
  `Support\ConditionOperatorOwnership` resolves, which is also where
  `OneConditionPerLine` reads its clause boundaries. So a wrapped operator
  inside an `if (...)` condition, a
  call-argument list (positional or named), or an array literal (keyed or not)
  anchors on the line the statement itself starts on; one inside a `{ … }` block,
  a `switch` case body, or a `match` arm anchors on that inner statement's own
  line, because a brace really does start a new statement.

Tests covering the token-level split, compliant inline and multi-line code,
per-line/column violation reporting for every operator category, operand-boundary
disambiguation (magic constants, interpolated strings, heredoc/nowdoc bodies,
short arrays, postfix `++`/`--`, backticks, value-producing versus
statement-closing braces — the owned ones and the ownerless bare block alike —
and reference `&`), the catch-clause exemption, and the
fixer's continuation indent — each expression divider paired against the form it
has to agree with, the brace boundaries that must *not* be escaped, and the
classification swept against every token PHP_CodeSniffer halts its own
statement-start walk on — live at `tests/Standards/ManipulationOperatorPlacementTest.php`,
over the fixtures in `tests/fixtures/ManipulationOperatorPlacementSniff/`. The
byte-for-byte auto-fix output, its idempotency, and the passing/failing floor
come from the generic sweep in `tests/Contract/SniffContractTest.php`.

## What remains code review

Nothing about *placement* — every wrap the standard governs is detected by one of
the three cooperating sniffs. Only the *fix* is partial: the math and bitwise
groups are auto-fixable; a wrapped `.`, `&&`, or `||` is reported by
`CleanCode.Operators.OperatorLineBreak`, which is deliberately report-only
because where the operator lands on the rewritten line is a layout judgement it
does not make (see
[Arrays: Operator Spacing & Line Breaks](arrays-operator-spacing-and-line-breaks.md)).
Whether a long manipulation expression should be *broken up at all* (extracting
intermediate variables, simplifying the arithmetic) is a judgement call the
sniff does not make.
