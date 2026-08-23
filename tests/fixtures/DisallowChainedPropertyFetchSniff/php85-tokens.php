<?php

// The two tokens PHP 8.5 adds, each in front of a grouping parenthesis holding
// the root of a chain. Neither line can live in group-preceders.php: `|>` is a
// parse error before 8.5, and `(void) (...)` parses on 8.1 and 8.4 as a call to
// the function named by the constant `void`, which is a different shape
// entirely and would report differently. So this file is read only by the test
// that PHP 8.5 gates, and every line in it is a chain that must be reported.
(void) ($book)->author->name;
$piped = $book |> ($handler)->resolver->fn;
