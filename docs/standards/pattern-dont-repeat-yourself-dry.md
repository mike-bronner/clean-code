# Pattern: Don't Repeat Yourself (DRY)

## Standard

- Code should be abstracted/refactored into small, manageable parts.
- Unrelated code can then use common functionality abstracted from other code
  paths.
- Don't abstract prematurely; only start abstracting when other code needs to
  perform the same logic.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The textual side of this standard **is statically lintable**: a copy-pasted run
of lines is token-visible. No bundled PHPCS or Slevomat sniff compares one block
of code against another. Every rule either tool ships with "Duplicate" in its
name was evaluated and matches a different construct — Slevomat's
`Whitespaces.DuplicateSpaces` (repeated space characters) and
`Variables.DuplicateAssignmentToVariable` (two assignments to one variable), and
PHPCS's `Generic.Classes.DuplicateClassName`, `Squiz.Classes.DuplicateProperty`,
and `Generic.Functions.FunctionDuplicateArgument` (repeated *names*, not
repeated code). Slevomat's one whole-declaration metric,
`Complexity.Cognitive`, scores each declaration on its own and never compares
two. The standard's textual half is therefore enforced by the custom
`CleanCode.Pattern.AvoidDuplicateCodeBlocks` sniff, wired into the master
`rules.xml` via the CleanCode standard
([#134](https://github.com/mike-bronner/phpcs-rules/issues/134)).

- **Detection** — a run of code lines in one file that repeats an earlier run is
  reported as a duplication candidate, under
  `CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found`. The warning sits on the
  first line of the copy and names both the line the copy ends at and the line
  the original starts at.
- **Blocks, not bodies** — the comparison slides a window over the file's code
  lines, so a duplicate is found wherever it sits: twice inside one method,
  across two methods, or spanning a declaration boundary. A duplicated method
  body is simply the case where the window fills a body.
- **Near-identical, not exact** — each line is summarized by the *types* of its
  tokens. Token content is dropped, so a renamed variable, a renamed method or
  class, and a changed literal all still match. Token types are kept, so code
  that differs in structure does not: a swapped operator (`+` is T_PLUS, `-` is
  T_MINUS), a different keyword, an extra argument, or an added statement are
  genuinely different blocks, and "extract the shared logic" is not the remedy
  for them.
- **Code lines only** — blank lines, comment-only lines, inline HTML, and lines
  holding nothing but a brace, bracket, or `);` are not counted. PSR-12 gives
  braces lines of their own, and counting them would let three lines of logic
  clear a five-line threshold.
- **Configurable threshold** — `minimumLines` is a sniff property, so projects
  can tune sensitivity. It defaults to **5**, the length the standard's own
  guidance calls out, and is floored at 1.
- **Non-overlapping copies only** — a copy is reported once it stands a whole
  window clear of what it repeats, and grows only as far as it can without
  reaching back into it. A long column of same-shaped statements is one run of
  similar lines, not a block repeating itself.
- **Warning severity, not error** — the standard explicitly tolerates
  duplication until an abstraction is warranted ("don't abstract
  prematurely"), so the sniff points at abstraction candidates rather than
  mandating a fix.
- **Auto-fixable — No (detection only).** Extracting shared logic and
  rewriting both call sites is a design change, not a mechanical rewrite.
- **Same-file scope** — a PHPCS sniff sees one file's tokens at a time.
  Project-wide copy/paste detection is the domain of a dedicated copy/paste
  detector such as `phpcpd`, not a PHPCS sniff.

A consequence worth stating plainly: repetitive code that was never copy-pasted
still matches. Enough consecutive same-shaped statements — a long run of
property assignments, a file of near-identical accessors — will clear the
threshold. That is the standard pointing at an abstraction candidate, which is
what a warning is for; a project that disagrees raises `minimumLines`.

Behaviour tests covering the compliant fixture's near misses, the exact
line/column of every report, both threshold boundaries, the overlap rule, and
the open-tag guard live at
`tests/Standards/AvoidDuplicateCodeBlocksTest.php`.

## What remains code review

DRY is ultimately about duplicated *knowledge*, not duplicated text: two
near-identical blocks may encode different business rules that merely coincide
today, and the standard itself defers abstraction until reuse actually
arrives. Whether a flagged duplication has earned an abstraction is a
judgement about intent and cross-file relationships — that call stays with
code review.
