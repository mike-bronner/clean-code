# Clear Code: One Idea Per Statement

Multiple thoughts combine to form an idea; therefore, each code statement
should encapsulate a single idea, potentially spanning multiple lines.

## Compliant

```php
$user = $this->findUser($id);

if ($user === null) {
    return;
}
```

## Non-compliant

```php
if (($user = $this->findUser($id)) === null) {
    return;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.CodeAnalysis.AssignmentInCondition` | no |
| `Squiz.PHP.DisallowMultipleAssignments` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/clear-code-one-idea-per-statement.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/clear-code-one-idea-per-statement.md)
