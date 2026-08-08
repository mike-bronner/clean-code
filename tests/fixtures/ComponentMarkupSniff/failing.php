<div wire:model="query" class="search">
    @foreach ($rows as $row)
        <livewire:row-item :row="$row" />
    @endforeach

    <p>Adjacent components, neither wrapped:</p>
    <livewire:panel-header />
    <livewire:panel-body />
    <livewire:panel-footer />

    <p>Adjacent components, one wrapped under the wrong key:</p>
    <template wire:key="left">
        <livewire:left-pane wire:key="left" />
    </template>
    <template wire:key="right">
        <livewire:right-pane wire:key="wrong" />
    </template>
</div>
