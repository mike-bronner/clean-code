<div class="rows">
    <button wire:click="loadMore">More</button>

    @foreach ($rows as $row)
        <livewire:row-item :row="$row" />
        @include('rows.partials.close-loop')
</div>
