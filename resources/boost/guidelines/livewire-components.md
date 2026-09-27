# Livewire: Components

- Each Livewire component must have a single root element (usually a `div`)
  with no Livewire, Blade, or Alpine attributes.
- Add a unique `wire:key` to each component.
- Components in loops or adjacent to other components must each be wrapped in
  a `<template>` tag with the same `wire:key` as the component.

## Compliant

```blade
<div>
    @foreach ($items as $item)
        <template wire:key="item-{{ $item->id }}">
            <livewire:item-row :item="$item" wire:key="item-{{ $item->id }}" />
        </template>
    @endforeach
</div>
```

## Non-compliant

```blade
<div wire:poll>
    @foreach ($items as $item)
        <livewire:item-row :item="$item" />
    @endforeach
</div>
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Livewire.ComponentMarkup` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/livewire-components.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/livewire-components.md)
