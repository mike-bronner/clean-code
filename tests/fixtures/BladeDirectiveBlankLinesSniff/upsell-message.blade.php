<flux:callout icon="sparkles">
    <flux:callout.heading>Unlock more</flux:callout.heading>
    <flux:callout.text>
        @auth
            @if ($user->isFree())

                Upgrade to read every translation.

            @endif
        @endauth
        @guest
            Create a free account to keep reading.
        @endguest
    </flux:callout.text>
</flux:callout>
