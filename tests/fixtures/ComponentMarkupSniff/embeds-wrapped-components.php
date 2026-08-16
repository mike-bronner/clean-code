<div x-data="{ sidebarOpen: false }" class="app-shell">
    <template wire:key="bell">
        <livewire:notifications-bell wire:key="bell" />
    </template>
    <template wire:key="inbox">
        <livewire:inbox-badge wire:key="inbox" />
    </template>
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
