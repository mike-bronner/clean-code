<?php

// @formatter:off
$a = 1;
// @formatter:on

# prettier-ignore
$b = 2;

/* @formatter:off */
$c = 3;

/*
 * @formatter:on
 * ordinary prose on its own line
 */
$d = 4;

/** @formatter:off */
$e = 5;

/**
 * @formatter:on
 * prettier-ignore
 */
$f = 6;

/** Prose naming @formatter:off in the middle of a sentence. */
$g = 7;

/**
 * @param string $value prettier-ignore
 */
function tagged(string $value): string
{
    return $value;
}

$h = 8; // @FORMATTER:OFF

// @formatter:off prettier-ignore
$i = 9;
