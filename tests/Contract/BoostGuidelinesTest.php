<?php

declare(strict_types=1);

const BOOST_GUIDELINE_DIRECTORY = 'resources/boost/guidelines';

const BOOST_STANDARD_URL = 'https://github.com/mike-bronner/clean-code/blob/main/docs/standards/';

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

it('ships one guideline per documented standard', function () use ($standardSlugs): void {
    expect($standardSlugs(BOOST_GUIDELINE_DIRECTORY))->toBe($standardSlugs('docs/standards'))
        ->and($standardSlugs('docs/standards'))->toHaveCount(73);
});

it('shapes every guideline as rule, examples, sniffs', function (string $slug) use ($guidelineEnforcement): void {
    $path = cleanCodeRoot() . '/' . BOOST_GUIDELINE_DIRECTORY . '/' . $slug . '.md';
    $guideline = (string) file_get_contents($path);
    $title = strtok((string) file_get_contents(cleanCodeRoot() . '/docs/standards/' . $slug . '.md'), "\n");
    $enforcement = $guidelineEnforcement($slug);

    expect(strtok($guideline, "\n"))->toBe($title)
        ->and($guideline)->toContain("\n## Compliant\n\n```")
        ->and($guideline)->toContain("\n## Non-compliant\n\n```")
        ->and($guideline)->toContain("\n## Enforcement\n")
        ->and($guideline)->toContain('(' . BOOST_STANDARD_URL . $slug . '.md)')
        ->and($enforcement === [])->toBe(str_contains($guideline, BOOST_REVIEW_ONLY));
})->with($standardSlugs('docs/standards'));

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

it('reads a sniff table row and ignores prose that names a sniff', function () use ($guidelineEnforcement): void {
    $slug = 'conditionals-no-else-or-elseif';

    expect($guidelineEnforcement($slug))->toBe(['CleanCode.Conditionals.DisallowElse' => 'yes']);
});
