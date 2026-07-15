# Models: Eager Loading

## Standard

- Avoid eager loading relationships in the `protected $with = [];` variable as
  this could lead to data bloat.
- Try to explicitly load relationships at the point they are used using the
  `with()` method on the eloquent query, instead of using the `load()` method
  later in the code.

**Takeaway:** relationships are loaded explicitly at the query site with
`with()` — never always-on via a populated `$with` property, and not
retroactively via `load()` after the query has already run.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. It is **not** enforced
by a PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint time.
This standard is about *where* relationships should be loaded — a judgement
about the query site, the data actually used there, and the cost of loading it
— which spans the model, every query against it, and the consuming code. No
single file's tokens carry enough of that picture to verify it.

## Partial enforcement assessment

One narrow, token-visible slice was found: **a non-empty
`protected $with = [...];` property on a model**. The standard's first rule
prohibits exactly that construct, and it is readable by single-file token
analysis — a class property named `$with` whose array default contains at
least one element. Per this standard's execution process, that slice is not
implemented here; it is tracked as a focused sniff issue:
[#153](https://github.com/mike-bronner/phpcs-rules/issues/153).

- **`->load(` call check** (the other obvious candidate) — rejected. At token
  level the receiver's type is unknown: `load` is a common method name outside
  Eloquent (config loaders, file loaders, translators, third-party SDKs), so a
  string match on `->load(` cannot tell lazy eager loading on a model from an
  unrelated call. The rule is also a stated preference ("try to"), with
  legitimate `load()` uses such as conditionally loading relations on an
  already-retrieved model. High false-positive rate for a slice review catches
  easily.

Everything else about the standard — judging whether a given query loads the
right relationships at the right place, and keeping eager loading proportionate
to the data actually used — remains enforced by code review.
