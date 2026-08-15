<div class="board">
    <livewire:card wire:key="a">
        <livewire:card-icon wire:key="icon-a" />
    </livewire:card>
    <livewire:card wire:key="b">
        <livewire:card-icon wire:key="icon-b" />
    </livewire:card>

    <button wire:click="refresh">Refresh</button>
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
