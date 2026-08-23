<?php

// The two tokens PHP 8.5 adds, one chain each, on the same terms as
// group-preceders.php: every admission gets a line, and removing the admission
// silences exactly its own line.
//
// They are kept out of group-preceders.php because `(void)` and `|>` are parse
// errors before PHP 8.5, and that fixture has to keep tokenising the same way
// on every supported version. The test that drives this file skips below 8.5
// for the same reason.

// T_VOID_CAST. `(void)` is a cast, so the parenthesis after it groups the
// subject of the chain rather than writing a construct's own subject.
(void) ($book)->author->name;

// T_PIPE. `|>` takes an expression on the right, so the parenthesis after it
// groups exactly as `.` or `&&` does.
$piped = $value |> ($book)->author->name;
