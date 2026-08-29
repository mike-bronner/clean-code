<div wire:model="query" class="search">
    <!--
        Two unwrapped adjacent components follow this comment, which spans
        three lines. blankComments() replaces every character in it except the
        line breaks, so the violations below keep the line numbers they have
        here. A read that gave out and was cast to a string would drop the
        comment entirely and report them three lines early.
    -->
    <p>Adjacent components, neither wrapped:</p>
    <livewire:panel-header />
    <livewire:panel-body />
</div>

<?php

// A real open tag, so PHPCS finds PHP code in the file. Without one it reports
// Internal.NoCodeFound wherever the runtime has short_open_tag disabled, which
// is a property of the install rather than of the fixture.
