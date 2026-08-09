# Conditionals: Mapping Arrays

## Standard

Use mapping arrays instead of multiple if-statements when inspecting different
values of the same variable.

**Why:** reduces code complexity (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The trigger pattern of this standard **is statically lintable**: an
`if`/`elseif` chain whose conditions all compare the same variable against
different scalar literals is token-visible. The custom sniff
`CleanCode.Conditionals.MappingArrayCandidate`
([#163](https://github.com/mike-bronner/phpcs-rules/issues/163)) flags such
chains as mapping-array (or `match`) candidates, once per chain, at the leading
`if`.

- **Detection** — an `if`/`elseif` chain in which every condition is
  `<same variable> === <scalar literal>` (or `==`), with each branch body a
  single `return` or a single assignment to the same target, is flagged as a
  mapping-array candidate.
- **Configurable threshold** — the minimum branch count is the public sniff
  property `minimumBranches`, defaulting to 3 and counting a trailing `else` as
  the default entry, so projects can tune sensitivity:

  ```xml
  <rule ref="CleanCode.Conditionals.MappingArrayCandidate">
      <properties>
          <property name="minimumBranches" value="4"/>
      </properties>
  </rule>
  ```

- **Warning severity, not error** — the sniff points at rewrite candidates;
  whether a mapping array actually improves a given case is a judgement call.
- **Detection only** — there is no fixer. The safe rewrite depends on where the
  map should live and what the missing-key fallback is, and neither is in the
  chain.
- **Boundaries** — mixed operators (`>`, `instanceof`, ranges), compound
  boolean conditions, function calls in conditions, and multi-statement or
  side-effectful branch bodies stay out: flagging those from tokens alone is
  noise-prone, and an array literal evaluates all its values eagerly, so
  hoisting non-literal branch expressions into a map can change behaviour.

Every continuation spelling is walked — merged `elseif`, spaced `else if`,
brace-less, and alternative syntax (`if:` … `endif`) — because PHP_CodeSniffer
attaches scope to a different token in each.

### What the sniff excludes, beyond the boundaries above

These are narrower than the boundary list, and each is a deliberate choice to
stay silent rather than guess:

| Excluded | Why |
|---|---|
| A subject that is not a plain variable (`$this->status`, `$row['type']`) | The standard speaks about values of *one variable*; a property or index read can differ on each evaluation. |
| `null` as the compared literal | It is not a scalar, and `$x == null` is an emptiness test rather than a value lookup. |
| A chain mixing `return` with assignment, or assigning to different targets | Neither collapses into a single lookup. |
| A branch expression that can do work — a call, `new`, an increment, a nested assignment | Hoisting it into an array literal changes when, and how often, it runs. |
| `switch` and `match` | The sniff registers on `if` alone. `switch` already centralises its subject, and `match` is the construct this standard recommends. |

The sniff deliberately overlaps
[Conditionals: Avoid Conditionals](conditionals-avoid-conditionals.md), which
warns once per branch. A qualifying chain draws warnings from both: one counts
the branches, the other names the replacement.

## What remains code review

Everything outside the flagged slice: branches with side effects, compound or
mixed-operator conditions, range checks, and — above all — the judgement of
whether a mapping array actually reduces complexity for a given case. A chain
of conditions can look mapping-shaped and still encode logic a lookup table
would obscure; that call stays with code review.
