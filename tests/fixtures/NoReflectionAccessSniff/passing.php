<?php

// Compliant: the test drives the class through its public API only.
$calculator = new Calculator();
$total = $calculator->total([1, 2, 3]);

// new ReflectionClass() is deliberately not in $reflectionClasses. Reading a
// class's metadata is legitimate (data providers, framework plumbing); it only
// becomes a violation once getMethod()/getProperty() narrows it to one member.
$reflection = new ReflectionClass(Calculator::class);
$fullyQualified = new \ReflectionClass(Calculator::class);
$named = new ReflectionNamedType();

// Reflection members outside $reflectionMembers stay silent: none of them
// reaches a *named* non-public member.
$name = $reflection->getName();
$short = $reflection->getShortName();
$methods = $reflection->getMethods();
$parent = $reflection->getParentClass();

// Names that merely start with, extend, or paraphrase a configured name.
$label = $service->getMethodName();
$flag = $service->invoker();
$other = $service->setAccessibleLabel();

// A property read of a configured name reaches nothing on its own — without
// the "next token is an open parenthesis" check these read as calls.
$callable = $reflection->invoke;
$accessor = $reflection::setAccessible;

// A dynamic member name is unknowable at token level, so every spelling of one
// is silent: `{` for the two braced forms, T_VARIABLE for the plain one.
$member = 'getMethod';
$dynamic = $reflection->{$member}();
$braced = $reflection->{'getProperty'}();
$variable = $reflection->$member();

// Class names that merely contain a configured one.
$factory = new ReflectionMethodFactory();
$builder = new MyReflectionProperty();

// A class *declaring* members that share the configured names must not flag
// itself: a declaration carries no ->, ?->, :: or new before the name.
class Job
{
    public function invoke(): void
    {
    }

    public function setAccessible(bool $accessible): void
    {
    }

    public function getMethod(): string
    {
        return 'run';
    }
}
