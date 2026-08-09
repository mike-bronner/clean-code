<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags repeating blocks of near-identical code within one file.
 *
 * Partial enforcement of the "Pattern: Don't Repeat Yourself (DRY)" standard
 * (docs/standards/pattern-dont-repeat-yourself-dry.md, #4), spun out as #134.
 * DRY is ultimately about duplicated *knowledge*, which no token walk can see —
 * but a copy-pasted run of lines inside one file is token-visible, and that
 * slice is what this sniff reports.
 *
 * Reports a **warning**, never an error. The standard explicitly tolerates
 * duplication until an abstraction is warranted ("don't abstract prematurely"),
 * so the sniff points at abstraction candidates rather than mandating a fix.
 *
 * Detection only. The remedy — extract the shared logic and rewrite both call
 * sites — is a design change with no mechanical rewrite.
 *
 * How the comparison works:
 *
 * - The file is reduced to its **code lines**: every line carrying at least one
 *   token that is not whitespace, a comment, an open/close tag, inline HTML, or
 *   bare structural punctuation. Blank and comment-only lines vanish entirely,
 *   so reformatting or re-commenting a copy-paste does not hide it, and they
 *   never pad a block up to the threshold either. Nor do the lines holding
 *   nothing but a brace, bracket, or `);`: PSR-12 gives braces lines of their
 *   own, and counting them would let three lines of logic clear a five-line
 *   threshold.
 * - Each code line is summarized by the **types** of its tokens, in order.
 *   Token *content* is deliberately dropped, which is what makes the match
 *   near-identical rather than exact: a renamed variable, a renamed method or
 *   class, and a changed literal all leave the summary unchanged.
 * - Every token type still discriminates, so code that differs in *structure*
 *   does not match: a swapped operator (`+` is T_PLUS, `-` is T_MINUS), a
 *   different keyword, an extra argument, or an added statement all change the
 *   summary. Those are genuinely different blocks, not superficially renamed
 *   ones, and the standard's remedy — extract the shared logic — does not
 *   apply to them.
 * - Blocks are compared as sliding windows of $minimumLines consecutive code
 *   lines, so a duplicate is found wherever it sits: inside one function, split
 *   across two methods, or spanning a declaration boundary. Whole duplicated
 *   method bodies are simply the case where the window happens to fill a body.
 *
 * Scope decisions:
 *
 * - **Same file only.** A PHPCS sniff sees one file's tokens at a time.
 *   Project-wide copy/paste detection is the domain of a dedicated detector
 *   such as `phpcpd`.
 * - **Non-overlapping copies only.** Two windows that share code lines are one
 *   run of similar lines, not two blocks, so a long column of same-shaped
 *   statements is not reported against a shifted copy of itself. A repeat is
 *   reported only once its distance from the original is at least the window
 *   length, and a matched block is then extended only as far as it can grow
 *   without touching the original.
 * - **One warning per copy.** A block that repeats a run longer than the
 *   window is reported once, at its first code line, with the run's real
 *   length — not once per window inside it.
 *
 * Cost. Summarizing the file is one walk of its tokens. Each window key is
 * built from at most $minimumLines interned line ids, and the greedy extension
 * skips the windows inside a block it has already reported, so the scan is
 * linear in the file's size for any configured window: the window length comes
 * from the ruleset, never from the file being analyzed.
 */
class AvoidDuplicateCodeBlocksSniff implements Sniff
{
    /**
     * Tokens that carry no PHP code, on top of PHP_CodeSniffer's own empty set
     * (whitespace, comments, doc comments, annotations).
     *
     * Inline HTML and the tags around it are template output rather than logic,
     * so a repeated markup block is not the duplication this standard is about.
     *
     * @var array<int, int|string>
     */
    private const NON_CODE_TOKENS = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_INLINE_HTML];

    /**
     * The tokens a file's PHP can open on, which is both what the sniff
     * registers for and what it looks back for to know it has already run.
     *
     * @var array<int, int|string>
     */
    private const OPEN_TAGS = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];

    /**
     * The punctuation that structures code without being code itself, listed as
     * the opener/closer pairs it comes in.
     *
     * A line built entirely from these — `{`, `}`, `];`, `);`, `,` — is a
     * layout artefact of PSR-12, not a line of logic, and is dropped from the
     * comparison so it cannot pad a block up to the threshold.
     *
     * @var array<int, int|string>
     */
    private const DELIMITER_TOKENS = [
        T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET,
        T_OPEN_PARENTHESIS, T_CLOSE_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET, T_CLOSE_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY, T_CLOSE_SHORT_ARRAY,
        T_COMMA, T_SEMICOLON,
    ];

    /**
     * How many consecutive code lines a block must span before it is compared.
     *
     * Defaults to 5, the length the standard's own guidance calls out, and is
     * floored at 1. Blank and comment-only lines are not code lines and never
     * count towards it.
     *
     * Deliberately untyped. PHP_CodeSniffer assigns a `<property>` value from a
     * ruleset verbatim, as the string it read from the XML, so a native `int`
     * declaration would turn a mistyped threshold into an uncatchable TypeError
     * rather than a lint run. The value is cast where it is read, and floored
     * at 1.
     *
     * That floor is not cosmetic. A window of no lines matches everywhere and
     * grows without ever differing, and the scan advances by the length of the
     * block it just reported — so a zero, a negative, or any value that casts
     * to one of them would leave the walk running backwards over the same file
     * until it exhausted memory. A mistyped threshold has to report too much,
     * never fail to return.
     *
     * @var int|string
     */
    public $minimumLines = 5;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return self::OPEN_TAGS;
    }

    /**
     * Runs once per file, at its first PHP open tag, because duplication is a
     * property of the file rather than of any one token. Driving the whole scan
     * from a single dispatch keeps the sniff stateless: a per-file map built
     * across calls would have to be invalidated by hand, and would carry
     * entries from phpcbf's first pass into its second, where the *original*
     * block would then look like a duplicate of itself.
     *
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->findPrevious(self::OPEN_TAGS, ($stackPtr - 1)) !== false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $minimumLines = max(1, (int) $this->minimumLines);
        [$lineShapes, $anchors] = $this->summarizeCodeLines($tokens);

        foreach ($this->findRepeatedBlocks($lineShapes, $minimumLines) as $block) {
            $phpcsFile->addWarning(
                'This block of code, through line %d, repeats the block starting on line %d,'
                    . ' ignoring variable, literal, and identifier names. Extract the shared logic'
                    . ' once the duplication has earned an abstraction (see'
                    . ' docs/standards/pattern-dont-repeat-yourself-dry.md).',
                $anchors[$block['start']],
                'Found',
                [
                    $tokens[$anchors[($block['start'] + $block['length']) - 1]]['line'],
                    $tokens[$anchors[$block['origin']]]['line'],
                ]
            );
        }
    }

    /**
     * Reduces the file to one entry per code line: a shape id summarizing the
     * types of that line's tokens, and the pointer to the line's first token.
     *
     * Lines carrying the same token types share an id, which is what makes a
     * window comparison a comparison of small integers rather than of
     * concatenated token streams. Ids are per file and mean nothing outside it.
     *
     * A line whose every token is structural punctuation is dropped: it is
     * layout, not logic, and both returned lists skip it together so an
     * anchor always belongs to the shape beside it. The shape of a line that
     * *is* kept still counts its punctuation, so `foo($a, $b);` and
     * `foo($a, $b, $c);` stay different.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array{array<int, int>, array<int, int>}
     */
    private function summarizeCodeLines(array $tokens): array
    {
        $ignored = Tokens::$emptyTokens + array_fill_keys(self::NON_CODE_TOKENS, true);
        $delimiters = array_fill_keys(self::DELIMITER_TOKENS, true);
        $summaries = [];
        $line = null;

        foreach ($tokens as $pointer => $token) {
            if (isset($ignored[$token['code']]) === true) {
                continue;
            }

            if ($token['line'] !== $line) {
                $line = $token['line'];
                $summaries[$line] = ['anchor' => $pointer, 'shape' => '', 'code' => false];
            }

            $summaries[$line]['shape'] .= $token['code'] . ',';
            $summaries[$line]['code'] = $summaries[$line]['code']
                || isset($delimiters[$token['code']]) === false;
        }

        $shapes = [];
        $lineShapes = [];
        $anchors = [];

        foreach ($summaries as $summary) {
            if ($summary['code'] === false) {
                continue;
            }

            $lineShapes[] = $shapes[$summary['shape']] ??= count($shapes);
            $anchors[] = $summary['anchor'];
        }

        return [$lineShapes, $anchors];
    }

    /**
     * Every repeated block, as {start, origin, length} in code-line indices:
     * the copy starts at `start`, repeats the block starting at `origin`, and
     * both run for `length` code lines.
     *
     * A window is remembered the first time its shape is seen; a later window
     * with the same shape is a copy of it. Two guards keep one duplication from
     * becoming a cascade of reports:
     *
     * - A copy closer to its original than the window length overlaps it, which
     *   makes it one run of same-shaped lines rather than two blocks.
     * - Once a copy is found it is extended line by line, as far as it can grow
     *   without reaching back into its original, and the scan then resumes past
     *   the block — so the windows inside it are not reported again.
     *
     * @param array<int, int> $lineShapes
     *
     * @return array<int, array{start: int, origin: int, length: int}>
     */
    private function findRepeatedBlocks(array $lineShapes, int $minimumLines): array
    {
        $blocks = [];
        $firstSeenAt = [];
        $windowCount = (count($lineShapes) - $minimumLines) + 1;

        for ($index = 0; $index < $windowCount; $index++) {
            $window = implode(',', array_slice($lineShapes, $index, $minimumLines));

            if (isset($firstSeenAt[$window]) === false) {
                $firstSeenAt[$window] = $index;

                continue;
            }

            $origin = $firstSeenAt[$window];

            if (($index - $origin) < $minimumLines) {
                continue;
            }

            $length = $this->measureBlock($lineShapes, $index, $origin, $minimumLines);
            $blocks[] = ['start' => $index, 'origin' => $origin, 'length' => $length];
            $index += ($length - 1);
        }

        return $blocks;
    }

    /**
     * How far a copy found at $index keeps matching its original at $origin,
     * in code lines, starting from the window that matched.
     *
     * Growth stops at the end of the file, at the first line that differs, and
     * at the point where the original would run into the copy — a block and the
     * block it repeats never share a line.
     *
     * @param array<int, int> $lineShapes
     */
    private function measureBlock(
        array $lineShapes,
        int $index,
        int $origin,
        int $minimumLines
    ): int {
        $length = $minimumLines;

        while (
            isset($lineShapes[$index + $length], $lineShapes[$origin + $length]) === true
            && $lineShapes[$index + $length] === $lineShapes[$origin + $length]
            && ($index - $origin) > $length
        ) {
            $length++;
        }

        return $length;
    }
}
