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
`CleanCode/ruleset.xml` ([#64](https://github.com/mike-bronner/clean-code/issues/64)). No
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

### Nothing is ceded — how the collision was resolved

Binary `+`/`-` spacing is enforced by a separate rule: the *Arrays: Operator
spacing & line breaks* standard (#35) requires exactly one space around a binary
operator. Both rules are auto-fixable, so wherever the two disagree about
whether a given sign is unary or binary, one fixer strips a space the other
re-adds, `phpcbf` never reaches a fixed point, and it abandons the **whole
file** (exit 2).

Four contexts diverged: a sign after `@`, after `;`, after `<?php`, and after
`<?=`. In each, no value can precede the sign, so it is unambiguously unary —
but PHPCS's bundled `Squiz.WhiteSpace.OperatorSpacing` reads it as binary.

Rather than narrow this standard, the collision is resolved at the ruleset
level. `CleanCode/ruleset.xml` wires in `CleanCode.Operators.BinaryOperatorSpacing` — that
Squiz sniff subclassed, with the same checks, message codes and properties, and
its unary detection corrected — which cedes those four contexts. Every sign
therefore has exactly one owner, and this standard enforces flush passive
operators **everywhere**, template context (`<?= -$total ?>`) and error control
(`@-$a`) included.

The four contexts are not a hand-kept list. `tests/Standards/BinaryOperatorSpacingTest.php`
derives the divergence between the two sniffs' operand detection from the
classes themselves and pins all three sides of it: neither sniff may gain a
context the other does not account for, and the passive sniff may not drop one
its binary counterpart still holds — that last one would leave a sign no sniff
owns at all. The collision is therefore closed as a class rather than one
surface at a time. `tests/Ruleset/OperatorsPassiveTest.php` additionally drives
the real `phpcbf` binary over the real master ruleset and asserts it reaches a
stable fixed point on every one of them.

### Existing sniffs — the rest

- **Increment / decrement** — `Generic.WhiteSpace.IncrementDecrementSpacing`,
  auto-fixable.
- **Object operator `->`** — `Squiz.WhiteSpace.ObjectOperatorSpacing`, with
  `ignoreNewlines=true`. Only inline spacing (`$obj -> prop`) is a violation; a
  newline before `->` is a multi-line fluent chain, which the
  [Clear Code: One Thought Per Line](clear-code-one-thought-per-line.md)
  standard mandates — so the two standards do not contradict.
- **Array access `[]`** — `Squiz.Arrays.ArrayBracketSpacing`, auto-fixable.

Tests split by scope. `tests/Standards/PassiveOperatorSpacingTest.php` drives
the custom sniff on its own — the unary-vs-binary distinction, the sign-merge
guard, the ceded contexts it owns, and the `phpcbf` auto-fix (violating fixture
→ recorded output). `tests/Ruleset/OperatorsPassiveTest.php` covers the standard
as wired into `CleanCode/ruleset.xml` — all four sniffs together, per-line violation
reporting attributed to the sniff that owns each operator, the auto-fix, and the
real-`phpcbf` convergence check described above.

## What remains code review

Nothing. Every passive-operator spacing violation is machine-detected and
auto-fixable via `phpcbf`.
