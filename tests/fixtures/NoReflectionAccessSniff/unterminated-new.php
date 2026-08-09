<?php

// Lines 4-5 keep the assertion honest, as in unterminated-member.php.
$method = new ReflectionMethod(Calculator::class, 'applyDiscount');
$narrowed = $reflection->getMethod('applyDiscount');

// The instantiation half of the same truncation: `new` with no class name
// after it, at the end of the file.
$truncated = new
