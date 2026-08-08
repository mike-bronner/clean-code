<div class="panes">
    <button wire:click="refresh">Refresh</button>

    <template>
        <livewire:left-pane wire:key="left" />
    </template>
    <template>
        <livewire:right-pane wire:key="right" />
    </template>
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
