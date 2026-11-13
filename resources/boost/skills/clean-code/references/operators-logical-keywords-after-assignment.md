# Operators: Logical Keywords After an Assignment

- Do not combine an assignment with `and`, `or` or `xor`.
- PHP binds these keywords looser than `=` and every compound assignment, so
  the assignment runs first and the keyword only combines its result. The
  right-hand side is never assigned.
- Use `&&` or `||`, which bind tighter than the assignment. For `xor`, which
  has no symbol form, wrap the right-hand side in parentheses.

## Compliant

```php
$granted = $isOwner || $isAdmin;

$granted = ($isOwner or $isAdmin);
```

## Non-compliant

```php
$granted = $isOwner or $isAdmin;
```

This runs as `($granted = $isOwner) or $isAdmin`, so `$granted` holds only
`$isOwner`.

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Operators.LogicalKeywordAfterAssignment` | no |

The sniff reports a keyword at the top level of a statement that starts with an
assignment. An assignment inside an `if`, `while` or `for` condition is left to
`Generic.CodeAnalysis.AssignmentInCondition`.
