# Clean Code

This project checks its PHP with the `CleanCode` PHPCS standard from
`mike-bronner/clean-code`. The standard enforces a set of clean code rules for
PHP and Laravel: naming, conditionals, class and method shape, models,
controllers, routes, tests, and more. Code review enforces the rules that no
sniff can check.

- Before you write, edit, refactor or review any PHP or Blade code, activate the
  `clean-code` skill. Then read the references it lists for the code you touch.
- Write code that matches each reference's compliant examples. Do not wait for
  `phpcs` to report a violation.
- After a change, run `vendor/bin/phpcs` over the changed files. Fix every
  violation before you report the work as done.
- A sniff message that names a file under `resources/boost/skills/clean-code/`
  points at the reference for the rule it enforces. Read that reference before
  you fix the violation.
- A reference whose Enforcement section says "Enforced by code review only" has
  no sniff. Follow it anyway, because `phpcs` will not catch a violation of it.
