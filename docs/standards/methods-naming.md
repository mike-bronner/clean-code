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
facts. That slice is implemented by the custom
`CleanCode.Naming.ActionMethodReturn` sniff
([#172](https://github.com/mike-bronner/clean-code/issues/172)); see
[The command–query slice](#the-commandquery-slice) below.

Adjacent slices are already tracked elsewhere and are **not** duplicated
here:

- Boolean accessor naming (`getX(): bool` → `isX()`/`hasX()`) —
  [#116](https://github.com/mike-bronner/clean-code/issues/116)
  (PHPMD `BooleanGetMethodName`).
- Model query-method prefixes (`find*`/`get*`) and attribute naming —
  [#44](https://github.com/mike-bronner/clean-code/issues/44)
  (Models: Naming Conventions).

## The command–query slice

`CleanCode.Naming.ActionMethodReturn` reports a **warning** on a method whose
name starts with an action verb and that hands a value back. Both halves are
read from tokens:

- **The name is an action** when it starts with one of the configured verbs and
  the next character is not lower-case. `setName()` and `set()` match;
  `settle()` and `addressOf()` do not — a lower-case continuation means the verb
  was never a word of its own. The match is case-sensitive, because a method
  named `SetName()` is a *casing* violation owned by
  [#22](https://github.com/mike-bronner/clean-code/issues/22) /
  [#100](https://github.com/mike-bronner/clean-code/issues/100).
- **It hands a value back** when it declares a return type other than `void` or
  `never`, or — lacking a declared type — its own body holds a `return` with an
  expression after it. A bare `return;` is flow control, not a value, and a
  `return` inside a closure, an arrow function, a nested function, or an
  anonymous class belongs to that declaration, not this one.

  `void` and `never` are the only two types that report nothing. A standalone
  `: null` is a declared type in its own right rather than the nullable marker,
  so it reports — a caller still receives something, and `null` is the answer.
  Beside another type (`?static`, `Builder|null`) the same word only says the
  value may be absent, and it is read that way instead.

Methods only. A plain function, a closure, and an arrow function are left
alone: the standard describes an action taken on a class.

**Warning severity, deliberately.** Framework idioms legitimately return a
status from an action method — Eloquent's `save(): bool` is the obvious one —
so a report is a review candidate, not a build failure. Detection only:
dropping a return type changes what every call site can do with the call, and
renaming the method rewrites the call sites themselves.

### Properties

| Property | Default | What it does |
|---|---|---|
| `actionPrefixes` | `add`, `apply`, `attach`, `clear`, `delete`, `detach`, `post`, `remove`, `reset`, `save`, `send`, `set`, `store`, `update` | The verbs a method name is matched against, in order. |
| `allowFluentInterface` | `true` | Exempts a method that hands back its own object. |

The fluent exemption covers a declared `static`, `self`, or a bare return type
naming the enclosing class, and a body whose *every* value-return is
`return $this;`. The nullable spellings (`?static`, `Builder|null`) are covered
too: `null` only adds a third state to the same object. So is a union whose
*every* member is one of those spellings — `self|static`, `Builder|static` —
since no member of it can be a value.

The body half reads the whole returned expression, so how it is written down
does not change the answer: `return ($this);`, `return (($this));` and a
`return $this;` with a comment before it are the same builder as the plain
spelling. Only what comes back counts, and *grouping* parentheses and comments
are not part of it.

Four shapes are **not** exempt. A body that returns `$this` on one path and a
result on another — that mix is the command–query blur the rule is about. A
union with a member that *is* a value, such as `static|false`, which hands back
either the object or something to read. A qualified spelling of the enclosing
class (`\App\Models\Order`): resolving it needs the file's imports, and a wrong
answer there would silence a real finding. And `return $this();` or
`return ($this)();`, which invoke `__invoke()` and hand back *its* result: those
parentheses are a call, not a grouping, so what comes back is a value like any
other.

Switch the exemption off and those declarations report like any other returning
method:

```xml
<rule ref="CleanCode.Naming.ActionMethodReturn">
    <properties>
        <property name="actionPrefixes" type="array" value="set,save,delete,publish"/>
        <property name="allowFluentInterface" value="false"/>
    </properties>
</rule>
```

## What remains code review

Whether a method name is a verb, whether an accessor's noun matches what it
actually returns, whether a Model accessor is named after the attribute it
exposes, and whether a call site reads as an action taken on the class are
all judgements about meaning, not tokens. Those calls stay with code review.
