<div wire:model="query" class="search">
    @foreach ($rows as $row)
        <livewire:row-item :row="$row" />
    @endforeach

    <p>Adjacent components, neither wrapped:</p>
    <livewire:panel-header />
    <livewire:panel-body />
    <livewire:panel-footer />

    <p>Adjacent components, one wrapped under the wrong key:</p>
    <template wire:key="left">
        <livewire:left-pane wire:key="left" />
    </template>
    <template wire:key="right">
        <livewire:right-pane wire:key="wrong" />
    </template>
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
