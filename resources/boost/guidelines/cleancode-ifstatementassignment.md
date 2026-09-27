# PHPMD CleanCode: IfStatementAssignment

- Do not assign inside a condition. That covers `if`, `elseif`, `while`,
  `do`/`while`, `switch`, `case` and `match`.
- The condition tests the assigned value, not a relationship, so a typo for
  `===` passes silently. Assign first, then test.
- An assignment in a `while` or `do`/`while` condition is reported as a warning,
  not an error.

## Compliant

```php
$user = $this->users->find($id);

if ($user !== null) {
    $this->notify($user);
}
```

## Non-compliant

```php
if ($user = $this->users->find($id)) {
    $this->notify($user);
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.CodeAnalysis.AssignmentInCondition` | no |
| `CleanCode.Conditionals.DisallowListAssignmentInCondition` | no |

Code review checks the parts of this standard that the sniffs cannot see.
