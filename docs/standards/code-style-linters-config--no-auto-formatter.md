# Code Style: Linters (config & no auto-formatter)

## Standard

- Your editor must support PHPCS linting and be configured to use the
  `phpcs.xml` file in the root of the project, alerting you to style
  violations. Violations not covered by linters should be caught and fixed
  during review.
- Do not use any auto-formatter that corrects linter issues, as this blows out
  reviews and hides the actual changes made. Manual correction reinforces good
  coding habits.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is a **process/tooling standard**, not a rule about code shape. It is
**not** enforced by a PHPCS sniff. Enforcement is via **code review and
developer discipline**.

Both rules describe the *environment and workflow* that produce the code, not
the tokens the code leaves behind. Whether a developer's editor runs PHPCS
against the repo's `phpcs.xml` is editor-side state that never reaches the
repository; whether a style violation was fixed by hand or by an
auto-formatter is a fact about the process — the resulting tokens are
identical either way. A PHPCS sniff sees one file's tokens at lint time and
can recover neither.

## Partial enforcement assessment

- **Formatter directive comments** — *heuristic found.* Auto-formatter usage
  leaves one token-visible artifact: on/off and ignore directives committed in
  comments (`@formatter:off` / `@formatter:on`, originating in Eclipse and
  honored by JetBrains/PhpStorm and other formatters; `prettier-ignore` from
  Prettier's PHP plugin). These markers exist only to
  steer an auto-formatter — carving out regions it would otherwise rewrite —
  so their presence in a scanned file is direct evidence a formatter is being
  run over it. Comment contents are plain single-file token data
  (`T_COMMENT` / doc-comment tokens). Focused sniff issue:
  [#143](https://github.com/mike-bronner/phpcs-rules/issues/143).
- **Committed formatter config files** (`.php-cs-fixer.dist.php`, `.php_cs`,
  `pint.json`) — considered and rejected. PHPCS's file scanner skips hidden
  files by default and never tokenizes JSON, so these configs never reach a
  sniff. A filesystem probe from each scanned file up to the project root is
  anchored to the *project*, not the file being scanned: it would either
  re-fire on every file in the run or need run-global state, neither of which
  fits PHPCS's per-file reporting model. Better caught in review or by a CI
  script.
- **`phpcbf` runs** — not detectable. PHPCS's own fixer is exactly the class
  of tool the standard bans, but its output is indistinguishable from
  hand-corrected code; auto-fixing leaves no token trace.
- **Editor configuration** (the first rule) — not statically enforceable.
  What a developer's editor does is environment state, invisible to any
  analysis of repository contents.

Resolution: **documentation-only** for this standard — the process rules
remain enforced by code review and developer discipline, with the narrow
directive-comment slice tracked separately in
[#143](https://github.com/mike-bronner/phpcs-rules/issues/143).
