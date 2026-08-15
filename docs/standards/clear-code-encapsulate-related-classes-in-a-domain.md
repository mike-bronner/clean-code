# Clear Code: Encapsulate Related Classes in a Domain

## Standard

- Group related classes into a domain representing functional blocks in the
  real world.
- Domain-driven design takes this to the extreme by creating software
  abstractions called domain models that include business logic linking actual
  product conditions to code.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

The standard itself **is not statically lintable**: whether two classes are
*related*, and whether a namespace models a real-world functional block rather
than an arbitrary bucket, are semantic judgements about the problem domain. A
token-based PHPCS sniff sees one file at a time and no token stream reveals
them. Enforcement of the full standard is code review and developer
discipline.

## Partial-enforcement assessment

One narrow slice **is token-visible**: the junk-drawer namespace. A class
filed under a generic technical bucket such as `App\Helpers`, `App\Utils`,
`App\Misc`, or `App\Common` is definitionally *not* grouped by domain, and a
single-file sniff can read the `namespace` declaration and flag discouraged
segment names. Focused sniff issue:
[#190](https://github.com/mike-bronner/phpcs-rules/issues/190).

- **Detection** — namespace declarations containing a discouraged segment
  (case-insensitive) are flagged; suggested defaults: `Helpers`, `Utils`,
  `Utilities`, `Misc`, `Common`, `General`.
- **Configurable list** — the discouraged segments are a sniff property so
  projects can tune them.
- **Warning severity, not error** — a small library may legitimately keep a
  `Helpers` bucket; the sniff points at domain-grouping candidates rather than
  mandating a move.
- **Anti-pattern only** — the sniff cannot verify *positive* domain grouping;
  it flags the well-known names that signal grouping by technical role instead
  of by domain. Framework-mandated layer namespaces (`Controllers`, `Models`,
  `Providers`, …) are out of scope.

## What remains code review

Everything positive about this standard: recognizing which classes belong
together, naming the domain after a real-world functional block, and deciding
when a codebase has grown enough to warrant carving out a domain model.
Whether `Billing` is a genuine domain or a relabeled junk drawer is a
judgement about the business, not the tokens — that call stays with code
review.
