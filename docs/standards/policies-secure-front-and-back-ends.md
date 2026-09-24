# Policies: Secure Front- and Back-Ends

## Standard

- Checks should be implemented on the front end to prevent displaying of
  unwanted elements.
- Checks should be implemented on the back end to prevent execution of
  unwanted code, in the event front-end restrictions are circumvented.

The two halves are not alternatives. A hidden button is a usability
affordance, not a control: the back-end check is what actually denies the
action once someone reaches the endpoint directly.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This standard is architectural: it constrains the *relationship* between two
pieces of code in different layers, not the shape of any one of them. A PHPCS
sniff sees one file's token stream at a time, so neither half is reachable.

- **The front-end half carries no token to register on.** A JavaScript bundle
  is outside `CleanCode/ruleset.xml`'s `extensions="php"` scope entirely. A Blade template
  is nominally in scope — `*.blade.php` does end in `.php` — but the
  restriction there is a directive or a markup attribute, and even when PHPCS
  tokenizes the file there is nothing in it that names the back-end guard it
  is supposed to be paired with.
- **The back-end half is an absence, not a token.** Verifying it means proving
  that *no* authorization check guards an endpoint. Authorization legitimately
  lives in route middleware (`routes/web.php`), a `FormRequest::authorize()`,
  an auto-registered policy, or a constructor `middleware()` call — most of
  them in a different file from the action they protect. A single-file sniff
  cannot tell "this endpoint is unguarded" from "the guard is somewhere I
  cannot see," so it would report the two identically.

That second point is what separates this standard from the name-based sniffs
this ruleset does ship. `CleanCode.Models.DisallowExternalPersistenceCalls`
tolerates false positives because it flags a token that is *present* and
merely ambiguous in type; a reviewer confirms or dismisses it by looking at
the flagged line. An absence-based check has no line to look at, and its
false-positive rate is set by the project's routing conventions rather than
by anything in the file — no threshold makes it useful.

No subset of the standard survives that, so no partial-enforcement sniff issue
is opened and no rule is wired into `CleanCode/ruleset.xml`. The assessment is recorded on
[#52](https://github.com/mike-bronner/clean-code/issues/52).

## What remains code review

All of it. The review obligation is concrete: whenever a change adds or
modifies a front-end restriction — a hidden control, a disabled input, a
conditionally rendered section — the reviewer confirms the matching back-end
check exists and denies the same action on its own, with the front end assumed
bypassed.
