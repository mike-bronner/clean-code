<?php

// A `use function` import binds exactly one name, and an aliased import binds
// the alias rather than the source. passing.php proves an imported debug name
// goes quiet; this proves the import does not take the rest of the file's debug
// calls quiet with it — the source name behind an alias included.

use function Acme\Support\ray;
use function Acme\Support\dump as render;

ray($value);

var_dump($value);
dump($value);
print_r($value);
