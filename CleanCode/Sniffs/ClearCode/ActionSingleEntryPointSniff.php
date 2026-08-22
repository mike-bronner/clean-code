<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

/**
 * Flags an Action class that declares more than one public entry point.
 *
 * Partial enforcement of the "Clear Code: Encapsulate Related Methods in a
 * Class" standard (docs/standards/clear-code-encapsulate-related-methods-in-a-
 * class.md, #15). The standard's core rule — that scattered logic *should* be
 * pulled into a class of its own — is a semantic judgement about intent and
 * cross-file relationships, and stays with code review. One narrow slice is
 * token-visible: once an Action class exists, its shape can be checked. An
 * Action encapsulates one concept behind one way in, so a second public method
 * is a second entry point, and a second concept that belongs in its own Action
 * class.
 *
 * A class is examined when either half of the convention holds:
 *
 * - its own name ends in `Action`, or
 * - its declared namespace carries an `Actions` segment (`App\Actions\…`,
 *   including any namespace below it).
 *
 * The two halves are matched with deliberately different case sensitivity,
 * because they are different kinds of match. The class name is a *suffix*
 * match, and `Action` is a suffix of ordinary English words a project really
 * declares — `Transaction`, `Reaction`, `Interaction`, `Faction` — every one
 * of which a case-insensitive suffix would swallow. The namespace half is an
 * *exact segment* match, where no such collision exists, so it is compared
 * case-insensitively and a project spelling its directory `actions` is still
 * covered.
 *
 * The first public method the class declares is its entry point, whatever it
 * is called. The standard's doc names `__invoke()` and `handle()` as the
 * convention, but only as a convention: naming is not what this sniff decides,
 * count is. Every *further* public method is reported, at its own declaration.
 *
 * `__construct` is never an entry point and never counted — an Action is
 * constructed with its collaborators and then invoked, so a constructor is not
 * a second way in. No other method is exempt. A public accessor handing back a
 * result the entry point computed is reported like any other extra public
 * method; the standard prescribes a design vehicle rather than a mechanical
 * fact, so the judgement of whether a given accessor has earned its place is
 * the reader's. That is exactly why this is a **warning** and not an error: it
 * must not fail a consumer's build on a class it has merely misread.
 *
 * Boundaries — accepted, by design:
 *
 * - Convention-dependent, in both directions. An Action class named and placed
 *   outside the convention is never examined; a class that merely matches the
 *   convention is examined whether or not it is really an Action.
 * - Only `T_CLASS` is registered. An interface method is a contract rather
 *   than an entry point, a trait's methods belong to whichever class mixes
 *   them in, an enum is not an Action, and an anonymous class carries no name
 *   the convention can read. PHP_CodeSniffer gives each of those its own token
 *   type, so registering `T_CLASS` alone excludes them without an exclusion
 *   list to keep current.
 * - Traits and parents are invisible. A public method mixed in from a trait or
 *   inherited from a parent is declared in another file, and a sniff reads one
 *   file at a time.
 * - Protected and private methods are never counted. An entry point has to be
 *   public.
 * - Detection only. Splitting an Action in two means creating a class, moving
 *   a method, and rewriting every call site that reaches it — an architectural
 *   change with no mechanical rewrite — so nothing is offered to the fixer and
 *   there is no autofixed.php fixture.
 */
class ActionSingleEntryPointSniff implements Sniff
{
    /**
     * The class-name suffix that puts a class in scope. Compared
     * case-sensitively; see the class docblock for why.
     */
    private const CLASS_NAME_SUFFIX = 'Action';

    /**
     * The namespace segment that puts a class in scope, lowercased for a
     * case-insensitive comparison against each segment of the declared
     * namespace.
     */
    private const NAMESPACE_SEGMENT = 'actions';

    /**
     * The one method name that is never an entry point, lowercased because PHP
     * method names are case-insensitive.
     */
    private const CONSTRUCTOR = '__construct';

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
        if ($this->isActionClass($phpcsFile, $stackPtr) === false) {
            return;
        }

        // The first entry point is the one the Action is entitled to; every
        // later one is a second concept sharing the class.
        $extras = array_slice($this->entryPoints($phpcsFile, $stackPtr), 1);

