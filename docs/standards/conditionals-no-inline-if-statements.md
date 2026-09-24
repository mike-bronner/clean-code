# Conditionals: No Inline If-Statements

## Standard

- Do not use inline if-statements.

**Why:** makes code easier to parse (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (existing sniff)

Enforced by `Generic.ControlStructures.InlineControlStructure` — no custom
sniff needed ([#9](https://github.com/mike-bronner/clean-code/issues/9)). The
`PSR12` standard in the master ruleset (`CleanCode/ruleset.xml`) already bundles this sniff,
so it is active as part of the industry baseline. `CleanCode/ruleset.xml` also references it
by name as intentional belt-and-suspenders (per CONTRIBUTING step 3, standards
name the third-party sniff they depend on), keeping the standard enforced even
if the PSR12 baseline is ever narrowed.

- **Detection** — any control structure without a braced body is flagged at
  the offending line, including inline `else`/`elseif` branches, nested
  inline conditionals, and inline conditionals inside loop bodies.
- **Alternative syntax is compliant** — `if (...): ... endif;` opens an
  explicit scope, so it is not an inline control structure and is not
  flagged.
- **Auto-fixable** — `phpcbf` wraps each inline body in braces. The fixer
  adds braces only; brace placement and indentation are the concern of
  formatting rules, not this sniff.
- **Broader than if-statements** — the sniff also covers brace-less loop
  bodies (`while`, `for`, `foreach`, `do`). That is a superset of this
  standard and consistent with its intent, so the rule is used unrestricted.
