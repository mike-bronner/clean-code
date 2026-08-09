# Pattern: Don't Repeat Yourself (DRY)

## Standard

- Code should be abstracted/refactored into small, manageable parts.
- Unrelated code can then use common functionality abstracted from other code
  paths.
- Don't abstract prematurely; only start abstracting when other code needs to
  perform the same logic.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The textual side of this standard **is statically lintable**: a copy-pasted
function body is token-visible. No bundled PHPCS or Slevomat sniff compares one
declaration's body against another's. Every rule either tool ships with
"Duplicate" in its name was evaluated and matches a different construct —
Slevomat's `Whitespaces.DuplicateSpaces` (repeated space characters) and
`Variables.DuplicateAssignmentToVariable` (two assignments to one variable),
and PHPCS's `Generic.Classes.DuplicateClassName`,
`Squiz.Classes.DuplicateProperty`, and
`Generic.Functions.FunctionDuplicateArgument` (repeated *names*, not repeated
bodies). Slevomat's one whole-declaration metric, `Complexity.Cognitive`,
scores each declaration on its own and never compares two. The standard's
textual half is therefore enforced by the custom
`CleanCode.Functions.AvoidDuplicateFunctionBodies` sniff, wired into the master
`rules.xml` via the CleanCode standard
([#134](https://github.com/mike-bronner/phpcs-rules/issues/134)).

- **Detection** — two or more function or method bodies in one file whose
  normalized token streams are identical are reported as duplication
  candidates, under `CleanCode.Functions.AvoidDuplicateFunctionBodies.Found`.
  The warning sits on each copy and names the original's declaration and line,
  so a group of three identical bodies produces two warnings, not three.
- **Normalization** — comments and whitespace are stripped, so reformatting or
  re-commenting a copy does not hide it, and only the tokens *between* the
  braces are read, so the signature plays no part: two bodies match even when
  their names, visibility, parameter types, and return types differ.
- **Exact match only** — every surviving token contributes its type *and* its
  content, so a renamed variable, a changed literal, or a swapped operator
  makes two bodies different. Near-miss clone detection is deliberately out of
  scope: at the token level it is noise-prone, and this standard's remedy
  (extract the shared logic) only applies where the logic really is shared.
- **Configurable threshold** — `minimumStatements` is a sniff property, so
  projects can tune sensitivity. It defaults to **3** and is floored at 1. A
  statement is a `;` terminator anywhere in the body, except the two separators
  inside a `for (…;…;…)` header. Below the threshold a body is never compared,
  which is what keeps boilerplate accessors, empty stubs, and one-line
  delegations quiet.
- **Warning severity, not error** — the standard explicitly tolerates
  duplication until an abstraction is warranted ("don't abstract
  prematurely"), so the sniff points at abstraction candidates rather than
  mandating a fix.
- **Auto-fixable — No (detection only).** Extracting shared logic and
  rewriting both call sites is a design change, not a mechanical rewrite.
- **Same-file scope** — a PHPCS sniff sees one file's tokens at a time.
  Project-wide copy/paste detection is the domain of a dedicated copy/paste
  detector such as `phpcpd`, not a PHPCS sniff.
- **Named declarations only, outermost first** — closures and arrow functions
  are anonymous callbacks and are not compared. A declaration nested inside
  another declaration's body — a method of an anonymous class returned from a
  method, say — is skipped too, because its tokens already form part of the
  enclosing body's stream: comparing both would report one duplication twice,
  and the enclosing report is the actionable one. The cost is a deliberate
  blind spot, pinned by
  `tests/fixtures/AvoidDuplicateFunctionBodiesSniff/nested-declarations.php`:
  identical inner declarations inside two *differing* outer bodies go
  unreported.

Behaviour tests covering the compliant fixture's near misses, the exact
line/column of every report, the statement-threshold boundaries, and the
nesting rule live at `tests/Standards/AvoidDuplicateFunctionBodiesTest.php`.

## What remains code review

DRY is ultimately about duplicated *knowledge*, not duplicated text: two
near-identical blocks may encode different business rules that merely coincide
today, and the standard itself defers abstraction until reuse actually
arrives. Whether a flagged duplication has earned an abstraction is a
judgement about intent and cross-file relationships — that call stays with
code review.
