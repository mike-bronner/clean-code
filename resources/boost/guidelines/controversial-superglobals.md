# PHPMD Controversial: Superglobals

- Do not access a superglobal directly: `$GLOBALS`, `$_SERVER`, `$_GET`,
  `$_POST`, `$_FILES`, `$_COOKIE`, `$_SESSION`, `$_REQUEST` or `$_ENV`.
- A superglobal cannot be substituted in a test, and its shape is unvalidated.
  Inject the framework's request, session or config object instead.

## Compliant

```php
public function store(Request $request): void
{
    $name = $request->input('name');
}
```

## Non-compliant

```php
public function store(): void
{
    $name = $_POST['name'];
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Controversial.Superglobals` | no |

Code review checks the parts of this standard that the sniffs cannot see.
