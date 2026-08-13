<?php

// Framework plumbing and data-provider metadata are the legitimate Reflection
// uses the standard's doc names, and they take the ordinary PHPCS per-line
// suppression rather than a sniff-specific escape hatch.

// phpcs:ignore CleanCode.Testing.NoReflectionAccess.Found
$suppressed = new ReflectionMethod(Calculator::class, 'applyDiscount');

$disabled = $reflection->getMethod('applyDiscount'); // phpcs:ignore CleanCode.Testing.NoReflectionAccess.Found

// Keeps the assertion honest: the same constructs still report when they are
// not suppressed, so a sniff that had fallen silent altogether would fail here
// rather than pass.
$reported = new ReflectionProperty(Calculator::class, 'rate');
$alsoReported = $reflection->setAccessible(true);