        foreach ($extras as $methodPtr) {
            $name = (string) $phpcsFile->getDeclarationName($methodPtr);

            $phpcsFile->addWarning(
                'Public method %s() is an additional entry point; an Action class exposes a'
                    . ' single public entry point, so move %s() into an Action class of its own'
                    . ' (see docs/standards/clear-code-encapsulate-related-methods-in-a-class.md)',
                $methodPtr,
                'Found',
                [$name, $name]
            );
        }
    }

    /**
     * Whether the class at $stackPtr matches either half of the Action
     * convention.
     *
     * The name is read first because it is the cheaper of the two and the one
     * a reader checks first. A class keyword PHP_CodeSniffer hands over
     * mid-edit has no name yet, which fails the suffix half and leaves the
     * namespace half to answer on its own.
     */
    private function isActionClass(File $phpcsFile, int $stackPtr): bool
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name !== null && str_ends_with($name, self::CLASS_NAME_SUFFIX) === true) {
            return true;
        }

        $segments = $this->namespaceSegments($phpcsFile, $stackPtr);

        return in_array(self::NAMESPACE_SEGMENT, $segments, true);
    }

    /**
     * The lowercased segments of the namespace enclosing the class, or [] in
     * the global namespace.
     *
     * Slevomat's NamespaceHelper resolves the enclosing declaration rather than
     * merely the nearest `namespace` token, which is what keeps the two
     * spellings and one look-alike apart: the one-per-file form, braced blocks,
     * and the `namespace\thing()` relative-name operator, which is the same
     * T_NAMESPACE token and can sit in a method body above a later class.
     * slevomat/coding-standard is a hard `require` of this package, not a
     * dev-only tool, so the helper ships wherever this sniff does; the fixtures
     * pin the behaviour relied on here, so a vendor upgrade that changed it
     * fails the suite rather than silently narrowing the rule.
     *
     * @return array<int, string>
     */
    private function namespaceSegments(File $phpcsFile, int $stackPtr): array
    {
        $namespace = NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);

        return $namespace === null ? [] : array_map('strtolower', explode('\\', $namespace));
    }

    /**
     * Every public method the class declares itself, in declaration order,
     * `__construct` aside.
     *
     * The class body is walked at its own top level only: each member's extent
     * is stepped over once it has been read, so nothing written *inside* a
     * member is ever looked at. That is what keeps three shapes out of the
     * count — a method of an anonymous class built in the entry point, a named
     * function declared in a method body, and a named function declared in the
     * body of a PHP 8.4 property hook. The third is why the walk is structural
     * rather than a scan filtered by each declaration's `conditions`:
     * PHP_CodeSniffer opens no scope for a property hook, so a function
     * declared inside one carries the *class* as its innermost condition and is
     * indistinguishable from a real method by that test.
     *
     * A class PHP_CodeSniffer never saw closed yields nothing at all: it gets
     * no scope pointers, and attributing methods to a class whose extent the
     * tokenizer could not settle would be a guess. A single *declaration* the
     * walk cannot find the end of — the half-typed `public function` an editor
     * hands over mid-keystroke — ends the walk instead of emptying it. What was
     * read before it was resolved correctly; only what follows is unknown, so
     * the class is reported on as far as it was legible and no further.
     *
     * @return array<int, int>
     */
    private function entryPoints(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $class = $tokens[$classPtr];

        if (isset($class['scope_opener'], $class['scope_closer']) === false) {
            return [];
        }

        $closer = $class['scope_closer'];
        $pointer = $class['scope_opener'] + 1;
        $pointers = [];

        while ($pointer < $closer) {
            $code = $tokens[$pointer]['code'];

            // A brace at the class's own top level opens a property hook or a
            // trait-adaptation block. Neither holds a method of this class.
            if ($code === T_OPEN_CURLY_BRACKET) {
                $pointer = ($tokens[$pointer]['bracket_closer'] ?? $closer) + 1;

                continue;
            }

            if ($code !== T_FUNCTION) {
                ++$pointer;

                continue;
            }

            $end = $this->endOfDeclaration($phpcsFile, $pointer, $closer);

            if ($end === null) {
                break;
            }

            if ($this->isEntryPoint($phpcsFile, $pointer) === true) {
                $pointers[] = $pointer;
            }

            $pointer = $end + 1;
        }

        return $pointers;
    }

    /**
     * The last token of the method declaration at $methodPtr: its closing brace
     * where it has a body, or the `;` that ends an abstract declaration.
     *
     * Null when neither is resolvable before the class ends, which only a
     * half-written file produces — and which ends entryPoints()'s walk rather
     * than letting it carry on from a position it cannot trust.
     */
    private function endOfDeclaration(File $phpcsFile, int $methodPtr, int $closer): ?int
    {
        $end = $phpcsFile->getTokens()[$methodPtr]['scope_closer']
            ?? $phpcsFile->findNext(T_SEMICOLON, $methodPtr + 1, $closer);

        return $end === false ? null : $end;
    }

    /**
     * Whether the method declaration at $methodPtr is a public entry point.
     *
     * A declaration with no name yet is neither counted nor reported. There is
     * nothing to name in a message, and letting a half-typed `public function`
     * be the second entry point would report a compliant class mid-keystroke.
     */
    private function isEntryPoint(File $phpcsFile, int $methodPtr): bool
    {
        if ($phpcsFile->getMethodProperties($methodPtr)['scope'] !== 'public') {
            return false;
        }

        $name = $phpcsFile->getDeclarationName($methodPtr);

        return $name !== null && strtolower($name) !== self::CONSTRUCTOR;
    }
}
