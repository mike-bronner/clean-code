# Constructors: No Logic in Constructors

## Standard

Constructors should not include any functionality or logic, but merely assign
values to object properties. If logic needs to be performed, that is an
indication the information passed in should actually be another object. Any code
in the constructor is parsed every time an object is created, regardless of
necessity, and can't be optimized. If only assignments are handled, optimization
can be controlled.

**Why:** prevents optimization (technical debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff flags non-assignment statements inside a
constructor body. Slevomat's
[`Classes.RequireConstructorPropertyPromotion`](https://github.com/slevomat/coding-standard/blob/master/doc/classes.md#requireconstructorpropertypromotion)
(already wired in for [#47](https://github.com/mike-bronner/phpcs-rules/issues/47))
pushes assignments into promoted parameters but says nothing about other logic,
and was evaluated against this standard's test suite without matching it. The
standard is therefore enforced by the custom `CleanCode.Constructors.NoLogic`
sniff, wired into the master `rules.xml` via the CleanCode standard
([#40](https://github.com/mike-bronner/phpcs-rules/issues/40)).

- **Detection** — the sniff walks the top-level statements of every
  `__construct()` body and flags each statement that is not one of the two
  allowed forms, reporting at the statement's first token as
  `CleanCode.Constructors.NoLogic.LogicFound`. Allowed:
  - a **property assignment** — a statement beginning with `$this->…` and
    carrying a plain `=` operator at its top level whose target is a direct
    property chain on `$this` (`$this->foo = …;`, `$this->arr[] = …;`,
    `$this->cfg['k'] = …;`). The right-hand side is not inspected, so defaulting
    with `??` or a ternary (`$this->foo = $foo ?? 0;`) stays compliant.
  - a **`parent::__construct(…)` call** — delegating to the parent constructor
    is assignment, not logic. The statement has to be *exactly* that call: a
    real argument list whose closing parenthesis is the last thing before the
    semicolon.
- **Flagged** — everything else in the body:
  - **Control structures** — `if`/`elseif`/`else`, `for`, `foreach`, `while`,
    `do … while`, `switch`, and `try … catch … finally`, in both the brace form
    and the `:` … `endif;` alternative syntax. Each construct is reported
    **once**, at its opening keyword; continuation clauses (including the
    two-word `else if`) and nested statements are not reported separately.
  - **`match` expressions used as statements**, **free `{ … }` blocks** and
    **`return`**.
  - **`throw` statements**, **method calls** (`$this->configure()`) and
    **function calls** (`doSomething()`).
  - **Non-property assignments** — a local-variable assignment (`$x = …;`) is
    intermediate computation, not object state, and an increment (`$this->n++;`)
    computes rather than assigns.
  - **Compound assignments** — `+=`, `.=` and `??=` (`$this->n += 1;`,
    `$this->s .= 'x';`, `$this->n ??= 2;`) read the property before writing it,
    so they compute rather than plainly assign.
  - **Trailing logic on a parent call** — `parent::__construct($a) or
    $this->boot();` and `parent::__construct($a)->initializeExtra();` run work
    on every instantiation, and a bare `parent::__construct;` is a reference
    rather than a call. None of the three is delegation.
  - **Assignments whose target invokes something** — work in the assignment
    *target* runs on every instantiation, so the statement is flagged even
    though it ends in an assignment. Three spellings, and only the first
    carries a parenthesis:
    - a **call** (`$this->getConfig()->value = …;`,
      `$this->items[$this->key()] = …;`, `$this->loadDefaults()['k'] = …;`).
      A parenthesis that only **groups** invokes nothing and stays compliant
      (`$this->items[($this->a + $this->b)] = …;`) — what precedes it decides,
      because a parenthesis calls whatever comes before it;
    - an **invoking keyword** carrying no argument list of its own: a
      **backtick shell execution** (`` $this->items[`hostname`] = …; ``), a
      `new` or `clone` (`$this->items[(clone $this->seed)->k] = …;`,
      `$this->items[(new class { … })->k] = …;`), or one of `eval`, `exit`,
      `print`, `throw`, `yield`, `include` and `require`;
    - a **complex interpolation**, `{$…}` or `${…}`, inside a double-quoted
      string or a heredoc (`$this->items["{$this->key()}"] = …;`). PHPCS hands
      an interpolated string over as one opaque token, so a call spelled inside
      it surfaces no parenthesis at all.

    Only the target is inspected — a call on the *right-hand side*
    (`$this->foo = compute();`, `$this->foo = "{$this->key()}";`) stays outside
    the sniff's scope, as noted above.
- **Compliant edge cases** — an **empty constructor**, a constructor with only
  **promoted-property parameters** (no body), a constructor **mixing promoted
  parameters with body assignments**, a constructor **calling only
  `parent::__construct(…)`**, and assignments whose RHS uses **null-coalescing
  (`??`) or a ternary** default all pass.
- **Auto-fixable — No (detection only).** Moving logic out of a constructor is a
  refactor, not a mechanical rewrite: the code has to land somewhere deliberate
  (a named constructor, a factory method, or a collaborator object), and that
  target cannot be inferred from tokens. The sniff surfaces the violation and
  leaves the refactor to the developer.

### Known boundaries

A few intentional edges, decided rather than accidental:

- **A free `function __construct()`** (a function at namespace scope, not a
  class method) is **not** a constructor and is never inspected — the sniff
  guards on the declaration living inside an object-oriented container.
- **List-destructuring straight into properties** (`[$this->a, $this->b] = $pair;`)
  is **flagged**: the statement begins with `[`, not `$this->`, so it does not
  match the property-assignment form. Uncommon in constructors and treated as
  logic by design.
- **Explicit-ancestor delegation** (`ParentClass::__construct(…)`, naming the
  class instead of using the `parent` keyword) is **flagged**; only the
  `parent::__construct(…)` form is recognised as delegation.
- **The first-class callable `parent::__construct(...)`** (PHP 8.1, the argument
  list being a lone `...`) is **flagged**. It builds a `Closure` over the parent
  constructor and discards it — the parent constructor never runs, so the
  statement delegates nothing and is a call-shaped lookalike. Every argument
  list that really invokes stays compliant, including the spread
  `parent::__construct(...$args)` and named arguments.
- **A declaration held on an assignment's right-hand side** — a closure, an
  arrow function, or an anonymous class assigned to a property — is
  **compliant** however much logic it contains. It runs when something calls
  it, not on instantiation, and the right-hand side is not inspected.
- **A complex interpolation in an assignment target is flagged on sight**, even
  when it holds no call (`$this->items["{$this->prefix}"] = …;`). PHPCS gives an
  interpolated string as text rather than tokens, and splits a multi-line one at
  every physical line, so finding a call inside it would mean re-lexing PHP
  across token boundaries; rejecting the syntax that *can* carry one is the
  conservative side of that trade, and the call-free key has a compliant
  spelling already — the direct `$this->items[$this->prefix] = …;`. **Simple
  interpolation stays compliant** (`$this->items["$key"] = …;`,
  `$this->items["$this->prefix"] = …;`): it admits no parentheses, so it can
  only read, exactly like the bare subscript it spells. A **nowdoc** key is
  compliant too — it interpolates nothing.
- **A backslash before `{$` makes it simple interpolation, not complex**, and
  the key is then **compliant**. PHP reads `"\{$this->prefix}"` as a literal
  `\{`, the simple interpolation `$this->prefix`, and a literal `}` — the
  complex opener never forms, so `"\{$this->key()}"` never calls anything. The
  parity of the backslash run decides: an odd count suppresses, an even count
  leaves a real `\` and interpolates, so `"\\{$this->key()}"` is flagged and
  `"\\\{$this->key()}"` is not. `${…}` follows the ordinary `\$` escape instead
  — `"\${key}"` is literal text, `"\\${resolveKey()}"` interpolates.
- **A construct that evaluates without calling user code** — `isset(…)`,
  `empty(…)`, `array(…)` — is **flagged** in an assignment target. Its
  parenthesis follows a keyword rather than an operator, and the sniff reads
  every such parenthesis as invoking. Over-reporting is the deliberate side of
  that trade: a missing entry in the grouping list costs a false positive on an
  exotic key, while a missing entry in a list of *call* spellings would let real
  work run unseen on every instantiation.

The sniff's behaviour lives at `tests/Standards/NoLogicTest.php`, over the
fixtures at `tests/fixtures/NoLogicSniff/`: `passing.php` for compliant code
and the near-miss shapes the sniff must stay silent on, `failing.php` for every
flagged statement kind, asserted by exact line, column, and violation source.
There is no `autofixed.php` — the sniff is detection-only.

## What remains code review

Nothing about *detecting* constructor logic — that is fully machine-enforced.
The *refactor* to relocate flagged logic (introducing a named constructor or
factory, extracting a collaborator, threading the result to call sites) is
manual work the sniff surfaces but does not perform.
