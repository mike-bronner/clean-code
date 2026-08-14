<?php

// Line 5 warns under the shipped defaults and line 6 does not; once
// $reflectionClasses is replaced by ReflectionClass the verdicts swap.
$shipped = new ReflectionMethod(Calculator::class, 'applyDiscount');
$retuned = new ReflectionClass(Calculator::class);

// Line 10 warns under the shipped defaults and line 11 does not; once
// $reflectionMembers is replaced by getName the verdicts swap.
$shippedMember = $reflection->getMethod('applyDiscount');
$retunedMember = $reflection->getName();
