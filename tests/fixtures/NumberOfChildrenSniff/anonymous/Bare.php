<?php

declare(strict_types=1);

/**
 * One class in the global namespace per trait the anonymous-class fixtures
 * beside it use. Nothing extends any of them, so
 * every one has zero children and this file is silent at any threshold.
 *
 * They exist to be the wrong answer. A trait `use` inside an anonymous class
 * body, misread as a namespace import because the body was popped before it was
 * ever opened, binds the trait's short name to the *global* class of that name
 * — and a following `extends` of that short name is then counted here, against
 * a class with no children at all.
 */
class Ghost
{
}

class Phantom
{
}

class Specter
{
}

class Wraith
{
}

class Revenant
{
}

class Shade
{
}

class Banshee
{
}
