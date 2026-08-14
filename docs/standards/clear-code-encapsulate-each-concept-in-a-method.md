# Clear Code: Encapsulate Each Concept in a Method

## Standard

Refactor each concept into its own method. Through careful naming, this
results in readable, clean code.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

What constitutes a single *concept* is a semantic judgement — a token-based
PHPCS sniff sees statements and expressions, not conceptual boundaries, so it
cannot decide whether a method encapsulates one concept or three. Enforcement
is via code review and developer discipline.

## Partial enforcement

A narrow token heuristic **can** catch the most common textual footprint of
an unextracted concept. Focused sniff issue:
[#159](https://github.com/mike-bronner/phpcs-rules/issues/159).

- **Section-labelling comments** — a standalone `//` comment inside a method
  body that labels the block of statements after it (`// validate the
  payload`, `// build the response`) is the classic sign of a concept that
  wants its own method: extract the block into a method named after the
  comment and the comment becomes redundant. Token-visible: a whole-line
  comment inside a function scope followed by further statements in the same
  scope.
- **Warning severity, not error** — comments that explain *why* rather than
  labelling *what* are legitimate, and the token stream cannot tell the two
  apart; the sniff points at extraction candidates rather than mandating a
  fix.
- **Size and complexity proxies are already tracked** — an over-long or
  over-branchy method usually holds several concepts, but those proxies have
  their own issues: method length
  ([#91](https://github.com/mike-bronner/phpcs-rules/issues/91)), cyclomatic
  complexity ([#88](https://github.com/mike-bronner/phpcs-rules/issues/88)),
  NPath complexity
  ([#89](https://github.com/mike-bronner/phpcs-rules/issues/89)), and nesting
  depth ([#36](https://github.com/mike-bronner/phpcs-rules/issues/36)). This
  standard adds no duplicates.

## What remains code review

Concept boundaries themselves. A method can mix several concepts without a
single comment or excessive length, and a long, comment-free method can
legitimately express one concept. Whether each method encapsulates exactly
one concept — and whether its name states that concept honestly — stays with
code review.
