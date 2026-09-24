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

## Enforceability — Tier 3 (code review, with one token-visible slice)

This is an architectural standard: whether a model's attribute and query logic
lives in properly-paired traits, whether shared concerns sit on a `BaseModel`,
and whether the model is lean are judgements about cross-file structure that a
token-based PHPCS sniff — which sees one file's tokens at a time — cannot
verify. Enforcement is via code review and developer discipline.

## Partial enforcement — custom sniff

One narrow slice **is** token-visible: Laravel's attribute and scope methods
follow strict naming/typing conventions, so a sniff can flag them when they
are declared directly in a class body instead of a trait. Sniff:
`CleanCode.Models.ModelMagicMethodLocation`
([#192](https://github.com/mike-bronner/clean-code/issues/192)).

- **Legacy accessors/mutators** — a `get<Name>Attribute()` /
  `set<Name>Attribute()` method name.
- **Modern attributes** — a return type resolving to
  `Illuminate\Database\Eloquent\Casts\Attribute`.
- **Local query scopes** — a `scope<Name>()` method name.
- **Laravel ≥ 12 query scopes** — a method carrying the `#[Scope]` attribute,
  whatever it is named.

The enclosing scope is token-visible, so "model-magic method declared in a
class body" is a high-signal heuristic for this standard's anti-pattern. The
warning names the method and the trait it belongs in (`Attributes` or
`Queries`), with the standard's own namespace convention as the example.

### What the sniff decides, and why

- **A class body only.** The sniff registers on `T_CLASS`, so a trait — the
  compliant destination — is never visited, and neither is an interface (no
  body to extract), an enum (not an Eloquent model), or an anonymous class (no
  name for a paired `App\Concerns\Attributes\<Model>` trait). Declarations
  written inside a method body, including a nested anonymous class, belong to
  that body rather than to the class.
- **The conventional spelling.** Names are matched as Laravel builds them —
  `get`/`set` or `scope` followed by an upper-case letter. That is what keeps
  Eloquent's own `getAttribute()` and `setAttribute()` overrides, and ordinary
  methods like `scopes()`, out of the report. An off-convention spelling
  (`getfooattribute`) does resolve at runtime and is a disclosed false
  negative: matching it would take those near misses with it.
- **The return type has to resolve.** A bare `Attribute` counts only when the
  file's own imports — plain, aliased, or group — bind it to Laravel's cast,
  or the type is written fully qualified. Union, intersection and nullable
  types are split, because one member being the cast is enough.
- **`#[Scope]` is matched on its short name.** Unlike the return type, that
  name carries no other plausible meaning on a model method, and Laravel's own
  documentation writes it bare.
- **Warning severity, detection only.** One file's tokens cannot confirm the
  class is an Eloquent model, so a non-model class using one of these naming
  shapes is reported too — code review settles those. Moving a method to
  another file is not a rewrite the fixer can make.

## What remains code review

- The namespace convention and trait↔model pairing
  (`App\Concerns\Attributes\Book` belongs to `App\Models\Book`) — cross-file
  structure a sniff cannot see.
- Whether shared concerns live on a `BaseModel`.
- Whether the model is genuinely lean — un-prefixed query helpers, business
  logic, and other extraction candidates carry no token-visible signature.
