<?php

// Compliant markup in both PHP string contexts.
$doubleQuoted = "<a class=\"card\">link</a>";
$singleQuotedPhp = '<a class="ok">fine</a>';
$noAttributes = "<br>";

// Near misses the sniff must stay silent on. An apostrophe only counts when it
// delimits an attribute *inside* a tag span, so prose, SQL, and body text keep
// theirs verbatim.
$notHtml = "WHERE name = 'admin'";
$proseInsideTags = "<p>Query: name = 'admin' here</p>";
$attrLikeBody = "<div>total = 'x'</div>";

// A `>` inside a quoted attribute value is content, not the tag terminator.
// Every attribute after it is already compliant here, so the sniff stays quiet.
$greaterThanInValue = "<a data-x=\"a>b\" class=\"y\">link</a>";
