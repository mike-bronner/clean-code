# PHPMD Design: DevelopmentCodeFragment

- Do not commit a call to a debug function: `var_dump()`, `print_r()`,
  `debug_zval_dump()`, `debug_print_backtrace()`, `dump()` or `ray()`.
- A debug call that reaches the repository leaks internals into output. Log
  through the application logger instead.

## Compliant

```php
$this->logger->debug('Imported rows', ['count' => count($rows)]);
```

## Non-compliant

```php
var_dump($rows);
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Debug.DisallowDebugFunctions` | no |

Code review checks the parts of this standard that the sniffs cannot see.
