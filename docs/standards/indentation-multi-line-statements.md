# Indentation: Multi-Line Statements

## Standard

- If a single statement extends over multiple lines, any lines subsequent to
  the first should be indented by one level.

**Why:** indicates coherence between lines of code (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (custom sniff)

Enforced by `CleanCode.WhiteSpace.MultiLineStatementIndent`, auto-fixable via
`phpcbf`. The sniff applies the standard as an exact rule — one level is
4 spaces:

- **Sibling lines** — call arguments, array items, and conditions led by a
  boolean operator (`&&`, `||`, `and`, `or`, `xor`) — sit exactly one level
  in from the line their enclosing construct opens on, whether or not the
  first of them shares that line. Boolean operators lead siblings rather than
  continuations because
  [One Condition Per Line](conditionals-one-condition-per-line.md) puts every
  top-level condition on a line of its own: each is a peer of the first
  condition, not a continuation of it.
- **Continuation lines** — led by a chain operator (`->`, `?->`, `::`) or by
  any other binary or ternary operator (`.`, `+`, `?`, `:`, `??`, …), or
  sitting below a trailing `=>` — sit exactly one level in from the line
  where the expression they continue started. Inside a bracket that is the
  element's own line rather than the opener's, so a wrapped argument's
  continuation hangs below the argument:

  ```php
  $phpcsFile->addError(
      'a message long enough to run '
          . 'onto a second line',
      $stackPtr,
  );
  ```

  `=>` is the only operator read in *trailing* position, in every form it
  takes — an array key, a named argument, or an arrow function:

  ```php
  $incremented = array_map(
      fn (int $value): int =>
          $value + 1,
      $numbers
  );
  ```

  Every other dangling operator is
  [`CleanCode.Operators.OperatorLineBreak`](arrays-operator-spacing-and-line-breaks.md)'s
  to report, and re-anchoring around one here would put a second violation on
  a line that already carries its own.
- **A closing bracket on its own line** matches the indent of the line that
  opened the bracket.
- **Scope bodies are out of scope**: lines inside the bodies of closures,
  anonymous classes, and match expressions are governed by scope-indent
  rules, not by this sniff — but their headers (parameter lists, match
  subjects) are continuation lines and are checked. An arrow function is not
  one of these: its body is an expression rather than a scope block, so it
  stays part of the statement and is checked as a continuation.
- **Attributes are their own construct**: a `#[…]` attribute never merges
  with the declaration it decorates into one statement.
- **Heredoc and nowdoc bodies are raw content** — their indentation is data,
  never checked or fixed. The same holds for the second and later lines of a
  quoted string written across lines: those lines are the string's own value.
  The opening fragment is still the argument or operand its line begins with,
  and is indented as one. Writing a string that way is itself a violation of
  [Multiline Strings (HEREDOC)](code-style-multiline-strings-heredoc.md), which
  rewrites it — measuring its interior here would only leave the two rules
  fighting over the same lines.

### Why a custom sniff

Existing rules were evaluated against the full fixture suite
(`tests/fixtures/MultiLineStatementIndentSniff/`) before writing one:

- `Generic.WhiteSpace.ScopeIndent` treats continuation-line indent as
  non-exact and flags none of the violations.
- `PEAR.WhiteSpace.ObjectOperatorIndent`, `Generic.Arrays.ArrayIndent`, and
  `PEAR.Functions.FunctionCallSignature` each match the expected semantics
  but only for one construct (chains, arrays, calls); nothing covers
  string concatenation, ternaries, or boolean-condition indent.
- `PSR12.ControlStructures.ControlStructureSpacing` false-positives on a
  compliant fixture (it additionally requires the first condition expression
  on its own line — a constraint this standard does not impose).
- `SlevomatCodingStandard.ControlStructures.RequireMultiLineCondition`
  governs *when* a condition must become multi-line, not how continuation
  lines are indented — a different concern.

Configuring the partial matches would leave gaps and weaken the tests, so
the standard gets one custom sniff covering every construct uniformly.
