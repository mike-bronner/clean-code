<template wire:key="header">
    <livewire:panel-header wire:key="header" />
</template>
<template wire:key="body">
    <livewire:panel-body wire:key="body" />
</template>

<button wire:click="refresh">Refresh</button>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
