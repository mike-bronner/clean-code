<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Sniffs\WhiteSpace\PassiveOperatorSpacingSniff;
use MikeBronner\CleanCode\Support\ParameterDeclaration;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;
use PHP_CodeSniffer\Tests\ConfigDouble;

function cleanCodeRoot(): string
{
    return dirname(__DIR__);
}

function fixturePath(string $directory, string $fixture): string
{
    return __DIR__ . '/fixtures/' . $directory . '/' . $fixture;
}

function sniffFixtureDirectory(string $sniffCode): string
{
    $segments = explode('.', $sniffCode);

    return end($segments) . 'Sniff';
}

function installedStandardPaths(): array
{
    return [
        cleanCodeRoot() . '/vendor/sirbrillig/phpcs-variable-analysis',
        cleanCodeRoot() . '/vendor/slevomat/coding-standard',
    ];
}

function restoreInstalledPaths(): void
{
    Config::setConfigData('installed_paths', implode(',', installedStandardPaths()), true);
}

function buildRuleset(array $sniffCodes = [], bool $fresh = false): array
{
    static $cache = [];

    $key = $sniffCodes === [] ? '*master*' : implode('|', $sniffCodes);

    if ($fresh === false && isset($cache[$key]) === true) {
        return $cache[$key];
    }

    $config = new ConfigDouble(['--standard=' . cleanCodeRoot() . '/CleanCode/ruleset.xml']);
    $config->cache = false;

    restoreInstalledPaths();

    $ruleset = new Ruleset($config);

    if ($sniffCodes !== []) {
        $isolated = [];

        foreach ($sniffCodes as $code) {
            $class = $ruleset->sniffCodes[$code];
            $isolated[$class] = $ruleset->sniffs[$class];
        }

        $ruleset->sniffs = $isolated;
        $ruleset->populateTokenListeners();
    }

    $built = [$config, $ruleset];

    if ($fresh === false) {
        $cache[$key] = $built;
    }

    return $built;
}

function parseFixture(string $directory, string $fixture): LocalFile
{
    [$config, $ruleset] = buildRuleset();

    $file = new LocalFile(fixturePath($directory, $fixture), $ruleset, $config);
    $file->parse();

    return $file;
}

function globalFunctionCallVerdicts(LocalFile $file, string $prefix): array
{
    $verdicts = [];
    $functionCalls = new FunctionCalls();

    foreach ($file->getTokens() as $pointer => $token) {
        if ($token['code'] !== T_STRING || str_starts_with($token['content'], $prefix) === false) {
            continue;
        }

        $verdicts[$token['content']][] = $functionCalls->isGlobalFunctionCall($file, $pointer);
    }

    return $verdicts;
}

