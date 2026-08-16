# PHPMD/CleanCode: DuplicatedArrayKey

## Standard

- An array literal must not declare the same key twice.

**Why:**

- The later entry wins and the earlier one never exists at runtime, so one of
  the two is always dead code.
- The usual cause is a copy-paste or a typo in the key, which no other rule
  catches — the file parses, the tests pass, and a value silently disappears.

PHPMD flags this as `DuplicatedArrayKey` in its Clean Code ruleset (since PHPMD
2.7.0). It has no configurable thresholds.

```php
return [
    0 => 'a',
    false => 'b',   // the same key as 0
    'foo' => 'bar',
    "foo" => 'baz', // the same key as 'foo'
];
```

_Source: [phpmd.org/rules/cleancode.html](https://phpmd.org/rules/cleancode.html)_

## Enforceability — Tier 2 (custom sniff)

No PHPCS, Generic, Squiz, or Slevomat sniff compares array keys for equality.
`Squiz.Arrays.ArrayDeclaration` is the only bundled sniff that looks at array
keys at all, and every code it emits is about layout — whether a key is
present (`KeySpecified`, `NoKeySpecified`) and how keys, arrows, and commas are
spaced and aligned — never about two keys naming the same slot. So the rule is
the custom `CleanCode.Arrays.DuplicatedArrayKey` sniff, wired into the master
`rules.xml` through the `./CleanCode/ruleset.xml` ref
([#81](https://github.com/mike-bronner/phpcs-rules/issues/81)).

- **Detection** — each duplicate is reported at the *later*, overriding key,
  and the message names the line of the declaration it overrides. A key written
  three times is reported twice, both against the first declaration: that is
  the entry that survives at runtime.
- **Two keys are the same when PHP stores them in the same slot**, not when
  they are spelled the same way. PHP coerces every array key to an int or a
  string on insertion, so the sniff resolves each key literal to its value and
  compares the values.

  | Written | The key PHP stores |
  |---|---|
  | `false`, `0`, `'0'`, `0.4` | int `0` |
  | `true`, `1`, `'1'`, `0b1`, `1.9` | int `1` |
  | `15`, `0xF`, `0b1111`, `017`, `0o17`, `1_5` | int `15` |
  | `null` | string `''` |
  | `'foo'`, `"foo"` | string `'foo'` |
  | `'01'`, `'1.0'`, `' 1'` | those strings — only a canonical decimal string becomes an int key |

- **Only a key the token stream settles is compared.** A constant, a class
  constant, a variable, an expression, an interpolated string, and a
  double-quoted string carrying an escape sequence are all skipped, because
  their values are not in the source and a guess would be worse than silence.
  An implicit (auto-incrementing) key is skipped for the same reason: no
  literal names it, since its value depends on every element before it. PHPMD
  skips all of these too.
- **Every array literal is inspected** — the short form, the long `array()`
  form, a nested array (in its own right, and independently of its parent), and
  a keyed destructuring pattern, where a repeated key makes the second binding
  a copy of the first rather than a second value.
- **Reported as an error, not a warning** — a warning would leave `phpcs`
  exiting 0 on a duplicate key, which would mean `phpmd` still had to run for
  this rule, the one thing this issue exists to stop.
- **Not auto-fixable.** Deleting the overridden entry looks mechanical and is
  not: its value can carry a side effect (`0 => register($handler)`), and which
  of the two entries is the mistake is a judgement about intent — the key may
  be the typo rather than the duplication. PHPMD reports rather than rewrites
  for the same reason. A test runs the real fixer over `failing.php` and
  asserts its output is byte-identical to the input, so "unfixable" is measured
  rather than assumed.

### Where the sniff and PHPMD differ

The sniff is not a drop-in match for PHPMD, and no property makes it one —
PHPMD's rule has no properties at all. Rather than weaken a fixture to force
agreement, the differences are recorded here and pinned by
`tests/fixtures/DuplicatedArrayKeySniff/divergences.php`.

The two tools ask different questions. PHPMD compares the *source text* of each
key, after stripping quotes and rewriting `true`, `false`, and `null`. This
sniff compares the *value* PHP would store the entry under. PHPMD also inspects
arrays inside methods and functions only; this sniff registers on the array
literal itself.

| Shape | PHPMD 2.15.0 | This ruleset |
|---|---|---|
| `'foo'` and `"foo"`, `0` and `false`, `null` and `''`, `1` and `'1'` | flags | flags |
| `15` and `0xF` / `0b1111` / `017` / `1_5` | silent | **flags** |
| `1` and `1.9` (a float key truncates toward zero) | silent | **flags** |
| `-1` and `-1` (a negated literal) | silent | **flags** |
| A duplicate outside any method or function | silent | **flags** |
| `01` and `'01'` | **flags** | silent |

Every extra report is a real duplicate: PHP stores both entries in one slot, so
the first is dead whatever base or type the literal is written in. The one
report dropped is a PHPMD false positive — stripping the quotes makes `'01'`
look like the octal literal `01`, but PHP keeps `'01'` a string key, because
only a canonical decimal string becomes an integer key. Adopting this ruleset
can therefore surface findings a previous `phpmd` run did not, and drops one it
made in error.

Verified by running both tools over the same fixtures — PHPMD 2.15.0 with a
ruleset enabling only `rulesets/cleancode.xml/DuplicatedArrayKey`, and
`phpcs --standard=rules.xml`. The PHPMD half of the comparison ran against a
copy of each fixture wrapped in a function, since PHPMD's rule is method- and
function-aware.

Behaviour tests covering compliant code, the exact line and column of every
report, the coerced key named in the message, the first-declaration rule, the
absence of a fixer, the divergences above, and both unterminated-source cases
live at `tests/Standards/DuplicatedArrayKeyTest.php`. The sniff is also in the
generic three-fixture sweep in `tests/Contract/SniffContractTest.php`.

Its fixtures follow the contract CONTRIBUTING.md prescribes, under
`tests/fixtures/DuplicatedArrayKeySniff/`: `passing.php` for code the rule must
stay silent on and `failing.php` for the parity set, plus `divergences.php` for
the shapes above and `unterminated-array.php` / `unterminated-index.php` for
source a developer is halfway through typing. There is no `autofixed.php`,
because the rule is not fixable.

## What remains code review

**An explicit key that collides with an implicit one.**

```php
return [
    'a',            // this is key 0
    0 => 'b',       // and so is this — neither tool flags it
];
```

An implicit key's value depends on every element before it, so no literal in
the source names it. Both tools compare literals and both stay silent.
Replacing `phpmd` with this ruleset loses no coverage here — PHPMD never caught
it either — but the shape is a real defect and needs a reader.

Everything else this rule covers is machine-enforced, and `phpmd` no longer
needs to run separately for it.
