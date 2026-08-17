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
  comments that are mostly code-shaped tokens. An explanatory prose comment
  stays below its threshold and is not flagged. A doc-block is never scored at
  all: `/** … */` tokenizes as `T_DOC_COMMENT_*`, and the sniff registers only
  `T_COMMENT`, so it never sees one.
- **Unused parameters** — custom `CleanCode.DeadCode.UnusedFormalParameter`
  sniff (error): flags declared parameters never read in the function body.
  `SlevomatCodingStandard.Functions.UnusedParameter` carried this until
  [#120](https://github.com/mike-bronner/phpcs-rules/issues/120) landed; it has
  no inherited-signature exemption, so it reports every override. The custom
  sniff exempts an override it can resolve in the same file, plus one annotated
  `@inheritdoc` or `#[\Override]`, and is a strict superset otherwise — see
  [docs/phpmd/unusedcode-unusedformalparameter.md](../phpmd/unusedcode-unusedformalparameter.md).
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
  element's name in the body — `$this->`/`self::`/`static::` access or
  a string literal (callable arrays, `compact()`, interpolation) — counts as
  a usage. Magic methods and promoted constructor properties are never
  flagged.

  **Constructs scanned:** named classes, **enums**, and **anonymous classes**.
  A private member of any of the three is reachable only from that same body,
  so a single-file scan can prove it dead. An enum can only ever report a
  method — PHP forbids enum properties.

  **Property and method names are tracked separately**, because PHP keeps them
  in separate namespaces. `$this->foo` marks the property used and leaves a
  same-named `private function foo()` reportable; `$this->foo()` does the
  reverse. Only a name mined from a *string literal* still marks both — a bare
  `'foo'` is as plausibly a callable-array method name as a `compact()`
  property name, so that one stays a deliberate false negative.

## What remains code review

Unused *public/protected* elements (their callers live outside the file, so a
single-file token scan cannot prove them dead), unused private constants,
unused local variables, dynamic access via variable variables, and unreachable
branches.

**Private members of a `trait`** are on this list by name, not just by the
catch-all above: a trait's private method or property is flattened into every
consuming class and may be used only there, so the trait's own body never
proves it dead. `CleanCode.DeadCode.UnusedPrivateElements` therefore does not
scan trait bodies at all. (Interfaces raise no question — PHP forbids private
interface members.)

Dead code that a token-based, single-file sniff cannot prove dead stays with
code review.
