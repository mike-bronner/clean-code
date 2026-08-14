# No Dead Code

## Standard

- There should be no unused or commented code.

**Why:** dead code creates unnecessary code bloat, making files harder to
parse; commented or unused code raises questions rather than answering them —
code should answer questions.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (existing rules + custom sniff)

Most of this standard is covered by existing sniffs wired into the master
`rules.xml`; unused private elements needed a custom sniff. Focused issue:
[#29](https://github.com/mike-bronner/phpcs-rules/issues/29).

- **Commented-out code** — `Squiz.PHP.CommentedOutCode` (warning): flags
  comments that are mostly code-shaped tokens. Explanatory prose comments and
  doc-blocks stay below its threshold and are not flagged.
- **Unused parameters** — `SlevomatCodingStandard.Functions.UnusedParameter`
  (error): flags declared parameters never read in the function body.
- **Unused imports** — `SlevomatCodingStandard.Namespaces.UnusedUses`
  (error, **auto-fixable** via `phpcbf`): flags and removes `use` statements
  never referenced. Configured with `searchAnnotations` enabled so imports
  referenced only from doc-block annotations (`@param`, `@var`, …) count as
  used — without it, `phpcbf` would strip them and break the docs.
- **Unused private methods/properties** — custom
  `CleanCode.DeadCode.UnusedPrivateElements` sniff (error). Slevomat's
  `Classes.UnusedPrivateElements` was removed in slevomat/coding-standard 7.0
  (this package pins ^8.15), so the check is reimplemented here. Detection is
  deliberately conservative to avoid false positives: any mention of the
  element's name in the class body — `$this->`/`self::`/`static::` access or
  a string literal (callable arrays, `compact()`, interpolation) — counts as
  a usage. Magic methods and promoted constructor properties are never
  flagged.

## What remains code review

Unused *public/protected* elements (their callers live outside the file, so a
single-file token scan cannot prove them dead), unused private constants,
unused local variables, dynamic access via variable variables, and unreachable
branches. Dead code that a token-based, single-file sniff cannot prove dead
stays with code review.
