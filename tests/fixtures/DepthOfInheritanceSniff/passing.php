<?php

/**
 * Compliant code, and the near-miss shapes the sniff has to stay silent on.
 *
 * The chain at the top stops one parent short of the default threshold of 6,
 * so a sniff that counted a link twice — or that used `>` where PHPMD uses
 * `>=` in the other direction — would speak up here.
 *
 * Everything below it must contribute nothing however deep it is nested. An
 * interface, a trait and an enum are not classes and are never checked at all;
 * an anonymous class is not reported in its own right even when it extends the
 * deepest class in the file; an `implements` clause and a `use` of a trait are
 * not inheritance. `Deep5::class` is a constant expression, and the `class`
 * keyword in it must not be read as a declaration. The closure's `use ($x)`
 * captures a variable rather than importing a name, and `use function` /
 * `use const` bind no class.
 */

declare(strict_types=1);

namespace Pass;

use function array_map;
use const PHP_EOL;

class Deep0
{
}

class Deep1 extends Deep0
{
}

class Deep2 extends Deep1
{
}

class Deep3 extends Deep2
{
}

class Deep4 extends Deep3
{
}

// Five parents — one below the threshold.
class Deep5 extends Deep4
{
}

// An unseen parent weighs 2, which is still four short of the threshold.
class ExtendsVendor extends \Vendor\Framework\BaseController
{
}

// An interface hierarchy is deeper than the threshold and is never reported.
interface Contract0
{
}

interface Contract1 extends Contract0
{
}

interface Contract2 extends Contract1
{
}

interface Contract3 extends Contract2
{
}

interface Contract4 extends Contract3
{
}

interface Contract5 extends Contract4
{
}

interface Contract6 extends Contract5
{
}

// Implementing that whole hierarchy is not inheriting from it.
class Implementor implements Contract6
{
}

trait Helper
{
}

// A trait `use` inside a class body is not an import and not a parent.
class UsesTrait extends Deep0
{
    use Helper;
}

enum Suit: string
{
    case Hearts = 'H';
}

// An anonymous class is not reported in its own right, however deep it sits.
$anonymous = new class extends Deep5 {
};

// A closure's captured variables are not an import.
$capture = static function () use ($anonymous): string {
    return Deep5::class . PHP_EOL;
};

$mapped = array_map($capture, []);
