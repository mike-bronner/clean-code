<?php

/**
 * The threshold, from one below it to one above.
 *
 * PHPMD compares the metric with `>=` on the `minimum` path, so `Parents6` is
 * a violation and `Parents5` is not. Every line here is pinned against a live
 * PHPMD 2.15.0 run, because phpmd.org's prose and #112's acceptance criteria
 * both describe the opposite boundary.
 */

declare(strict_types=1);

namespace Boundaries;

class Parents0
{
}

class Parents1 extends Parents0
{
}

class Parents2 extends Parents1
{
}

class Parents3 extends Parents2
{
}

class Parents4 extends Parents3
{
}

class Parents5 extends Parents4
{
}

class Parents6 extends Parents5
{
}

class Parents7 extends Parents6
{
}
