# Indentation: Logical Groupings

## Standard

- If-statements with complex logic should have one condition per line, and
  groupings of conditions (using parentheses) should indent subsequent
  conditions to the first within the parentheses.

**Why:** indicates the logical structure of conditions so they can be quickly
parsed (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

Enforced by the auto-fixable **`CleanCode.Indentation.LogicalGroupings`** sniff
([#41](https://github.com/mike-bronner/phpcs-rules/issues/41)), covering the
conditions of `if`, `elseif`, `while`, `for`, and `do-while`.

The one-condition-per-line baseline is the concern of the separate
[Conditionals: One Condition Per Line](conditionals-one-condition-per-line.md)
standard ([#17](https://github.com/mike-bronner/phpcs-rules/issues/17)). This
sniff adds the *indentation of parenthesized condition groups* on top of it:

- **Grouping indented one level deeper** — when a parenthesized sub-grouping of
  conditions (e.g. `($a && $b)`) is broken across multiple lines, each condition
  directly inside the parentheses must be indented one level (four spaces)
  deeper than the line the group opens on. A group at or shallower than the
  enclosing level, or deeper than one level, is flagged
  (`GroupNotIndented`, reported at the group's first condition).
- **A first condition needs a line of its own** — a first condition written on
  the group's opening line (`&& (   $a`) cannot sit one level deeper while it
  stays there: the whitespace in front of it is mid-line spacing, and the
  indentation of the line it shares is the enclosing condition's. It is flagged
  under the same `GroupNotIndented` code and the fixer moves it onto its own
  line at the group's level. The condition after it is the group's *second*, so
  it is measured as one — a group's first condition is the first condition
  inside the parentheses, not the first one that happens to start a line.
- **Conditions within a group align** — every subsequent condition in the same
  group aligns with the group's first condition (i.e. sits at that same one-level
  indent). A condition off that level is flagged
  (`MisalignedGroupedCondition`, reported at the offending condition).
- **Nested groups step in further** — a grouping inside a grouping is measured
  against its own immediate parent, so each nesting level indents one step
  further than the last, to arbitrary depth.
- **Left alone** — a simple multi-line condition with no parenthesized
  sub-grouping (that layout is #17's concern) and a single-line group are never
  touched, so there are no false positives.

  Detection recognises groupings rather than excluding calls, and that direction
  is deliberate. A parenthesis can only open a grouping where a new operand may
  begin: directly after an operator (boolean, comparison, arithmetic,
  assignment, concatenation or cast), after `!`, after a ternary arm, after an
  opening delimiter such as `(` or `[`, or after a `,`/`;` separator. Each of
  those operator classes is taken from PHP_CodeSniffer entire rather than
  member by member, so the set cannot end up complete except for the one token
  nobody thought of. Everything else is an operand belonging to whatever precedes
  it, and is skipped whole — a function, method, or constructor argument list, a
  `new class(…)` argument list, a `match` subject, a closure or arrow-function
  parameter list. Asking the opposite question ("is this a call?") would need an
  allowlist of every such preceding token, and every name missing from it would
  be a false positive on valid code; asking this one means an unrecognised
  construct is left alone instead.

  Only the condition's own tokens are read at all. An array literal, a
  subscript, a brace block, and an arrow-function body are each jumped whole, so
  nothing below them is examined: a parenthesized boolean used as an array value
  (`'flag' => ($a && $b)`) or element, as a subscript, or as an assignment inside
  a closure body is that construct's own expression, not a grouping of the
  condition it happens to sit inside. This is why the rule above ("a parenthesis
  after `[` or `,` may open a grouping") never fires within an array: the walk
  never gets there.

  Three interiors are held out of the measured conditions as well, because none
  of them is a condition: a comment line inside a grouping, the body of an arrow
  function (`fn () => …`) used as a boolean operand, and the continuation lines
  of a multi-line string, heredoc, or nowdoc. The last matters most — PHP_CodeSniffer
  splits such a literal into one token per physical line, and each of those
  tokens begins a line, so measuring them would report violations and reindenting
  one would rewrite the string's value rather than the code's layout. All three
  walks that read a grouping — the one deciding whether a parenthesis opens one,
  the one testing it for a top-level boolean, and the one measuring the
  conditions inside it — share a single list of the constructs to step over, so a
  construct can never be recognised by one and missed by another.

  On source PHP cannot parse, a construct can be opened and never closed, and
  PHP_CodeSniffer then records no end for it. A walk that cannot find where a
  nested construct ends cannot tell that construct's tokens from the condition's
  own, so all three stop there: the parenthesis goes unclassified, nothing
  inside it is measured, and `phpcbf` moves nothing. A file that does not lint is
  a file this sniff reports nothing about, rather than one it reindents on a
  guess.
- **Cost** — a group is measured from its own direct tokens, stepping over each
  nested construct in one jump rather than walking through it, so the work is
  linear in the size of the condition however deeply its groups nest. A group's
  level is read from the line its parenthesis opens on, and the first token of
  that line is looked up in an index built once per token stream rather than
  found by stepping backwards from the group, so the work stays linear when
  many openers share one physical line as well. This package runs inside other
  projects' lint pipelines, where a pathological file costs somebody else's CI.
- **Auto-fixer** — `phpcbf` reindents each offending condition line to the
  correct nesting level, and breaks the line of a first condition glued to its
  group's opening parenthesis so it starts at that level; the resulting file
  passes the sniff with zero violations.

### Why not an existing sniff

Existing PHPCS/Slevomat indentation sniffs were evaluated first (per the
execution process) and none enforces this rule:

1. `Generic.WhiteSpace.ScopeIndent` tracks indentation of *brace scopes*
   (function bodies, control-structure blocks); it has no concept of a
   parenthesized condition sub-grouping, so it never checks the indentation
   inside a multi-line condition.
2. `PSR12.ControlStructures.ControlStructureSpacing` (already wired in via
   `PSR12`) requires a multi-line condition's lines to be indented at least once
   relative to the keyword, but treats every condition line as one flat level —
   it does not step nested parenthesized groups progressively deeper, which is
   the whole point of this standard.
3. Slevomat's `ControlStructures.RequireMultiLineCondition` checks only that a
   long condition *is* multi-line (line count), not the per-group indentation
   depth of the conditions within it.

A custom sniff is therefore required; the tests were not weakened to fit any of
the above.

Tests live at `tests/Standards/LogicalGroupingsTest.php`, over the fixtures in
`tests/fixtures/LogicalGroupingsSniff/`. They pin each violation to its exact
line, column, and error code (unindented, too-shallow, too-deep, misaligned,
nested, and glued to the group's opening parenthesis both with spacing after
that parenthesis and with none, across all five control structures and both
operator spellings), pin the expected indent a nested group is measured
against, and prove the fixer moves the reported condition lines and nothing
else. The generic floor — the compliant
fixture is clean, the failing one is flagged, and the fixer round-trips and is
idempotent — comes from the shared sweep in `tests/Contract/`, which this sniff
joins through `tests/Sniffs.php`.

## What remains code review

Whether a complex condition should be decomposed into well-named boolean
variables or a query method rather than merely being laid out and indented
correctly — layout is lintable, the decision to extract is a readability
judgement left to review.
