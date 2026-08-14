# Methods: Naming

## Standard

From Robert Martin's *Clean Code*: methods should have verb or verb-phrase
names like `postPayment`, `deletePage`, or `save`.

- Name methods according to what they do or return; names should be
  self-documenting.
- Methods that perform an action should be a verb and not return anything.
- Methods that return objects should be nouns named after the object they
  return (optionally prefixed with adjectives); in Models this should always
  be attributes.
- Methods should read as an action being taken on the class.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

The core of this standard is semantic: whether a name is a genuine verb or
noun, and whether it accurately describes what the method does or returns,
are natural-language judgements a token-based PHPCS sniff cannot make.
Enforcement is via code review and developer discipline.

One narrow slice **is** token-visible — the command–query separation rule
("action methods should not return anything") for methods whose names start
with a known action-verb prefix, since declared return types are token-level
facts. Focused sniff issue:
[#172](https://github.com/mike-bronner/phpcs-rules/issues/172).

Adjacent slices are already tracked elsewhere and are **not** duplicated
here:

- Boolean accessor naming (`getX(): bool` → `isX()`/`hasX()`) —
  [#116](https://github.com/mike-bronner/phpcs-rules/issues/116)
  (PHPMD `BooleanGetMethodName`).
- Model query-method prefixes (`find*`/`get*`) and attribute naming —
  [#44](https://github.com/mike-bronner/phpcs-rules/issues/44)
  (Models: Naming Conventions).

## What remains code review

Whether a method name is a verb, whether an accessor's noun matches what it
actually returns, whether a Model accessor is named after the attribute it
exposes, and whether a call site reads as an action taken on the class are
all judgements about meaning, not tokens. Those calls stay with code review.
