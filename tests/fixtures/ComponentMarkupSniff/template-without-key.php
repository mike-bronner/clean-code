<div class="panes">
    <button wire:click="refresh">Refresh</button>

    <template>
        <livewire:left-pane wire:key="left" />
    </template>
    <template>
        <livewire:right-pane wire:key="right" />
    </template>
</div>
