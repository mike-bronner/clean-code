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
wired into the master `rules.xml` via the CleanCode standard
([#71](https://github.com/mike-bronner/phpcs-rules/issues/71)).

- **Detection** — a literal `null` passed as a *positional* argument to a
  parameter that declares a default is flagged at the `null` token as
  `CleanCode.Methods.NoNullArguments.PositionalNull`. Function calls, `$this->`
  (and `$this?->`) method calls, `self::`/`static::`/`ClassName::` static calls,
  and `new ClassName(...)`/`new self(...)` constructor calls are all covered.
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
- **Auto-fixable — Yes, with one documented exception.** The flagged argument is
  rewritten to its named form (`send('body', null)` →
  `send('body', subject: null)`). Because PHP rejects a positional argument that
  follows a named one, every positional argument *after* the flagged one is
  named in the same rewrite — `new Notifier(null, 'sms')` becomes
  `new Notifier(mailer: null, channel: 'sms')`. Where one of those later
  arguments cannot be given a name — it is unpacked from a spread
  (`f(null, ...$rest)`) or lands in a variadic parameter (`f(null, 'extra')`
  against `f(?string $first = null, ...$rest)`) — the violation is still
  reported but marked **not fixable**, and the message says so. Rewriting it
  would produce code that does not parse.

### Resolution stops at the file boundary

Deciding whether the target parameter is *optional* — and knowing the name to
put in the fix — requires the callee's declaration, and PHPCS analyses one file
at a time with no cross-file symbol table. The sniff therefore resolves only
what the file itself proves: functions, methods, and classes declared in the
same file. A call it cannot resolve — `$mailer->send('body', null)` on a typed
object, `Vendor\Thing::make(null)`, an inherited method, a qualified name — is
**left alone rather than guessed at**.

This is deliberate under-reporting. Passing `null` into a *required* nullable
parameter is perfectly legitimate (`json_decode($json, null, 512)`), so a sniff
that flagged every positional `null` would cry wolf on correct code, and its
"fix" would have no parameter name to use. A linter that is silent where it
cannot know beats one that must be suppressed.

Sniff tests covering compliant code, per-line violation reporting for every
call form, the multiple-null and nested-expression edge cases, the
auto-fix output, and the non-fixable guarantee live at
`tests/Standards/NoNullArgumentsTest.php`.

## What remains code review

The cross-file cases the sniff cannot see: `null` passed positionally into an
optional parameter of a class declared elsewhere — the common case in
application code. Reviewers should still call these out; the sniff catches the
in-file subset mechanically and leaves the rest to human eyes.
