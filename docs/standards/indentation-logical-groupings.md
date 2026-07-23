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
- **Conditions within a group align** — every subsequent condition in the same
  group aligns with the group's first condition (i.e. sits at that same one-level
  indent). A condition off that level is flagged
  (`MisalignedGroupedCondition`, reported at the offending condition).
- **Nested groups step in further** — a grouping inside a grouping is measured
  against its own immediate parent, so each nesting level indents one step
  further than the last, to arbitrary depth.
- **Left alone** — a simple multi-line condition with no parenthesized
  sub-grouping (that layout is #17's concern), a single-line group, and a
  function or language-construct call argument list (its contents count as a
  single condition, never a grouping) are never touched, so there are no false
  positives.
- **Auto-fixer** — `phpcbf` reindents each offending condition line to the
  correct nesting level; the resulting file passes the sniff with zero
  violations.

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

Ruleset-integration tests covering compliant single-level and nested groups,
per-line/column violation reporting (unindented, too-shallow, too-deep,
misaligned, and nested cases), and the auto-fixer (separate before/after
fixtures) live at `tests/Ruleset/LogicalGroupingsRulesetTest.php`.

## What remains code review

Whether a complex condition should be decomposed into well-named boolean
variables or a query method rather than merely being laid out and indented
correctly — layout is lintable, the decision to extract is a readability
judgement left to review.
