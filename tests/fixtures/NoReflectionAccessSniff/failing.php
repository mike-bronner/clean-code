<?php

$method = new ReflectionMethod(Calculator::class, 'applyDiscount');
$property = new ReflectionProperty(Calculator::class, 'rate');
$qualified = new \ReflectionMethod(Calculator::class, 'applyDiscount');
$cased = new REFLECTIONPROPERTY(Calculator::class, 'rate');
$method->setAccessible(true);
$property->setAccessible(false);
$result = $method->invoke($calculator, 100);
$args = $method->invokeArgs($calculator, [100]);
$narrowed = $reflection->getMethod('applyDiscount');
$field = $reflection->getProperty('rate');
$nullsafe = $reflection?->getMethod('applyDiscount');
$static = ReflectionClass::getMethod('applyDiscount');
$chained = (new ReflectionClass(Calculator::class))->getProperty('rate');
$upper = $reflection->GETMETHOD('applyDiscount');
$callable = $method->invoke(...);

class CalculatorTest
{
    public function testDiscount(): void
    {
        $method = new ReflectionMethod(Calculator::class, 'applyDiscount');
        $method->setAccessible(true);
    }
}
