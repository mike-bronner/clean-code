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

## Enforceability — Tier 2 (partial: warning-level sniff)

The core of this standard is an architectural judgement: whether a given
collaborator *should* be injected ("where possible") depends on what the class
is — service, value object, DTO — and on how the surrounding application
resolves it. None of that is visible to a token-based sniff reading one file at
a time, so the core stays with code review.

One same-file slice **is** token-visible, and is enforced:
`CleanCode.Classes.DisallowConstructorInstantiation`, registered automatically
from `CleanCode/Sniffs/` when the standard loads. The scoping of the
slice is recorded on
[#176](https://github.com/mike-bronner/clean-code/issues/176).

### Detection — one warning per `new` in a constructor body

A constructor's job is to *receive* collaborators, not to build them, so a
`new` expression among its statements is the clearest token-level signal of
hard-wiring over injection. The sniff registers on `T_FUNCTION`, keeps only the
declarations named `__construct` (case-insensitively, as PHP method names are)
that a class-like scope holds directly, and walks the token range between that
method's braces.

- **Warning, not error.** Value objects, DTOs, and default collaborator
  instances are legitimately constructed inline. The sniff points at injection
  candidates; it does not mandate a fix.
- **Reported at the `new` keyword**, once per instantiation, under the code
  `CleanCode.Classes.DisallowConstructorInstantiation.Found`.
- **Detection only.** Replacing an instantiation with an injected parameter
  rewrites the class's signature and every call site — not a mechanical fix, so
  nothing here is auto-fixable.

Deliberately silent on:

- **Anything a `throw` raises** — an exception is not a dependency. The whole
  thrown expression is exempt, not just a `new` written straight after the
  keyword, so `throw (new X())`, `throw match (…) { … => new X() }`,
  `throw $cond ? new A() : new B()` and an exception's own arguments
  (`throw new Wrapper(new Cause())`) are all silent. The exemption ends where
  the thrown expression ends: in `$x = $cond ? throw new E() : new Mailer()`
  the `new Mailer()` belongs to the ternary, not the `throw`, and is reported.
- **A `__construct` that is not a method** — the name is legal on a plain
  function, and PHPCS lints whatever paths it is pointed at, so the declaration
  counts as a constructor only when a class-like scope holds it directly.
- **`new` outside the body** — an ordinary method's `new` is far too noisy to
  flag (factories, named constructors, collections, dates), and a constructor's
  *parameter list* is exactly where an inline default collaborator belongs
  (`private Logger $logger = new NullLogger()`, PHP 8.1 new-in-initializers).
- **`new` inside a closure, arrow function, or anonymous-class body declared in
  a constructor** — that code runs later, or belongs to another type. A lazily
  built collaborator (`$this->make = fn () => new Mailer();`) is the deferred
  construction this standard asks for. The `new` of the anonymous class itself
  is still reported: that instantiation does happen in the constructor.

### Why a custom sniff

No existing PHPCS or Slevomat sniff reports instantiation by *location*. The
catalogues police the *shape* of a `new`: `Squiz.Objects.ObjectInstantiation`
requires it be assigned or returned rather than used bare,
`PSR12.Classes.ClassInstantiation` and
[Slevomat's `ControlStructures.NewWithParentheses` / `NewWithoutParentheses`](https://github.com/slevomat/coding-standard/blob/master/doc/control-structures.md)
prescribe the parentheses, and Slevomat's constructor sniffs
(`Classes.RequireConstructorPropertyPromotion`, already wired in for
[#47](https://github.com/mike-bronner/clean-code/issues/47)) speak about
promotion. None expresses "not inside a constructor body", so this half is a
custom sniff.

### What the cross-file heuristic cannot do

The heuristic sketched on the tracking issue — flagging `new ClassName()` for
classes that are also type-hinted as constructor parameters elsewhere in the
project — is **not** feasible as a PHPCS sniff: it requires cross-file
knowledge, and a sniff sees one file's tokens at a time.

## What remains code review

Everything beyond the flagged slice: whether a collaborator warrants injection
at all, whether the IoC container should resolve it automatically, and whether
a given inline construction is a legitimate value object or a coupling smell.
Those are judgements about intent and application architecture, so they stay
with the reviewer — a warning from this sniff is a prompt for that
conversation, not a verdict.
