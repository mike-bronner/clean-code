# Conditionals: One Condition Per Line

## Standard

- If there is only a single condition in an if-statement, keep the condition
  portion on a single line; do not place the condition on its own line.
- For conditions consisting of multiple conditions, place each condition on
  its own line with the operator **preceding** the condition.

**Why:** makes code easier to parse (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

Enforced by the auto-fixable **`CleanCode.Conditionals.OneConditionPerLine`**
sniff ([#17](https://github.com/mike-bronner/clean-code/issues/17)), covering
`if`, `elseif`, `while`, `for`, and `do-while`:

- **Single condition** — a condition with no top-level boolean operator
  (`&&`, `||`, `and`, `or`) must stay on one line with its keyword; splitting
  it across lines is flagged (`SingleConditionNotOnOneLine`). In a `for`
  header only the condition section itself must not wrap — the three
  semicolon-separated sections may sit on separate lines.
- **Multiple conditions** — a condition combining several top-level boolean
  expressions must place each on its own line
  (`MultipleConditionsOnOneLine`), with the operator leading the continuation
  line rather than trailing the previous one (`BooleanOperatorNotLeading`,
  reported at the trailing operator's line).
- **Not misidentified** — boolean operators nested inside parentheses,
  square brackets, or braces (grouped sub-conditions, function-call
  arguments, array literals) are part of a single condition, not separate
  conditions. Ternary expressions are out of scope — they belong to the
  ternary-conditionals standard
  ([#20](https://github.com/mike-bronner/clean-code/issues/20)).
- **Auto-fixer** — `phpcbf` joins an unnecessarily split single condition,
  splits a collapsed multi-condition, and moves trailing operators to lead
  the next line. The one exception: a split single condition containing a
  comment is reported but left alone, since joining would corrupt the
  comment.

### Why not Slevomat

`SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition` /
`RequireMultiLineCondition` were evaluated first (per the execution process)
and rejected on three exact mismatches, verified against their source and by
running them over this sniff's test fixture:

1. Neither sniff checks `for` loops (they register only `if`/`elseif`/
   `while`/`do-while`).
2. `RequireSingleLineCondition` forces even multi-boolean conditions onto a
   single line whenever they fit `maxLineLength` (`0` means *always*) — the
   opposite of this standard, with no configuration that scopes it to simple
   conditions only.
3. `RequireMultiLineCondition` never flags a multi-line condition whose
   operators trail the line ends, as long as the line count matches — the
   leading-operator layout exists only in its fixer, so the
   trailing-operator violation goes undetected.

## What remains code review

Whether a long single condition should be decomposed into well-named
variables or methods instead of merely staying on one line, and whether a
multi-condition would read better combined or extracted — layout is lintable,
readability judgement is not.
