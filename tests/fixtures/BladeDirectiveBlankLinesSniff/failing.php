<flux:callout>
    <flux:callout.text>
        @guest
            <a href="/login">Sign in</a>
        @endguest
    </flux:callout.text>
    <p>Intro.</p>
    @if ($showLink)
        <a href="/more">More</a>
    @endif
    <p>Outro.</p>
</flux:callout>
@auth
    <p>Welcome back.</p>
@endauth
@foreach ($items as $item)
    @unless ($item->hidden)
        <li>{{ $item }}</li>
    @endunless
@endforeach
@hasSection('sidebar')
    @yield('sidebar')
@endif
@section('content')
    <main>Body</main>
@show
@pushOnce('scripts')
    <script src="/app.js"></script>
@endPushOnce
@php
    $count = 1;
@endphp
<footer>Done</footer>

<?php
