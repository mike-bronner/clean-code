<div class="rows">
    <button wire:click="loadMore">More</button>

    @foreach ($rows as $row)
        <livewire:row-item :row="$row" />
        @include('rows.partials.close-loop')
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
