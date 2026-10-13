# PHPMD CleanCode: BooleanArgumentFlag

- A method, function, closure, or arrow function does not take a boolean flag
  parameter.
- A flag means the callee holds two behaviours and the caller picks one. Extract
  each branch the flag selects into its own method.

## Compliant

```php
public function publish(Post $post): void
{
    $post->publish();
}

public function publishAsDraft(Post $post): void
{
    $post->saveDraft();
}
```

## Non-compliant

```php
public function publish(Post $post, bool $asDraft = false): void
{
    $asDraft === true ? $post->saveDraft() : $post->publish();
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Functions.DisallowBooleanArgumentFlag` | no |

Code review checks the parts of this standard that the sniffs cannot see.
