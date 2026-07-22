<?php

declare(strict_types=1);

$test = true;

if (!$test) {
    echo 'no space after';
}

if ( ! $test) {
    echo 'space before after open paren';
}

if (!  $test) {
    echo 'too much space after';
}

$values = [ ! $test];
