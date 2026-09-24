# Clear Code: One Idea Per Statement

## Standard

Multiple thoughts combine to form an idea; therefore, each code statement
should encapsulate a single idea, potentially spanning multiple lines.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

Whether a statement expresses one *idea* or several is a semantic judgement —
compound, multi-thought statements cannot be reliably detected by a
token-based PHPCS sniff. Enforcement is via code review and developer
discipline.

## Partial enforcement

A narrow token heuristic **can** catch two compound-statement shapes that are
unambiguously multiple ideas in one statement. Focused sniff issue:
[#157](https://github.com/mike-bronner/clean-code/issues/157).

- **Chained assignments** — `$a = $b = $c;`. Multiple assignment tokens at
  the same nesting depth within one statement assign to several targets in
  one go.
- **Assignment inside a condition** — `if ($row = $stmt->fetch())`. An
  assignment idea embedded inside a decision idea.

Both are token-visible with essentially no false positives, and upstream
PHPCS already ships checks for them (`Squiz.PHP.DisallowMultipleAssignments`,
`Generic.CodeAnalysis.AssignmentInCondition`), so the focused issue adopts
those rather than writing a novel sniff.

### Wired severities

| Shape | Rule | Severity |
| --- | --- | --- |
| Chained assignment — `$a = $b = $c;` | `Squiz.PHP.DisallowMultipleAssignments.Found` | error |
| Assignment in an `if`/`elseif`/`for`/`switch`/`case`/`match` condition | `Generic.CodeAnalysis.AssignmentInCondition.Found` | error |
| Assignment in a `while` or `do`/`while` condition | `Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition` | warning |

The `while` case is a warning rather than an error because
`while ($row = $statement->fetch())` is how a cursor is drained in PHP — the
deliberate use and the mistyped comparison look identical, so the rule points at
candidates instead of failing the build.

Three silences are the upstream sniffs' own limits, recorded here so they are
not mistaken for oversights. `Squiz.PHP.DisallowMultipleAssignments` registers
`T_EQUAL` only, so a chain built with a compound operator
(`$total = $count += 1;`) is not reported. It also bows out of `while`
conditions entirely, so `while ($a = $b = $c)` is reported only by the
warning-level `while` rule above. And it exempts parameter and property
defaults, which are declarations rather than statements.

An assignment written inside a condition breaks both readings at once, so both
rules report it — each in its own words.

## What remains code review

Everything beyond those two shapes. Operator count does not map to idea
count: a statement with several boolean operators may express exactly one
idea, and a short statement may smuggle in two. The judgement about what
constitutes a single idea — and whether a long statement should be split or
merely reformatted across lines — stays with code review.
