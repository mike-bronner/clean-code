# Clear Code: Encapsulate Each Concept in a Method

## Standard

Refactor each concept into its own method. Through careful naming, this
results in readable, clean code.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

What constitutes a single *concept* is a semantic judgement — a token-based
PHPCS sniff sees statements and expressions, not conceptual boundaries, so it
cannot decide whether a method encapsulates one concept or three. Enforcement
is via code review and developer discipline.

## Partial enforcement

A narrow token heuristic **can** catch the most common textual footprint of
an unextracted concept. It is **implemented** as the custom sniff
`CleanCode.ClearCode.SectionComment`, described below
([#159](https://github.com/mike-bronner/phpcs-rules/issues/159)).

- **Section-labelling comments** — a standalone `//` comment inside a method
  body that labels the block of statements after it (`// validate the
  payload`, `// build the response`) is the classic sign of a concept that
  wants its own method: extract the block into a method named after the
  comment and the comment becomes redundant. Token-visible: a whole-line
  comment inside a function scope followed by further statements in the same
  scope.
- **Warning severity, not error** — comments that explain *why* rather than
  labelling *what* are legitimate, and the token stream cannot tell the two
  apart; the sniff points at extraction candidates rather than mandating a
  fix.
- **Size and complexity proxies are already tracked** — an over-long or
  over-branchy method usually holds several concepts, but those proxies have
  their own issues: method length
  ([#91](https://github.com/mike-bronner/phpcs-rules/issues/91)), cyclomatic
  complexity ([#88](https://github.com/mike-bronner/phpcs-rules/issues/88)),
  NPath complexity
  ([#89](https://github.com/mike-bronner/phpcs-rules/issues/89)), and nesting
  depth ([#36](https://github.com/mike-bronner/phpcs-rules/issues/36)). This
  standard adds no duplicates.

## The rule: `CleanCode.ClearCode.SectionComment`

A comment is reported when **all** of the following hold:

- It is a **self-contained single-line** comment — `//`, `#`, or a one-line
  `/* … */`. A `/* … */` spanning several lines is not, because PHP_CodeSniffer
  splits it into one comment token per physical line and a continuation line
  reads, on its own, exactly like a label.
- It has **its line to itself** — no code before or after it there.
- It is the **first line of its comment run**. Every *label* line with nothing
  but whitespace above it belongs to the same run — a blank line between two
  label lines does not start a second label — and only the head reports. A
  comment the rule would not report in its own right is not a label line and
  does not absorb the one below it, so a label written directly under a debt
  marker, a formatter directive, a docblock, a `phpcs:` annotation or a comment
  trailing a statement still reports.
- It stands at a **statement boundary** — the token before it is `;`, `}`, or
  the opener of the block it stands in (`{`, or the `:` of a `case`/`default`
  arm or an alternative-syntax block). This is what separates a label from a
  comment inside an array literal or an argument list.
- It stands **between two statements rather than inside one**. A `}` ends a
  statement only when the construct it belongs to is over there: a closure, an
  anonymous class and a `match` are expressions whose brace is followed by the
  rest of the expression around them, and a `do` block is followed by its
  `while`. Read forwards, an `else`, `elseif`, `catch` or `finally` after the
  comment continues the construct above it rather than starting a statement. A
  comment in either position labels nothing.
- Its **innermost enclosing scope is a function body** — a method, a function
  or a closure, reached through any number of nested control structures
  (`if`/`else`, the loops, `switch` arms, `try`/`catch`/`finally`). The
  innermost scope is what decides it, so a comment inside an anonymous class or
  above a `match` arm nested in a method is not a label.
- At least **one further statement follows in that same scope**, blank lines
  and further comments ignored. A comment with only the closing brace after it
  introduces no block.

Docblocks (`T_DOC_COMMENT_*`) and `phpcs:` annotations never reach the rule:
the tokenizer gives both their own token types.

Two comment shapes are excluded because a sibling standard owns them:

| Shape | Owned by | Matching |
|---|---|---|
| `TODO`, `FIXME`, `HACK`, `XXX` | Debt: Technical Debt ([#138](https://github.com/mike-bronner/phpcs-rules/issues/138)) | case-insensitive, on word boundaries |
| `@formatter:off`, `@formatter:on`, `prettier-ignore` | Code Style: Linters & Config ([#143](https://github.com/mike-bronner/phpcs-rules/issues/143)) | case-insensitive substring |

Both lists are public properties, so a consuming ruleset can extend either:

```xml
<rule ref="CleanCode.ClearCode.SectionComment">
    <properties>
        <property name="debtMarkers" type="array" value="TODO,FIXME,HACK,XXX,NOTE"/>
        <property name="formatterDirectives" type="array" value="@formatter:off,@formatter:on,prettier-ignore,@fmt:off"/>
    </properties>
</rule>
```

A comment that genuinely explains *why* takes the ordinary per-line
suppression:

```php
// phpcs:ignore CleanCode.ClearCode.SectionComment.Found
// The upstream feed mixes casing, so the comparison below must not be
// case-sensitive.
$payload = array_change_key_case($payload);
```

### Known limits

All four are deliberate silence rather than a guess:

- A comment inside a **PHP 8.4 property hook** is not reported. The tokenizer
  opens no scope for a hook body, so its comments carry the class as their
  innermost scope and read as class-level.
- A comment above a **`match` arm** is not reported. An arm list is a
  comma-separated expression list, like an array literal, so what a label there
  introduces is not a run of statements that can move into a method of its own.
  A `switch` arm, whose body *is* a run of statements, is reported.
- A comment inside an **arrow function** is judged as a label of the enclosing
  function's body. PHP_CodeSniffer leaves `T_FN` out of a token's conditions
  altogether, so the arrow never presents itself as the comment's scope. The
  criteria above already decide these correctly — an arrow function is an
  expression, so a comment inside one never stands between two statements.
- An **unrecognized enclosing scope** resolves to "not a function body", so a
  construct the rule has never seen stays silent.

## What remains code review

Concept boundaries themselves. A method can mix several concepts without a
single comment or excessive length, and a long, comment-free method can
legitimately express one concept. Whether each method encapsulates exactly
one concept — and whether its name states that concept honestly — stays with
code review.
