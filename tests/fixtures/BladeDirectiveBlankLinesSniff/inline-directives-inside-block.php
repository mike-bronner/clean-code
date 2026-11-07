<ul>

    @foreach ($items as $item)
        <li @if ($item->active) class="active" @endif>{{ $item }}</li>
        <li>@if ($item->new) New @endif</li>
    @endforeach
</ul>

<?php
