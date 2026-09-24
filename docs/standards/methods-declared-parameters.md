# Methods: Declared Parameters

## Standard

- Methods should have a declared parameter list and not use a dynamic one.
  Magic methods are the exception.

**Why:**

- A signature is the method's contract. Reading arguments the signature never
  declared hides that contract inside the body, so a caller cannot tell what
  the method accepts without reading its implementation (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff matches this standard. PHPCS's configurable
[`Generic.PHP.ForbiddenFunctions`](https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/src/Standards/Generic/Sniffs/PHP/ForbiddenFunctionsSniff.php)
comes closest — it can forbid `func_get_args()`, `func_get_arg()`, and
`func_num_args()` by name — but it matches on the name alone, with no notion of
the enclosing method or of what the name resolves to: run against this
standard's compliant fixture it reports six false positives — four dynamic
argument reads inside magic methods, the exception it cannot see, plus two
same-named symbols it mistakes for calls, a `namespace\`-relative qualifier and
a return-by-reference declaration.
Slevomat ships no sniff covering dynamic argument lists at all. Rather than
weaken the tests to fit, the standard is enforced by the custom
`CleanCode.Methods.DeclaredParameters` sniff, wired into the master `CleanCode/ruleset.xml`
via the CleanCode standard
([#69](https://github.com/mike-bronner/clean-code/issues/69)).

- **Detection** — every call to PHP's dynamic argument-list functions —
  `func_get_args()`, `func_get_arg()`, and `func_num_args()` — is flagged at the
  call itself, reported as
  `CleanCode.Methods.DeclaredParameters.DynamicArguments`. Names are matched
  case-insensitively, as PHP resolves them.
- **Scope** — the rule applies to every function-like declaration, not only
  methods: a closure, an arrow function, and a plain function each declare a
  parameter list, and hiding it behind a dynamic read costs the reader exactly
  the same.
- **Not flagged** — constructs that either satisfy the standard or merely share
  a name are deliberately left untouched:
  - **Variadic parameters** (`function join(string ...$parts)`) — a variadic
    *is* a declared parameter: named, optionally typed, and visible in the
    signature. It is the modern replacement for `func_get_args()`, and the
    standard asks for a declared parameter list, not a fixed-length one.
  - **Magic methods** — the standard's stated exception. `__construct()`,
    `__call()`, `__callStatic()`, `__get()`, `__toString()` and the rest are
    invoked by the engine against a fixed signature, so their bodies may reach
    for the dynamic argument list. The exemption belongs to the magic method's
    own body: a closure or arrow function declared *inside* one declares its own
    parameter list and does not inherit it, and a plain function that merely
    happens to be named `__get()` is not a magic method.
  - **Same-named symbols** — namespaced functions (`Acme\func_get_args()`),
    object and static member calls (`$collector->func_num_args()`,
    `Collector::func_num_args()`), function declarations
    (`function func_get_args()`, including return-by-reference
    `function &func_get_args()`), instantiations in every spelling
    (`new func_get_args()`, `new \func_get_args()`,
    `new namespace\func_get_args()`), and constants (`FUNC_NUM_ARGS`).
    Qualifying a name changes which symbol it reaches, never what the construct
    is, so a *call* through a leading separator alone (`\func_get_args()`)
    still qualifies the global namespace and *is* flagged.
  - **`namespace\func_get_args()` inside a named namespace** — the relative
    qualifier resolves against the current namespace with no fallback to the
    global one, so it names a different function. Where the current namespace
    *is* the global one the same spelling resolves to PHP's function and *is*
    flagged: in a file that declares no namespace, and inside a braced
    `namespace { … }` block whatever named blocks surround it.
  - **Names bound by a function import to another symbol** — a qualified import
    (`use function Acme\Support\func_get_args;`, including group use and
    aliases) makes the unqualified call resolve to the import, not to PHP's
    function. A mixed group prefixes the individual entry
    (`use Acme\Support\{ClassA, function func_get_args};`) and counts the same.
    An import that still names PHP's own function — unqualified under the same
    name (`use function func_get_args;`) or a self-alias — *is* flagged, and a
    *class* import never affects function resolution at all, in a group or on
    its own.
- **Auto-fixable — No (detection only).** Replacing a dynamic read with a
  declared parameter changes the method's signature, and every call site has to
  change with it. A token-based fixer cannot make those call-site changes
  safely, so the sniff is delivered detection-only; declaring the parameters is
  left to the developer performing the refactor.

Tests covering compliant code, per-line/column violation reporting for all
three functions, the exemption boundary around closures and arrow functions,
name resolution through function imports (statement-wide, aliased, and the
per-entry prefix of a mixed group), the `namespace\` qualifier in
namespaced files, global files, and braced namespace blocks, the reported
message, and the non-fixable (detection-only) guarantee live at
`tests/Standards/DeclaredParametersTest.php`, with their fixtures in
`tests/fixtures/DeclaredParametersSniff/`.

## What remains code review

Nothing about *detecting* a dynamic argument list — that is fully
machine-enforced. Whether the parameters a developer declares in its place are
the right ones, and whether a long parameter list should instead become an
object, stays a code-review judgement the sniff does not make.
