# Classes: No Statics

## Standard

- Avoid static classes. Classes are intended to be instantiated and
  identifiable. Static classes have no identity and are not true objects — a
  stow-away from the procedural era, little more than modern `GOTO` statements.

**Why:**

- There is virtually no overhead in instantiating a class (pointless
  optimization).
- Classes behave consistently (mental debt).
- Objects are easier to test and inspect than static classes (testability).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff flags static member *declarations*. Slevomat's
[`Classes.DisallowLateStaticBinding`](https://github.com/slevomat/coding-standard/blob/master/doc/classes.md#slevomatcodingstandardclassesdisallowlatestaticbinding-)
targets late static *binding* usage (`static::`), a different construct, and
was evaluated against this standard's test suite without matching it. The
standard is therefore enforced by the custom
`CleanCode.Classes.DisallowStaticMembers` sniff, wired into the master
`rules.xml` via the CleanCode standard
([#19](https://github.com/mike-bronner/phpcs-rules/issues/19)).

- **Detection** — every static method and static property declaration is
  flagged at its `static` keyword, in any object-oriented container: class,
  abstract class, interface, trait, and enum. Static methods are reported as
  `CleanCode.Classes.DisallowStaticMembers.StaticMethod`, static properties as
  `CleanCode.Classes.DisallowStaticMembers.StaticProperty`.
- **Not flagged** — the `static` keyword also appears in constructs that are
  not static member declarations, and these are deliberately left untouched:
  - **Class constants** — constants are not the target of this standard.
  - **`static` return types** (`function make(): static`) and **late static
    binding** (`new static`, `static::foo()`) — these reference the runtime
    class; they do not declare a static member.
  - **Static closures and arrow functions** (`static fn () => ...`) — anonymous
    functions that merely drop the `$this` binding.
  - **Function-local `static` variables** (`static $count = 0;`) — a statement
    inside a method body, not a class member.
- **Auto-fixable — No (detection only).** Converting a static member to an
  instance member is a refactor, not a mechanical rewrite: every call site
  (`Class::method()`, `Class::$property`) must change to an instance access,
  and the surrounding code must acquire an instance to call against. A
  token-based fixer cannot make those call-site changes safely, so the sniff is
  delivered detection-only. Removing the offending `static` keyword is left to
  the developer performing the refactor.

Ruleset-integration tests covering compliant code, per-line/column violation
reporting for static methods and properties, detection across every
object-oriented container, and the non-fixable (detection-only) guarantee live
at `tests/Ruleset/DisallowStaticMembersTest.php`.

## What remains code review

Nothing about *detecting* statics — that is fully machine-enforced. The
*refactor* to remove a flagged static (introducing an instance, threading it to
call sites) is manual work the sniff surfaces but does not perform.
