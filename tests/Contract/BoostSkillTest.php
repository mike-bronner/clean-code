<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Debug\DisallowDebugFunctionsSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use Symfony\Component\Yaml\Yaml;

const BOOST_GUIDELINE_DIRECTORY = 'resources/boost/guidelines';

const BOOST_SKILL_DIRECTORY = 'resources/boost/skills/clean-code';

const BOOST_REFERENCE_DIRECTORY = BOOST_SKILL_DIRECTORY . '/references';

const BOOST_REVIEW_ONLY = 'Enforced by code review only. No sniff checks this standard.';

$referenceSlugs = static function (): array {
    $slugs = array_map(
            static fn (string $path): string => basename($path, '.md'),
            glob(cleanCodeRoot() . '/' . BOOST_REFERENCE_DIRECTORY . '/*.md')
        );

    sort($slugs);

    return $slugs;
};

$referenceEnforcement = static function (string $slug): array {
    $path = cleanCodeRoot() . '/' . BOOST_REFERENCE_DIRECTORY . '/' . $slug . '.md';
    $reference = (string) file_get_contents($path);

    preg_match_all('/^\| `([\w.]+)` \| (yes|no) \|$/m', $reference, $rows);

    return array_combine($rows[1], $rows[2]);
};

$skillFrontmatter = static function (): array {
    $skill = (string) file_get_contents(cleanCodeRoot() . '/' . BOOST_SKILL_DIRECTORY . '/SKILL.md');
    preg_match('/^\s*---[^\S\r\n]*\R(.*?)\R---[^\S\r\n]*(?:\R|$)/s', $skill, $matches);

    return (array) Yaml::parse($matches[1] ?? '');
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

$sniffSources = static function (): array {
    $sources = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(cleanCodeRoot() . '/CleanCode', FilesystemIterator::SKIP_DOTS)
        );
    $contents = [];

    foreach ($sources as $source) {
        $text = (string) file_get_contents($source->getPathname());
        $contents[] = (string) preg_replace("/'\\s*\\.\\s*'/", '', $text);
    }

    return $contents;
};

it('ships one short always-on guideline that sends the agent to the skill', function (): void {
    $files = array_map('basename', glob(cleanCodeRoot() . '/' . BOOST_GUIDELINE_DIRECTORY . '/*'));
    $guideline = (string) file_get_contents(cleanCodeRoot() . '/' . BOOST_GUIDELINE_DIRECTORY . '/clean-code.md');
    $prose = (string) preg_replace('/\s+/', ' ', $guideline);

    expect($files)->toBe(['clean-code.md'])
        ->and(strlen($guideline))->toBeGreaterThanOrEqual(1000)->toBeLessThanOrEqual(2000)
        ->and($prose)->toContain('the `CleanCode` PHPCS standard')
        ->and($prose)->toContain(
            'Before you write, edit, refactor or review any PHP or Blade code, activate the `clean-code` skill.'
        );
});

it('ships a skill whose frontmatter Boost parses into a name and description', function () use (
    $skillFrontmatter,
): void {
    $frontmatter = $skillFrontmatter();

    expect($frontmatter['name'] ?? null)->toBe('clean-code')
        ->and($frontmatter['description'] ?? '')->toContain('Activate before you write, edit, refactor or review');
});

it('links every reference from the skill, and only references that exist', function () use ($referenceSlugs): void {
    $skill = (string) file_get_contents(cleanCodeRoot() . '/' . BOOST_SKILL_DIRECTORY . '/SKILL.md');
    preg_match_all('#\]\(references/([\w-]+)\.md\)#', $skill, $links);
    $linked = array_unique($links[1]);
    sort($linked);

    expect($referenceSlugs())->not->toBeEmpty()
        ->and($linked)->toBe($referenceSlugs())
        ->and(count($links[1]))->toBe(count($linked));
});

it('ships a reference naming every loaded sniff', function () use ($referenceSlugs, $referenceEnforcement): void {
    [, $ruleset] = buildRuleset();
    $named = [];

    foreach ($referenceSlugs() as $slug) {
        $named += $referenceEnforcement($slug);
    }

    $unnamed = array_values(array_diff(array_keys($ruleset->sniffCodes), array_keys($named)));

    expect($ruleset->sniffCodes)->not->toBeEmpty()
        ->and($unnamed)->toBe([]);
});

it('shapes every reference as rule, examples, sniffs', function (string $slug) use ($referenceEnforcement): void {
    $path = cleanCodeRoot() . '/' . BOOST_REFERENCE_DIRECTORY . '/' . $slug . '.md';
    $reference = (string) file_get_contents($path);
    $enforcement = $referenceEnforcement($slug);

    expect($reference)->toMatch('/\A# \S[^\n]*\n\n\S/')
        ->and($reference)->toContain("\n## Compliant\n\n```")
        ->and($reference)->toContain("\n## Non-compliant\n\n```")
        ->and($reference)->toContain("\n## Enforcement\n")
        ->and($enforcement === [])->toBe(str_contains($reference, BOOST_REVIEW_ONLY));
})->with($referenceSlugs());

it('points every sniff message at a reference that exists', function () use ($sniffSources): void {
    $targets = [];

    foreach ($sniffSources() as $contents) {
        preg_match_all('#resources/boost/[\w./-]*#', $contents, $paths);
        $targets = [...$targets, ...array_map(static fn (string $path): string => rtrim($path, '.'), $paths[0])];
    }

    $shape = '#\A' . BOOST_REFERENCE_DIRECTORY . '/[\w-]+\.md\z#';
    $root = cleanCodeRoot() . '/';
    $broken = array_values(array_filter(
            array_unique($targets),
            static fn (string $path): bool => (preg_match($shape, $path) === 1 && is_file($root . $path)) === false
        ));

    expect($targets)->not->toBeEmpty()
        ->and($broken)->toBe([]);
});

it('names only loaded sniffs, with their real fixability', function () use (
    $referenceSlugs,
    $referenceEnforcement,
    $sniffCarriesFixer,
): void {
    [, $ruleset] = buildRuleset();
    $claims = [];
    $actual = [];

    foreach ($referenceSlugs() as $slug) {
        foreach ($referenceEnforcement($slug) as $code => $fixable) {
            $sniff = $ruleset->sniffs[$ruleset->sniffCodes[$code] ?? ''] ?? null;
            $claims[$slug . ' ' . $code] = $fixable;
            $actual[$slug . ' ' . $code] = $sniff === null ? 'not loaded' : ($sniffCarriesFixer($sniff) ? 'yes' : 'no');
        }
    }

    expect($claims)->not->toBeEmpty()
        ->and($actual)->toBe($claims);
});

it('lists every name its sniff checks', function (string $slug, Closure $names): void {
    $path = cleanCodeRoot() . '/' . BOOST_REFERENCE_DIRECTORY . '/' . $slug . '.md';
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

it('reads a sniff table row and ignores prose that names a sniff', function () use ($referenceEnforcement): void {
    $slug = 'conditionals-no-else-or-elseif';

    expect($referenceEnforcement($slug))->toBe(['CleanCode.Conditionals.DisallowElse' => 'yes']);
});
