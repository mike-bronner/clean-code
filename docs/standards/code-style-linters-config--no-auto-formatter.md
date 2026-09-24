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

This is a **process/tooling standard**, not a rule about code shape. Its core
is enforced by **code review and developer discipline**. One narrow slice of it
*is* token-visible and carries a sniff —
`CleanCode.CodeStyle.NoFormatterDirectives`, described under *Partial
enforcement* below — but that slice is a heuristic over one artifact, not the
standard.

Both rules describe the *environment and workflow* that produce the code, not
the tokens the code leaves behind. Whether a developer's editor runs PHPCS
against the repo's `phpcs.xml` is editor-side state that never reaches the
repository; whether a style violation was fixed by hand or by an
auto-formatter is a fact about the process — the resulting tokens are
identical either way. A PHPCS sniff sees one file's tokens at lint time and
can recover neither.

## Partial enforcement assessment

- **Formatter directive comments** — *enforced.* Auto-formatter usage leaves
  one token-visible artifact: on/off and ignore directives committed in
  comments (`@formatter:off` / `@formatter:on`, originating in Eclipse and
  honored by JetBrains/PhpStorm and other formatters; `prettier-ignore` from
  Prettier's PHP plugin). These markers exist only to
  steer an auto-formatter — carving out regions it would otherwise rewrite —
  so their presence in a scanned file is direct evidence a formatter is being
  run over it. Comment contents are plain single-file token data
  (`T_COMMENT` / doc-comment tokens). Covered by the custom
  `CleanCode.CodeStyle.NoFormatterDirectives` sniff
  ([#143](https://github.com/mike-bronner/clean-code/issues/143)) — see
  *The directive-comment sniff* below.
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

Resolution: **one narrow sniff** over the directive-comment slice; the process
rules themselves remain enforced by code review and developer discipline.

## The directive-comment sniff

`CleanCode.CodeStyle.NoFormatterDirectives` reports every comment carrying a
known auto-formatter directive. One error per comment, on the comment's own
line and column, source `CleanCode.CodeStyle.NoFormatterDirectives.Found`.

- **What it reads.** `T_COMMENT` — the `//`, `#` and `/* */` spellings — and a
  doc comment's `T_DOC_COMMENT_TAG` and `T_DOC_COMMENT_STRING` tokens, which is
  where a directive lands inside a `/** */` block. A block comment spanning
  several physical lines is one token per line, so a directive on any of those
  lines is reported on that line. Strings, heredoc and nowdoc bodies are not
  comments and are never read.
- **What it matches.** Each configured directive as a case-insensitive
  substring of one comment token. A comment carrying two directives is reported
  once, naming the first configured one.
- **Shipped directives.** `@formatter:off`, `@formatter:on`, `prettier-ignore`.
- **Configuring it.** The public `directives` property takes a ruleset
  `<property name="directives" type="array">` of `<element>` nodes, keyed or
  not. A configured list **replaces** the shipped one, so a project adding a
  directive of its own re-lists the three above beside it. An entry that is
  empty once trimmed is ignored rather than matching every comment.
- **Detection only.** Deleting the marker removes the evidence rather than the
  formatter, and leaves the region it guarded formatted, so there is no
  mechanical fix.
- **What it cannot see.** A formatter run that was never told to skip anything
  leaves no marker, and everything under *Partial enforcement assessment*
  above that was considered and rejected stays with code review.
