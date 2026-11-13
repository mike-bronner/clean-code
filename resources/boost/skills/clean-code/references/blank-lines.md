# Blank Lines

- Should only be used to separate concepts.
- At most there should be a single blank line; never multiple.
- There should be no blank lines at the beginning or end of classes, methods,
  or functions.
- In a Blade view, put a blank line before every opening block directive
  (`@if`, `@auth`, `@guest`, `@foreach`, `@php` and the other paired
  directives) and after its closing directive (`@endif`, `@endauth`,
  `@endguest`, `@endforeach`, `@endphp`). This also applies when the block is
  the first or last child of an element. Two directives are exempt: an opening
  directive directly after the opening directive of the block it sits in, and
  a closing directive directly before the closing directive of the block it
  sits in. Sibling blocks still need the blank line between them. Only a
  directive that starts its line counts. Text inside `{{ }}`, `{!! !!}`,
  `{{-- --}}`, PHP tags (`<?php ?>`), `@php` and `@verbatim` is not a
  directive, and neither is `@@if` or an email address.

## Compliant

```php
public function total(): int
{
    $subtotal = $this->subtotal();

    return $subtotal + $this->shipping();
}
```

```blade
<flux:callout.text>

    @auth
        @if ($user->isFree())

            Upgrade to read every translation.

        @endif
    @endauth

    @guest
        <a href="/login">Sign in</a>
    @endguest

</flux:callout.text>
```

## Non-compliant

```php
public function total(): int
{

    $subtotal = $this->subtotal();


    return $subtotal + $this->shipping();

}
```

```blade
<flux:callout.text>
    @auth
        <p>Welcome back.</p>
    @endauth
    @guest
        <a href="/login">Sign in</a>
    @endguest
</flux:callout.text>
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.WhiteSpace.BladeDirectiveBlankLines` | yes |
| `CleanCode.WhiteSpace.BlankLines` | yes |
