@auth
    <p>Welcome back.</p>
@endauth

<flux:callout>
    <flux:callout.text>

        @guest
            <a href="/login">Sign in</a>
        @endguest

    </flux:callout.text>

    @if ($showLink)

        @foreach ($links as $link)
            <a href="{{ $link }}">{{ $link }}</a>
        @endforeach

    @else
        <span>Nothing here.</span>
    @endif

</flux:callout>

<flux:callout.text>

    @auth
        @if ($user->isFree())

            Upgrade to read every translation.

        @endif
    @endauth

    @guest
        @foreach ($plans as $plan)
            <li>{{ $plan }}</li>
        @endforeach
    @endguest

</flux:callout.text>

<button
    @click="open = true"
    class="@if ($active) active @endif"
>Contact support@example.com</button>
<p>Write @@if to print a directive.</p>
@@if ($escaped)
@@endif
<p>{{ $flag ? '@if' : '@endif' }}</p>
{!! '@foreach' !!}
{{-- @unless ($hidden)
@endunless --}}

@php
    $label = '@if';
    $closing = '@endif';
@endphp

@verbatim
    <div>@if (shown) {{ raw }}</div>
    @endif
@endverbatim

@section('title', 'Reader')
<?php echo '@isset'; ?>
<?php echo '@endisset'; ?>
<p>Orphan @endauth text.</p>

<?php
