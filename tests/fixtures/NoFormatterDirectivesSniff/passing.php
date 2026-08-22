<?php

// Positive: an ordinary line comment describing the code below it.
$total = array_sum($amounts);

# Positive: the hash spelling of the same thing.
$label = strtoupper($name);

/* Positive: a block comment written on one line. */
$rows = $records;

/*
 * Positive: a block comment over several physical lines, which PHPCS splits
 * into one token per line. Nothing here steers a formatter.
 */
$flag = true;

/**
 * Positive: a doc comment carrying tags and prose.
 *
 * @param string $name
 *
 * @return string
 */
function greet(string $name): string
{
    return $name;
}

// Positive: prose about formatters is not a directive. The words formatter
// and off appear here, apart, and prettier appears without its marker.
$editor = 'phpstorm';

// Positive: @formatter on its own steers nothing — the marker is the whole
// word, colon and state included.
$partial = 1;

// Positive: near-miss spellings of the two shipped markers.
// formatter:off
// prettier ignore
$spellings = [];

// Positive: a marker quoted in a string literal is data, not a directive, and
// the sniff reads comment tokens only.
$literal = '@formatter:off';
$mixed = "the @formatter:on marker inside a double-quoted string";

// Positive: the same inside a nowdoc body, which the tokenizer never hands
// over as a comment however the lines are spelled.
$body = <<<'EOT'
// @formatter:off
prettier-ignore
EOT;

// Positive under the shipped list, flagged once a ruleset configures it —
// custom-directives.xml is the other half of the directives property.
// @fmt:off
$configured = 2;
