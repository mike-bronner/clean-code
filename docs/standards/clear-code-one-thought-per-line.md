# Clear Code: One Thought Per Line

## Standard

- Each line of code should express a single thought: at most one access
  operator (`::`, `->`, or `?->`) per expression chain per line, or the
  operator positioned to the right of the assignment operator.
- Avoid chaining access operators; create model attributes that encapsulate
  references to related objects, keeping code concise and moving logic closer
  to its source.

**Why:** one thought per line keeps each line scannable and each dependency
explicit (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

Implemented by **`CleanCode.ClearCode.OneThoughtPerLine`**
([#7](https://github.com/mike-bronner/phpcs-rules/issues/7)). No existing
PHPCS core or Slevomat sniff counts access operators per line —
`PEAR.WhiteSpace.ObjectOperatorIndent` and
`Squiz.WhiteSpace.ObjectOperatorSpacing` only format chains that are already
split, and Slevomat's null-safe-operator sniffs govern `?->` usage, not
chaining.

- **Detection** — every `::`/`->`/`?->` that follows another access operator
  of the *same expression chain* on the *same line* is an error, reported at
  that line. Counting per chain (not per raw line) keeps genuinely separate
  thoughts compliant: `$this->name = $user->name;` carries one operator on
  each side of the assignment, and `format($first->name, $second->name)`
  holds two independent single-operator chains.
- **Null-safe operator** — `?->` counts exactly like `->`; `static::`,
  `self::`, and `parent::` count exactly like instance access.
- **Fluent style is compliant** — a chain split so each line carries one
  operator (`User::query()` newline `->where(…)` newline `->get()`) raises no
  errors, whether split across continuation lines or separate statements.
- **Auto-fixer** — moves each offending operator onto its own continuation
  line, indented one level past the line that starts the chain. The fix is
  purely syntactic and behavior-preserving. Extracting the chain into an
  intermediate variable or model attribute — the standard's preferred
  refactor — needs a well-chosen name and a statement boundary, which is a
  semantic call the fixer cannot make safely; it stays with the developer.

## What remains code review

Whether a split chain should instead become a model attribute (Law of
Demeter), whether the intermediate names chosen actually clarify the thought,
and operators inside interpolated strings (`"{$user->profile->name}"` is one
string token to the tokenizer) — those judgements stay with code review.