function analyzeWithMasterRuleset(string $path): LocalFile
{
    [$config, $ruleset] = buildRuleset();

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

function analyzeWithStandard(string $standard, string $path): LocalFile
{
    static $cache = [];

    if (isset($cache[$standard]) === false) {
        $config = new ConfigDouble(['--standard=' . $standard]);
        $config->cache = false;

        restoreInstalledPaths();

        $cache[$standard] = [$config, new Ruleset($config)];
    }

    [$config, $ruleset] = $cache[$standard];

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

function analyzeWithSniffs(array $sniffCodes, string $path, ?callable $configure = null): LocalFile
{
    [$config, $ruleset] = buildRuleset($sniffCodes, $configure !== null);

    if ($configure !== null) {
        $configure($ruleset->sniffs[$ruleset->sniffCodes[$sniffCodes[0]]]);
    }

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

function analyzeStdinSource(array $sniffCodes, string $source): DummyFile
{
    [$config, $ruleset] = buildRuleset($sniffCodes);

    $file = new DummyFile($source, $ruleset, $config);
    $file->process();

    return $file;
}

function sniffInstance(string $sniffCode): object
{
    [, $ruleset] = buildRuleset([$sniffCode]);

    return $ruleset->sniffs[$ruleset->sniffCodes[$sniffCode]];
}

function cacheCountsDelta(array $before, array $after): array
{
    $counted = [];

    foreach ($after as $counter => $count) {
        $counted[$counter] = $count - $before[$counter];
    }

    return $counted;
}

function analyzeFixture(string $sniffCode, string $fixture, ?callable $configure = null): LocalFile
{
    return analyzeWithSniffs(
        [$sniffCode],
        fixturePath(sniffFixtureDirectory($sniffCode), $fixture),
        $configure
    );
}

function analyzeProjectFixture(
    string $sniffCode,
    array|string $paths,
    string $file,
    ?callable $configure = null
): LocalFile {
    [$config, $ruleset] = buildRuleset([$sniffCode], true);

    $config->files = (array) $paths;

    if ($configure !== null) {
        $configure($ruleset->sniffs[$ruleset->sniffCodes[$sniffCode]]);
    }

    $analyzed = new LocalFile($file, $ruleset, $config);
    $analyzed->process();

    return $analyzed;
}

function analyzeFixtureWithProperty(
    string $sniffCode,
    string $fixture,
    string $property,
    mixed $value
): LocalFile {
    return analyzeFixture($sniffCode, $fixture, static function (object $sniff) use ($property, $value): void {
        $sniff->{$property} = $value;
    });
}

function analyzeFixtureWithRulesetProperties(
    string $sniffCode,
    string $fixture,
    array $properties
): LocalFile {
    [$config, $ruleset] = buildRuleset([$sniffCode], true);
    $sniffClass = $ruleset->sniffCodes[$sniffCode];

    foreach ($properties as $name => $value) {
        $ruleset->setSniffProperty($sniffClass, $name, ['scope' => 'sniff', 'value' => $value]);
    }

    $file = new LocalFile(
        fixturePath(sniffFixtureDirectory($sniffCode), $fixture),
        $ruleset,
        $config
    );
    $file->process();

    return $file;
}

function analyzeRulesetFixture(array $sniffCodes, string $directory, string $fixture): LocalFile
{
    return analyzeWithSniffs($sniffCodes, fixturePath('_rulesets/' . $directory, $fixture));
}

function analyzeWithoutExcludes(array $sniffCodes, array $excludedCodes, string $path): LocalFile
{
    [$config, $ruleset] = buildRuleset($sniffCodes, true);

    foreach ($excludedCodes as $code) {
        $ruleset->ruleset[$code]['severity'] = 5;
    }

    $file = new LocalFile($path, $ruleset, $config);
    $file->process();

    return $file;
}

function analyzeWithConfiguredRuleset(
    string $sniffCode,
    string $fixture,
    array $properties
): LocalFile {
    $lines = [];

    foreach ($properties as $name => $value) {
        if (is_array($value) === false) {
            $lines[] = '            <property name="' . $name . '" value="' . $value . '"/>';

            continue;
        }

        $lines[] = '            <property name="' . $name . '" type="array">';

        foreach ($value as $element) {
            $lines[] = '                <element value="' . $element . '"/>';
        }

        $lines[] = '            </property>';
    }

    $standard = sys_get_temp_dir() . '/' . uniqid('cleancode-ruleset-', true) . '.xml';
    file_put_contents($standard, implode("\n", [
        '<?xml version="1.0"?>',
        '<ruleset name="Consumer">',
        '    <description>Consumer ruleset built by the test suite.</description>',
        '    <rule ref="' . cleanCodeRoot() . '/CleanCode/ruleset.xml"/>',
        '    <rule ref="' . $sniffCode . '">',
        '        <properties>',
        implode("\n", $lines),
        '        </properties>',
        '    </rule>',
        '</ruleset>',
        '',
    ]));

    $config = new ConfigDouble(['--standard=' . $standard]);
    $config->cache = false;

    restoreInstalledPaths();

    $ruleset = new Ruleset($config);
    unlink($standard);

    $class = $ruleset->sniffCodes[$sniffCode];
    $ruleset->sniffs = [$class => $ruleset->sniffs[$class]];
    $ruleset->populateTokenListeners();

    $file = new LocalFile(fixturePath(sniffFixtureDirectory($sniffCode), $fixture), $ruleset, $config);
    $file->process();

    return $file;
}

function installedPhpcsViolations(string $standard, string $path, string $sniffCode): array
{
    $report = installedPhpcsReport($standard, $path);
    $violations = [];

    foreach ($report as $message) {
        if ($message['source'] === $sniffCode) {
            $violations[] = ['line' => (int) $message['line'], 'message' => (string) $message['message']];
        }
    }

    return $violations;
}

function installedPhpcsReport(string $standard, string $path): array
{
    return installedPhpcsRun($standard, $path)['messages'];
}

function installedPhpcsRun(string $standard, string $path, array $extraArguments = []): array
{
    $binary = cleanCodeRoot() . '/vendor/bin/phpcs';

    if (is_file($binary) === false) {
        throw new RuntimeException("the installed phpcs binary is missing at {$binary}; run composer install");
    }

    $arguments = array_merge(
        [PHP_BINARY, $binary, '--standard=' . $standard],
        $extraArguments,
        ['--report=json', '--no-cache', $path]
    );
    [$stdout, $stderr, $status] = runOutsidePackage(implode(' ', array_map('escapeshellarg', $arguments)));

    $decoded = json_decode($stdout, true);
    $files = is_array($decoded) === true ? $decoded['files'] ?? null : null;

    if (is_array($files) === false) {
        throw new RuntimeException("phpcs produced no JSON report for {$path}; stdout: {$stdout} stderr: {$stderr}");
    }

    if (count($files) !== 1) {
        throw new RuntimeException('phpcs reported on ' . count($files) . " files, expected only {$path}");
    }

    return ['status' => $status, 'messages' => reset($files)['messages'] ?? []];
}

function installedStandardNames(): array
{
    $binary = cleanCodeRoot() . '/vendor/bin/phpcs';

    if (is_file($binary) === false) {
        throw new RuntimeException("the installed phpcs binary is missing at {$binary}; run composer install");
    }

    [$stdout, $stderr, $status] = runOutsidePackage(
        implode(' ', array_map('escapeshellarg', [PHP_BINARY, $binary, '-i']))
    );

    if ($status !== 0 || preg_match('/^The installed coding standards are (.+?)\.?\s*$/', $stdout, $matches) !== 1) {
        throw new RuntimeException("phpcs -i listed no standards; stdout: {$stdout} stderr: {$stderr}");
    }

    return array_map('trim', (array) preg_split('/,\s*|\s+and\s+/', $matches[1]));
}

function installedSniffRun(string $sniffCode, string $path): array
{
    return installedPhpcsRun('CleanCode', $path, ['--sniffs=' . $sniffCode]);
}

function installedSniffFixtureRun(string $sniffCode, string $fixture): array
{
    return installedSniffRun($sniffCode, fixturePath(sniffFixtureDirectory($sniffCode), $fixture));
}

function runOutsidePackage(string $command): array
{
    $pipes = [];
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, sys_get_temp_dir());

    if (is_resource($process) === false) {
        throw new RuntimeException("could not start: {$command}");
    }

    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return [$stdout, $stderr, proc_close($process)];
}

function autofixedContents(LocalFile $file): string
{
    $file->fixer->fixFile();

    return $file->fixer->getContents();
}

function unclassifiedTokens(string $source): array
{
    [$config, $ruleset] = buildRuleset();

    $file = new DummyFile($source, $ruleset, $config);
    $file->parse();

    $faults = [];

    foreach ($file->getTokens() as $token) {
        if ($token['type'] === 'T_NONE' && trim($token['content']) !== '') {
            $faults[] = $token['line'] . ':' . trim($token['content']);
        }
    }

    return $faults;
}

function violationSourcesByLine(array $messages): array
{
    $sources = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $sources[$line][] = $violation['source'];
            }
        }
    }

    ksort($sources);

    return $sources;
}

