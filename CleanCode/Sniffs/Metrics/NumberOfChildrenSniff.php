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
 * ## The map is read from disk, and the sniff is handed a token stream
 *
 * Those are the same bytes for a plain `phpcs` run, and not for a phpcbf one:
 * the fixer works in memory and writes at the end, so from its second loop on,
 * the stream holds lines the file on disk does not. `--stdin-path` opens the
 * same gap for an editor linting an unsaved buffer. A subject looked up at a
 * line the map does not hold it at is simply not found, and a parent over the
 * threshold then goes unreported — so the file under test is re-read from its
 * own stream whenever the two differ. See refreshFile().
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
 * What the two readers of a file do still have to agree on is which declaration
 * is which, and that is deliberately the smallest thing it could be: the line
 * the `class` keyword sits on, and the declaration's position among the
 * same-named ones on that line. See $declarations and declarationOrdinal().
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
     * The classes each scanned file declares, keyed by file path, then by the
     * line the `class` keyword sits on, then by lower-cased short name, to the
     * lower-cased fully qualified names declared there.
     *
     * The line is part of the key because a short name is *not* unique within a
     * file. Braced namespace blocks are legal PHP, and
     * `namespace A { class Foo {} } namespace B { class Foo {} }` declares two
     * different classes both called `Foo`. Keyed on the short name alone the
     * second overwrote the first, and process() then read back the wrong fully
     * qualified name — so a genuine violation in the first block went
     * unreported.
     *
     * The line and the short name are also all PHP_CodeSniffer gives process()
     * to identify the class it was handed, which is why the map is keyed by
     * exactly those two and not by the fully qualified name it is looking up.
     *
     * The value is a list, in source order, rather than one name: the line does
     * not separate two same-named declarations whose `class` keywords share a
     * physical line, which the same two namespace blocks written on one line
     * produce. Nothing in PHP forbids that either, so it is not assumed away —
     * declarationOrdinal() picks the entry out by source order.
     *
     * @var array<string, array<int, array<string, array<int, string>>>>
     */
    private array $declarations = [];

    /**
     * The `extends` edges each scanned file contributed to $childCounts, keyed
     * by file path, then by lower-cased fully qualified parent name, to the
     * number of children that file declares for it.
     *
     * Kept so that a file can be read a second time without its first reading
     * being counted twice: $childCounts is one total per parent, with nothing
     * in it recording which file each child came from, so a re-read has to be
     * able to take its own earlier contribution back out. See refreshFile().
     *
     * @var array<string, array<string, int>>
     */
    private array $edges = [];

    /**
     * The digest of the source each scanned file was read from, keyed by file
     * path, so that a file whose source has not changed is not read again.
     *
     * md5 for identity, not for security: it is comparing two strings this
     * process already holds, and the alternative is keeping every file's whole
     * source in memory for the length of the run.
     *
     * @var array<string, string>
     */
    private array $digests = [];

    /**
     * The run the two maps above describe, so a second run through the same
     * sniff instance rebuilds them rather than answering from the first one's
     * codebase. Null until the first scan.
     */
    private ?string $scannedRun = null;

    /**
     * The token stream the file under test was last checked against, so the
     * check is paid once per stream rather than once per class declaration in
     * it.
     *
     * Keyed like $ordinalsKey, and for the same reason: the file, its token
     * count, and the fixer's loop counter, because phpcbf re-tokenises and
     * re-runs every sniff against the same File object once another sniff has
     * fixed something.
     *
     * @var string|null
     */
    private ?string $refreshedStream = null;

    /**
     * The token stream $ordinals was built from, so that it is discarded when
     * the stream changes and its pointers could mean something else.
     *
     * The same key CleanCode.DeadCode.UnusedFormalParameter and
     * CleanCode.Arrays.ArrayAccessors build for their own per-stream maps: the
     * file, its token count, and the fixer's loop counter. phpcbf re-tokenises
     * and re-runs every sniff against the *same* File object once another
     * sniff has fixed something, so neither the object nor the path identifies
     * a stream on its own. The token count moves whenever a fix moves a
     * pointer, and the loop counter states the pass outright rather than
     * inferring it from that count.
     *
     * @var string|null
     */
    private ?string $ordinalsKey = null;

    /**
     * Which of the same-named declarations on its line each class declaration
     * of the current token stream is, keyed by its PHP_CodeSniffer pointer.
     *
     * Built in one forward pass over the stream rather than re-derived per
     * declaration; see declarationOrdinal().
     *
     * @var array<int, int>
     */
    private array $ordinals = [];

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
        $this->refreshFile($phpcsFile, $path);

        $line = $phpcsFile->getTokens()[$stackPtr]['line'];
        $candidates = $this->declarations[$this->realPath($path)][$line][strtolower($name)] ?? [];
        $fullyQualified = $candidates[$this->declarationOrdinal($phpcsFile, $stackPtr)] ?? null;

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
     * Which of the same-named declarations recorded for this line this one is:
     * the number of classes of the same name PHP_CodeSniffer has already passed
     * on it.
     *
     * A line holds one class and this is zero, until it does not.
     * `namespace A { class Foo {} } namespace B { class Foo {} }` is legal PHP,
     * and written on one line it puts two different classes called Foo on the
     * same line of the same file. Source order is the one thing the two readers
     * of that file can both see, so it is what tells the pair apart.
     *
     * They agree on what to count because they enumerate the same declarations.
     * PHP_CodeSniffer hands this sniff T_CLASS, which it gives to a named class
     * declaration and to nothing else — an anonymous class is T_ANON_CLASS and
     * the `class` of `Type::class` is a T_STRING — and the scan records that
     * same set, excluding both shapes explicitly.
     *
     * The answer is read out of an index built once per token stream, not
     * counted here. It used to be counted here, by walking back from $stackPtr
     * over every token sharing the class's physical line — which the i-th
     * declaration on a line pays O(i) for, and K of them pay O(K²) for
     * together. The names are compared only once a T_CLASS is found, so the
     * walk was paid whether or not a line held two declarations of one name,
     * and nothing bounds K: `class C0{}class C1{}…` on one line is ordinary
     * PHP, and 8,000 of them are 100KB that took the shipped binary 34.3s
     * against 0.52s indexed. Anything running this ruleset over source it did
     * not write — a CI job on a pull request, a pre-commit hook, a lint service
     * — is handed that file by whoever wrote it.
     *
     * A pointer absent from the index reads as the first declaration on its
     * line, which is what the walk returned when it found no same-named
     * predecessor. Nothing reaches it: the index holds every pointer
     * PHP_CodeSniffer hands process(), because both take the name from
     * getDeclarationName() over the same stream and process() has already
     * dropped the ones that have none.
     *
     * @see buildOrdinals() for the single pass that replaced the walk.
     */
    private function declarationOrdinal(File $phpcsFile, int $stackPtr): int
    {
        $this->buildOrdinals($phpcsFile);

        return $this->ordinals[$stackPtr] ?? 0;
    }

    /**
     * Indexes the ordinal of every class declaration in the file, once per
     * token stream.
     *
     * The walk reaches the declarations in the one order PHP_CodeSniffer hands
     * them to process(), so a running count per line and short name gives each
     * one the same ordinal a backward scan from it would have — for the cost of
     * a single forward pass over the stream rather than one pass per
     * declaration.
     *
     * A declaration with no name takes no ordinal, because process() drops one
     * before it asks and the scan records none for it either.
     */
    private function buildOrdinals(File $phpcsFile): void
    {
        $tokens = $phpcsFile->getTokens();
        $key = $phpcsFile->getFilename()
            . '|' . count($tokens)
            . '|' . ($phpcsFile->fixer->loops ?? 0);

        if ($this->ordinalsKey === $key) {
            return;
        }

        $this->ordinalsKey = $key;
        $this->ordinals = [];
        $counts = [];
        $pointer = $phpcsFile->findNext(T_CLASS, 0);

        while ($pointer !== false) {
            $name = $phpcsFile->getDeclarationName($pointer);

            if ($name !== null) {
                $slot = $tokens[$pointer]['line'] . '|' . strtolower($name);
                $ordinal = ($counts[$slot] ?? 0);
                $this->ordinals[$pointer] = $ordinal;
                $counts[$slot] = ($ordinal + 1);
            }

            $pointer = $phpcsFile->findNext(T_CLASS, ($pointer + 1));
        }
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
        $this->edges = [];
        $this->digests = [];
        $this->refreshedStream = null;
        $this->scannedRun = $run;

        foreach ($this->runFiles($phpcsFile, $roots, $path) as $file) {
            $this->scanFile($file);
        }
    }

    /**
     * Reads the file under test again when the token stream PHP_CodeSniffer is
     * processing is no longer the source the run's scan read for it.
     *
     * scanRun() reads every file of the run from disk, and process() looks its
     * subject up by the line the stream puts the `class` keyword on. The two
     * agree only while the stream and the disk hold the same bytes, and there
     * are two ordinary ways they do not:
     *
     * - **phpcbf.** The fixer applies its fixes in memory and re-runs every
     *   sniff against the fixed stream, writing to disk only at the end. Any
     *   fixable sniff in the same ruleset that adds or removes a line above a
     *   class — the master ruleset's own DeclareStrictTypes inserts one —
     *   moves that class's declaration line for every later loop. Looked up at
     *   its new line against a map still holding its old one, the class is not
     *   found, and a parent over the threshold goes unreported: phpcbf tells
     *   the user the file is clean while `phpcs` on the same fixed file
     *   reports it.
     * - **`--stdin-path`.** The editor integrations that lint a buffer hand
     *   PHP_CodeSniffer the buffer's contents under the real file's path, and
     *   an unsaved buffer is not what is on disk.
     *
     * So the stream is what the sniff answers from: its source is rebuilt from
     * the tokens, and the file is re-read from *that* whenever it differs from
     * what was scanned. The earlier reading's edges are taken back out first —
     * $childCounts holds one total per parent, so a re-read that only added
     * would count this file's children twice.
     *
     * Only the file being linted is re-read. The rest of the run keeps the
     * counts the scan read for it, which a fix cannot move: a count is keyed by
     * a parent's name, and moving one would take a fixer that renames a class
     * or rewrites an `extends` clause.
     *
     * Two guards keep this off the hot path, and neither changes what the sniff
     * reports — no test below distinguishes them, because nothing outside this
     * method can. They are here for their cost and are described as such.
     *
     * The stream key skips a stream already checked, so the work is done once
     * per file rather than once per class declaration in it — which is the
     * shape declarationOrdinal() was rewritten to stop paying. The digest then
     * skips the re-read itself in the ordinary case, a `phpcs` run over
     * unedited files, where the stream and the scan hold the same bytes:
     * rebuilding the source string and hashing it costs no tokenizer pass,
     * where re-reading unconditionally would tokenize every file of the run a
     * second time.
     */
    private function refreshFile(File $phpcsFile, string $path): void
    {
        $tokens = $phpcsFile->getTokens();
        $key = $path . '|' . count($tokens) . '|' . ($phpcsFile->fixer->loops ?? 0);

        if ($this->refreshedStream === $key) {
            return;
        }

        $this->refreshedStream = $key;
        $resolved = $this->realPath($path);
        $source = $this->streamSource($tokens);
        $digest = md5($source);

        if (($this->digests[$resolved] ?? null) === $digest) {
            return;
        }

        $this->forget($resolved);
        $this->declarations[$resolved] = [];
        $this->digests[$resolved] = $digest;

        $this->parse($source, $resolved);
    }

    /**
     * The source a token stream was tokenized from.
     *
     * The concatenated token contents are the file, byte for byte: it is how
     * PHP_CodeSniffer's own Fixer reconstructs what it writes back to disk, and
     * this reads the stream exactly as Fixer::startFile() does — original
     * content first, because a tab expanded by --tab-width leaves the expansion
     * in `content` and the tab itself in `orig_content`.
     *
     * The tokens are read rather than Fixer::getContents(), which is the same
     * string only between loops. Within one, a fix another sniff has already
     * staged is in the fixer's contents and not yet in the stream, and it is
     * the stream that process() takes its line numbers from.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function streamSource(array $tokens): string
    {
        $source = '';

        foreach ($tokens as $token) {
            $source .= (string) ($token['orig_content'] ?? $token['content']);
        }

        return $source;
    }

    /**
     * Takes one file's reading back out of the run's maps, leaving the run as
     * though the file had never been read.
     *
     * A parent left with no children at all is dropped rather than kept at
     * zero, which is the state a run that never saw the file would be in.
     */
    private function forget(string $path): void
    {
        foreach (($this->edges[$path] ?? []) as $parent => $count) {
            $remaining = (($this->childCounts[$parent] ?? 0) - $count);

            if ($remaining > 0) {
                $this->childCounts[$parent] = $remaining;

                continue;
            }

            unset($this->childCounts[$parent]);
        }

        unset($this->edges[$path], $this->declarations[$path], $this->digests[$path]);
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

        if ($roots === []) {
            return $files;
        }

        return array_merge(
            $files,
            $this->listedPaths(new FileList($phpcsFile->config, $phpcsFile->ruleset))
        );
    }

    /**
     * The paths a run's file list holds, taken without building a file for any
     * of them.
     *
     * The list is walked by key() rather than with foreach, because only the
     * paths are wanted. foreach asks an iterator for its current *value* on
     * every step whether or not the loop body uses it, and FileList::current()
     * builds a LocalFile for the path — which reads the whole file from disk in
     * its constructor. Every one of those objects would be discarded here, after
     * a read this class then repeats for itself in scanFile(): two reads of
     * every file in the run where one is wanted. valid() and key() consult the
     * underlying array directly and construct nothing.
     *
     * The walk is its own method, taking the list rather than making it, so
     * that a test can hand it a list that records what it is asked for and
     * hold this to asking for keys only — which is the whole of the fix, and
     * is otherwise invisible from outside.
     *
     * @return array<int, string>
     */
    private function listedPaths(FileList $listed): array
    {
        $paths = [];

        for ($listed->rewind(); $listed->valid() === true; $listed->next()) {
            $paths[] = (string) $listed->key();
        }

        return $paths;
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
        $this->digests[$resolved] = md5($source);

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
        $this->declarations[$path][$tokens[$index][2]][strtolower($name)][] = strtolower($qualified);

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
        $this->childCounts[$resolved] = (($this->childCounts[$resolved] ?? 0) + 1);
        $this->edges[$path][$resolved] = (($this->edges[$path][$resolved] ?? 0) + 1);
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
     * $index is left on the semicolon that ends the statement, so the caller's
     * walk resumes after it rather than re-entering it — a group's braces would
     * otherwise be counted as nesting by the brace tracker. A statement with no
     * terminating semicolon has no such token to stop on: skipStatement() then
     * leaves $index exactly where it was, and the walk resumes inside the
     * statement rather than after it. Only a truncated or otherwise
     * non-compiling source reaches that, since every `use` import PHP accepts
     * ends in a semicolon.
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

        // A closure's `use (…)` binds variables and imports nothing. Its
        // statement is not skipped either, because the body that follows it can
        // declare a class of its own.
        if (is_array($next) === false) {
            return [];
        }

        if (in_array($next[0], [T_FUNCTION, T_CONST], true) === true) {
            return [];
        }

        $imports = $this->readImportList($tokens, $index);
        $this->skipStatement($tokens, $index);

        return $imports;
    }

    /**
     * The imports of the statement starting at $index.
     *
     * One loop reads both a comma-separated list of imports and the inside of a
     * group's braces, because the two are the same grammar with a prefix in
     * front of it. The prefix is taken once, and a `{` met while a group is
     * already open is read as an ordinary name rather than opening a second:
     * PHP's grammar allows exactly one level of group braces, so anything deeper
     * is source PHP itself would refuse to compile.
     *
     * That single level is what keeps this a loop rather than a recursion.
     * token_get_all() *lexes* `use A\{A\{A\{…` happily — it never requires the
     * braces to close or to form valid grammar — so a reader that recursed on
     * every `{` was a memory exhaustion away from any file that opened enough of
     * them, and a 60KB one was enough to end the whole phpcs run. PHP_CodeSniffer
     * caps its own scope recursion for the same reason
     * (Tokenizer::recurseScopeMap, depth > 50), and the sibling
     * CouplingBetweenObjectsSniff::readImportGroup reads this same grammar
     * without recursing.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array<string, string>
     */
    private function readImportList(array $tokens, int &$index): array
    {
        $imports = [];
        $prefix = '';

        while ($index < count($tokens)) {
            $name = $this->readName($tokens, $index);

            if ($name === '') {
                return $imports;
            }

            if ($prefix === '' && $this->significantAfter($tokens, $index) === '{') {
                $this->significantIndexAfter($tokens, $index);
                $prefix = $name;

                continue;
            }

            $imports += $this->readImport($tokens, $index, $prefix . $name);

            if ($this->significantAfter($tokens, $index) !== ',') {
                return $imports;
            }

            $this->significantIndexAfter($tokens, $index);
        }

        return $imports;
    }

    /**
     * Leaves $index on the semicolon that ends the `use` statement it is inside.
     *
     * The statement is stepped over whole rather than walked out of, so none of
     * its tokens reach the brace tracker: a group import's braces belong to the
     * import and not to a class body, and a malformed one whose brace never
     * closes would otherwise raise the depth for the rest of the file. A `use`
     * import holds no semicolon of its own, so the next one is always its
     * terminator; a source that is truncated before it leaves $index where it
     * already is rather than running off the end of the file.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function skipStatement(array $tokens, int &$index): void
    {
        for ($cursor = $index; $cursor < count($tokens); $cursor++) {
            if ($tokens[$cursor] === ';') {
                $index = $cursor;

                return;
            }
        }
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
