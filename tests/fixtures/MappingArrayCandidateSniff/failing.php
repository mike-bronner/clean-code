<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Conditionals.MappingArrayCandidate.
 *
 * Every chain here is a mechanical mapping-array candidate: one variable, one
 * scalar literal per branch, one value-producing statement per body. Each is
 * reported once, at its leading `if`.
 *
 * Line numbers are asserted exactly in tests/Standards/MappingArrayCandidateTest.php.
 */

final class Labels
{
    // 20 — the minimum shape: if + elseif + else, three branches, all return.
    public function short(string $code): string
    {
        if ($code === 'a') {
            return 'Alpha';
        } elseif ($code === 'b') {
            return 'Bravo';
        } else {
            return 'Unknown';
        }
    }

    // 32 — no trailing else; the three conditions alone reach the minimum.
    public function withoutDefault(int $level): string
    {
        if ($level === 1) {
            return 'low';
        } elseif ($level === 2) {
            return 'medium';
        } elseif ($level === 3) {
            return 'high';
        }

        return 'unknown';
    }

    // 46 — the assignment form: one identical target across every branch.
    public function assigned(string $state): string
    {
        if ($state === 'new') {
            $label = 'New';
        } elseif ($state === 'open') {
            $label = 'Open';
        } else {
            $label = 'Closed';
        }

        return $label;
    }

    // 60 — Yoda order, and loose `==`. Same shape, operands reversed.
    public function yoda(int $status): string
    {
        if (1 == $status) {
            return 'one';
        } elseif (2 == $status) {
            return 'two';
        } elseif (3 == $status) {
            return 'three';
        }

        return 'many';
    }

    // 75 — four branches, and branch values that are constants and array
    // literals rather than inline scalars. Neither can do work, so both qualify.
    public function richValues(string $kind): mixed
    {
        if ($kind === 'list') {
            return [];
        } elseif ($kind === 'flag') {
            return self::DEFAULT_FLAG;
        } elseif ($kind === 'pair') {
            return ['left' => 1, 'right' => -1];
        } else {
            return $this->fallback;
        }
    }

    public const DEFAULT_FLAG = false;

    public mixed $fallback = null;
}