function violationCountsByLine(array $messages): array
{
    $counts = [];

    foreach ($messages as $line => $columns) {
        $counts[$line] = array_sum(array_map('count', $columns));
    }

    ksort($counts);

    return $counts;
}

function violationMessagesByLine(array $messages): array
{
    $rendered = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $rendered[$line][] = $violation['message'];
            }
        }
    }

    ksort($rendered);

    return $rendered;
}

function violationTuples(LocalFile $file): array
{
    return tuplesFromMessages($file->getErrors());
}

function warningTuples(LocalFile $file): array
{
    return tuplesFromMessages($file->getWarnings());
}

function tuplesFromMessages(array $messages): array
{
    $flat = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $column => $lineMessages) {
            foreach ($lineMessages as $message) {
                $flat[] = ['line' => $line, 'column' => $column, 'source' => $message['source']];
            }
        }
    }

    usort($flat, static fn (array $a, array $b): int => [$a['line'], $a['column']] <=> [$b['line'], $b['column']]);

    return $flat;
}

function allViolationSourcesByLine(LocalFile $file): array
{
    $map = [];

    foreach ([$file->getErrors(), $file->getWarnings()] as $violations) {
        foreach ($violations as $line => $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    $map[$line][] = $message['source'];
                }
            }
        }
    }

    ksort($map);
    array_walk($map, static function (array &$sources): void {
        sort($sources);
    });

    return $map;
}

