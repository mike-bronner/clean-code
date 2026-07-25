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

- a `collect(...)` call (the global helper — a namespaced `App\collect()` is a
  different function and is left alone);
- a `Collection::make(...)` / `Collection::wrap(...)` factory call, or
  `new Collection(...)`, on any class whose name ends in `Collection`
  (`Collection`, `EloquentCollection`, `OrderCollection`, …);
- a parameter type-hinted as such a class;
- a variable assigned any of the above — tracked per function scope, and
  retired again as soon as the variable is reassigned to something else.

A chain of method calls on one of those is still a Collection
(`collect($rows)->map(...)`), *unless* the last call in the chain returns
something else (`->toArray()`, `->all()`, `->sum()`, `->first()`, …). That is
what keeps `count($collection->toArray())` — plain-array code — unreported.

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

The sniff needs a *token-provable* Collection, so it stays silent where the
type is only knowable at runtime: values read from properties
(`count($this->rows)`), returns of arbitrary methods, arrays that merely happen
to hold a Collection, and variables captured into a closure's `use (...)` list.
Those calls stay with code review.
