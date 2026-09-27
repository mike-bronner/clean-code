# Pattern: Repository

Laravel models are the de-facto persistence repository. The repository
behaviour — persistence methods, attribute/query traits — is realised through
the Models standards rather than dedicated repository classes.

The model-side conventions are defined by
the `models-persistence-methods-repository-pattern` guideline
([#37](https://github.com/mike-bronner/clean-code/issues/37)), which asks for
descriptive persistence methods on the model itself, organised into single-use
traits; this entry cross-references that standard rather than restating it.

**Takeaway:** don't create dedicated `Repository` classes; the model *is* the
repository, shaped by the Models standards.

## Compliant

```php
$user->activate();
```

## Non-compliant

```php
class UserRepository
{
    public function activate(User $user): void
    {
        $user->active = true;
        $user->save();
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Pattern.DisallowRepositoryClasses` | no |

Code review checks the parts of this standard that the sniffs cannot see.
