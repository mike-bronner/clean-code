# Dependency Injection

## Standard

- Where possible, classes should be injected via the constructor, allowing
  resolution through Inversion of Control (IoC) and avoiding tight coupling
  between classes.

**Why:**

- Provides loose coupling between classes (SOLID).
- Easier-to-maintain code, deciding which instance to provide at instantiation
  (tech debt).
- Allows automatic resolution through IoC if we don't provide an instance
  (SOLID).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

The core of this standard is an architectural judgement: whether a given
collaborator *should* be injected ("where possible") depends on what the class
is — service, value object, DTO — and on how the surrounding application
resolves it, none of which is visible to a token-based sniff reading one file
at a time. Enforcement is via code review and developer discipline.

The heuristic sketched in the tracking issue — flagging `new ClassName()`
calls for classes that are also type-hinted as constructor parameters
elsewhere in the project — is **not** feasible as a PHPCS sniff: it requires
cross-file knowledge, and a sniff sees a single file's tokens.

## Partial enforcement — narrow sniff slice

One same-file slice **is** token-visible: a `new` expression inside a
`__construct` body. A constructor's job is to receive dependencies, not build
them, so instantiating a collaborator there is the clearest token-level signal
of hard-wiring over injection. Focused sniff issue:
[#176](https://github.com/mike-bronner/phpcs-rules/issues/176).

- **Detection** — flag `T_NEW` tokens within `__construct` method bodies.
- **`throw new` excluded** — raising an exception is not dependency
  construction.
- **Warning severity, not error** — value objects, DTOs, and default
  collaborator instances are legitimately constructed inline, so the sniff
  points at injection candidates rather than mandating a fix.
- **Constructor bodies only** — `new` inside ordinary methods is far too noisy
  (factories, named constructors, collections, dates) to flag at the token
  level.

## What remains code review

Everything beyond that slice: whether a collaborator warrants injection at
all, whether the IoC container should resolve it automatically, and whether an
inline construction is a legitimate value object or a coupling smell. Those
are judgements about intent and application architecture — that call stays
with code review.
