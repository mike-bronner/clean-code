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
`CleanCode/ruleset.xml` via the CleanCode standard
([#19](https://github.com/mike-bronner/clean-code/issues/19)).

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
  - **A member an ancestor already declares static** — PHP refuses to load a
    class that makes an inherited static method non-static (`Cannot make static
    method Base::x() non static`) or redeclares an inherited static property as
    non-static (`Cannot redeclare static Base::$x as non static`), so reporting
    the line would be asking for code that does not run. The case that reaches
    consumers is a Laravel facade: `Illuminate\Support\Facades\Facade` declares
    `getFacadeAccessor()` abstract protected static, so every facade written
    against it has to keep the keyword. Calling a facade
    (`Cache::get($key)`) was never flagged — the sniff registers on `T_STATIC`,
    and a call site carries no such token.

    The skip is narrow in two ways. A **private** ancestor member is not
    inherited, so a child may legally redeclare it non-static and the report
    stands. An ancestor that cannot be resolved during a lint run answers
    nothing and the report also stands — an unfixable report is better than a
    silently hidden real one. Both halves are pinned by
    `tests/Ruleset/DisallowStaticMembersTest.php` against real vendor
    ancestors, because a fixture class is not autoloadable and would exercise
    the unresolvable path instead.
- **Auto-fixable — No (detection only).** Converting a static member to an
  instance member is a refactor, not a mechanical rewrite: every call site
  (`Class::method()`, `Class::$property`) must change to an instance access,
  and the surrounding code must acquire an instance to call against. A
  token-based fixer cannot make those call-site changes safely, so the sniff is
  delivered detection-only. Removing the offending `static` keyword is left to
  the developer performing the refactor.

  Two findings settle it, and both were measured rather than assumed:

  - **A PHPCS fixer cannot leave the file it is fixing.** `Fixer::startFile()`
    binds it to one `File`, so a call site in another file is unreachable. The
    declaration would lose `static` while its callers kept calling statically.
  - **The breakage is a runtime `Error`, not a load-time one.** `php -l` reports
    no syntax error on a file that calls a now-instance method statically, and a
    script whose call sits on a branch that never runs finishes cleanly.
    `Error: Non-static method X::y() cannot be called statically` fires only when
    that line executes, so cold paths ship broken and stay quiet.

  A quieter hazard applies to the manual refactor too. A static property is one
  slot per class; an instance property is one slot per object. Converting a
  memoisation cache, a registry, or a counter without moving its holder changes
  the answers and raises nothing: `A::bump()` three times counts 1, 2, 3, while
  three fresh instances each count 1. Where this package converted such a cache,
  the helper is **held** by its caller rather than built per call, and the reason
  is written at the constructor.

Ruleset-integration tests covering compliant code, per-line/column violation
reporting for static methods and properties, detection across every
object-oriented container, and the non-fixable (detection-only) guarantee live
at `tests/Ruleset/DisallowStaticMembersTest.php`.

## What remains code review

Nothing about *detecting* statics — that is fully machine-enforced. The
*refactor* to remove a flagged static (introducing an instance, threading it to
call sites) is manual work the sniff surfaces but does not perform.
