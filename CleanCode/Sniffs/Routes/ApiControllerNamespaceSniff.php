<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Requires a controller's `API` namespace segment and its `API` path segment to
 * agree (Routes: Types (API / View), #66) —
 * docs/standards/routes-types-api-view.md.
 *
 * This sniff is a *proxy* for the written standard, not the standard itself.
 * The standard constrains the **route** namespace: API routes are registered
 * under an `API` route namespace, view routes carry no prefix. In modern
 * Laravel that decision lives outside any file a sniff lints — in
 * bootstrap/app.php or the RouteServiceProvider — so no token carries it. What
 * *is* token-visible, per file, is the controller's own declared namespace, and
 * the standard's separation shows up there too: an API controller belongs in
 * App\Http\Controllers\API\…, a view controller does not. That is the slice
 * enforced here.
 *
 * The check is symmetric, because either half alone is satisfiable by moving
 * the file rather than fixing it:
 *
 *   - a controller under an `API` path segment whose namespace has none
 *     (`MissingApiNamespace`);
 *   - a controller declared in an `API` namespace that does not live under an
 *     `API` path segment (`UnexpectedApiNamespace`).
 *
 * "Under an API segment" is read *relative to the controller root* on both
 * sides: only the segments following the first `Controllers` segment count. A
 * project checked out at /srv/api or a vendor package namespaced `Api\…` would
 * otherwise read as API-everything.
 *
 * Detection only. Reconciling the two halves means moving the file or rewriting
 * its declared namespace — and which of those is correct depends on the
 * application's layout, not on anything in the file. There is no safe
 * mechanical rewrite, so no fixer and no autofixed.php fixture.
 *
 * The one-controller-per-model clause of the same standard stays with code
 * review: it needs a cross-file mapping between controllers, models and
 * resources/views/**, and a sniff sees one file's tokens.
 */
class ApiControllerNamespaceSniff implements Sniff
{
    /**
     * The segment that marks the controller root, on both the namespace and
     * the path side. Compared case-insensitively.
     */
    private const CONTROLLERS_SEGMENT = 'controllers';

    /**
     * The segment that marks the API group. Compared case-insensitively, so
     * `API`, `Api` and `api` all read as the same grouping — the standard is
     * about the segment being there, not about how it is cased.
     */
    private const API_SEGMENT = 'api';

    /**
     * The path PHPCS reports when it lints piped input with no --stdin-path.
     * There is no file location to compare a namespace against, so the sniff
     * has nothing to say.
     */
    private const UNKNOWN_PATH = 'STDIN';

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->getFilename() === self::UNKNOWN_PATH) {
            return;
        }

        $namespaceTail = $this->segmentsBelowControllerRoot(
            $this->namespaceSegments($phpcsFile, $stackPtr)
        );
        $pathTail = $this->segmentsBelowControllerRoot($this->pathSegments($phpcsFile));

        // Neither the namespace nor the location says "controller", so this
        // class is none of the standard's business.
        if ($namespaceTail === null && $pathTail === null) {
            return;
        }

        $namespaceIsApi = $namespaceTail !== null && in_array(self::API_SEGMENT, $namespaceTail, true);
        $pathIsApi = $pathTail !== null && in_array(self::API_SEGMENT, $pathTail, true);

        if ($namespaceIsApi === $pathIsApi) {
            return;
        }

        if ($pathIsApi === true) {
            $phpcsFile->addError(
                'A controller under an API path segment must be declared in a namespace with a'
                    . ' matching API segment, e.g. App\Http\Controllers\API',
                $stackPtr,
                'MissingApiNamespace'
            );

            return;
        }

        $phpcsFile->addError(
            'A controller declared in an API namespace must live under a matching API path'
                . ' segment; a view controller carries no API namespace segment',
            $stackPtr,
            'UnexpectedApiNamespace'
        );
    }

    /**
     * The segments of $segments that follow the first `Controllers` one, or
     * null when there is no `Controllers` segment at all.
     *
     * Null and [] are different answers: null means "this side does not
     * describe a controller", [] means "a controller sitting directly on the
     * controller root" — which is exactly the compliant view controller.
     *
     * @param array<int, string> $segments
     *
     * @return array<int, string>|null
     */
    private function segmentsBelowControllerRoot(array $segments): ?array
    {
        foreach ($segments as $index => $segment) {
            if (strtolower($segment) === self::CONTROLLERS_SEGMENT) {
                return array_map('strtolower', array_slice($segments, ($index + 1)));
            }
        }

        return null;
    }

    /**
     * The directory segments of the file's own path, the file name dropped.
     *
     * @return array<int, string>
     */
    private function pathSegments(File $phpcsFile): array
    {
        $segments = explode('/', str_replace('\\', '/', $phpcsFile->getFilename()));

        array_pop($segments);

        return $segments;
    }

    /**
     * The segments of the namespace enclosing the class at $stackPtr, or [] for
     * the global namespace.
     *
     * The enclosing namespace is the nearest *declaration* above the class,
     * which covers both spellings: with the one-per-file form every class
     * follows the single declaration, and with braced blocks the nearest one
     * above is the block the class sits in. T_NAMESPACE is also the `namespace\`
     * relative-name operator (`namespace\formatted()`), which can appear in a
     * method body above a later class — a following T_NS_SEPARATOR is what
     * tells the two apart, and the operator is skipped rather than read as a
     * declaration.
     *
     * @return array<int, string>
     */
    private function namespaceSegments(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $searchFrom = $stackPtr;

        while (true) {
            $namespacePtr = $phpcsFile->findPrevious(T_NAMESPACE, ($searchFrom - 1));

            if ($namespacePtr === false) {
                return [];
            }

            $searchFrom = $namespacePtr;
            $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($namespacePtr + 1), null, true);

            if ($namePtr === false || $tokens[$namePtr]['code'] === T_NS_SEPARATOR) {
                continue;
            }

            return $this->readName($phpcsFile, $namePtr);
        }
    }

    /**
     * Reads a namespace name from $startPtr up to its `;` or `{` terminator.
     *
     * The name is rebuilt from raw token content rather than from a specific
     * token sequence, so it does not care whether the tokenizer spells a
     * qualified name as T_STRING/T_NS_SEPARATOR pairs or as one name token.
     *
     * @return array<int, string>
     */
    private function readName(File $phpcsFile, int $startPtr): array
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

        $name = trim($name, '\\');

        return $name === '' ? [] : explode('\\', $name);
    }
}
