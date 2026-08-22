<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\CodeStyle;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags auto-formatter directive comments committed in PHP source.
 *
 * Partial enforcement of Code Style: Linters (config & no auto-formatter)
 * (#51, sniff #143) —
 * docs/standards/code-style-linters-config--no-auto-formatter.md. The standard
 * itself is Tier 3: whether an editor lints against the repo's phpcs.xml, and
 * whether a style violation was corrected by hand or by a formatter, are facts
 * about the environment and the workflow that leave the tokens identical
 * either way.
 *
 * One slice of it is token-visible. A formatter on/off or ignore marker
 * committed in a comment exists only to steer an auto-formatter around a
 * region it would otherwise rewrite, so a scanned file carrying one is direct
 * evidence a formatter is being run over it — which is what the standard
 * forbids.
 *
 * The shipped markers are the Eclipse-originated on/off pair that
 * JetBrains/PhpStorm also honors, and the ignore marker from Prettier's PHP
 * plugin. $directives spells all three; this docblock deliberately does not.
 * The sniff reads comments, so a marker written out in the package's own prose
 * would make this file violate its own rule the moment a consuming project
 * scans the package.
 *
 * The heuristic catches only a formatter that was told to skip something: a
 * formatter run with no directives, and phpcbf itself, leave output
 * indistinguishable from hand-corrected code. Committed formatter config files
 * and editor-side configuration never reach a sniff at all. The standard's
 * document records what stays with code review.
 *
 * Detection only. Deleting the marker removes the evidence rather than the
 * formatter, and leaves the region it guarded formatted, so there is no
 * mechanical fix worth applying.
 */
class NoFormatterDirectivesSniff implements Sniff
{
    /**
     * The directives to flag, each matched as a case-insensitive substring of
     * one comment token's text. Configurable from a ruleset via a <property
     * name="directives" type="array"> holding <element> nodes.
     *
     * A configured list *replaces* this one rather than extending it — that is
     * what assigning an array property does — so a ruleset adding a marker of
     * its own re-lists the three below beside it. Element keys are ignored, so
     * the keyless list spelling and the keyed one configure the sniff alike.
     *
     * @var array<array-key, string>
     */
    public array $directives = [
        '@formatter:off',
        '@formatter:on',
        'prettier-ignore',
    ];

    /**
     * The comment tokens whose content can carry a directive.
     *
     * T_COMMENT covers the //, # and block spellings. A block comment spanning
     * several physical lines is *not* one token: PHPCS splits it into one
     * T_COMMENT per line, which is why matching a token's own content is
     * enough and no line reassembly is needed.
     *
     * Inside a doc comment the body is split by role. An @-prefixed marker
     * arrives whole as a T_DOC_COMMENT_TAG, everything else — including a
     * marker written mid-sentence, after a tag, or with no @ at all — arrives
     * in a T_DOC_COMMENT_STRING. The remaining doc-comment tokens are the
     * opener, closer, stars and whitespace, which carry no comment text.
     *
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_COMMENT,
            T_DOC_COMMENT_TAG,
            T_DOC_COMMENT_STRING,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $directive = $this->directiveIn($tokens[$stackPtr]['content']);

        if ($directive === null) {
            return;
        }

        $phpcsFile->addError(
            'Auto-formatter directive %s must not be committed; correct style by hand instead',
            $stackPtr,
            'Found',
            [$directive]
        );
    }

    /**
     * The first configured directive the comment text carries, in configured
     * order, or null where it carries none.
     *
     * One answer per comment token, so a token carrying two directives is
     * reported once, naming the first. Matching is case-insensitive because a
     * marker is read by the formatter that wrote it rather than by PHP, and
     * the shouted spelling steers it just as well.
     *
     * Each entry is trimmed before it is matched, so a directive written with
     * surrounding whitespace still matches the comment that carries it. An
     * entry that is empty once trimmed is skipped: stripos() finds an empty
     * needle at offset 0 of every string, so a blank <element> would otherwise
     * turn the sniff from a directive check into a ban on comments.
     */
    private function directiveIn(string $content): ?string
    {
        foreach ($this->directives as $directive) {
            $needle = trim($directive);

            if ($needle === '') {
                continue;
            }

            if (stripos($content, $needle) !== false) {
                return $needle;
            }
        }

        return null;
    }
}
