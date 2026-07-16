<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags generic Eloquent CRUD calls on receivers other than $this.
 *
 * Partial enforcement of the "Models: Persistence Methods (Repository
 * Pattern)" standard (docs/standards/models-persistence-methods-repository-
 * pattern.md): persistence belongs inside the model behind descriptive
 * methods, so a call like $user->save() outside the model signals that
 * persistence is being driven externally. Calls on $this are the blessed
 * usage — the model's own descriptive methods calling $this->save().
 *
 * The check is name-based (PHPCS has no type information), so it emits
 * warnings, not errors. Static Model::create([...]) is excluded: at the
 * token level it is indistinguishable from named constructors and factory
 * APIs. Exclude tests/ via ruleset path scoping — factory chains make the
 * pattern idiomatic there (see rules.xml).
 */
class DisallowExternalPersistenceCallsSniff implements Sniff
{
    /**
     * Generic CRUD method names that signal persistence when called on a
     * receiver other than $this. Configurable from a ruleset via
     * <property name="persistenceMethods" type="array" .../>.
     *
     * @var array<string>
     */
    public array $persistenceMethods = [
        'create',
        'delete',
        'save',
        'update',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
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

        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($methodPtr === false || $tokens[$methodPtr]['code'] !== T_STRING) {
            return;
        }

        $method = strtolower($tokens[$methodPtr]['content']);

        if (in_array($method, array_map('strtolower', $this->persistenceMethods), true) === false) {
            return;
        }

        $afterMethod = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if ($afterMethod === false || $tokens[$afterMethod]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $receiverPtr !== false
            && $tokens[$receiverPtr]['code'] === T_VARIABLE
            && $tokens[$receiverPtr]['content'] === '$this'
        ) {
            return;
        }

        $phpcsFile->addWarning(
            'Generic Eloquent CRUD method %s() called on a receiver other than $this; '
                . 'persistence belongs inside the model behind a descriptive method '
                . '(see docs/standards/models-persistence-methods-repository-pattern.md)',
            $methodPtr,
            'Found',
            [$tokens[$methodPtr]['content']]
        );
    }
}
