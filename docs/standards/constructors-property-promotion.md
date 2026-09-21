# Constructors: Property Promotion

## Standard

Use property promotion in constructors; avoid defining class properties
outside of the constructor.

**Why:**

- Reduces lines of code (mental debt).
- Defines parameters where they are introduced, making code easier to parse
  (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (existing sniff)

Enforced by Slevomat's
`SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion`, wired
into the master `CleanCode/ruleset.xml`.

- **Detection** — a property declared in the class body is flagged (on the
  declaration line) when the constructor takes a same-named, same-typed
  parameter and does nothing with it but assign it to that property: the
  declaration belongs in the constructor signature as a promoted parameter.
- **Auto-fixable** — `phpcbf` removes the property declaration and the
  constructor assignment, moves the visibility/readonly modifiers onto the
  parameter, and carries the property's default value over to the parameter.
- **Safety boundaries** — the sniff only fires where promotion is mechanically
  safe: the property and parameter type hints must match, the assignment must
  be unconditional, and the parameter must not be modified before it. Promoted
  and non-promoted parameters may mix; properties with attributes, hooks, or
  meaningful doc comments are left alone. Requires PHP 8.0+, which the
  package's `php: ^8.1` constraint already guarantees.

## What remains code review

The sniff flags only promotion *candidates* — properties the constructor
merely copies a parameter into. A property declared in the class body that the
constructor never assigns (or assigns conditionally, or derives from other
values) is not token-provably promotable, so keeping such declarations out of
the class body where a promoted parameter would serve stays a code-review
call.
