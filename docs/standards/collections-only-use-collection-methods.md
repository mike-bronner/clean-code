# Collections: Only Use Collection Methods

## Standard

- Don't use generic PHP methods on collections; collections should be
  implemented for the built-in methods, as they are optimized and decouple us
  from direct PHP implementation.

**Why:**

- Decoupling from PHP methods (technical debt).
- Optimized functionality (performance).
- Consistent usage (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

Enforced by the custom sniff **`CleanCode.Collections.OnlyUseCollectionMethods`**,
partly auto-fixable. It flags a generic PHP array/string function whenever one
of its arguments is a Collection, naming the Collection method that replaces it
(`array_map()` → `map()`, `in_array()` → `contains()`, `count()` → `count()`).

This is the mirror image of
[Arrays: Convert To Collection](arrays-convert-to-collection.md): that standard
flags native array functions on *arrays* as conversion candidates (a warning,
"whenever possible"), this one flags them on *Collections*, where the standard
is unconditional and the finding is an error.

### What counts as a Collection

The sniff only reports what the tokens *prove*. A value is a Collection when it
is one of:

- a `collect(...)` call (the global helper — a namespaced `App\collect()`, or a
  `collect` the file imported with `use function … as collect;`, is a different
  function and is left alone);
- a `Collection::make(...)` / `Collection::wrap(...)` factory call, or
  `new Collection(...)`, on any class whose name ends in `Collection`
  (`Collection`, `EloquentCollection`, `OrderCollection`, …);
- a parameter type-hinted as such a class;
- a variable *unconditionally* assigned any of the above — tracked per function
  scope, and only when **every** binding of that name in the scope proved a
  Collection. Any other binding of it — a reassignment, `foreach`, `catch`,
  destructuring, `global`/`static`, a `use (&$name)` capture, a compound
  assignment or an index write — retires the name for the whole scope, not just
  from that line on.

The name is also left alone where the call itself is not what it appears to be:
a `use function … as count;` import rebinds the name for the whole file, so an
unqualified `count($collection)` is that import rather than the builtin. A
fully-qualified `\count($collection)` is the builtin again, and is reported.

An imported function of that name is also userland code, free to declare
`&$items` and hand back a rebound variable — so, exactly like any other call the
sniff cannot prove takes its argument by value, it costs the variable its proven
type. A later `array_sum($collection)` in the same scope is still reported, but
never rewritten. A method merely spelled like one of the mapped functions
(`$aggregator->count($collection)`) is userland code for the same reason and is
treated the same way.

A bare class name is resolved through the file's `use` imports first, so the
alias never decides on its own: `use Illuminate\Support\Collection as Coll`
makes `Coll::make($rows)` a Collection, and `use Illuminate\Support\Arr as
RowCollection` stops `RowCollection::wrap($rows)` looking like one. A name
written with a namespace prefix names its class directly and is never resolved
through the imports.

A chain of method calls on one of those is still a Collection
(`collect($rows)->map(...)`), *unless* the last call in the chain returns
something else (`->toArray()`, `->all()`, `->sum()`, `->first()`, …). That is
what keeps `count($collection->toArray())` — plain-array code — unreported.
The sniff's terminal-method list is audited against the whole Collection API in
one pass, because an omission there is a false positive; a method that returns
a non-Collection only for *some* arguments (`pop()`, `shift()`, `random()`,
`find()`) is listed anyway, trading a false negative for never accusing wrongly.

That list is a hand-maintained mirror of a framework API that changes without
this package, so it will always be somewhat behind. The auto-fixer therefore
never relies on it — see [Auto-fixing](#auto-fixing) — which caps the cost of an
omission at a spurious warning.

### Arrow-function parameters shadow, they do not leak

An arrow function auto-captures, so a Collection in scope outside `fn () => …`
is the same Collection inside it. A name the arrow function *declares as a
parameter* is a different matter: that is a new binding, and it shadows the
outer name in both directions.

```php
$counter = static fn (Collection $items): int => $items->count();

count($items);   // not reported: $items here is the enclosing array
```

```php
$rows = collect($rowSets);
$counter = static fn (array $rows): int => count($rows);
//                                         ^ not reported: the parameter is an
//                                           array, not the outer Collection
```

An assignment inside an arrow function's body binds there too, and runs only
when the arrow function is called, so it never registers a variable outside it.

### Only unconditional assignments count

An assignment inside an `if`, a loop, a `try` or a `match` proves nothing about
what the variable holds at the call site — the branch may not have run, and the
branch next door may assign something else:

```php
if ($useArray) {
    $data = $rows;
} else {
    $data = collect($rows);
}

count($data);   // not reported: neither branch is provable
```

Rather than let the textually last assignment win — which would report, and
offer to auto-fix, code that is correct at runtime — the sniff retires the
variable. A property write (`self::$items = collect($rows)`) never registers
the local or parameter that shares its name, for the same reason.

### Flagged functions

`array_diff`, `array_filter`, `array_intersect`, `array_key_exists`,
`array_keys`, `array_map`, `array_merge`, `array_reduce`, `array_search`,
`array_slice`, `array_sum`, `array_unique`, `array_values`, `count`, `implode`,
`in_array`, `join`.

### Auto-fixing

Only the unambiguous 1:1 swaps are fixed: a single-argument `count($c)` and
`array_sum($c)` become `$c->count()` and `$c->sum()`. Both take no arguments on
the Collection side and return the same scalar, so the rewrite cannot change
behaviour.

The receiver has to be a Collection the tokens prove outright, too — a tracked
variable, a `collect()` call, a `Collection::make()`/`::wrap()` factory call or
a `new Collection()`. A **chained** receiver is rewritten only when every link
in the chain is a method whose Collection return is contractual:

```php
count($c->filter($fn));        // auto-fixed to $c->filter($fn)->count()
count($c->chunk(2));           // reported, not auto-fixed
count($c?->filter($fn));       // reported, not auto-fixed
```

The two lists behind that answer opposite questions and fail in opposite
directions, which is why the sniff keeps both. The **report** asks "did this
chain stop being a Collection?" and consults the terminal-method list, which
fails open: a method it has never heard of is assumed to keep the chain alive,
so an omission costs a spurious report and never a missed one. The **fixer**
asks the stronger question "is this chain still a Collection *for certain*?" and
consults a separate chainable-method list, which fails closed: a method it has
never heard of ends provability, so an omission costs a declined fix and never a
rewrite.

That is what keeps the terminal-method list out of the fixer's path. Anything
missing from it is assumed to return a Collection, which is acceptable in a
report and unacceptable in a rewrite — `count($c->random())` turned into
`$c->random()->count()` is a runtime fatal, not a style nit. Because the fixer
re-derives the type instead, a list that has drifted behind the framework can
only ever produce noise, and a chain the fixer does not recognise is declined
rather than guessed at.

Everything else is reported but left alone, because the swap needs judgement a
fixer cannot make:

- **argument order changes** — `array_map($fn, $c)` → `$c->map($fn)`,
  `implode($glue, $c)` → `$c->implode($glue)`;
- **flags that alter behaviour** — `in_array()`'s `$strict`,
  `array_filter()`'s `$mode`, `count()`'s `$mode`. A `count($c, COUNT_RECURSIVE)`
  is reported but never rewritten;
- **a changed return type** — `array_keys()` hands back an array,
  `$c->keys()` a Collection. Staying in Collection-land is the point of the
  standard, but whether the caller can take one is a code-review question.

## Existing sniffs evaluated first

No bundled PHPCS or Slevomat sniff matches this standard:

- **`Generic.PHP.ForbiddenFunctions`** / **`Squiz.PHP.DiscouragedFunctions`**
  come closest — they forbid named functions and can name a replacement — but
  they are unconditional. Configured with `array_map`/`count`/…, they would
  flag those calls on plain arrays too, which this standard explicitly permits
  and the test suite pins as a boundary case. Bending the tests to fit was not
  an option.
- **Slevomat** has no Collection-aware rule; its array rules
  (`Arrays.TrailingArrayComma`, …) are about layout, not call targets.

The receiver-type condition is what makes this rule its own sniff.

## What remains code review

The sniff needs a *token-provable* Collection, so it stays silent where the type
is only knowable at runtime. Those calls stay with code review:

- values read from properties (`count($this->rows)`);
- returns of arbitrary methods, and arrays that merely happen to hold a
  Collection;
- variables captured into a closure's `use (...)` list;
- variables whose only assignment sits inside a branch or loop, and variables
  assigned inside an arrow function's body;
- any name a construct the sniff cannot fully parse has bound. A name is tracked
  only when *every* binding of it in the scope proved a Collection, so
  `foreach`, `catch`, destructuring, `global`/`static`, a `use (&$name)`
  capture, a compound assignment (`.=`, `??=`) and an index write (`$c[] = …`)
  all retire it. Two of those retire a name that really is still a Collection —
  `$c[] = …` and `$c ??= collect(…)` — and both are deliberate: missing a
  violation costs a report, and the alternative direction costs working code
  (see below).

Missing a violation is the direction this sniff is built to fail in, and the
list above is the harmless half. The dangerous half is where it *speaks*.

## What the sniff can get wrong

Read this before running `phpcbf` across a codebase — everything here is a case
where the sniff reports, and the entries marked **fixable** are cases where
`--fix` would rewrite your source:

| Case | Reported | Fixable | Consequence |
|---|---|---|---|
| A chain whose last method is missing from `TERMINAL_METHODS` | yes | **no** | A spurious report on `count($c->newMethod())`. The list is a hand-curated mirror of a framework API, so it drifts; the fixer does not consult it, and declines the call because the method is absent from the chainable list too. |
| A receiver handed bare to another call that may take it by reference (`preg_match('/x/', $s, $c)`, a userland `&$target`) | yes | **no** | A spurious report after the callee has replaced the value. Neither a userland signature nor PHP's by-reference builtins are knowable from the tokens. |
| A Collection stored in a property or returned from a method | no | — | Silent; see above. |

Both reported-but-unfixable rows are deliberate **severity collapses**: the sniff
cannot prove the type at the call site, so it says so and declines to act. That
is the whole design rule — *the fixer only ever rewrites a receiver the tokens
prove outright*: a tracked variable, `collect()`, a `Collection::make()`/`::wrap()`
factory call, or a `new Collection()`, none of which has escaped to another call.
An inference good enough to raise a warning is not good enough to rewrite source,
because a wrong rewrite is a runtime fatal rather than a style nit.

There is no known case where the fixer rewrites working code into a fatal. If
you find one, it is a bug of the highest severity in this sniff — the tests pin
every shape found so far as byte-for-byte untouched by `phpcbf`.