function violationMessages(LocalFile $file): array
{
    $messages = [];

    foreach ($file->getErrors() as $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $messages[] = $violation['message'];
            }
        }
    }

    return $messages;
}

function violationFixableFlags(LocalFile $file): array
{
    $flags = [];

    foreach ($file->getErrors() as $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                $flags[] = $message['fixable'];
            }
        }
    }

    return $flags;
}

function violationFixableLines(array $messages): array
{
    $lines = [];

    foreach ($messages as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                if ($violation['fixable'] === true) {
                    $lines[$line] = $line;
                }
            }
        }
    }

    ksort($lines);

    return array_values($lines);
}

function stageSource(string $source, string $filename = 'view.blade.php'): string
{
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-source-', true);

    if (mkdir($directory, 0700) === false) {
        throw new RuntimeException("could not stage a source file in {$directory}");
    }

    $path = $directory . '/' . $filename;
    stagedFixtures($path);
    file_put_contents($path, $source);

    return $path;
}

function stageFixtureOutsideTests(string $path, string $subdirectory = ''): string
{
    $directory = stagingDirectory($subdirectory);
    $staged = $directory . '/' . basename($path);
    copy($path, $staged);

    return $staged;
}

function stageGeneratedFixture(string $filename, string $contents): string
{
    $staged = stagingDirectory() . '/' . $filename;

    if (file_put_contents($staged, $contents) === false) {
        throw new RuntimeException("could not write a generated fixture to {$staged}");
    }

    return $staged;
}

function stagingDirectory(string $subdirectory = ''): string
{
    $root = sys_get_temp_dir() . '/' . uniqid('cleancode-fixture-', true);
    $directory = $subdirectory === '' ? $root : $root . '/' . $subdirectory;

    if (mkdir($directory, 0700, true) === false) {
        throw new RuntimeException("could not stage a fixture in {$directory}");
    }

    stagedFixtures($root);

    return $directory;
}

function stageProjectOutsideTests(array $files): string
{
    $root = stagingDirectory();

    foreach ($files as $relativePath => $contents) {
        $path = $root . '/' . $relativePath;
        $directory = dirname($path);

        if (is_dir($directory) === false && mkdir($directory, 0700, true) === false) {
            throw new RuntimeException("could not stage a project directory at {$directory}");
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("could not stage a project file at {$path}");
        }
    }

    return $root . '/' . array_key_first($files);
}

