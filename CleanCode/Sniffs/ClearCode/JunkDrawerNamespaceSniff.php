<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Warns on a namespace segment naming a generic technical bucket rather than a
 * domain (Clear Code: Encapsulate Related Classes in a Domain, #16) —
 * docs/standards/clear-code-encapsulate-related-classes-in-a-domain.md.
 *
 * The standard itself is Tier 3. Whether two classes are *related*, and whether
 * a namespace models a real-world functional block, are judgements about the
 * problem domain that no token stream carries. This sniff is the one slice that
 * is token-visible, and it enforces the standard only from the negative side:
 * a class filed under `App\Helpers` or `App\Support\Utils` is definitionally not
 * grouped by domain, whatever else is true of it (#190).
 *
 * That asymmetry is the whole design. The sniff cannot confirm that `Billing` is
 * a genuine domain, so it never tries — it recognises the well-known junk-drawer
 * names and nothing else. Positive domain grouping stays with code review.
 *
 * Two consequences follow, and both are deliberate:
 *
 *   - **Warning, not error.** A small library may legitimately keep one
 *     `Helpers` bucket. The report points at a domain-grouping candidate; it
 *     does not mandate a move.
 *   - **Framework layers are absent from the default list.** `Controllers`,
 *     `Models` and `Providers` are technical groupings the framework requires,
 *     so flagging them would fight the framework rather than enforce the
 *     standard.
 *
 * One warning is raised per offending *segment*, so `App\Helpers\Utils` reports
 * twice and each message names the one segment it is about. Both are reported at
 * the start of the namespace name: the name is one construct, and the fix
 * rewrites all of it.
 *
 * Detection only. Renaming a namespace means moving files and rewriting every
 * reference to them, and which domain the classes belong in is the judgement the
 * sniff cannot make — so there is no fixer and no autofixed.php fixture.
 */
class JunkDrawerNamespaceSniff implements Sniff
{
    /**
     * The namespace segments that name a technical bucket instead of a domain.
     * Compared case-insensitively, and exposed as a public property so a
     * consuming ruleset can replace or extend the list.
     *
     * @var array<int, string>
     */
    public array $discouragedSegments = [
        'Helpers',
        'Utils',
        'Utilities',
        'Misc',
        'Common',
        'General',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_NAMESPACE];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        // T_NAMESPACE is also the `namespace\` relative-name operator
        // (`namespace\format()`), which carries no declaration to read. A
        // following T_NS_SEPARATOR is what tells the two apart.
        if ($namePtr === false || $tokens[$namePtr]['code'] === T_NS_SEPARATOR) {
            return;
        }

        $segments = $this->nameSegments($phpcsFile, $namePtr);
        $discouraged = array_map('strtolower', $this->discouragedSegments);

        foreach ($segments as $segment) {
            if (in_array(strtolower($segment), $discouraged, true) === false) {
                continue;
            }

            $phpcsFile->addWarning(
                'Namespace %s groups classes by technical role: the "%s" segment names a generic'
                    . ' bucket, not a real-world domain. Group related classes into a domain'
                    . ' namespace instead (see'
                    . ' docs/standards/clear-code-encapsulate-related-classes-in-a-domain.md)',
                $namePtr,
                'Found',
                [implode('\\', $segments), $segment]
            );
        }
    }

    /**
     * The segments of the namespace name starting at $startPtr, read up to the
     * `;` or `{` that ends the declaration.
     *
     * The name is rebuilt from raw token content and then split on the
     * separator, rather than read off a specific token sequence. PHP_CodeSniffer
     * 3.x backfills PHP 8's single qualified-name token into T_STRING and
     * T_NS_SEPARATOR pairs, and this survives either spelling: a tokenizer
     * handing back `App\Helpers` whole would otherwise yield one segment that
     * matches nothing.
     *
     * Empty tokens are skipped because a declaration may be spaced before its
     * terminator — `namespace App\Helpers {` is the ordinary braced form, and
     * that whitespace would otherwise end up inside the last segment. Nothing
     * empty can appear *within* the name itself: PHP 8 rejects a qualified name
     * broken by whitespace or a comment.
     *
     * `namespace { … }` opens the global namespace and names no segments; the
     * loop breaks on the first token and the single empty segment it yields
     * matches nothing.
     *
     * @return array<int, string>
     */
    private function nameSegments(File $phpcsFile, int $startPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($i = $startPtr; $i < $phpcsFile->numTokens; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_SEMICOLON || $code === T_OPEN_CURLY_BRACKET) {
                break;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $name .= $tokens[$i]['content'];
        }

        return explode('\\', $name);
    }
}
