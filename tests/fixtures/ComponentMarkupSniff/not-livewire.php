<div x-data="{ open: false }" class="dropdown">
    @foreach ($items as $item)
        <x-menu-item :item="$item" />
    @endforeach

    <x-menu-footer />
    <x-menu-close />
</div>
