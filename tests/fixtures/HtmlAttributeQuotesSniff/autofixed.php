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

// An uppercase binary-string prefix stays inside the token's content, so
// reading the first character as the delimiter reports `B`, and this
// single-quoted literal was scanned as if it were double-quoted — its escaped
// attribute apostrophes never matched and the violation went unreported.
$binaryPrefixed = B'<a class="card">link</a>';

// The delimiter of a multi-line string used to be resolved by asking each
// fragment in turn and taking the first answer. A continuation line whose prose
// happens to open like a literal — `B'day` reads as a binary-string prefix plus
// an apostrophe delimiter — answered for the whole string, so this
// double-quoted literal was scanned with the single-quoted escaping convention
// and the real violation two lines down went unreported. Only the opener holds
// the delimiter, so only the opener is asked.
$delimiterCollision = "greetings
B'day wishes to you
<a class=\"card\">link</a>";

// Not fixable: in a double-quoted PHP string `\' ` is not an escape sequence, so
// the captured value ends on a backslash. Injecting the `\"` closer straight
// after it would pair the two backslashes and leave a bare quote that ends the
// string early — `"<a class=\"card\\">link</a>"` no longer parses. Reported for
// manual conversion instead.
$backslashInValue = "<a class='card\'>link</a>";
