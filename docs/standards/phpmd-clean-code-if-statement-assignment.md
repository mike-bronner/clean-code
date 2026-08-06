# PHPMD/CleanCode: IfStatementAssignment

Tier 1 — replicated in PHPCS so `phpmd` no longer has to run for this rule.
Issue [#79](https://github.com/mike-bronner/phpcs-rules/issues/79).

## The rule

PHPMD's Clean Code ruleset flags an assignment used where a comparison was
meant. The value of an assignment is the assigned value, so the condition
tests that instead of a relationship, and the bug is easy to miss:

```php
if ($foo = 'bar') {    // always true — a typo for ===
}

if ($baz = 0) {        // always false
}
```

The rule has no configurable properties and no auto-fix. Rewriting `=` to
`===` would be a guess at intent: `if (($row = next($rows)) !== false)` is a
deliberate assign-and-test, and PHPMD flags it too.

## How it is enforced

`rules.xml` wires in PHPCS's bundled `Generic.CodeAnalysis.AssignmentInCondition`
sniff and raises it from a warning to an error, so an assignment in a condition
fails a `phpcs` run the way it fails a `phpmd` run.

One custom sniff sits beside it,
`CleanCode.Conditionals.DisallowListAssignmentInCondition`, covering the single
shape the Generic sniff misses. Both are report-only.

## How the two tools line up

Verified against `phpmd` 2.15 running `rulesets/cleancode.xml/IfStatementAssignment`,
with the fixtures under `tests/Ruleset/Fixtures/IfStatementAssignment/`.

**Every assignment PHPMD reports, PHPCS reports at the same line** — a single
assignment in an `if`, several in one condition, one nested in a call argument,
chained assignments, a negated or parenthesised assign-and-test, an assignment
in an `elseif`, and assignments in nested `if` statements.

**PHPCS reports more.** PHPMD's rule reads `if` and `elseif` clauses only,
accepts a plain `=` only, and visits function and method bodies only. So these
are silent under PHPMD and flagged here:

| Shape | Example |
| --- | --- |
| Compound assignment operators | `if ($sum += 1)` |
| `while` and `do`/`while` conditions | `while ($row = array_pop($rows))` |
| The condition section of a `for` | `for ($i = 0; $row = $rows[$i]; $i++)` |
| `switch` subjects and `case` labels | `switch ($state = 1)` |
| `match` subjects | `match ($state = 1) { … }` |
| Code outside any function or method | a top-level `if ($x = 1)` |

The wider coverage is deliberate. Each shape is the same code smell, no other
PHPMD rule owns any of them, and the sniff groups the constructs under two
codes (`Found`, `FoundInWhileCondition`), so narrowing to PHPMD's subset is not
expressible as configuration anyway.

**PHPCS misses one shape, which the custom sniff restores.** The Generic sniff
decides an assignment is worth reporting by walking back from the `=` and
requiring a variable or a `]` on the left. A `list()` destructuring target ends
in `)`, which that walk reads as a function call and abandons:

```php
if (list($first, $second) = $data) {    // PHPMD flags it; the Generic sniff does not
}
```

`CleanCode.Conditionals.DisallowListAssignmentInCondition` reports it, using the
same message and the same `Found` code suffix. It covers the same constructs as
the Generic sniff rather than PHPMD's narrower `if`/`elseif` pair, so the two
together read as one rule. `case` is the exception: a case label has no
parentheses to anchor the enclosure test on. PHPMD does not read case labels
either, so leaving them out opens no gap against it.

Short-list destructuring (`if ([$first, $second] = $data)`) ends in `]` and is
already covered by the Generic sniff, so only the long form needs the custom one.

## What stays silent

- Comparisons of every kind — `===`, `==`, `!==`, `<`, and so on.
- An assignment as an ordinary statement, outside any condition.
- A `for` loop's initialiser and increment sections. Only the middle section is
  a condition; `for ($i = 0; $i < 3; $i++)` is not a violation. The three
  sections are read off the header's own two semicolons, so a closure in the
  initialiser or the increment stays in the section it is written in — the
  semicolons in its body do not move the condition.
- `=>` inside a condition — that is an array key, not an assignment.
- Assignments in the conditional part of a ternary. Neither tool detects those.

## Running it

```bash
vendor/bin/phpcs --standard=rules.xml src/
```
