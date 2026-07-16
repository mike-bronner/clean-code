# phpcs-rules
PHPCS linter rules for all coding standards defined in https://mikebronner.dev/clean-code.

## Standards

Each standard is documented under [`docs/standards/`](docs/standards/). Standards
that a token-based sniff cannot verify (Tier 3) are enforced by code review and
developer discipline rather than a PHPCS rule.

### Controllers

- [Controllers: Route Model Binding](docs/standards/controllers-route-model-binding.md) — Tier 3, code review; narrow Tier 2 slice: manual-model-resolution detection ([#169](https://github.com/mike-bronner/phpcs-rules/issues/169))

### Patterns

- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
