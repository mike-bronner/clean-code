# Clear Code: One Thought Per Line

- Each line of code should express a single thought: at most one access
  operator (`::`, `->`, or `?->`) per expression chain per line, or the
  operator positioned to the right of the assignment operator.
- Avoid chaining access operators; create model attributes that encapsulate
  references to related objects, keeping code concise and moving logic closer
  to its source.

## Compliant

```php
$name = $book->authorName;
```

## Non-compliant

```php
$name = $book->author->profile->name;
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.ClearCode.OneThoughtPerLine` | yes |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/clear-code-one-thought-per-line.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/clear-code-one-thought-per-line.md)
