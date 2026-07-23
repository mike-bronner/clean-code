# Operators: Passive

## Standard

Passive operators must sit flush against the object they are acting on — no
space between the operator and its operand:

- **Identity** — `+` (`+$a`)
- **Negation** — `-` (`-$a`)
- **Increment** — `++` (`$a++`, `++$a`)
- **Decrement** — `--` (`$a--`, `--$a`)
- **Error control** — `@` (`@file_get_contents(...)`)
- **Execution** — backticks (`` `ls` ``)
- **Access** — `[]` and `->` (`$a[0]`, `$a->b`)

Binary arithmetic (`$a + $b`, `$a - $b`) is a different operator and is out of
scope — its spacing is left untouched.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (auto-fixable)

The standard is enforced by a **combination of rules** wired into the master
`rules.xml` ([#64](https://github.com/mike-bronner/phpcs-rules/issues/64)). No
single existing sniff covers every passive operator, and
`Squiz.WhiteSpace.OperatorSpacing` — the obvious candidate — was evaluated
against the fixture suite and flags **none** of them: it deliberately skips
unary `+`/`-`. The remaining operators split cleanly between existing sniffs
and a small custom sniff that closes the gaps.

### Custom sniff — `CleanCode.WhiteSpace.PassiveOperatorSpacing`

Covers the passive operators no existing sniff handles, all auto-fixable by
removing the offending space:

- **Identity** — unary `+`, reported as `…PassiveOperatorSpacing.Identity`.
- **Negation** — unary `-`, reported as `…PassiveOperatorSpacing.Negation`.
- **Error control** — `@`, reported as `…PassiveOperatorSpacing.ErrorControl`.
- **Execution** — backticks, reported as `…PassiveOperatorSpacing.Execution`
  (trims horizontal whitespace directly inside the backticks).

A `+`/`-` is only treated as a passive sign when it is **unary** — i.e. no
value precedes it (`$a++ + $b` is binary arithmetic and is left untouched). The
fix is withheld only when closing the gap would fuse the sign into a
**same-direction** increment/decrement and change the meaning: `- -$a` → `--$a`
and `+ +$a` → `++$a` are left untouched. A cross-direction pair does not fuse
(`- ++$a` → `-++$a`, `+ --$a` → `+--$a`), so those are still flagged and fixed.

### Ceded to PSR12 — out of scope

The master ruleset also wires in `PSR12.Operators.OperatorSpacing`, which
governs **binary** `+`/`-` and *requires* a space around them. In two contexts
the two rules would disagree — this sniff would strip a space PSR12 immediately
re-adds, so `phpcbf` could never converge. To avoid that oscillation, this
standard **cedes both contexts to PSR12** and does not enforce flush spacing
there:

- **A bare `+`/`-` sign immediately after `@`** (`@ -$a`). Removing the space
  yields `@-$a`, whose `-` PSR12 reads as binary and re-spaces. (`@` before any
  other operand — `@ func()`, `@ --$a` — is still enforced.)
- **A `+`/`-` sign immediately after an open tag** — `<?= -$x ?>`, or a bare
  sign at the very start of a `<?php` block. PSR12 reads a sign right after an
  open tag as binary and forces a space around it, so template-context signs are
  governed by PSR12.

Enforcing flush signs in these contexts would require taming or replacing
PSR12's `OperatorSpacing` — a separate, larger piece of work, out of scope here.

### Existing sniffs — the rest

- **Increment / decrement** — `Generic.WhiteSpace.IncrementDecrementSpacing`,
  auto-fixable.
- **Object operator `->`** — `Squiz.WhiteSpace.ObjectOperatorSpacing`, with
  `ignoreNewlines=true`. Only inline spacing (`$obj -> prop`) is a violation; a
  newline before `->` is a multi-line fluent chain, which the
  [Clear Code: One Thought Per Line](clear-code-one-thought-per-line.md)
  standard mandates — so the two standards do not contradict.
- **Array access `[]`** — `Squiz.Arrays.ArrayBracketSpacing`, auto-fixable.

Ruleset-integration tests covering compliant code, per-line violation reporting
for every operator, the unary-vs-binary distinction, the sign-merge guard, and
the `phpcbf` auto-fix (violating fixture → compliant fixture) live at
`tests/Ruleset/PassiveOperatorSpacingTest.php`.

## What remains code review

Nothing. Every passive-operator spacing violation is machine-detected and
auto-fixable via `phpcbf`.
