<div x-data="{ open: false }" class="dropdown">
    @foreach ($items as $item)
        <x-menu-item :item="$item" />
    @endforeach

    <x-menu-footer />
    <x-menu-close />
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
