<?php

// The compliant form: markup in a HereDoc. These tokenize as T_HEREDOC, never
// as the encapsed-string tokens the sniff registers on.
$heredoc = <<<HTML
<div class="card">
    <p>Content</p>
</div>
HTML;

$nested = <<<HTML
<section>
    <article>
        <header><h1>Title</h1></header>
        <p>Body with <a href="/x">a link</a> inside.</p>
    </article>
</section>
HTML;

// A NowDoc body is literal, and equally out of the sniff's reach.
$nowdoc = <<<'HTML'
<footer><small>&copy; 2026</small></footer>
HTML;

// A quoted string literal nested inside a HereDoc's interpolation. The whole
// HereDoc body is one T_HEREDOC token, inner expression and all, so the
// `'<em>x</em>'` argument never reaches the sniff as a string literal.
$interpolatedCall = <<<HTML
<div>{$renderer->wrap('<em>x</em>')}</div>
HTML;

// Near misses: text that looks tag-shaped but is not an HTML element.
$plainText = "Just a sentence, no markup.";
$comparison = 'a < b and c > d';
$generic = 'List<int>';
$shellRedirect = "run script.sh <input.txt >output.txt";
$cInclude = 'see <time.h> for details';
$unclosedAngle = 'a <div without a close';
