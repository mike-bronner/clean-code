# phpcs-rules
PHPCS linter rules for all coding standards defined in https://mikebronner.dev/clean-code.

## Standards

Each standard is documented under [`docs/standards/`](docs/standards/). Standards
that a token-based sniff cannot verify (Tier 3) are enforced by code review and
developer discipline rather than a PHPCS rule.

- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
- [Models: Persistence Methods (Repository Pattern)](docs/standards/models-persistence-methods-repository-pattern.md) — Tier 3, not statically enforceable; partial slice tracked in [#186](https://github.com/mike-bronner/phpcs-rules/issues/186)
