# phpcs-rules
PHPCS linter rules for all coding standards defined in https://mikebronner.dev/clean-code.

## Standards

Each standard is documented under [`docs/standards/`](docs/standards/). Standards
that a token-based sniff cannot verify (Tier 3) are enforced by code review and
developer discipline rather than a PHPCS rule.

- [Models: Eager Loading](docs/standards/models-eager-loading.md) — Tier 2, runtime `preventLazyLoading()` safety check + custom sniffs: safety-check verification ([#154](https://github.com/mike-bronner/phpcs-rules/issues/154)), non-empty `$with` ([#153](https://github.com/mike-bronner/phpcs-rules/issues/153))
- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
