# Constructors: Primary + Named Constructors

## Standard

- Use one primary constructor (`__construct`).
- Provide multiple secondary (named) constructors — static factory methods —
  for the different scenarios in which the object is created.
- Every named constructor makes use of the primary constructor, so
  initialization logic lives in exactly one place.

**Why:** accommodate different construction scenarios without repeating code
(DRY).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (code review), with one lintable slice

The full standard **is not statically enforceable**: whether a named
constructor meaningfully "makes use of" the primary constructor — rather than
duplicating its initialization logic by other means — is a semantic judgement
about intent, and construction flows routinely cross file boundaries
(builders, container factories) that a PHPCS sniff, which sees one file's
tokens at a time, cannot follow. Enforcement is via code review and developer
discipline.

One narrow slice **is** token-visible, and a focused sniff is tracked in
[#184](https://github.com/mike-bronner/phpcs-rules/issues/184):

- **Delegation check** — a `static` method whose declared return type is
  `self`, `static`, or the declaring class name is recognizably a named
  constructor. Its body should contain a `new self(...)` / `new static(...)` /
  `new <DeclaringClass>(...)`, or a static call delegating to another named
  constructor of the same class. A body with neither obtains its instance
  while bypassing the primary constructor (e.g. `unserialize()`, reflection
  instantiation) and gets flagged.
- **Warning severity, not error** — legitimate patterns return a stored
  instance (singleton/registry accessors on the warm path), so the sniff
  points at delegation candidates rather than mandating a fix.

**Considered and rejected:** a naming-prefix check on named constructors
(`from*`, `create*`, `make*`, …). PHP has no canonical prefix vocabulary —
`of()`, `parse()`, and the enum-native `from()`/`tryFrom()` are all idiomatic
— so the check would enforce an arbitrary word list rather than the standard's
actual substance (delegation / DRY).

## What remains code review

Everything semantic: whether the class offers named constructors where
multiple construction scenarios exist, whether the primary constructor holds
*all* the initialization logic or a named constructor quietly duplicates part
of it after delegating, and whether cross-file construction paths (builders,
factories, DI container definitions) respect the single primary constructor.
The sniff in [#184](https://github.com/mike-bronner/phpcs-rules/issues/184)
catches the mechanical bypass; the design judgement stays with review.
