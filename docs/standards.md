# Coding Standards

Standards from [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code) tracked by this
ruleset. Standards that can be verified by a PHPCS sniff are implemented as sniffs. Standards that
cannot be statically enforced (Tier 3) are documented here and enforced through code review and
developer discipline.

## Tier 3 — not statically enforceable

### Policies: Secure Front- and Back-Ends

- Checks should be implemented on the front end to prevent displaying of unwanted elements.
- Checks should be implemented on the back end to prevent execution of unwanted code, in the event
  front-end restrictions are circumvented.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

**Enforceability:** Tier 3 — architectural / semantic. A PHPCS sniff analyzes a single file's token
stream; verifying this standard requires pairing a front-end restriction (a hidden or disabled UI
element, often in a template or JavaScript file PHPCS never parses) with its corresponding back-end
authorization or validation check in a different file and layer. No token-level heuristic can
establish that cross-file correspondence, so no sniff is provided. Enforcement relies on code
review and developer discipline: whenever a review encounters a front-end restriction, it must
confirm the matching back-end check exists.
