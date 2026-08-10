# PHPMD Controversial: Superglobals

## Rule

- Do not read a superglobal directly.

**Why:** a superglobal read couples the code to PHP's request environment. The
value cannot be substituted in a test, its shape is unvalidated, and every
caller has to trust that whatever wrote it did so correctly. A framework's
request object encapsulates all three concerns, so the remedy is to inject it.

```php
// PHPMD (and this ruleset) flags this:
$name = $_POST['foo'];
```

_Source: [phpmd.org/rules/controversial.html](https://phpmd.org/rules/controversial.html)
(PHPMD Controversial ruleset, since PHPMD 1.1.0)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Controversial/Superglobals` | `CleanCode.Controversial.Superglobals` (message code `.Found`) |

Enforced by the custom `CleanCode.Controversial.Superglobals` sniff, picked up
automatically through the `./CleanCode/ruleset.xml` reference in the master
ruleset (`rules.xml`) — see [#90](https://github.com/mike-bronner/phpcs-rules/issues/90).
Running `phpcs` with `rules.xml` therefore covers this rule, and `phpmd` does
not have to run separately for it.

### The names flagged

PHPMD 2.15.0 carries sixteen names in its rule's own `$superglobals` list, and
so does this sniff. The nine PHP 8 actually defines:

| | | |
| --- | --- | --- |
| `$GLOBALS` | `$_SERVER` | `$_GET` |
| `$_POST` | `$_FILES` | `$_COOKIE` |
| `$_SESSION` | `$_REQUEST` | `$_ENV` |

and the seven PHP 4 long-form aliases (`register_long_arrays`) that PHP 5.4
removed:

| | | |
| --- | --- | --- |
| `$HTTP_SERVER_VARS` | `$HTTP_GET_VARS` | `$HTTP_POST_VARS` |
| `$HTTP_POST_FILES` | `$HTTP_COOKIE_VARS` | `$HTTP_SESSION_VARS` |
| `$HTTP_ENV_VARS` | | |

The aliases are kept for parity. On PHP 8 they are ordinary undefined variables
rather than superglobals, which makes the diagnostic more useful than PHPMD's,
not less: code still reading `$HTTP_POST_VARS` is not merely coupled to the
request environment, it is reading nothing at all.

Matching is case sensitive, because PHP variable names are. `$_get` is a
different variable from `$_GET`, and neither PHPMD nor this sniff reports it.

Both spellings of an access are covered — the bare variable, and the
interpolated form inside a double-quoted string or a heredoc, which PHPMD
reports too. A nowdoc and a single-quoted string do not interpolate, so neither
is inspected. The backtick operator interpolates as well and is covered, though
by the bare-variable path rather than the string one: PHPCS tokenises a variable
inside backticks as a variable rather than as part of the string.

### No configurable properties

PHPMD's rule has no threshold and no configurable property, so there is nothing
to tune and this sniff exposes no public property either. That is why the
`rules.xml` block for this rule sets no `<properties>`, unlike the metric rules
next to it.

### Not auto-fixable

Matching PHPMD. Replacing a superglobal read with an injected request
abstraction depends on which abstraction the project uses and on what the value
is for, so there is no safe mechanical rewrite.

### Severity raised to error

The sniff reports errors, not warnings, for the same reason `Squiz.PHP.Eval` and
`VariableAnalysis` are raised to error in `rules.xml`: PHPMD fails a run on this
violation, and a warning would leave `phpcs` exiting `0`, so `phpmd` would still
have to run separately for this rule.

## Why no existing sniff was used

Two sniffs come close. Both were run against
`tests/fixtures/SuperglobalsSniff/`, whose `failing.php` holds 26 accesses.
Every count below is measured at the versions `composer.lock` pins, and
`tests/Standards/SuperglobalsTest.php` re-measures each of them on every run
and reads them back out of this table, so a vendor upgrade that moves one fails
the suite rather than leaving this table to drift.

| Candidate | Reports | Why it was rejected |
| --- | --- | --- |
| `SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable` | 12 of 26 | Nine names against PHPMD's sixteen, so every long-form alias goes unreported; it also matches on the bare `T_VARIABLE` token alone, whatever encloses it, so its twelve are the nine modern names plus three unusual bare-variable contexts — a variable-variable `$$_GET`, a dynamic property read `$this->{$_POST['field']}`, and a backtick shell-exec `` `ls $_SERVER[PWD]` ``. What it never does is look inside a string token, so all seven interpolated accesses go unreported. In the other direction it reports both member *declarations* in `passing.php`, which PHPMD does not. Exposes no property, so none of it is configurable. |
| `Generic.PHP.DisallowRequestSuperglobal` | 1 of 26 | Matches the single name `$_REQUEST`. Its message recommends `$_GET`, `$_POST` and `$_COOKIE` as the replacement — the very names this rule exists to forbid. |

Neither matches, so the custom sniff carries the rule, and it reports 26 of 26.

## Divergences from PHPMD

Every shape below is pinned by
`tests/fixtures/SuperglobalsSniff/divergences.php` and asserted in
`tests/Standards/SuperglobalsTest.php`. `passing.php` and `failing.php` hold no
divergence at all, so both claim exact parity: on `failing.php` PHPMD reports
the same 26 accesses under the same 26 names, and on `passing.php` its
Superglobals rule is silent.

### Stricter — two shapes

| Shape | Why PHPMD misses it |
| --- | --- |
| A superglobal read at **file scope** | PHPMD's rule is `MethodAware` and `FunctionAware`, so it inspects function and method bodies only. The read is the same defect wherever it sits. |
| The `"${_POST}"` **interpolation form** | PDepend does not model it. PHP reads the superglobal all the same. The form is deprecated in PHP 8.2 and removed in PHP 9, which makes the report more useful, not less — the code has to be rewritten anyway. |

Both are true superglobal reads, so both are kept rather than suppressed for
parity — the posture `rules.xml` already takes for the extra `VariableAnalysis`
and `DisallowExitExpression` reports. Adopting this ruleset can therefore
surface findings a previous `phpmd` run did not.

### Narrower — one shape

| Shape | Why PHPMD reports it anyway |
| --- | --- |
| A **static property access**: `self::$_POST`, `static::$_POST`, `Holder::$_POST` | PDepend matches a variable's image without looking left. `::` binds the name to a static property of the named class, so no superglobal is reached and all three PHPMD reports are false positives. |

Replicating them would mean replicating a false positive, which this rule's
acceptance criteria forbid.

## Shapes both tools leave alone

- A member **declaration** that happens to carry a superglobal's name
  (`public $_GET = [];`). It declares a property; nothing reads the superglobal.
  The PHP 8 promoted-constructor spelling is the same declaration and is left
  alone too, though only the long-form aliases can be written that way:
  PHP refuses to compile a parameter named after one of the nine real
  superglobals, promoted or not (`Cannot re-assign auto-global variable`).
- An object property read as `$request->_GET` or `$request?->_SERVER`. PHPCS
  tokenises the name after an object operator as an identifier, never as a
  variable.
- A name that merely starts with a superglobal's spelling — `$_GETTER`,
  `$_ENVIRONMENT` — bare or interpolated.
- A superglobal spelled inside a single-quoted string, a nowdoc, or behind a
  backslash (`"\$_POST"`). None of the three interpolates.

  "Behind a backslash" means an **odd** run of them. A backslash can itself be
  escaped, so what cancels the interpolation is a backslash left spare once the
  run is paired off: `"\$_GET"` and `"\\\$_GET"` read nothing, while `"\\$_GET"`
  is a literal backslash followed by a live read and is reported — by PHPMD too.
  `tests/fixtures/SuperglobalsSniff/escape-pairs.php` carries runs of one
  through four and claims exact parity the way `passing.php` and `failing.php`
  do.
