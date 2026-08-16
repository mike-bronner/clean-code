<?php

namespace App\Support;

// Silent: with a namespace declared, namespace\array_filter() names
// App\Support\array_filter() — a different symbol from the native function.
$active = namespace\array_filter($rows);

// Silent for the same reason, and the reason it is here twice: the walk back
// to the declaration in force passes the namespace\ above, which is the same
// keyword used as an operator rather than a declaration. Reading it as one
// would strand this second call outside any namespace.
$totals = namespace\array_reduce($rows, 'sum', 0);

// Flagged: an unqualified call still falls back to the native function, so
// declaring a namespace must not switch the sniff off wholesale. Pairing the
// two in one file is what makes the silence above a verdict about the
// namespace\ prefix rather than about the file.
$names = array_map('trim', $rows);
