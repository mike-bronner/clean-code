<?php

// Lines 6-7 keep the assertion honest: the file still has to report the
// access that precedes the truncation, so a sniff that fell silent on the
// whole file would fail rather than pass.
$method = new ReflectionMethod(Calculator::class, 'applyDiscount');
$narrowed = $reflection->getMethod('applyDiscount');

// PHP_CodeSniffer tokenizes files mid-edit, so a chain can end at the operator
// with no member after it at all. At the end of the file there is genuinely no
// following token, which is the only way to reach the sniff's `false` guard.
$truncated = $reflection->
