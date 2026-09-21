# Clear Code: Encapsulate Related Classes in a Domain

## Standard

- Group related classes into a domain representing functional blocks in the
  real world.
- Domain-driven design takes this to the extreme by creating software
  abstractions called domain models that include business logic linking actual
  product conditions to code.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

The standard itself **is not statically lintable**: whether two classes are
*related*, and whether a namespace models a real-world functional block rather
than an arbitrary bucket, are semantic judgements about the problem domain. A
token-based PHPCS sniff sees one file at a time and no token stream reveals
them. Enforcement of the full standard is code review and developer
discipline.

## Partial enforcement — Tier 2 slice: the junk-drawer namespace

One narrow slice **is token-visible**: the junk-drawer namespace. A class
filed under a generic technical bucket such as `App\Helpers`, `App\Utils`,
`App\Misc`, or `App\Common` is definitionally *not* grouped by domain, and a
single-file sniff can read the `namespace` declaration and flag discouraged
segment names.

That slice is enforced by the custom sniff
**`CleanCode.ClearCode.JunkDrawerNamespace`**
([#190](https://github.com/mike-bronner/phpcs-rules/issues/190)), registered
automatically from `CleanCode/Sniffs/` when the standard loads.

- **Detection** — every segment of a `namespace` declaration is compared
  against a discouraged list, case-insensitively and whole-segment. Both
  spellings report: `namespace App\Helpers;` and `namespace App\Helpers { … }`.
  A name carrying two discouraged segments (`App\Helpers\Utils`) reports twice,
  once per segment.
- **Defaults** — `Helpers`, `Utils`, `Utilities`, `Misc`, `Common`, `General`.
- **Configurable list** — the segments are the public `discouragedSegments`
  property, so a consuming project's ruleset can replace or extend them:

  ```xml
  <rule ref="CleanCode.ClearCode.JunkDrawerNamespace">
      <properties>
          <property name="discouragedSegments" type="array">
              <element value="Helpers"/>
              <element value="Shared"/>
          </property>
      </properties>
  </rule>
  ```

- **Warning severity, not error** — a small library may legitimately keep a
  `Helpers` bucket; the sniff points at domain-grouping candidates rather than
  mandating a move.
- **Detection only, no fixer** — renaming a namespace means moving files and
  rewriting every reference to them, and which domain the classes belong in is
  exactly the judgement the sniff cannot make.
- **Anti-pattern only** — the sniff cannot verify *positive* domain grouping;
  it flags the well-known names that signal grouping by technical role instead
  of by domain. Framework-mandated layer namespaces (`Controllers`, `Models`,
  `Providers`, …) are absent from the default list and are not flagged out of
  the box. The sniff reads *declarations* only: importing from a junk drawer
  (`use App\Helpers\Formatter;`) or naming a *class* `Helpers` is silent.

## What remains code review

Everything positive about this standard: recognizing which classes belong
together, naming the domain after a real-world functional block, and deciding
when a codebase has grown enough to warrant carving out a domain model.
Whether `Billing` is a genuine domain or a relabeled junk drawer is a
judgement about the business, not the tokens — that call stays with code
review, and the standard itself remains tracked as Tier 3 under
[#16](https://github.com/mike-bronner/phpcs-rules/issues/16).
