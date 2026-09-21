<?php

/**
 * The package's public entry points, from outside the package.
 *
 * This package ships one ruleset file, `CleanCode/ruleset.xml`, and installs
 * exactly one PHP_CodeSniffer standard from it, `CleanCode`. That standard is
 * the whole rule set. There are two ways to name it and they must agree:
 *
 * - `<rule ref="CleanCode"/>` — the installed standard's *name*, which is what
 *   the README documents and what a consuming project writes. PHP_CodeSniffer
 *   names a standard after the directory holding its ruleset.xml and ignores
 *   the ruleset's own name= attribute, so `CleanCode/` is the whole of the
 *   public API here. That directory name is also the sniff-code prefix, so the
 *   standard and every CleanCode.* code agree by construction.
 * - the path to `CleanCode/ruleset.xml` itself, which is how `phpcs.self.xml`
 *   and the self-lint gate reach it, and how anyone pointing `--standard` at a
 *   checkout rather than an install reaches it.
 *
 * The name is the interesting half. It is the only one that can fail while the
 * file is perfectly intact: a standard registers through the dealerdirect
 * installer writing PHP_CodeSniffer's installed_paths, and nothing about the
 * ruleset's contents makes that happen.
 *
 * ShippedPackageSmokeTest.php drives the shipped binary per sniff through the
 * name. It cannot answer either question here: it compares each run against the
 * sniff's own fixtures rather than against the other way in, and it never asks
 * how many standards exist.
 *
 * Every run here goes through installedPhpcsRun()/installedStandardNames(),
 * which execute the real vendor/bin/phpcs from a working directory outside the
 * package. That is what makes the name a statement about installation:
 * PHP_CodeSniffer resolves --standard against the working directory first, so
 * from the package root "CleanCode" would find ./CleanCode as a plain relative
 * directory and prove nothing. Both helpers throw rather than returning an
 * empty result whenever the run produced no readable output.
 */

declare(strict_types=1);

/**
 * One standard, and it is called CleanCode.
 *
 * Both halves are asserted, because either alone passes in a state the other
 * rejects. The name alone was true while this package also shipped a second
 * standard beside it, which is the state this test exists to keep closed. The
 * count alone would be satisfied by one standard under any name at all.
 *
 * The count is taken from the package tree rather than from `phpcs -i`, and
 * that is not a weaker reading of the same thing. `-i` prints every standard on
 * the machine, PHPCS's own bundled ones included, and nothing in its output says
 * which package contributed which name — so the *count* question cannot be
 * asked of it. It can be asked of the tree, because the installer decides what
 * to register by searching this package for files literally named ruleset.xml,
 * and PHP_CodeSniffer then reads a standard's name off the directory holding
 * each one. Every such file is therefore a standard this package installs.
 *
 * vendor/ is excluded because it is not part of what ships: a dependency's own
 * ruleset.xml is registered against that dependency, and `composer require` of
 * this package does not install a nested vendor/ at all.
 *
 * `-i` still carries the other half. It is the only thing that can say the name
 * really registered, rather than merely being a directory on disk.
 */
it('installs exactly one standard, named CleanCode', function (): void {
    $rulesets = array_merge(
        (array) glob(cleanCodeRoot() . '/*/ruleset.xml'),
        (array) glob(cleanCodeRoot() . '/*/*/ruleset.xml'),
        (array) glob(cleanCodeRoot() . '/*/*/*/ruleset.xml')
    );

    $shipped = array_values(array_filter(
        array_map(static fn (string $path): string => substr($path, strlen(cleanCodeRoot()) + 1), $rulesets),
        static fn (string $path): bool => str_starts_with($path, 'vendor/') === false
    ));

    sort($shipped);

    expect($shipped)->toBe(['CleanCode/ruleset.xml'])
        ->and(installedStandardNames())->toContain('CleanCode');
});

/**
 * The registered name resolves to the ruleset file this package ships, asserted
 * on the findings rather than on where installed_paths points.
 *
 * This is what the name being right actually means. installed_paths could name
 * a stale checkout, a sibling package that also ships a CleanCode/ directory, or
 * a copy left behind by an earlier install, and `phpcs -i` would still print
 * "CleanCode" in every one of those cases. Running both and comparing the
 * reports is the only thing that says the name and the file are the same rules.
 *
 * The status is compared too. Messages alone cannot separate "both runs found
 * the same thing" from "neither run reached the file", and installedPhpcsRun()
 * throws on the exit-3 case that has no report at all.
 *
 * The list of reporting standards carries two claims and is the point of the
 * assertion rather than decoration.
 *
 * First, two runs that both resolved to nothing produce identical empty reports
 * and identical statuses, so the comparison above needs something to have been
 * reported at all. The fixture is chosen for breadth rather than for what it is
 * a fixture of: it reports across the custom sniffs, the bundled standards and
 * the third-party ones together, where a fixture tripping one standard would
 * agree across both routes while the rest of the wiring had been lost.
 *
 * Second, and this is the invariant the collapse to one standard turns on: the
 * list is read off the run made *by the bare name*, so it says the name carries
 * the whole rule set. CleanCode/ used to hold the custom sniffs alone, with
 * everything else in a second ruleset beside it, and `--standard=CleanCode` then
 * reported CleanCode.* codes and nothing more. A consumer writing the bare name
 * got a fraction of the package with no error saying so. A regression to that
 * shape leaves this list one entry long and fails here.
 */
