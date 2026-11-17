# PHPMD CleanCode: BooleanArgumentFlag

- A method, function, closure, or arrow function does not take a boolean flag
  parameter.
- A flag means the callee holds two behaviours and the caller picks one. Extract
  each branch the flag selects into its own method.
- The closure or arrow function passed as the test body to a Pest `it()` or
  `test()` call is exempt. Its parameters receive dataset values, so a `bool`
  there is an expected value, not a flag. A closure passed to any other
  function is still checked.

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
