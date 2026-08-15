<div x-data="{ sidebarOpen: false }" class="app-shell">
    <a wire:navigate href="/dashboard">Dashboard</a>
    <livewire:notifications-bell wire:key="bell" />
    <main>{{ $slot }}</main>
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