it('resolves the standard name to the ruleset this package ships', function (): void {
    $fixture = fixturePath('MultiLineStatementIndentSniff', 'failing.php');

    $byName = installedPhpcsRun('CleanCode', $fixture);
    $byPath = installedPhpcsRun(cleanCodeRoot() . '/CleanCode/ruleset.xml', $fixture);

    $sources = array_unique(array_map(
        static fn (array $message): string => explode('.', (string) $message['source'])[0],
        $byName['messages']
    ));

    sort($sources);

    expect($byName['messages'])->toBe($byPath['messages'])
        ->and($byName['status'])->toBe($byPath['status'])
        ->and($sources)->toBe([
            'CleanCode',
            'Generic',
            'PSR1',
            'PSR12',
            'SlevomatCodingStandard',
            'Squiz',
            'VariableAnalysis',
        ]);
});

/**
 * Blade views are linted whichever way the standard is named.
 *
 * A *directory* is handed to phpcs rather than the view's own path, because the
 * two are answered by different code. PHP_CodeSniffer runs Filters\Filter over
 * a directory scan and does not run it over a file named on the command line,
 * so only the directory form asks whether a view would be picked up in a
 * consumer's project at all. The staged directory holds exactly the one view,
 * which is what lets installedPhpcsRun()'s one-file guard turn "the filter
 * rejected it" into a failure rather than into silence.
 *
 * Asserted on a Livewire finding rather than on any finding: silence and a
 * skipped file are the same empty report, and CleanCode.Livewire.ComponentMarkup
 * reads a view's markup as T_INLINE_HTML, so it cannot report at all unless the
 * PHP tokenizer really saw the file.
 *
 * What this does *not* discriminate, stated because the docblock is otherwise
 * the obvious place to read the opposite: removing the ruleset's
 * <arg name="extensions" value="php,blade.php/php"/> leaves this test green,
 * measured rather than reasoned. Filter::shouldProcessFile() builds every
 * multi-part suffix of the name — for component.blade.php, both "blade.php" and
 * "php" — and intersects them with the configured list, and "php" is in
 * PHP_CodeSniffer's default list and maps to the PHP tokenizer already. The arg
 * therefore changes what happens to *other* extensions (it replaces the default
 * list rather than extending it) and not what happens to a Blade view. The
 * behaviour this pins is the one the AC names: a view is linted whichever way
 * the standard is named. It reddens when the standard stops resolving and when
 * ComponentMarkup stops being registered.
 */
it('scans Blade views whichever way the standard is named', function (string $standard): void {
    $staged = stageFixtureOutsideTests(fixturePath('ComponentMarkupSniff', 'component.blade.php'));
    $run = installedPhpcsRun($standard, dirname($staged));

    expect(array_column($run['messages'], 'source'))->not->toBeEmpty()
        ->each->toStartWith('CleanCode.Livewire.ComponentMarkup.');
})->with([
    'by standard name' => ['CleanCode'],
    'by ruleset path' => [cleanCodeRoot() . '/CleanCode/ruleset.xml'],
]);

/**
 * The php_version pin, and the comment justifying it, against the requirement
 * they both claim to follow.
 *
 * Derived from composer.json rather than restated, so a bump to the package's
 * PHP floor cannot leave either behind. That is not hypothetical: the pin read
 * 80100 and its comment read "^8.1" while composer.json had required ^8.3 for
 * some time, and version-gated Slevomat options resolved against the wrong
 * runtime the whole while.
 *
 * The comment is pinned as well as the value, because the comment is what the
 * next reader checks the value against. The extraction is scoped to the comment
 * block immediately above the <config> element — the ruleset names other ^8.x
 * constraints elsewhere, Slevomat's ^8.15 among them, and a whole-file search
 * would be satisfied by any of them.
 */
it('pins php_version to the package PHP floor', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(cleanCodeRoot() . '/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $constraint = (string) $manifest['require']['php'];
    $source = (string) file_get_contents(cleanCodeRoot() . '/CleanCode/ruleset.xml');

    expect(preg_match('/^\^(\d+)\.(\d+)$/', $constraint, $floor))->toBe(1);

    $ruleset = simplexml_load_string($source);

    expect($ruleset)->not->toBeFalse();

    $pinned = $ruleset->xpath('//config[@name="php_version"]');

    expect($pinned)->toHaveCount(1);

    preg_match('/<!--(?:(?!-->).)*-->\s*<config name="php_version"/s', $source, $justification);
    preg_match_all('/\^\d+\.\d+/', $justification[0] ?? '', $cited);

    expect((string) $pinned[0]['value'])->toBe(sprintf('%d%02d00', (int) $floor[1], (int) $floor[2]))
        ->and(array_values(array_unique($cited[0])))->toBe([$constraint]);
});
