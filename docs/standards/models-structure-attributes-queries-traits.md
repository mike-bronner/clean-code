# Models: Structure (Attributes/Queries traits)

## Standard

- All attribute methods are extracted to an `Attributes` trait, and all query
  methods to `Queries` trait(s) (e.g. `App\Concerns\Attributes\Book`,
  `App\Concerns\Queries\Book`).
- A `BaseModel` holds shared concerns; models `use` their `Attributes` and
  `Queries` traits and stay lean (relationships, `$appends`, `$fillable`).
- Benefits: centralized maintenance, centralized caching/optimization, reduced
  technical and visual debt, and adoption of better DRY patterns.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural standard: whether a model's attribute and query logic
lives in properly-paired traits, whether shared concerns sit on a `BaseModel`,
and whether the model is lean are judgements about cross-file structure that a
token-based PHPCS sniff — which sees one file's tokens at a time — cannot
verify. Enforcement is via code review and developer discipline.

## Partial-enforcement assessment

One narrow slice **is** token-visible: Laravel's attribute and scope methods
follow strict naming/typing conventions, so a sniff can flag them when they
are declared directly in a class body instead of a trait. Focused sniff issue:
[#192](https://github.com/mike-bronner/phpcs-rules/issues/192).

- **Legacy accessors/mutators** — `get*Attribute()` / `set*Attribute()`
  method names.
- **Modern attributes** — methods whose return type resolves to
  `Illuminate\Database\Eloquent\Casts\Attribute`.
- **Local query scopes** — `scope*()` method names (and the `#[Scope]`
  attribute on Laravel ≥ 12).

The enclosing scope (class vs. trait) is token-visible via PHPCS conditions,
so "model-magic method declared in a class body" is a high-signal heuristic
for this standard's anti-pattern — at warning severity, since a single file's
tokens cannot confirm the class is actually an Eloquent model.

## What remains code review

- The namespace convention and trait↔model pairing
  (`App\Concerns\Attributes\Book` belongs to `App\Models\Book`) — cross-file
  structure a sniff cannot see.
- Whether shared concerns live on a `BaseModel`.
- Whether the model is genuinely lean — un-prefixed query helpers, business
  logic, and other extraction candidates carry no token-visible signature.
