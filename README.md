# phpcs-rules
PHPCS linter rules for all coding standards defined in https://mikebronner.dev/clean-code.

## Standards

Each standard is documented under [`docs/standards/`](docs/standards/). Standards
that a token-based sniff cannot verify (Tier 3) are enforced by code review and
developer discipline rather than a PHPCS rule.

- [Clear Code: Encapsulate Each Concept in a Method](docs/standards/clear-code-encapsulate-each-concept-in-a-method.md) — Tier 3, code review; partial enforcement via section-labelling-comment sniff ([#159](https://github.com/mike-bronner/phpcs-rules/issues/159))
- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
