<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Debug\DisallowDebugFunctionsSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;

const BOOST_GUIDELINE_DIRECTORY = 'resources/boost/guidelines';

const BOOST_REVIEW_ONLY = 'Enforced by code review only. No sniff checks this standard.';

$standardSlugs = static function (string $directory): array {
    $slugs = array_map(
        static fn (string $path): string => basename($path, '.md'),
        glob(cleanCodeRoot() . '/' . $directory . '/*.md')
    );

    sort($slugs);

    return $slugs;
};

$guidelineEnforcement = static function (string $slug): array {
    $path = cleanCodeRoot() . '/' . BOOST_GUIDELINE_DIRECTORY . '/' . $slug . '.md';
    $guideline = (string) file_get_contents($path);

    preg_match_all('/^\| `([\w.]+)` \| (yes|no) \|$/m', $guideline, $rows);

    return array_combine($rows[1], $rows[2]);
};

$sniffCarriesFixer = static function (object $sniff): bool {
    $class = new ReflectionClass($sniff);

    while ($class !== false) {
        $source = (string) file_get_contents((string) $class->getFileName());

        if (preg_match('/addFixable(Error|Warning)|->fixer->/', $source) === 1) {
            return true;
        }

        $class = $class->getParentClass();
    }

    return false;
};

it('ships a guideline naming every loaded sniff', function () use ($standardSlugs, $guidelineEnforcement): void {
    [, $ruleset] = buildRuleset();
    $named = [];

    foreach ($standardSlugs(BOOST_GUIDELINE_DIRECTORY) as $slug) {
        $named += $guidelineEnforcement($slug);
    }

    $unnamed = array_values(array_diff(array_keys($ruleset->sniffCodes), array_keys($named)));

    expect($ruleset->sniffCodes)->not->toBeEmpty()
        ->and($unnamed)->toBe([]);
});

it('shapes every guideline as rule, examples, sniffs', function (string $slug) use ($guidelineEnforcement): void {
    $path = cleanCodeRoot() . '/' . BOOST_GUIDELINE_DIRECTORY . '/' . $slug . '.md';
    $guideline = (string) file_get_contents($path);
    $enforcement = $guidelineEnforcement($slug);

    expect($guideline)->toMatch('/\A# \S[^\n]*\n\n\S/')
        ->and($guideline)->toContain("\n## Compliant\n\n```")
        ->and($guideline)->toContain("\n## Non-compliant\n\n```")
        ->and($guideline)->toContain("\n## Enforcement\n")
        ->and($enforcement === [])->toBe(str_contains($guideline, BOOST_REVIEW_ONLY));
})->with($standardSlugs(BOOST_GUIDELINE_DIRECTORY));

it('points every sniff message at a guideline that exists', function (): void {
    $sources = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(cleanCodeRoot() . '/CleanCode', FilesystemIterator::SKIP_DOTS)
    );
    $targets = [];

    foreach ($sources as $source) {
        $text = (string) file_get_contents($source->getPathname());
        $contents = (string) preg_replace("/'\\s*\\.\\s*'/", '', $text);
        preg_match_all('#' . BOOST_GUIDELINE_DIRECTORY . '/[\w-]+\.md#', $contents, $paths);
        $targets = [...$targets, ...$paths[0]];
    }

    $missing = array_values(array_filter(
        array_unique($targets),
        static fn (string $target): bool => is_file(cleanCodeRoot() . '/' . $target) === false
    ));

    expect($targets)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

it('names only loaded sniffs, with their real fixability', function () use (
    $standardSlugs,
    $guidelineEnforcement,
    $sniffCarriesFixer,
): void {
    [, $ruleset] = buildRuleset();
    $claims = [];
    $actual = [];

    foreach ($standardSlugs(BOOST_GUIDELINE_DIRECTORY) as $slug) {
        foreach ($guidelineEnforcement($slug) as $code => $fixable) {
            $sniff = $ruleset->sniffs[$ruleset->sniffCodes[$code] ?? ''] ?? null;
            $claims[$slug . ' ' . $code] = $fixable;
            $actual[$slug . ' ' . $code] = $sniff === null ? 'not loaded' : ($sniffCarriesFixer($sniff) ? 'yes' : 'no');
        }
    }

    expect($claims)->not->toBeEmpty()
        ->and($actual)->toBe($claims);
});

it('lists every name its sniff checks', function (string $slug, Closure $names): void {
    $path = cleanCodeRoot() . '/' . BOOST_GUIDELINE_DIRECTORY . '/' . $slug . '.md';
    [$rule] = explode("\n## Compliant\n", (string) file_get_contents($path));
    $rule = (string) preg_replace('/\s+/', ' ', $rule);

    $unlisted = array_values(array_filter(
        $names(),
        static fn (string $name): bool => str_contains($rule, "`{$name}`") === false
    ));

    expect($names())->not->toBeEmpty()
        ->and($unlisted)->toBe([]);
})->with([
    'debug functions' => [
        'design-developmentcodefragment',
        static fn (): array => array_map(
                static fn (string $function): string => "{$function}()",
                (new ReflectionClassConstant(DisallowDebugFunctionsSniff::class, 'DEBUG_FUNCTIONS'))->getValue()
            ),
    ],
    'assignment conditions' => [
        'cleancode-ifstatementassignment',
        static fn (): array => array_map(
                static fn (int|string $token): string => strtolower(substr(token_name((int) $token), 2)),
                (new AssignmentInConditionSniff())->register()
            ),
    ],
]);

it('reads a sniff table row and ignores prose that names a sniff', function () use ($guidelineEnforcement): void {
    $slug = 'conditionals-no-else-or-elseif';

    expect($guidelineEnforcement($slug))->toBe(['CleanCode.Conditionals.DisallowElse' => 'yes']);
});
