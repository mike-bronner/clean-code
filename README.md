# phpcs-rules
PHPCS linter rules for all coding standards defined in https://mikebronner.dev/clean-code.

## Standards

Each standard is documented under [`docs/standards/`](docs/standards/). Standards
that a token-based sniff cannot verify (Tier 3) are enforced by code review and
developer discipline rather than a PHPCS rule.

- [Conditionals: Avoid Conditionals](docs/standards/conditionals-avoid-conditionals.md) — Tier 3, not statically enforceable
