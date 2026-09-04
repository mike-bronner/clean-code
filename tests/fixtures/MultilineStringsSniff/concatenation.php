<?php

// Lines of text, never lines of source. The distinction is the whole fixture:
// a sentence wrapped over four source lines is one line of text, and a HEREDOC
// cannot express it — the body would sit on one line and break the 120-character
// limit, or wrap and put real newlines into the value.

// Positive: prose wrapped to stay inside the line limit. Four source lines, one
// line of text. This is the shape the 100-character limit and the leading-operator
// rule produce between them, so flagging it would put three rules in contradiction
// and report something no fix can satisfy.
$wrapped = "Dear customer, your order has shipped and should arrive within the "
    . "next two working days. Track it from your account page at any time, or "
    . "reply to this message and a member of the team will look it up for you "
    . "and confirm where it is.";

// Positive: three lines of text is within maximumLines, whatever the source
// layout is. The `\n` escapes are what make them lines.
$threeLines = "first line\n"
    . "second line\n"
    . "third line";

// Violation: four lines of text is a block, and a HEREDOC reads as the block it
// is — with the newlines the value already has, so the fix costs nothing.
$fourLines = "first line\n"
    . "second line\n"
    . "third line\n"
    . "fourth line";

// Violation, and the shape the source-counting rule missed entirely: four lines
// of text on a single source line. Length of source has nothing to do with it.
$oneSourceLine = "first line\nsecond line\n" . "third line\nfourth line";

// Two independent multi-line string-concat chains in ONE statement (both
// branches of a ternary). Ternary '?'/':' are not statement boundaries, so both
// chains must be flagged, not just the first.
$branches = $isPostgres
    ? "first line\n"
        . "second line\n"
        . "third line\n"
        . "fourth line"
    : "alpha\n"
        . "beta\n"
        . "gamma\n"
        . "delta";

// Structure is somebody else's concern. CleanCode.Strings.RequireHeredocForStructuredText
// owns SQL and markdown at any length, so this sniff must stay silent on them
// or every such finding is reported twice.
$sql = "SELECT id, name"
    . " FROM users"
    . " WHERE active = 1";
$markdown = "## Heading\n"
    . "- first item\n"
    . "- second item\n"
    . "- third item";
