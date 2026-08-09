# PHPMD/CleanCode: MissingImport

## Standard

- Every external class is imported through a `use` statement; class names are
  not written out fully qualified inline.

**Why:**

- The `use` block is the file's dependency list. A name written inline is
  invisible there, so a reader cannot tell what a file depends on without
  reading all of it.
- Inline fully qualified names repeat the namespace at every call site, so
  moving a class means editing every use of it instead of one import.

PHPMD flags this as `MissingImport` in its Clean Code ruleset (since PHPMD
2.7.0).

```php
function make() {
    return new \stdClass();
}
```

**Configurable property:**

| Property | Default | Meaning |
|---|---|---|
| `ignore-global` | `false` | Skip classes, interfaces, and traits in the global namespace. |

_Source: [phpmd.org/rules/cleancode.html](https://phpmd.org/rules/cleancode.html)_

## Enforceability — Tier 1 (existing sniff)

Slevomat's
[`SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly`](https://github.com/slevomat/coding-standard)
covers this rule and is wired into the master `rules.xml`
([#84](https://github.com/mike-bronner/phpcs-rules/issues/84)).

- **Detection** — a fully qualified name used inline is flagged at its own line
  and column. In a file that declares a namespace the code is
  `…ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName`; in one that does not,
  it is `…ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedNameWithoutNamespace`,
  because the remedy differs — see below.
- **Auto-fixable** — unlike the other PHPMD mappings here, `phpcbf` resolves
  this one: it inserts the missing `use` statement and shortens the reference in
  place. The fixer is total; nothing is left for a second pass.
- **Already an error** — the sniff reports errors out of the box, so unlike
  `Squiz.PHP.Eval` and `VariableAnalysis.CodeAnalysis.VariableAnalysis` it needs
  no severity override to fail a `phpcs` run the way PHPMD fails a `phpmd` run.
- **`self` and `static` are never flagged**, matching PHPMD, which skips both
  explicitly.

### The two remedies

PHPMD reports one defect. The sniff splits it by what the fix has to be.

| File | Code reported | Fix |
|---|---|---|
| Declares a namespace | `ReferenceViaFullyQualifiedName` | Add `use Foo\Bar;`, write `Bar` |
| Declares no namespace | `ReferenceViaFullyQualifiedNameWithoutNamespace` | Drop the leading `\` |

In the global namespace a `use` statement buys nothing, so the second form only
asks for the backslash to go. Both tools agree the inline name is the defect;
they differ only in what they suggest writing instead.

### Properties configured

`rules.xml` sets two of the sniff's properties and leaves the rest at their
vendor defaults.

| Property | Value | Why |
|---|---|---|
| `allowFullyQualifiedGlobalFunctions` | `true` | PHPMD's rule walks allocation expressions only, so it has no opinion on functions. The sniff's default would demand `use function strlen;` for a written-out `\strlen()`, which the mapping does not call for — and the leading backslash there is a deliberate idiom that skips the namespace fallback lookup. |
| `allowFullyQualifiedGlobalConstants` | `true` | The same reasoning for `\PHP_EOL`. |

References to *namespaced* functions and constants are still reported: those are
genuinely importable.

Three defaults are load-bearing and left alone on purpose.

- **`allowFullyQualifiedGlobalClasses` (`false`)** is PHPMD's `ignore-global`,
  and both tools default it to reporting. Setting it `true` reproduces PHPMD's
  `ignore-global: true` behaviour inside namespaced files, with one caveat noted
  in the divergence table below.
- **`allowWhenNoNamespace` (`true`)** reads backwards: its *false* branch is an
  early return that skips namespace-less files wholesale. PHPMD flags
  `new \stdClass()` in a function or method whether or not the file declares a
  namespace, so `true` is what parity needs.
- **`allowFullyQualifiedExceptions` (`false`)** is the one property that would
  break parity in the direction that matters. Setting it `true` silences every
  name ending in `Exception`, `\Throwable`, and global names ending in `Error` —
  including `new \RuntimeException()`, which PHPMD does report. A missed report
  is a reason to keep running `phpmd`, so the sniff stays strict here. The cost
  is described under "Interaction with the Throwable rule" below.

`searchAnnotations` is left `false`, and the deprecated
`SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation` sniff is
not referenced. Both police fully qualified names in doc blocks, which PHPMD's
rule never reads — it walks the parsed AST's allocation expressions. Enabling
either would report code `phpmd` passes. (The `searchAnnotations="true"` on
`SlevomatCodingStandard.Namespaces.UnusedUses` in the same `rules.xml` runs the
other way: there it stops live docblock references being called dead.)

### Where the sniff and PHPMD differ

PHPMD's rule inspects **allocation expressions only** — `new X(…)` — and is
`MethodAware`/`FunctionAware`, so it never looks outside a function or method
body. The sniff inspects every referenced name anywhere in the file. It is
therefore broader, and deliberately so: each extra report is the same defect the
standard describes, in a position PHPMD happens not to visit.

| Shape | PHPMD 2.15.0 | This ruleset |
|---|---|---|
| `new \Foo\Bar()` in a method or function | flags | flags |
| `new \stdClass()` in a namespace-less file | flags | flags |
| `\Foo\Bar::method()` static call | silent | **flags** |
| `\Foo\Bar` as a parameter, return, or property type | silent | **flags** |
| `catch (\RuntimeException $e)` | silent | **flags** |
| `use \Foo\Bar;` — a trait use in a class body | silent | **flags** |
| `new \stdClass()` in top-level code, outside any function | silent | **flags** |
| `new Models\Invoice()` — partially qualified | silent | silent |

One divergence runs the other way, and only under a non-default setting: with
`ignore-global` on, PHPMD skips `new \stdClass()` in a namespace-less file,
while the sniff still reports it — its without-a-namespace branch runs before
the global-classes gate. `rules.xml` leaves both properties at the shared
default, so nothing here is live; it is recorded so the difference stays a known
fact if anyone turns `ignore-global` on.

The partially qualified `new Models\Invoice()` is missed by both tools and stays
code review.

Verified by running both tools over the same fixtures — PHPMD 2.15.0 with a
ruleset enabling only `rulesets/cleancode.xml/MissingImport`, and
`phpcs --standard=rules.xml`.

### Interaction with the Throwable rule

`SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly`, wired in for
[#63](https://github.com/mike-bronner/phpcs-rules/issues/63), rewrites
`catch (\Exception $e)` to the literal `\Throwable` — fully qualified, which
this rule then asks to be imported. The two report on the same line.

They agree on a compliant form, which is what matters: `use Throwable;` plus an
unqualified `catch (Throwable $exception)` satisfies both, and one `phpcbf` pass
over the whole ruleset reaches it. Both the double report and the convergence
are pinned by tests.

### Tests

Ruleset-integration tests covering compliant code, per-line and per-column
violation reporting, the error severity, the fixer's output and totality, both
halves of every property `rules.xml` sets, the `ignore-global` mapping, the
namespace-less path, the divergences above, and the Throwable interaction live
at `tests/Ruleset/MissingImportTest.php`.

Each member of the class/interface/trait triad the standard names is exercised
by name: classes throughout, the interface through `catch (\Throwable)`, and the
trait through a class-body `use` — flagged fully qualified, silent when
imported, and imported by the fixer. The sniff routes a trait use through the
same code path as a `new`, so this is coverage of the path rather than of a
separate behaviour, and it is held separately so a narrowing of that path fails
a test instead of passing in silence.

Fixtures live under `tests/fixtures/ReferenceUsedNamesOnlySniff/`:
`passing.php`, `failing.php`, and `autofixed.php` per the CONTRIBUTING.md
contract, plus `no-namespace.php`, `divergences.php`, and
`throwable-interaction.php` for the shapes that belong to none of them.

## What remains code review

**A partially qualified reference.**

```php
namespace App;

$invoice = new Models\Invoice();   // resolves to \App\Models\Invoice
```

Neither tool flags it: the name is not fully qualified, so PHPMD's
source-span check does not match and the sniff treats it as a legitimate
partial use. Replacing `phpmd` with this ruleset loses no coverage here — PHPMD
never caught it either — but a reader may still prefer the import.

Everything else this rule covers is machine-enforced, and `phpmd` no longer
needs to run separately for it.
