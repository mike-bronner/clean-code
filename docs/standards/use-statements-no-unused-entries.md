# Use Statements: No Unused Entries

## Standard

- Use statements should not include unused entries.

**Why:**

- Less code is the best code (mental debt).
- Unused code is useless (no dead code).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (existing sniff)

Fully enforceable by Slevomat's
[`SlevomatCodingStandard.Namespaces.UnusedUses`](https://github.com/slevomat/coding-standard/blob/master/doc/namespaces.md#slevomatcodingstandardnamespacesunuseduses-)
sniff, wired into the master `CleanCode/ruleset.xml`
([#68](https://github.com/mike-bronner/phpcs-rules/issues/68)).

- **Detection** — every `use` statement that is never referenced in the file
  is flagged individually at its own line
  (`SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse`).
- **Auto-fixable** — `phpcbf` removes each unused `use` statement cleanly,
  leaving used imports and surrounding code untouched.
- **Docblock references count as usage** — the sniff is configured with
  `searchAnnotations: true`, so an import referenced only in a docblock
  (`@param`, `@throws`, `@var`, ...) is *not* treated as unused. Those
  references are live — docblocks and static-analysis tooling resolve their
  type names against the `use` statements — so removing them would be
  removing working code, not dead code. With the sniff's default
  (`searchAnnotations: false`) such imports would be flagged and auto-removed.

Ruleset-integration tests covering compliant code, per-line violation
reporting, docblock-only references, and the auto-fixer live at
`tests/Ruleset/UnusedUsesTest.php`.

## What remains code review

Nothing — this standard is fully machine-enforced.
