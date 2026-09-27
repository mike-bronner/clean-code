# Strings: Interpolation, quoting, HereDocs

- Use **interpolation** in favor of concatenation.
- HTML attributes should always use **double quotes**, never apostrophes.
- Defining HTML or other code to be rendered within code should be done using
  **HereDocs**, never NowDocs.
- **Escape quotes** when rendering inside other quotes.

## Compliant

```php
$greeting = "Hello, {$user->name}!";
$link = "<a href=\"{$url}\">Open</a>";
```

## Non-compliant

```php
$greeting = 'Hello, ' . $user->name . '!';
$link = "<a href='{$url}'>Open</a>";
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Strings.RequireStringInterpolation` | yes |
| `CleanCode.Strings.HtmlAttributeQuotes` | yes |
| `CleanCode.Strings.RequireHeredocForStructuredText` | no |
| `CleanCode.Strings.EscapeNestedQuotes` | yes |
| `CleanCode.Strings.DisallowNowdoc` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
