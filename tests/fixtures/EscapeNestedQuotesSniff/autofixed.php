<?php

// Fixable: re-delimiting to double quotes cannot change the meaning.
$simple = "He said \"hi\" to me";
$attribute = "<span title=\"tip\">x</span>";

// Fixable: a `$` is escaped on the way out, so the result carries the dollar
// as text rather than interpolating it.
$withVariable = "echo \"\$value\" here";

// Fixable: the single-quoted body resolves `\\` and `\'` and nothing else, so
// those come back to the characters they stand for before the whole thing is
// escaped for a double-quoted body. The value is unchanged either way.
$withEscape = "path \"C:\\temp\"";

// Fixable: a brace only opens interpolation when a `$` follows it, and the `$`
// is escaped, so `{$` becomes `{\$` and stays text.
$withBrace = "render \"{name}\" now";

// Fixable, and load-bearing: an uppercase binary-string prefix stays inside the
// token's content, so a sniff reading the first character as the delimiter sees
// `B` and skips the literal entirely. The prefix is carried over by the fixer.
$binaryPrefixed = B"He said \"hi\" to me";

// Fixable, and the reason the prefix is safe to carry: this fixer's output
// never interpolates, because the `$` is escaped. A prefixed *interpolating*
// literal (`B"…$value…"`) is what PHP_CodeSniffer cannot read — it types the
// opener T_NONE and swallows the source after it — and no output of this fixer
// is one. Verified by tokenizing the produced form and confirming the code
// after it still reaches the sniffs.
$binaryWithVariable = B"echo \"\$value\" here";
