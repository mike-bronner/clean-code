<?php

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

$plainText = "Just a sentence, no markup.";
$comparison = 'a < b and c > d';
$generic = 'List<int>';
$shellRedirect = "run script.sh <input.txt >output.txt";
$cInclude = 'see <time.h> for details';
