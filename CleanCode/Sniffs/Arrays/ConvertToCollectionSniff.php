<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags calls to native array-manipulation functions that have a direct
 * Collection equivalent.
 *
 * Partial enforcement of Arrays: Convert To Collection (#30, Tier 2) — see
 * docs/standards/arrays-convert-to-collection.md. Only the *trigger* is
 * lintable: a bare call to one of the configured functions is a
 * collection-pipeline candidate, and the message names the Collection method
 * that replaces it. Whether a particular manipulation reads better as a
 * pipeline is a judgement about context, so this reports warnings rather than
 * errors and the standard's semantic core stays with code review.
 *
 * The check is name-based, exactly as PHPCS's own
 * Generic.PHP.ForbiddenFunctions works: method calls ($obj->array_map()),
 * static calls (Foo::array_map()), instantiation (new array_map()),
 * declarations (function array_map()), and namespaced functions of the same
 * name (App\Support\array_map(), and namespace\array_map() in a file that
 * declares a namespace) are all different symbols and stay out. A
 * fully-qualified \array_map() is the global function, so it is flagged — and
 * so is namespace\array_map() where no namespace has been declared, because
 * the namespace in force there is the global one.
 *
 * The sniff cannot verify that illuminate/collections is available in the
 * scanned project; projects without it exclude the sniff in their ruleset.
 */
class ConvertToCollectionSniff implements Sniff
{
    /**
     * How many characters the array_ prefix occupies.
     */
    private const ARRAY_PREFIX_LENGTH = 6;

    /**
     * Native array functions to flag, mapped to the Collection method that
     * replaces each one. Configurable from a ruleset via a <property
     * name="arrayFunctions" type="array"> holding <element key="array_map"
     * value="map"/> nodes.
     *
     * A ruleset may leave the key off (<element value="array_walk"/>), which
     * PHPCS hands over numerically keyed rather than as a name => method map.
     * Those entries still configure the sniff — replacements() derives the
     * Collection method for them by dropping the array_ prefix — so the
     * natural list spelling cannot silently switch the sniff off.
     *
     * @var array<array-key, string>
     */
    public array $arrayFunctions = [
        'array_filter' => 'filter',
        'array_map' => 'map',
        'array_reduce' => 'reduce',
    ];

    /**
     * Tokens that, when directly preceding the function name, mean this is
     * not a global function call (method call, static call, declaration, …).
     */
    private const NON_FUNCTION_CALL_PRECEDERS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_STRING];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // The open-parenthesis test comes first because it is the cheapest way
        // to discard the great majority of T_STRING tokens, which are not
        // calls at all — the configured map is only assembled for call sites.
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $replacements = $this->replacements();
        $function = strtolower($tokens[$stackPtr]['content']);

        if (isset($replacements[$function]) === false) {
            return;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $prev !== false
            && in_array($tokens[$prev]['code'], self::NON_FUNCTION_CALL_PRECEDERS, true) === true
        ) {
            return;
        }

        if (
            $prev !== false
            && $tokens[$prev]['code'] === T_NS_SEPARATOR
            && $this->isQualifiedName($phpcsFile, $prev) === true
        ) {
            return;
        }

        $phpcsFile->addWarning(
            '%s() manipulates a native array; use collect()->%s() instead',
            $stackPtr,
            'Found',
            [$tokens[$stackPtr]['content'], $replacements[$function]]
        );
    }

    /**
     * The configured functions as a lowercased function name => Collection
     * method map, whichever of the two ruleset spellings produced them.
     *
     * PHP resolves function names case-insensitively, so both sides of the
     * comparison are lowercased and ARRAY_MAP() matches array_map.
     *
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $replacements = [];

        foreach ($this->arrayFunctions as $key => $value) {
            $isListEntry = is_int($key) === true;
            $function = strtolower($isListEntry === true ? $value : (string) $key);
            $method = $isListEntry === true ? $this->collectionMethod($function) : $value;

            $replacements[$function] = $method;
        }

        return $replacements;
    }

    /**
     * The Collection method a list-form entry names: the function without its
     * array_ prefix. That is the Collection name for the shipped trio and for
     * most of the wider array_* family (array_values => values,
     * array_reverse => reverse). Give the keyed spelling instead wherever the
     * two names diverge, e.g. <element key="usort" value="sortBy"/>.
     *
     * $function arrives lowercased from replacements(), so the prefix test
     * holds for a ruleset that shouts its entries (ARRAY_VALUES => values).
     */
    private function collectionMethod(string $function): string
    {
        if (str_starts_with($function, 'array_') === false) {
            return $function;
        }

        return substr($function, self::ARRAY_PREFIX_LENGTH);
    }

    /**
     * Whether the T_NS_SEPARATOR at $separatorPtr belongs to a qualified name
     * (App\Support\array_map, and namespace\array_map inside a declared
     * namespace) rather than a fully-qualified global one (\array_map).
     * Qualified names resolve outside the global namespace, so they are never
     * the native array functions.
     */
    private function isQualifiedName(File $phpcsFile, int $separatorPtr): bool
    {
        $beforeSeparator = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($separatorPtr - 1), null, true);

        if ($beforeSeparator === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$beforeSeparator]['code'] === T_STRING) {
            return true;
        }

        return $tokens[$beforeSeparator]['code'] === T_NAMESPACE
            && $this->isInsideNamedNamespace($phpcsFile, $beforeSeparator) === true;
    }

    /**
     * Whether the token at $stackPtr sits inside a *named* namespace.
     *
     * namespace\array_map() resolves against the namespace in force where it
     * is written, so it only names a different symbol once one has been
     * declared. Where none has, the namespace in force is the global one and
     * namespace\array_map() is the native function, exactly as \array_map()
     * is — so the call is flagged rather than excluded.
     *
     * Walks back to the nearest declaration, which is the one in force:
     * namespace Foo; and namespace Foo {} both put a T_STRING after the
     * keyword, while the bare namespace {} block is the global namespace and
     * ends the walk. A T_NAMESPACE that opens a namespace\ name is skipped —
     * it is an operator rather than a declaration.
     */
    private function isInsideNamedNamespace(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $search = $stackPtr;

        while (($search = $phpcsFile->findPrevious(T_NAMESPACE, ($search - 1))) !== false) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($search + 1), null, true);

            if ($next === false || $tokens[$next]['code'] === T_NS_SEPARATOR) {
                continue;
            }

            return $tokens[$next]['code'] === T_STRING;
        }

        return false;
    }
}
