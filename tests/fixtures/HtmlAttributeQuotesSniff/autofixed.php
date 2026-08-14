<?php

$single = "<a href=\"/home\">Home</a>";
$multi = "<input type=\"text\" name=\"email\">";

// Combined token: one apostrophe attribute and one already-compliant
// double-quoted attribute in the *same* tag. This drives the per-tag callback
// wrapping the per-attribute callback — the path most likely to double-escape
// or drop the sibling `class="wrap"` — so it has to reach the fixer, not just
// the counter.
$mixed = "<div id=\"main\" class=\"wrap\">x</div>";

// A `>` inside a quoted attribute value must not end the tag span, or
// `class='y'` after it is never seen and the string comes back unchanged.
$greaterThanInValue = "<a data-x=\"a>b\" class=\"y\">link</a>";

// Single-quoted PHP string: the attribute apostrophes are themselves escaped,
// and the replacement double quotes need no escaping in this context.
$singleQuotedPhp = '<a class="card">link</a>';

// A multi-line double-quoted string tokenizes one token per physical line; the
// apostrophe attribute sits on a continuation line, which no token opens with.
$multiline = "<ul>
    <li class=\"item\">text</li>
</ul>";

// Not fixable: the value already carries a double quote, so re-delimiting it
// is ambiguous. Reported for manual conversion instead.
$nonFixable = "<a title='say \"hi\"'>x</a>";
