<?php

// Structure, not length: a query belongs in a HEREDOC at any size, because that
// is what an editor highlights as SQL.
$sql = "SELECT id, name"
    . " FROM users"
    . " WHERE active = 1";

// Positive: prose wrapped to stay inside the line limit. Two lines is the shape
// the 100-character limit and the leading-operator rule produce between them,
// so flagging it would put three rules in contradiction.
$message = "Dear customer, "
    . "your order has shipped.";

// Positive: three lines is still within maximumLines.
$threeLines = "Dear customer, your order has shipped and should arrive "
    . "within the next two working days. Track it from your account "
    . "page at any time.";

// Violation: four lines is a block of text, not a line-width concession.
$fourLines = "Dear customer, your order has shipped and should arrive "
    . "within the next two working days. Track it from your account "
    . "page at any time, or reply to this message and a member of the "
    . "team will look it up for you.";

// Structure again: markdown at any length.
$markdown = "## Heading"
    . "\n- first item";

// Two independent multi-line string-concat chains in ONE statement (both
// branches of a ternary). Ternary '?'/':' are not statement boundaries, so
// both chains must be flagged, not just the first. Both are SQL, so both
// report on structure rather than on length.
$query = $isPostgres
    ? "SELECT id, name"
        . " FROM pg_users"
    : "SELECT id, name"
        . " FROM users";
