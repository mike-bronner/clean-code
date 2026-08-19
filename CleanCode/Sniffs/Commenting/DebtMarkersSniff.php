<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Warns about HACK and XXX debt-marker comments (Debt: Technical Debt, #24 /
 * #138) — docs/standards/debt-technical-debt.md.
 *
 * A marker comment is a developer's explicit, in-code acknowledgement of debt
 * left unpaid, which is the one slice of that standard a single file's tokens
 * can decide. PHPCS core covers two of the four keywords already, so
 * rules.xml wires Generic.Commenting.Todo and Generic.Commenting.Fixme in for
 * TODO and FIXME and this sniff carries only the two core ships nothing for.
 *
 * Warnings, never errors: the marker documents debt honestly, and deleting the
 * comment without paying the debt would be the worse outcome. The two Generic
 * sniffs are held at the same severity in rules.xml for the same reason.
 *
 * The matching mirrors the Generic pair token for token — same registered
 * comment tokens, same keyword pattern, same message shape, same split between
 * a bare marker and one carrying a task description — so a reader cannot tell
 * from a report which of the four keywords is covered by which sniff.
 */
class DebtMarkersSniff implements Sniff
{
    /**
     * The debt-marker keywords PHPCS core ships no sniff for.
     *
     * Each maps to the prefix of its two violation codes: HACK reports
     * HackCommentFound / HackTaskFound, XXX reports XxxCommentFound /
     * XxxTaskFound. The keyword is spelled out in the message as well, so a
     * report identifies which marker it came from without reading the code.
     */
    private const MARKERS = [
        'HACK' => 'Hack',
        'XXX' => 'Xxx',
    ];

    /**
     * Characters carrying no meaning at the end of a marker's task text.
     */
    private const TRIMMED_FROM_TASK = '-:[](). ';

    /**
     * Every comment token except the phpcs: annotations.
     *
     * Line comments (both the slash and the hash form) and block comments
     * arrive as T_COMMENT, one token per physical line; a docblock's prose
     * arrives as T_DOC_COMMENT_STRING. Registering the whole set — the way
     * both Generic sniffs do — covers every comment style without enumerating
     * them. The phpcs: annotations are excluded because they configure the run
     * rather than describe the code.
     *
     * @return array<int|string>
     */
    public function register(): array
    {
        return array_diff(Tokens::$commentTokens, Tokens::$phpcsCommentTokens);
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        foreach (self::MARKERS as $marker => $codePrefix) {
            $task = $this->markerTask($tokens[$stackPtr]['content'], $marker);

            if ($task === null) {
                continue;
            }

            if ($task === '') {
                $phpcsFile->addWarning(
                    'Comment refers to a %s task',
                    $stackPtr,
                    $codePrefix . 'CommentFound',
                    [$marker]
                );

                continue;
            }

            $phpcsFile->addWarning(
                'Comment refers to a %s task "%s"',
                $stackPtr,
                $codePrefix . 'TaskFound',
                [$marker, $task]
            );
        }
    }

    /**
     * The task text following $marker in $content, '' when the marker carries
     * none, or null when the comment does not contain the marker at all.
     *
     * The pattern is Generic.Commenting.Todo's, with the keyword substituted:
     * the marker has to start the comment or follow a non-letter, and has to
     * end the comment or be followed by a non-letter. That is what keeps
     * "hacked", "shack" and "XXXX" from reporting while "HACK:", "(XXX)" and a
     * bare "HACK" all do.
     *
     * The /u modifier makes preg_match() return false rather than 0 on a
     * comment holding bytes that are not valid UTF-8, so the guard below tests
     * for 1 and folds that case in with "no marker here". Deliberate, and the
     * same disposition both Generic sniffs give it: there is nothing truthful
     * to report about a comment that cannot be read, and the skip is local —
     * every other comment in the file is still matched, since each arrives as
     * its own token. It is left untested because reaching it needs a fixture
     * with an illegal byte in it, which PHP_CodeSniffer's own tokenizer raises
     * an iconv notice over before this sniff is ever called.
     */
    private function markerTask(string $content, string $marker): ?string
    {
        $matches = [];
        $pattern = '/(?:\A|[^\p{L}]+)' . $marker . '([^\p{L}]+(.*)|\Z)/ui';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        return trim(trim($matches[1]), self::TRIMMED_FROM_TASK);
    }
}
