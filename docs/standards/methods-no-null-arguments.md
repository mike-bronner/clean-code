# Methods: No Null Arguments

## Standard

- When calling methods with optional parameters, don't pass `null` into the
  methods; use named parameters instead.

**Why:**

- Helps maintainability and readability (mental debt).
- Reduces code (no dead code).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

```php
// Positional null, only there to reach a later parameter — dead code.
$mailer->send('body', null, $cc);

// The named argument says what is meant; the skipped parameter keeps its default.
$mailer->send('body', cc: $cc);
```

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff covers this. Slevomat's
[`Functions.DisallowNamedArguments`](https://github.com/slevomat/coding-standard/blob/master/doc/functions.md#slevomatcodingstandardfunctionsdisallownamedarguments-)
is the *inverse* rule (it bans the very syntax this standard requires), and
[`Functions.NamedArgumentSpacing`](https://github.com/slevomat/coding-standard/blob/master/doc/functions.md#slevomatcodingstandardfunctionsnamedargumentspacing-)
only governs whitespace around an argument that is already named. Both were
evaluated against this standard's test suite without matching it. The standard
is therefore enforced by the custom `CleanCode.Methods.NoNullArguments` sniff,
wired into the master `CleanCode/ruleset.xml` via the CleanCode standard
([#71](https://github.com/mike-bronner/phpcs-rules/issues/71)).

- **Detection** — a literal `null` passed as a *positional* argument to a
  parameter that declares a default is flagged at the `null` token as
  `CleanCode.Methods.NoNullArguments.PositionalNull`. Function calls, `$this->`
  (and `$this?->`) method calls, `self::`/`static::`/`ClassName::` static calls,
  `new ClassName(...)`/`new self(...)` constructor calls, and
  `#[Attribute(...)]` instantiations are all covered.
- **Not flagged:**
  - **Named arguments** (`send(subject: null)`) — the compliant form.
  - **`null` into a required parameter** (`send($body, null)` where the second
    parameter declares no default) — there is no parameter being skipped, so
    the `null` is a real value.
  - **`null` inside a larger argument** — `[null]`, `$a ?? null`,
    `null !== $x`, `fn () => null`. Only an argument that is *exactly* `null`
    represents a skipped parameter.
  - **`null` outside a call-argument position** — assignments, returns,
    comparisons, array values, and parameter defaults in a declaration.
- **Auto-fixable — Yes, with two documented exceptions.** The flagged argument is
  rewritten to its named form (`send('body', null)` →
  `send('body', subject: null)`). Because PHP rejects a positional argument that
  follows a named one, every positional argument *after* the flagged one is
  named in the same rewrite — `new Notifier(null, 'sms')` becomes
  `new Notifier(mailer: null, channel: 'sms')`. A later argument the call
  already names is left as it stands — `notify(null, retries: 3)` becomes
  `notify(channel: null, retries: 3)`, because naming it a second time would
  not parse. Where one of those later arguments cannot be given a name — it is
  unpacked from a spread (`f(null, ...$rest)`) or lands in a variadic parameter
  (`f(null, 'extra')` against `f(?string $first = null, ...$rest)`) — the
  violation is still reported but marked **not fixable**, and the message says
  so. Rewriting it would produce code that does not parse. The second exception
  is a call whose target is chosen at runtime — see *Late-bound calls are
  reported, not rewritten* below.

### Resolution stops at the file boundary

Deciding whether the target parameter is *optional* — and knowing the name to
put in the fix — requires the callee's declaration, and PHPCS analyses one file
at a time with no cross-file symbol table. The sniff therefore resolves only
what the file itself proves: functions, methods, and classes declared in the
same file. A call it cannot resolve — `Vendor\Thing::make(null)`, an inherited
method, a qualified name — is **left alone rather than guessed at**.

The sniff also tracks no variable-to-class bindings, so `$mailer->send('body',
null)` is skipped even when `Mailer` is declared right there in the same file:
the only object reference whose class the sniff knows is `$this`. That is a
separate limitation from the file boundary, and it is the reason a call on any
other variable goes unjudged.

This is deliberate under-reporting. Passing `null` into a *required* nullable
parameter is perfectly legitimate (`json_decode($json, null, 512)`), so a sniff
that flagged every positional `null` would cry wolf on correct code, and its
"fix" would have no parameter name to use. A linter that is silent where it
cannot know beats one that must be suppressed.

### Late-bound calls are reported, not rewritten

Finding the declaration is not the same as knowing it runs. `$this->m()`,
`static::m()` and `new static(...)` dispatch against the **runtime** class, and
a subclass may override the method and rename the parameter — renaming one in
an override is entirely valid PHP, since signature compatibility covers types
and defaults but never names. That subclass normally lives in a file the sniff
never sees, so writing the enclosing class's parameter name into the call would
turn working code into an `Unknown named parameter` fatal.

So those calls are **reported but not auto-fixed** — the violation is real
either way — unless the file proves dispatch cannot be diverted:

| Call site | Fixed when |
| --- | --- |
| `ClassName::`, `TraitName::`, `new ClassName()`, a function, an attribute | always — an explicitly named target is not dispatched |
| `self::m()`, `new self()` | always **outside** a trait; **never inside** one |
| `$this->m()` | the class is `final`, or `m()` is `final` or `private` |
| `static::m()` | the class is `final`, or `m()` is `final` |
| `new static()` | the class is `final`, or the constructor is `final` |
| anything inside an `enum` or an anonymous class | always — neither can be extended |
| `$this->`, `static::`, `self::` or `new self()` inside a `trait` | never |

A `private` method helps `$this->` because PHP resolves it in the scope that
declares it, but not `static::`, which binds to the subclass before it checks
visibility. A trait proves nothing at all: its methods are copied into every
using class, which may declare its own version of any of them — `private` and
`final` included.

That last point is why the two trait rows override the rows above them, `self::`
included. **Inside a trait, `self` does not name the trait** — the trait is
flattened into each using class, and `self` names *that* class, whose own
declaration of a method takes precedence over the copied one. So `self::m(null)`
written in a trait may well run a body in another file entirely, exactly like
`$this->m(null)` does. Naming the trait explicitly (`TraitName::m()`) is
different, and stays fixable: an explicit trait name does not dispatch to the
using class's override.

Sniff tests covering compliant code, per-line violation reporting for every
call form, the multiple-null and nested-expression edge cases, the
auto-fix output, and the non-fixable guarantee live at
`tests/Standards/NoNullArgumentsTest.php`.

## What remains code review

The cross-file cases the sniff cannot see: `null` passed positionally into an
optional parameter of a class declared elsewhere — the common case in
application code. Reviewers should still call these out; the sniff catches the
in-file subset mechanically and leaves the rest to human eyes.
