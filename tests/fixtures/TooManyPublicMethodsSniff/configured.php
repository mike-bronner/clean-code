<?php

declare(strict_types=1);

namespace App\Fixtures;

// The fixture the `maxmethods` and `ignorepattern` property tests are measured
// against. Method bodies are empty and on one line for the reason given in
// passing.php.

// The anchor: eleven plain public methods, reported under every configuration
// the tests apply. Without it, a property value that silenced the sniff
// outright would be indistinguishable from a working exemption.
class AlwaysReported
{
    public function one(): void {}
    public function two(): void {}
    public function three(): void {}
    public function four(): void {}
    public function five(): void {}
    public function six(): void {}
    public function seven(): void {}
    public function eight(): void {}
    public function nine(): void {}
    public function ten(): void {}
    public function eleven(): void {}
}

// Fourteen public methods, four of them exempted by the default ignore pattern
// (get, set, is, with), leaving exactly ten counted — silent by default, and
// one alternative away from being reported. Narrowing the pattern to
// phpmd.org's documented `(^(set|get))i` exempts only two of the four and
// reports this class at twelve; emptying the pattern reports it at fourteen.
class ExemptedToTheBoundary
{
    public function one(): void {}
    public function two(): void {}
    public function three(): void {}
    public function four(): void {}
    public function five(): void {}
    public function six(): void {}
    public function seven(): void {}
    public function eight(): void {}
    public function nine(): void {}
    public function ten(): void {}
    public function getName(): string { return ''; }
    public function setName(string $name): void {}
    public function isReady(): bool { return true; }
    public function withTimeout(int $seconds): static { return $this; }
}

// Four public methods: silent at the default threshold of ten, reported once
// `maxmethods` drops to three. Nothing about the ignore pattern touches it, so
// only a threshold change can move it.
class FourPublicMethods
{
    public function one(): void {}
    public function two(): void {}
    public function three(): void {}
    public function four(): void {}
}
