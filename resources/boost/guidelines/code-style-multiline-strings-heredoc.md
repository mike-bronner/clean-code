# Code Style: Multiline Strings (HEREDOC)

Two separate rules, and they are not the same rule at different sizes.

- **An embedded language uses a HEREDOC at any length.** HTML, XML, SQL, JSON,
  YAML, an INI or config block, markdown — anything that is another language
  written inside PHP belongs in a HEREDOC whatever its size, because the
  delimiter is what gives an editor a language to
  highlight. A quoted string is one flat run of characters to every tool that
  reads it. Enforced by `CleanCode.Strings.RequireHeredocForStructuredText`.
- **Any text longer than three lines uses a HEREDOC.** Past that the wrapping is
  no longer a concession to the line limit, it is a block of text, and a HEREDOC
  reads as the block it is.
- **A HEREDOC, never a NOWDOC.** The two differ only in the quotes around the
  opening identifier, and carrying both means a reader checks the delimiter
  before trusting what the body says. Enforced by
  `CleanCode.Strings.DisallowNowdoc`.

Lines of *text*, never lines of source. The distinction decides whether the rule
asks for something reachable. A sentence wrapped across four source lines is one
line of text, and a HEREDOC cannot express it: the body would sit on one line and
break the 120-character limit, or wrap and put real newlines into the value. So a
rule that counted the source reported what no fix could satisfy. Source layout is
already governed — the 100-character limit says how long a line may be, and the
leading-operator rule says where it breaks — and between them they produce
exactly the wrapping this rule used to flag.

The threshold is configurable through the `maximumLines` property.

```php
$string = <<<HTML
    <div>
        Hello, world!
    </div>
    HTML;
```

This matters most for inline SQL, where a multi-line HEREDOC reads as the query
it is:

```php
$sql = DB::statement(<<<SQL
    SELECT "Hello, world!"
    SQL);
```

## Compliant

```php
$html = <<<HTML
    <div>
        Hello, {$name}!
    </div>
    HTML;
```

## Non-compliant

```php
$html = '<div>
    Hello, ' . $name . '!
</div>';
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Strings.MultilineStrings` | yes |
| `CleanCode.Strings.RequireHeredocForStructuredText` | no |
| `CleanCode.Strings.DisallowNowdoc` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
