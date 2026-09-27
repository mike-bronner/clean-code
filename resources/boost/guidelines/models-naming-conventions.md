# Models: Naming Conventions

**Boolean returns:**

- Boolean properties should start with `is`, `has`, `should`, etc. (a yes/no
  question).
- Boolean methods checking a condition should be named
  `has<Condition in past tense>`.

**Query methods:**

- Methods returning a single instance should be prefixed with `find` + model
  name, e.g. `->findUserByName(string $name)`.
- Methods returning a collection should be prefixed with `get` + model name,
  e.g. `->getUsersByType(string $type)`.

**Attributes:**

- Use the "new" attribute implementation (Laravel accessor).
- Create attributes to expose properties of related models.

## Compliant

```php
public function findUserByEmail(string $email): ?User
{
    return $this->where('email', $email)->first();
}
```

## Non-compliant

```php
public function userWithEmail(string $email): ?User
{
    return $this->where('email', $email)->first();
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.ModelNamingConventions` | no |

Code review checks the parts of this standard that the sniffs cannot see.
