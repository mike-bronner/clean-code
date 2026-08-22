<?php

namespace Tests\Feature;

// Source the sniff cannot read to the end of. Both shapes below reach a guard
// that no well-formed file can, and each one must stay silent rather than
// report on a token region that was never established.

// An argument list that is never closed. PHP_CodeSniffer leaves the opening
// parenthesis with no `parenthesis_closer`, so there is no argument region to
// look for a URL in — even though the URL is written right there.
$body = file_get_contents('https://api.example.test/orders';

// A `new` with nothing after it: the file ends in whitespace, so there is no
// token to read a class name from.
$client = new
