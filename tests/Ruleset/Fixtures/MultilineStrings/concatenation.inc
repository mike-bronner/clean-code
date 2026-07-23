<?php

$sql = "SELECT id, name"
    . " FROM users"
    . " WHERE active = 1";

$message = "Dear customer, "
    . "your order has shipped.";

// Two independent multi-line string-concat chains in ONE statement (both
// branches of a ternary). Ternary '?'/':' are not statement boundaries, so
// both chains must be flagged, not just the first.
$query = $isPostgres
    ? "SELECT id, name"
        . " FROM pg_users"
    : "SELECT id, name"
        . " FROM users";
