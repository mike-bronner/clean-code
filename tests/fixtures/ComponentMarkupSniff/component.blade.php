<div wire:poll class="feed">
    <livewire:feed-item-a />
    <livewire:feed-item-b />
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