function stageThrowawayPhpcsInstall(): string
{
    $vendor = stagingDirectory('vendor');
    $source = cleanCodeRoot() . '/vendor/squizlabs/php_codesniffer';
    $install = $vendor . '/squizlabs/php_codesniffer';

    if (mkdir($install, 0700, true) === false) {
        throw new RuntimeException("could not stage a PHP_CodeSniffer install at {$install}");
    }

    $entries = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $entry, string $key, RecursiveDirectoryIterator $directory): bool
            => in_array($directory->getSubPathname(), ['tests', 'CodeSniffer.conf'], true) === false
    );
    $offset = strlen($source) + 1;

    foreach (new RecursiveIteratorIterator($entries, RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $target = $install . '/' . substr($entry->getPathname(), $offset);

        if ($entry->isDir() === true) {
            if (mkdir($target, 0700, true) === false) {
                throw new RuntimeException("could not stage {$target}");
            }

            continue;
        }

        if (copy($entry->getPathname(), $target) === false) {
            throw new RuntimeException("could not stage {$target}");
        }
    }

    file_put_contents(
        $vendor . '/autoload.php',
        '<?php' . "\n\n" . 'return require ' . var_export(cleanCodeRoot() . '/vendor/autoload.php', true) . ';' . "\n"
    );

    return $install . '/bin/phpcs';
}

function buildRulesetForStandard(string $standard): Ruleset
{
    $config = new ConfigDouble(['--standard=' . $standard]);
    $config->cache = false;

    restoreInstalledPaths();

    return new Ruleset($config);
}

function stageOrdinalDiagnosticRuleset(): string
{
    $standard = stagingDirectory() . '/ordinal-diagnostic.xml';

    file_put_contents($standard, implode("\n", [
        '<?xml version="1.0"?>',
        '<ruleset name="OrdinalIndexDiagnostic">',
        '    <description>Consumer ruleset built by the test suite.</description>',
        '    <rule ref="' . cleanCodeRoot() . '/CleanCode/ruleset.xml"/>',
        '    <rule ref="CleanCode.Metrics.NumberOfChildren.OrdinalIndex">',
        '        <severity>5</severity>',
        '    </rule>',
        '</ruleset>',
        '',
    ]));

    return $standard;
}

function stageSourceOutsideTests(string $source, string $filename): string
{
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-source-', true);

    if (mkdir($directory, 0700) === false) {
        throw new RuntimeException("could not stage a source file in {$directory}");
    }

    stagedFixtures($directory);

    $staged = $directory . '/' . $filename;
    file_put_contents($staged, $source);

    return $staged;
}

function stagedFixtures(?string $add = null): array
{
    static $roots = [];

    if ($add !== null) {
        $roots[] = $add;

        return $roots;
    }

    $tracked = $roots;
    $roots = [];

    return $tracked;
}

function purgeStagedFixtures(): void
{
    foreach (stagedFixtures() as $root) {
        removeStagedDirectory($root);
    }
}

