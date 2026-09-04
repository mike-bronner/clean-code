<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class MemberOrderingSniff implements Sniff
{
    public array $modelParentClasses = [
        'Authenticatable',
        'Model',
        'Pivot',
    ];

    public array $relationReturnTypes = [
        'BelongsTo',
        'BelongsToMany',
        'HasMany',
        'HasManyThrough',
        'HasOne',
        'HasOneOrMany',
        'HasOneOrManyThrough',
        'HasOneThrough',
        'MorphMany',
        'MorphOne',
        'MorphOneOrMany',
        'MorphTo',
        'MorphToMany',
        'MorphedByMany',
        'Relation',
    ];

    private const VISIBILITY_RANKS = [
        'public' => 0,
        'protected' => 1,
        'private' => 2,
    ];

    private const PROPERTY_MODIFIERS = [
        T_FINAL,
        T_READONLY,
        T_STATIC,
        T_VAR,
    ];

    public function register(): array
    {
        return [T_ANON_CLASS, T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->hasModelShapedParent($phpcsFile, $stackPtr) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        // findExtendedClassName() returns false for a class the tokenizer never
        // opened, so the gate above has already excluded that case and both
        // bounds are set. They are read defensively all the same, because a
        // null $end would make every walk below run to the end of the file and
        // report a later class's members against this one.
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return;
        }

        $this->checkTraits($phpcsFile, $stackPtr, $opener, $closer);
        $this->checkProperties($phpcsFile, $stackPtr, $opener, $closer);
        $this->checkMethods($phpcsFile, $stackPtr, $opener, $closer);
    }

    private function checkTraits(File $phpcsFile, int $classPtr, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousName = null;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_USE, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            $names = $this->traitNames($phpcsFile, $ptr, $closer);

            if ($names === []) {
                continue;
            }

            if (count($names) > 1) {
                $phpcsFile->addError(
                    'Each trait needs its own use statement; this one declares %d '
                        . '(see docs/standards/models-organization.md)',
                    $ptr,
                    'MultipleTraitsPerLine',
                    [count($names)]
                );
            }

            // A multi-trait declaration is reported above and then ordered on
            // its first name, so the alphabetical check still sees one entry
            // per statement and never double-reports the same line.
            [$namePtr, $name] = $names[0];

            if (
                $previousName !== null
                && strcasecmp($name, $previousName) < 0
            ) {
                $phpcsFile->addError(
                    'Trait %s is out of alphabetical order; it belongs before %s '
                        . '(see docs/standards/models-organization.md)',
                    $namePtr,
                    'TraitOrder',
                    [$name, $previousName]
                );
            }

            $previousName = $name;
        }
    }

    private function traitNames(File $phpcsFile, int $usePtr, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], ($usePtr + 1), $closer);

        if ($end === false) {
            return [];
        }

        $names = [];
        $current = '';
        $currentPtr = null;

        for ($ptr = ($usePtr + 1); $ptr < $end; $ptr++) {
            if ($tokens[$ptr]['code'] === T_COMMA) {
                $this->appendName($names, $current, $currentPtr);

                continue;
            }

            if (in_array($tokens[$ptr]['code'], [T_STRING, T_NS_SEPARATOR], true) === false) {
                continue;
            }

            $currentPtr ??= $ptr;
            $current .= $tokens[$ptr]['content'];
        }

        $this->appendName($names, $current, $currentPtr);

        return $names;
    }

    private function appendName(array &$names, string &$current, ?int &$currentPtr): void
    {
        $name = ltrim($current, '\\');

        if (
            $name !== ''
            && $currentPtr !== null
        ) {
            $names[] = [$currentPtr, $name];
        }

        $current = '';
        $currentPtr = null;
    }

    private function checkProperties(File $phpcsFile, int $classPtr, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousName = null;
        $previousRank = null;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_VARIABLE, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            if (empty($tokens[$ptr]['nested_parenthesis']) === false) {
                continue;
            }

            if ($this->isPropertyDeclaration($phpcsFile, $ptr) === false) {
                continue;
            }

            $scope = $phpcsFile->getMemberProperties($ptr)['scope'];
            $rank = self::VISIBILITY_RANKS[$scope];
            $name = ltrim($tokens[$ptr]['content'], '$');

            if (
                $previousRank !== null
                && $rank < $previousRank
            ) {
                $phpcsFile->addError(
                    'Property $%s is %s and follows a %s property; list properties '
                        . 'public, then protected, then private '
                        . '(see docs/standards/models-organization.md)',
                    $ptr,
                    'PropertyGroupOrder',
                    [$name, $scope, array_search($previousRank, self::VISIBILITY_RANKS, true)]
                );
            }

            // Disjoint by construction: a rank is either below the previous one
            // or equal to it, never both, so the two reports read as one choice
            // written apart.
            if (
                $previousName !== null
                && $rank === $previousRank
                && strcasecmp($name, $previousName) < 0
            ) {
                $phpcsFile->addError(
                    'Property $%s is out of alphabetical order; it belongs before $%s '
                        . '(see docs/standards/models-organization.md)',
                    $ptr,
                    'PropertyOrder',
                    [$name, $previousName]
                );
            }

            // The displaced member becomes the new baseline whether or not it
            // was reported, so one member in the wrong place earns one error
            // rather than one for every member that follows it.
            $previousName = $name;
            $previousRank = $rank;
        }
    }

    private function isPropertyDeclaration(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundary = $phpcsFile->findPrevious(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET],
            ($variablePtr - 1)
        );

        // Not reachable from checkProperties(), which only calls this for a
        // variable inside a class body — and a class body opens with the `{`
        // this search cannot miss. It is here because the alternative is worse
        // than dead: `false + 1` is 1 in PHP, so without the guard a boundary
        // that was never found would silently start the scan at the file's
        // second token and answer from whatever is there.
        if ($boundary === false) {
            return false;
        }

        $start = ($boundary + 1);

        while (
            ($start = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $variablePtr, true)) !== false
            && $tokens[$start]['code'] === T_ATTRIBUTE
        ) {
            $start = ($tokens[$start]['attribute_closer'] + 1);
        }

        // Reached whenever the variable is itself the first thing in its
        // statement — `$local = $value;` in a hook body, which is exactly what
        // this method exists to reject. Answering false here is the same answer
        // the comparison below would reach, and it reaches it without indexing
        // $tokens with a bool.
        if ($start === false) {
            return false;
        }

        return in_array($tokens[$start]['code'], Tokens::$scopeModifiers, true)
            || in_array($tokens[$start]['code'], self::PROPERTY_MODIFIERS, true);
    }

    private function checkMethods(File $phpcsFile, int $classPtr, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $previousNames = [];
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $closer)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            $namePtr = $phpcsFile->findNext(T_STRING, ($ptr + 1), $closer);

            // A `function` with no name before the class closes is a fragment
            // the tokenizer could not finish reading. Nothing about it says
            // where it belongs, so it is passed over rather than guessed at.
            //
            // The name is read from this token rather than from
            // getDeclarationName(), which answers the same for every method
            // that has a name — PHPCS gives a method name T_STRING even when it
            // is a reserved word, so the first T_STRING after `function` is the
            // name — and answers from the *next class* for one that has none,
            // because with no parenthesis to stop at it searches to the end of
            // the file. One token, read once, cannot disagree with itself.
            if ($namePtr === false) {
                continue;
            }

            $name = $tokens[$namePtr]['content'];

            if (str_starts_with($name, '__') === true) {
                continue;
            }

            $category = $this->methodCategory($phpcsFile, $ptr, $name);
            $previousName = $previousNames[$category] ?? null;

            if (
                $previousName !== null
                && strcasecmp($name, $previousName) < 0
            ) {
                $phpcsFile->addError(
                    '%s %s() is out of alphabetical order; it belongs before %s() '
                        . '(see docs/standards/models-organization.md)',
                    $namePtr,
                    $category,
                    [$this->categoryLabel($category), $name, $previousName]
                );
            }

            $previousNames[$category] = $name;
        }
    }

    private function methodCategory(File $phpcsFile, int $methodPtr, string $name): string
    {
        $properties = $phpcsFile->getMethodProperties($methodPtr);

        if (
            $properties['scope'] === 'public'
            && $this->isRelationReturnType($properties['return_type']) === true
        ) {
            return 'RelationshipMethodOrder';
        }

        if (preg_match('/^(get|set)[A-Z]/', $name) === 1) {
            return 'AccessorMethodOrder';
        }

        return 'MethodOrder';
    }

    private function categoryLabel(string $category): string
    {
        $labels = [
            'RelationshipMethodOrder' => 'Relationship method',
            'AccessorMethodOrder' => 'Accessor',
        ];

        return $labels[$category] ?? 'Method';
    }

    private function isRelationReturnType(string $returnType): bool
    {
        $accepted = array_map('strtolower', $this->relationReturnTypes);
        // The pattern is a literal character class, so preg_split() cannot
        // fail; the ?: states that outright rather than leaning on it.
        $parts = preg_split('/[|&]/', $returnType) ?: [];

        foreach ($parts as $part) {
            $qualifiers = explode('\\', trim($part, '?()'));

            if (in_array(strtolower(end($qualifiers)), $accepted, true) === true) {
                return true;
            }
        }

        return false;
    }

    private function hasModelShapedParent(File $phpcsFile, int $classPtr): bool
    {
        $parent = $phpcsFile->findExtendedClassName($classPtr);

        if ($parent === false) {
            return false;
        }

        // explode() always yields at least one element, so end() is a string.
        $qualifiers = explode('\\', $parent);
        $shortName = strtolower(end($qualifiers));

        if (in_array($shortName, array_map('strtolower', $this->modelParentClasses), true) === true) {
            return true;
        }

        return str_ends_with($shortName, 'model');
    }
}
