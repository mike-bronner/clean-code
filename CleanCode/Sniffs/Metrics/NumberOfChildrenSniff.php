<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Replicates PHPMD's Design/NumberOfChildren rule (issue #110).
 *
 * A class with many direct subclasses is an unbalanced hierarchy: every child
 * is another thing that changes when the parent changes, and a parent with
 * fifteen of them has become a junction rather than an abstraction. The sniff
 * counts the *direct* subclasses of each class and reports the class
 * declaration once that count reaches $minimum — PHPMD's `minimum` property,
 * same default of 15.
 *
 * ## This is the ruleset's first cross-file rule
 *
 * Every other sniff here answers from the file it is handed. This one cannot:
 * a parent's children are declared elsewhere, and nothing in the parent's own
 * file records how many there are. So the sniff reads the whole run once,
 * builds the inheritance map, and answers every file from it.
 *
 * "The whole run" is PHP_CodeSniffer's own file list — the paths phpcs was
 * invoked with, expanded through PHP_CodeSniffer\Files\FileList. Using
 * FileList rather than a directory walk of its own is what keeps the scanned
 * set identical to the linted set: the configured extensions, the
 * --ignore patterns, and the ruleset's exclude-patterns all apply exactly once,
 * to both.
 *
 * The map is built on the first file that needs it and kept for the rest of the
 * run, keyed by the run's root paths. One extra read and tokenize per file in
 * the run, once — not once per file processed, which would be quadratic. Under
 * --parallel each worker process builds its own copy, because each is a
 * separate process with its own instance of this sniff.
 *
 * ## Running phpcs on one file reports nothing, and that is parity
 *
 * Point phpcs at a single file and the run contains one file, so only the
 * children declared *in* it are visible. A parent whose children live elsewhere
 * goes unreported. PHPMD does the same thing for the same reason — measured
 * against a live PHPMD 2.15.0: a Base with two children in its own file and one
 * in another reports "3 children" when the directory is scanned and "2
 * children" when only Base's file is. The metric is a property of the code
 * under analysis, not of the class in the abstract, in both tools.
 *
 * ## The threshold is inclusive
 *
 * PHPMD reports when the metric is *greater than or equal to* `minimum`:
 *
 * ```php
 * $nocc = $node->getMetric('nocc');
 * $threshold = $this->getIntProperty('minimum');
 * if ($nocc >= $threshold) {
 *     $this->addViolation($node, array(...));
 * }
 * ```
 *
 * So exactly 15 children is already a violation, and phpmd.org's "maximum
 * number of acceptable child classes" — echoed by #110's acceptance criteria,
 * which ask for 15 to be silent — is the advice, not the test. A live PHPMD
 * 2.15.0 run agrees with the code and not with the prose, and the tool is what
 * this package replaces. CleanCode.Metrics.CouplingBetweenObjects (#114) reads
 * its own `maximum` inclusively for the same reason.
 *
 * ## What counts as a child
 *
 * Every claim here was measured against a live PHPMD 2.15.0 (PDepend 2.16.2)
 * run, not read off phpmd.org, and each is pinned by a fixture:
 *
 * - **`extends` only.** Fifteen classes implementing one interface report
 *   nothing, and the interface is not a subject in the first place — PHPMD's
 *   rule is `ClassAware`, so interfaces, traits, and enums are never examined.
 * - **Direct children only.** A grandchild counts toward its own parent, never
 *   toward the class above it.
 * - **Anonymous classes are not children.** `new class extends Base {}` leaves
 *   Base's count untouched, in PHPMD and here.
 * - **Abstract classes are subjects.** An abstract parent is the normal shape
 *   for this smell, and PHPMD reports it.
 * - **Parents resolve fully qualified.** A child in another namespace reaching
 *   its parent through `use App\Base;` counts toward `App\Base`, not toward a
 *   second class called `Base`. Names are compared lower-cased, because PHP
 *   class names are case-insensitive.
 *
 * ## One parser, so the two halves cannot disagree
 *
 * Both halves of the answer — the child counts, and the fully-qualified name of
 * the class being reported on — come from the same token_get_all() pass. The
 * alternative, resolving the subject's name from PHP_CodeSniffer's token stream
 * while the counts come from PHP's own tokenizer, gives two independent notions
 * of "the fully qualified name of this class". Any disagreement between them
 * would not produce a wrong count; it would produce a lookup that silently
 * misses and a sniff that quietly reports nothing at all. Deriving both from
 * one pass makes that class of failure unrepresentable.
 *
 * The price of that choice is that the raw tokenizer's own quirks are this
 * sniff's to handle rather than PHP_CodeSniffer's. The one that reaches the
 * brace tracking is string interpolation: `{$expr}` and `${expr}` open with an
 * array token and close with a bare `}`, so only counting the bare braces would
 * unbalance the depth. Both openers are counted — see
 * INTERPOLATION_OPEN_TOKENS — and interpolation/ fixtures pin each shape.
 *
 * A file the sniff cannot read contributes nothing rather than aborting the
 * run. That direction is deliberate: a missing file can only lower a count, and
 * a count that is too low stays silent, where one that is too high accuses a
 * class of a hierarchy it does not have.
 *
 * Errors, matching the severity its sibling metric sniffs already use. Detection
 * only, matching PHPMD: rebalancing a hierarchy means moving behaviour between
 * classes, which is a design decision and not a mechanical rewrite.
 *
 * Fixtured in tests/fixtures/NumberOfChildrenSniff/ and covered by
 * tests/Standards/NumberOfChildrenTest.php.
 */
class NumberOfChildrenSniff implements Sniff
{
    /**
     * The path PHP_CodeSniffer reports when it lints piped input. There is no
     * file to read a codebase around, so the sniff has nothing to say.
     */
    private const UNKNOWN_PATH = 'STDIN';

    /**
     * The tokens that carry no meaning for this parse and are skipped wherever
     * a "next token" is read.
     *
     * @var array<int|string, true>
     */
    private const SKIPPED_TOKENS = [
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
    ];

    /**
     * The tokens a qualified name is built from. PHP 8 hands a namespaced name
     * over as a single T_NAME_* token, but a name split by a group-use brace —
     * `App\{Alpha}` — still arrives as the pre-8 run of T_STRING and
     * T_NS_SEPARATOR, so both shapes are read.
     *
     * @var array<int|string, true>
     */
    private const NAME_TOKENS = [
        T_STRING => true,
        T_NS_SEPARATOR => true,
        T_NAME_QUALIFIED => true,
        T_NAME_FULLY_QUALIFIED => true,
        T_NAME_RELATIVE => true,
    ];

    /**
     * The declaration keywords that open a class-like body. Tracked so that a
     * `use` inside one is read as importing a trait rather than a namespace:
     * only a `use` outside every class-like body is an import.
     *
     * @var array<int|string, true>
     */
    private const CLASS_LIKE_TOKENS = [
        T_CLASS => true,
        T_INTERFACE => true,
        T_TRAIT => true,
        T_ENUM => true,
    ];

    /**
     * The two shapes PHP's tokenizer gives the *opening* brace of a string
     * interpolation: T_CURLY_OPEN for `{$expr}` and T_DOLLAR_OPEN_CURLY_BRACES
     * for `${expr}`. Both are the only braces the tokenizer hands over as array
     * tokens rather than as the bare `{` every other opening brace arrives as,
     * and both are closed by a bare `}` like any other.
     *
     * They are tracked for exactly that asymmetry. Skipping them while their
     * closer is counted drives the brace depth one below the truth, which pops a
     * class-like body early and leaves a later trait `use` in that same body
     * reading as a namespace import — an alias that then misdirects an `extends`
     * onto a class in another namespace entirely. A literal brace *inside* a
     * string never reaches here in either shape: the tokenizer keeps it in the
     * T_ENCAPSED_AND_WHITESPACE or T_CONSTANT_ENCAPSED_STRING token around it.
     *
     * @var array<int|string, true>
     */
    private const INTERPOLATION_OPEN_TOKENS = [
        T_CURLY_OPEN => true,
        T_DOLLAR_OPEN_CURLY_BRACES => true,
    ];

    /**
     * The child count at which a class is reported. Inclusive — a class with
     * exactly this many direct children is already a violation, matching
     * PHPMD's `minimum` property of the same name and default.
     *
     * Untyped so that a ruleset supplying it as XML (`<property name="minimum"
     * value="8"/>`, always a string) sets it without a TypeError; it is cast
     * where it is read.
     *
     * @var int|string
     */
    public $minimum = 15;

    /**
     * Direct child counts for the current run, keyed by lower-cased fully
     * qualified parent name.
     *
     * @var array<string, int>
     */
    private array $childCounts = [];

    /**
     * The classes each scanned file declares, as lower-cased short name to
     * lower-cased fully qualified name, keyed by file path. A file cannot
     * declare two classes of the same name, so the short name is unambiguous
     * within its file.
     *
     * @var array<string, array<string, string>>
     */
    private array $declarations = [];

    /**
     * The run the two maps above describe, so a second run through the same
     * sniff instance rebuilds them rather than answering from the first one's
     * codebase. Null until the first scan.
     */
    private ?string $scannedRun = null;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        // T_CLASS alone. PHP_CodeSniffer tokenises an anonymous class as
        // T_ANON_CLASS and interfaces, traits, and enums as tokens of their
        // own, so the four PHPMD never reports on are excluded by not being
        // named here.
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $path = $phpcsFile->getFilename();

        if ($path === self::UNKNOWN_PATH) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null) {
            return;
        }

        $this->scanRun($phpcsFile, $path);

        $fullyQualified = $this->declarations[$this->realPath($path)][strtolower($name)] ?? null;

        if ($fullyQualified === null) {
            return;
        }

        $minimum = (int) $this->minimum;
        $children = $this->childCounts[$fullyQualified] ?? 0;

        if ($children < $minimum) {
            return;
        }

        $phpcsFile->addError(
            'The class %s has %s children.'
                . ' Consider to rebalance this class hierarchy to keep number of children under %s.',
            $stackPtr,
            'Found',
            [$name, $children, $minimum]
        );
    }

    /**
     * Builds the inheritance map for the run $phpcsFile belongs to, unless it
     * is already built.
     *
     * The run is identified by the paths phpcs was invoked with, which is a
     * handful of strings read straight off the Config — cheap enough to test on
     * every file, where expanding the file list would be a directory walk per
     * file rather than per run.
     *
     * When the Config carries no paths at all the run cannot be enumerated, and
     * the codebase is taken to be the file in hand. That is the same answer
     * PHPMD gives for a single-file invocation, and it keeps the sniff
     * answering from something real rather than from an empty map.
     */
    private function scanRun(File $phpcsFile, string $path): void
    {
        $roots = $this->runRoots($phpcsFile->config);
        $run = $roots === [] ? $this->realPath($path) : implode("\0", $roots);

        if ($this->scannedRun === $run) {
            return;
        }

        $this->childCounts = [];
        $this->declarations = [];
        $this->scannedRun = $run;

        foreach ($this->runFiles($phpcsFile, $roots, $path) as $file) {
            $this->scanFile($file);
        }
    }

    /**
     * The paths phpcs was invoked with, or an empty list when it was given
     * none.
     *
     * @return array<int, string>
     */
    private function runRoots(?Config $config): array
    {
        if ($config === null) {
            return [];
        }

        $files = $config->files;

        return is_array($files) === true ? array_values($files) : [];
    }

    /**
     * Every file in the run, the file being processed included.
     *
     * The processed file is added unconditionally rather than trusted to appear
     * in the expansion. It is being linted, so it belongs to the codebase by
     * definition, and the two ways it could be absent — a Config carrying no
     * paths, or a file reached by a route the expansion does not reproduce —
     * would otherwise leave a class counting none of its own file's children.
     * Paths are de-duplicated by their resolved form, so appearing in both is
     * not counted twice.
     *
     * @param array<int, string> $roots
     *
     * @return array<int, string>
     */
    private function runFiles(File $phpcsFile, array $roots, string $path): array
    {
        $files = [$path];

        if ($roots !== []) {
            foreach (new FileList($phpcsFile->config, $phpcsFile->ruleset) as $listed => $ignored) {
                $files[] = $listed;
            }
        }

        return $files;
    }

    /**
     * Reads one file and folds its declarations and its `extends` edges into
     * the run's maps.
     *
     * A file that cannot be read, or that has already been read under this
     * path, contributes nothing.
     */
    private function scanFile(string $path): void
    {
        $resolved = $this->realPath($path);

        if (isset($this->declarations[$resolved]) === true) {
            return;
        }

        $source = @file_get_contents($resolved);

        if ($source === false) {
            return;
        }

        $this->declarations[$resolved] = [];

        $this->parse($source, $resolved);
    }

    /**
     * Walks one file's tokens, recording the classes it declares and
     * incrementing the child count of every class it extends.
     */
    private function parse(string $source, string $path): void
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $aliases = [];
        $bodies = [];
        $depth = 0;

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (is_array($token) === false) {
                $depth = $this->trackBrace($token, $depth, $bodies);

                continue;
            }

            // The opening brace of a string interpolation, which is the one
            // opening brace the tokenizer does not hand over as a bare `{`. Its
            // closer is bare, so it is counted here to keep the pair balanced.
            if (isset(self::INTERPOLATION_OPEN_TOKENS[$token[0]]) === true) {
                $depth++;

                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $index);
                $aliases = [];

                continue;
            }

            if ($token[0] === T_USE && $bodies === []) {
                $aliases += $this->readImports($tokens, $index);

                continue;
            }

            if (isset(self::CLASS_LIKE_TOKENS[$token[0]]) === false) {
                continue;
            }

            // `Type::class` declares nothing and opens no body. Recording one
            // for it would swallow every import that followed, because a `use`
            // inside a class-like body is a trait's and not an import.
            if ($token[0] === T_CLASS && $this->isClassConstant($tokens, $index) === true) {
                continue;
            }

            $bodies[] = $depth;

            // Interfaces, traits, enums, and anonymous classes have bodies that
            // have to be tracked, but none of the four declares a name this
            // rule counts children for.
            if ($token[0] !== T_CLASS || $this->isAnonymous($tokens, $index) === true) {
                continue;
            }

            $this->readClass($tokens, $index, $namespace, $aliases, $path);
        }
    }

    /**
     * The brace depth after $brace, with any class-like body it closes popped
     * off $bodies.
     *
     * A class-like body is recorded at the depth its declaration sat at, so it
     * is closed by the brace that returns the walk to that depth.
     *
     * @param array<int, int> $bodies
     */
    private function trackBrace(string $brace, int $depth, array &$bodies): int
    {
        if ($brace === '{') {
            return ($depth + 1);
        }

        if ($brace !== '}') {
            return $depth;
        }

        $depth--;

        while ($bodies !== [] && end($bodies) >= $depth) {
            array_pop($bodies);
        }

        return $depth;
    }

    /**
     * Whether the T_CLASS at $index is the `class` of `Type::class`.
     *
     * PHP's tokenizer gives the constant the same T_CLASS token as a
     * declaration, and it is the only one of the three T_CLASS shapes that
     * opens no body at all.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isClassConstant(array $tokens, int $index): bool
    {
        return $this->isPrecededBy($tokens, $index, T_DOUBLE_COLON);
    }

    /**
     * Whether the T_CLASS at $index opens an anonymous class.
     *
     * An anonymous class has no name for a child to extend, and a live PHPMD
     * 2.15.0 run does not count one as a child of the class it extends, so it
     * contributes neither a declaration nor an edge — only a body to track,
     * since a trait `use` can sit inside it.
     *
     * `new readonly class` puts the modifier between the two tokens and so
     * reads as named here; it is caught a step later instead, where a
     * declaration with no name is dropped.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isAnonymous(array $tokens, int $index): bool
    {
        return $this->isPrecededBy($tokens, $index, T_NEW);
    }

    /**
     * Whether the significant token before $index is $code.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isPrecededBy(array $tokens, int $index, int $code): bool
    {
        $previous = $this->significantBefore($tokens, $index);

        if ($previous === null || is_array($previous) === false) {
            return false;
        }

        return $previous[0] === $code;
    }

    /**
     * Records the class declared at $index and, when it extends something, the
     * edge to its parent.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, string>                               $aliases
     */
    private function readClass(array $tokens, int $index, string $namespace, array $aliases, string $path): void
    {
        $cursor = $index;
        $name = $this->readName($tokens, $cursor);

        if ($name === '') {
            return;
        }

        $qualified = $namespace === '' ? $name : $namespace . '\\' . $name;
        $this->declarations[$path][strtolower($name)] = strtolower($qualified);

        $next = $this->significantAfter($tokens, $cursor);

        if ($next === null || is_array($next) === false || $next[0] !== T_EXTENDS) {
            return;
        }

        // Onto the `extends` itself, so the name read next is the parent's and
        // not the keyword the cursor is still sitting before.
        $this->significantIndexAfter($tokens, $cursor);

        $parent = $this->readName($tokens, $cursor);

        if ($parent === '') {
            return;
        }

        $resolved = $this->resolve($parent, $namespace, $aliases);
        $this->childCounts[$resolved] = ($this->childCounts[$resolved] ?? 0) + 1;
    }

    /**
     * The namespace declared at $index, as a name with no leading separator.
     *
     * An anonymous `namespace { … }` block, which has no name, resolves to the
     * global namespace — the empty string, which is what an unnamespaced file
     * already carries.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readNamespace(array $tokens, int $index): string
    {
        $cursor = $index;

        return trim($this->readName($tokens, $cursor), '\\');
    }

    /**
     * The imports of one `use` statement, as lower-cased alias to
     * fully-qualified name.
     *
     * Reads the three shapes that import a class — a plain `use A\B;`, an
     * aliased `use A\B as C;`, and a group `use A\{B, C as D};` — and skips the
     * two that do not: `use function` / `use const`, and a closure's `use (…)`,
     * which is not an import at all. A trait's `use` never reaches here,
     * because the caller only asks outside a class-like body.
     *
     * $index is left on the last token read, so the caller's walk resumes after
     * the statement rather than re-entering it — a group's braces would
     * otherwise be counted as nesting by the brace tracker.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array<string, string>
     */
    private function readImports(array $tokens, int &$index): array
    {
        $next = $this->significantAfter($tokens, $index);

        if ($next === null) {
            return [];
        }

        // A closure's `use (…)` binds variables and imports nothing.
        if (is_array($next) === false) {
            return [];
        }

        if (in_array($next[0], [T_FUNCTION, T_CONST], true) === true) {
            return [];
        }

        return $this->readImportList($tokens, $index, '');
    }

    /**
     * The imports of the statement starting at $index, each prefixed with
     * $prefix.
     *
     * One loop reads both a comma-separated list of imports and the inside of a
     * group's braces, because the two are the same grammar: the group is
     * re-entered with its prefix, and the brace that closes it ends the inner
     * read.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array<string, string>
     */
    private function readImportList(array $tokens, int &$index, string $prefix): array
    {
        $imports = [];

        while ($index < count($tokens)) {
            $name = $this->readName($tokens, $index);

            if ($name === '') {
                return $imports;
            }

            $next = $this->significantAfter($tokens, $index);

            if ($next === '{') {
                $this->significantIndexAfter($tokens, $index);
                $imports += $this->readImportList($tokens, $index, $prefix . $name);

                // Step over the brace that closed the group, so the caller's
                // brace tracker never sees an opening it did not see a match
                // for and drives its depth negative.
                if ($this->significantAfter($tokens, $index) === '}') {
                    $this->significantIndexAfter($tokens, $index);
                }

                continue;
            }

            $imports += $this->readImport($tokens, $index, $prefix . $name);
            $next = $this->significantAfter($tokens, $index);

            if ($next !== ',') {
                return $imports;
            }

            $this->significantIndexAfter($tokens, $index);
        }

        return $imports;
    }

    /**
     * One import's alias-to-target pair, reading an `as` clause when there is
     * one and falling back to the name's last segment when there is not.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array<string, string>
     */
    private function readImport(array $tokens, int &$index, string $name): array
    {
        $target = trim($name, '\\');
        $next = $this->significantAfter($tokens, $index);
        $alias = $target;

        if (is_array($next) === true && $next[0] === T_AS) {
            $this->significantIndexAfter($tokens, $index);
            $alias = $this->readName($tokens, $index);
        }

        if ($alias === '') {
            return [];
        }

        $segments = explode('\\', $alias);

        return [strtolower((string) end($segments)) => strtolower($target)];
    }

    /**
     * The fully-qualified, lower-cased form of a name written inside
     * $namespace with $aliases in scope.
     *
     * @param array<string, string> $aliases
     */
    private function resolve(string $name, string $namespace, array $aliases): string
    {
        if (str_starts_with($name, '\\') === true) {
            return strtolower(ltrim($name, '\\'));
        }

        $segments = explode('\\', $name);
        $first = strtolower((string) reset($segments));

        if ($first === 'namespace') {
            array_shift($segments);

            return strtolower(trim($namespace . '\\' . implode('\\', $segments), '\\'));
        }

        if (isset($aliases[$first]) === true) {
            array_shift($segments);

            return strtolower(trim($aliases[$first] . '\\' . implode('\\', $segments), '\\'));
        }

        return strtolower(trim($namespace . '\\' . $name, '\\'));
    }

    /**
     * The name starting at the first significant token after $index, with
     * $index left on the name's last token.
     *
     * A name is a run of the tokens PHP splits one into, which is a single
     * T_NAME_* token in PHP 8 and a run of T_STRING and T_NS_SEPARATOR wherever
     * the tokenizer still splits it.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readName(array $tokens, int &$index): string
    {
        $name = '';
        $cursor = $index;

        while (true) {
            $next = $this->significantIndexAfter($tokens, $cursor, false);

            if ($next === null) {
                break;
            }

            $token = $tokens[$next];

            if (is_array($token) === false || isset(self::NAME_TOKENS[$token[0]]) === false) {
                break;
            }

            $name .= $token[1];
            $cursor = $next;
        }

        $index = $cursor;

        return $name;
    }

    /**
     * The first significant token after $index, or null at the end of the file.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    private function significantAfter(array $tokens, int $index)
    {
        $next = $this->significantIndexAfter($tokens, $index, false);

        return $next === null ? null : $tokens[$next];
    }

    /**
     * The first significant token before $index, or null at the start of the
     * file.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    private function significantBefore(array $tokens, int $index)
    {
        for ($cursor = ($index - 1); $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (is_array($token) === false || isset(self::SKIPPED_TOKENS[$token[0]]) === false) {
                return $token;
            }
        }

        return null;
    }

    /**
     * The index of the first significant token after $index, advancing $index
     * onto it when $advance is true.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function significantIndexAfter(array $tokens, int &$index, bool $advance = true): ?int
    {
        for ($cursor = ($index + 1); $cursor < count($tokens); $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) === true && isset(self::SKIPPED_TOKENS[$token[0]]) === true) {
                continue;
            }

            if ($advance === true) {
                $index = $cursor;
            }

            return $cursor;
        }

        return null;
    }

    /**
     * The path in the form the maps are keyed by.
     *
     * realpath() is what makes the same file reached by two routes — an
     * absolute path from the file list and a relative one from the command line
     * — one entry rather than two, which is what stops its classes being
     * counted twice. A path it cannot resolve is kept as written; it is then
     * its own key, which is the behaviour a file that does not exist should
     * have.
     */
    private function realPath(string $path): string
    {
        $resolved = realpath($path);

        return $resolved === false ? $path : $resolved;
    }
}