function removeStagedDirectory(string $directory): void
{
    if (is_link($directory) === true) {
        unlink($directory);

        return;
    }

    if (is_dir($directory) === false) {
        return;
    }

    foreach (array_diff((array) scandir($directory), ['.', '..']) as $entry) {
        $path = $directory . '/' . $entry;

        if (is_link($path) === true) {
            unlink($path);

            continue;
        }

        if (is_dir($path) === true) {
            removeStagedDirectory($path);

            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}

function directoryOutsideEveryStagingRoot(): string
{
    $directory = sys_get_temp_dir() . '/' . uniqid('cleancode-outside-', true);

    if (mkdir($directory, 0700, true) === false) {
        throw new RuntimeException("could not create {$directory}");
    }

    file_put_contents($directory . '/keep.php', '<?php' . "\n");

    return $directory;
}

function removeDirectoryOutsideEveryStagingRoot(string $directory): void
{
    unlink($directory . '/keep.php');
    rmdir($directory);
}

function stageSymlink(string $target, string $link): void
{
    if (symlink($target, $link) === false) {
        throw new RuntimeException("could not stage a symlink at {$link}");
    }
}

function diagnosticsRaisedBy(Closure $callback): array
{
    $diagnostics = [];

    set_error_handler(static function (int $severity, string $message) use (&$diagnostics): bool {
        $diagnostics[] = $message;

        return true;
    });

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $diagnostics;
}

function measuredComplexities(LocalFile $file): array
{
    $measured = [];

    foreach (violationMessages($file) as $message) {
        if (preg_match('/^The (\S+ \S+\(\)) has a cyclomatic complexity of (\d+),/', $message, $matches) === 1) {
            $measured[$matches[1]] = (int) $matches[2];
        }
    }

    return $measured;
}

function reportedNestingLevels(LocalFile $file): array
{
    $levels = [];

    foreach (violationMessagesByLine($file->getErrors()) as $line => $messages) {
        foreach ($messages as $message) {
            if (preg_match('/^Method nesting level \((\d+)\) exceeds/', $message, $matches) === 1) {
                $levels[$line] = (int) $matches[1];
            }
        }
    }

    return $levels;
}

function evaluateFixtureVariables(string $path): array
{
    $load = static function (string $mikeBronnerFixturePath): array {
        require $mikeBronnerFixturePath;

        $variables = get_defined_vars();
        unset($variables['mikeBronnerFixturePath']);

        return $variables;
    };

    return $load($path);
}

function nestedChainFixture(int $depth): array
{
    $indent = str_repeat(' ', 8);
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'final class DeepNesting', '{'];
    $lines[] = '    public function nested(int $code): int';
    $lines[] = '    {';

    for ($level = 0; $level < $depth; $level++) {
        $lines[] = $indent . 'if ($code === ' . $level . ')';
    }

    $lines[] = $indent . 'return 0;';
    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    public function chain(int $code): int';
    $lines[] = '    {';

    $chainLine = count($lines) + 1;

    $lines = array_merge($lines, [
        $indent . 'if ($code === 1) {',
        $indent . '    return 1;',
        $indent . '} elseif ($code === 2) {',
        $indent . '    return 2;',
        $indent . '} else {',
        $indent . '    return 3;',
        $indent . '}',
        '    }',
        '}',
        '',
    ]);

    return [implode("\n", $lines), $chainLine];
}

function ternaryChainFixture(int $links): string
{
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'function chainedTernaries($a)', '{'];
    $lines[] = '    $value = $a' . str_repeat(' ?: $a', $links) . ';';
    $lines[] = '';
    $lines[] = '    return $value;';
    $lines[] = '}';
    $lines[] = '';

    return implode("\n", $lines);
}

function sequentialBranchFixture(int $branches): string
{
    $lines = ['<?php', '', 'declare(strict_types=1);', '', 'function manyBranches(int $a): int', '{'];

    for ($branch = 0; $branch < $branches; $branch++) {
        $lines[] = '    if ($a === ' . $branch . ') {';
        $lines[] = '        $a++;';
        $lines[] = '    }';
        $lines[] = '';
    }

    $lines[] = '    return $a;';
    $lines[] = '}';
    $lines[] = '';

    return implode("\n", $lines);
}

function measuredNPathComplexities(LocalFile $file): array
{
    $measured = [];

    foreach (violationMessages($file) as $message) {
        $matched = preg_match(
            '/The (?:function|method) ([A-Za-z_0-9]+)\(\) has an NPath complexity of (\d+)/',
            $message,
            $matches
        );

        if ($matched === 1) {
            $measured[$matches[1]] = (int) $matches[2];
        }
    }

    return $measured;
}

