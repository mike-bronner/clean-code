<div class="rounded border p-4">
    <span>{{ $count }}</span>

    <!-- A wire: attribute is only forbidden on the root element. -->
    <button wire:click="increment" class="btn">+</button>

    <!-- Every component in a loop carries its own wire:key. -->
    @foreach ($rows as $row)
        <livewire:row-item :row="$row" wire:key="row-{{ $row->id }}" />
    @endforeach

    <!-- Adjacent components, each wrapped in a template with a matching key. -->
    <template wire:key="header">
        <livewire:panel-header wire:key="header" />
    </template>
    <template wire:key="body">
        <livewire:panel-body wire:key="body" />
    </template>

    <!-- Commented-out markup is not analysed:
         <livewire:old-header />
         <livewire:old-body /> -->

    {{-- Nor is a Blade comment:
         <livewire:draft-a />
         <livewire:draft-b /> --}}

    <!-- A Blade component, not a Livewire one: no wire:key is owed, in a
         loop or beside a sibling. -->
    @foreach ($items as $item)
        <x-menu-item :item="$item" x-data="{ open: false }" />
    @endforeach

    <!-- A lone component is not adjacent to anything. -->
    <livewire:footer wire:key="footer" />
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
