<?php

declare(strict_types=1);

it('needs review only when its flag says so', function (Review $review, bool $expected): void {
    expect($review->needsReview())->toBe($expected);
})->with([[new Review, true]]);

test('publishes only when allowed', function (bool $allowed) {
    expect($allowed)->toBeBool();
});

it('reads an arrow function body', fn (bool $expected) => expect($expected)->toBeTrue());

test('reads a static closure body', static function ($expected = false): void {
    expect($expected)->toBeFalse();
});

\it('reads a qualified call', function (bool $expected): void {
    expect($expected)->toBeTrue();
});

describe('a group', function (bool $flag): void {
    expect($flag)->toBeTrue();
});

$mapped = array_map(fn (bool $value): bool => $value, [true]);

$this->it('is a method named it', function (bool $flag): void {
    expect($flag)->toBeTrue();
});

it('nests a closure in its body', function (): void {
    $inner = fn (bool $flag): bool => $flag;

    expect($inner(true))->toBeTrue();
});

it('nests an arrow function in its body', fn () => [1, fn (bool $flag): bool => $flag]);

function helper(bool $flag): void
{
    error_log((string) $flag);
}

class Review
{
    public function needsReview(bool $strict = false): bool
    {
        return $strict;
    }
}

it(function (bool $flag): void {
    expect($flag)->toBeTrue();
}, 'closure first');

it(sprintf('%s', 'comma in the description'), function (bool $flag): void {
    expect($flag)->toBeTrue();
});

IT('upper-case call', function (bool $flag): void {
    expect($flag)->toBeTrue();
});
