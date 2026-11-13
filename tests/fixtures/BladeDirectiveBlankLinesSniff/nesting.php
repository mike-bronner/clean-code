<flux:callout.text>

    @auth
        @if ($user->isFree())

            Upgrade to read every translation.

        @endif
    @endauth
    @guest
        @if ($trial) Trial @endif
        @foreach ($plans as $plan)
            <li>{{ $plan }}</li>
        @endforeach
        <div>@if ($promo)
            @unless ($hidden)
                Promo
            @endunless
        @endif</div>
    @endguest

</flux:callout.text>

<?php