function parameterDeclarationFile(string $source): File
{
    return analyzeStdinSource(
        ['CleanCode.Metrics.TooManyFields'],
        "<?php\n\ndeclare(strict_types=1);\n\n" . $source . "\n"
    );
}

function parameterDeclarationPointer(File $file, string $name, int $occurrence = 1): int
{
    $seen = 0;

    foreach ($file->getTokens() as $ptr => $token) {
        if ($token['code'] !== T_VARIABLE || $token['content'] !== $name) {
            continue;
        }

        $seen++;

        if ($seen === $occurrence) {
            return $ptr;
        }
    }

    throw new RuntimeException($name . ' is written fewer than ' . $occurrence . ' times in the analysed source');
}

function parameterDeclarationAnswers(File $file, string $name, int $occurrence = 1): array
{
    $ptr = parameterDeclarationPointer($file, $name, $occurrence);

    return [
        (new ParameterDeclaration())->isPlainParameter($file, $ptr),
        (new ParameterDeclaration())->isPromotedParameter($file, $ptr),
    ];
}

function passiveNonOperandTokens(): array
{
    $method = new ReflectionMethod(PassiveOperatorSpacingSniff::class, 'nonOperandTokens');

    return $method->invoke(new PassiveOperatorSpacingSniff());
}

function squizNonOperandTokens(): array
{
    $sniff = new OperatorSpacingSniff();
    $sniff->register();

    $property = new ReflectionProperty($sniff, 'nonOperandTokens');

    return $property->getValue($sniff) ?? [];
}

function tokenNamesInConstant(string $path, string $constant, array $sniffCodes): array
{
    $tokens = analyzeWithSniffs($sniffCodes, $path)->getTokens();
    $names = [];
    $reading = false;

    foreach ($tokens as $token) {
        if ($token['code'] === T_STRING && $token['content'] === $constant) {
            $reading = true;

            continue;
        }

        if ($reading === false) {
            continue;
        }

        if ($token['code'] === T_SEMICOLON) {
            break;
        }

        if ($token['code'] === T_STRING && str_starts_with($token['content'], 'T_') === true) {
            $names[] = $token['content'];
        }

        $quoted = trim($token['content'], '\'"');

        if ($token['code'] === T_CONSTANT_ENCAPSED_STRING && str_starts_with($quoted, 'T_') === true) {
            $names[] = $quoted;
        }
    }

    return $names;
}

function analyzeFileset(array $sniffCodes, string $directory): array
{
    $config = new ConfigDouble(['--standard=' . cleanCodeRoot() . '/CleanCode/ruleset.xml', $directory]);
    $config->cache = false;

    restoreInstalledPaths();

    $ruleset = new Ruleset($config);

    if ($sniffCodes !== []) {
        $isolated = [];

        foreach ($sniffCodes as $code) {
            $class = $ruleset->sniffCodes[$code];
            $isolated[$class] = $ruleset->sniffs[$class];
        }

        $ruleset->sniffs = $isolated;
        $ruleset->populateTokenListeners();
    }

    $list = new FileList($config, $ruleset);
    $files = [];

    for ($list->rewind(); $list->valid() === true; $list->next()) {
        $path = $list->key();
        $file = new LocalFile($path, $ruleset, $config);
        $file->process();
        $files[basename($path)] = $file;
    }

    ksort($files);

    return $files;
}

function withPhpDiagnostics(Closure $body): array
{
    $diagnostics = [];

    set_error_handler(static function (int $severity, string $message) use (&$diagnostics): bool {
        if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true) === false) {
            $diagnostics[] = $message;
        }

        return true;
    });

    try {
        $result = $body();
    } finally {
        restore_error_handler();
    }

    return [$result, $diagnostics];
}

function isTypeHintsSource(string $source): bool
{
    return str_starts_with($source, 'CleanCode.TypeHints.')
        || str_starts_with($source, 'SlevomatCodingStandard.TypeHints.');
}
