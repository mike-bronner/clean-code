<?php

// A multi-line quoted string whose body contains a line equal to the fixer's
// closing marker (TEXT). The violation is still reported, but the auto-fix is
// withheld — emitting the HEREDOC would place the marker in the body and PHP
// would close the doc early, producing broken code.
$doc = "first line
TEXT
last line";
