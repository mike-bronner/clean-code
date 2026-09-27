# PHPMD CodeSize: ExcessiveParameterList

- A method or function declares fewer than 10 parameters. The sniff reports it
  at 10 or more.
- A long parameter list is a bag of loose values that belong together. Group
  them into an object and pass that instead.

## Compliant

```php
public function register(Registration $registration): User
{
    return $this->users->create($registration);
}
```

## Non-compliant

```php
public function register(
    string $name,
    string $email,
    string $password,
    string $street,
    string $city,
    string $region,
    string $postcode,
    string $country,
    string $phone,
    string $timezone,
): User {
    // ...
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Functions.ExcessiveParameterList` | no |

Code review checks the parts of this standard that the sniffs cannot see.
