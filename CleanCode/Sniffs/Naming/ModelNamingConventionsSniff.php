<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Models: Naming Conventions" standard.
 *
 * Only Eloquent model classes are inspected. A class counts as a model when it
 * is declared in a namespace carrying a `Models` segment (the Laravel default,
 * `App\Models\…`) or when it extends a recognised Eloquent base class
 * (`Model`, `Authenticatable`, `Pivot`, `MorphPivot`, matched on the short
 * name). Everything else in the tree is left alone — the naming rules below
 * describe how models expose data and would be noise anywhere else.
 *
 * Five checks, all reporting-only (renaming an identifier is never safe for a
 * fixer, and rewriting a legacy accessor is a semantic change):
 *
 * - **BooleanPropertyPrefix** — a `bool`-typed property must read as a yes/no
 *   question (`isActive`, `hasQuota`, `shouldQueue`).
 * - **BooleanMethodPrefix** — a `bool`-returning method must read the same way.
 *   The standard's preferred shape for a condition check is
 *   `has<ConditionInPastTense>`; past-tense morphology is not machine
 *   checkable, so the sniff enforces the yes/no prefix family that `has`
 *   belongs to and leaves tense to review.
 * - **FindMethodPrefix** — a method returning a single model instance must be
 *   prefixed `find`.
 * - **FindModelName** — and must name the model it returns
 *   (`findUserByName(): User`). Only checked when the returned model's short
 *   name is knowable; `self`, `static`, `$this`, and `parent` name no model, so
 *   the prefix alone is required there.
 * - **GetMethodPrefix** — a method returning a collection must be prefixed
 *   `get`. The model name cannot be derived from a `Collection` return type, so
 *   only the prefix is enforced.
 * - **LegacyAttributeAccessor** — `getFooAttribute()` / `setFooAttribute()` are
 *   the superseded accessor style; the standard wants the "new" attribute
 *   implementation (a method returning `Illuminate\Database\Eloquent\Casts\Attribute`).
 *
 * Deliberate blind spots: an untyped property or a method with no return type
 * carries no signal to check against and is skipped (the Type Hints standard,
 * #45, is what makes those types appear); magic methods and the Eloquent
 * override points whose names are fixed by the framework are exempt.
 */
class ModelNamingConventionsSniff implements Sniff
{
    /**
     * Prefixes that make an identifier read as a yes/no question. The
     * auxiliary/modal family — `has` among them — rather than an open-ended
     * list of verbs, so the check stays predictable.
     */
    private const QUESTION_PREFIXES = [
        'is',
        'are',
        'was',
        'were',
        'has',
        'have',
        'had',
        'can',
        'could',
        'should',
        'shall',
        'will',
        'would',
        'must',
        'may',
        'might',
        'does',
        'did',
        'needs',
    ];

    /**
     * Eloquent base classes (short names) that mark their subclass as a model
     * when the namespace does not already say so.
     */
    private const MODEL_BASE_CLASSES = [
        'Model',
        'Authenticatable',
        'Pivot',
        'MorphPivot',
    ];

    /**
     * Namespace segment that marks a class as a model, matched
     * case-insensitively against every segment.
     */
    private const MODELS_SEGMENT = 'models';

    /**
     * Return types (short names) treated as "a collection of models".
     */
    private const COLLECTION_TYPES = [
        'collection',
        'lazycollection',
        'enumerable',
    ];

    /**
     * Types that are never a model, so a return type naming one is not held to
     * the `find` prefix.
     */
    private const BUILTIN_TYPES = [
        'string',
        'int',
        'integer',
        'float',
        'double',
        'bool',
        'boolean',
        'array',
        'void',
        'never',
        'mixed',
        'iterable',
        'object',
        'callable',
        'null',
        'false',
        'true',
        'resource',
    ];

    /**
     * Types that always denote the enclosing model itself.
     */
    private const SELF_TYPES = [
        'self',
        'static',
        '$this',
        'parent',
    ];

    /**
     * Eloquent override points that return a model or a collection but whose
     * names are fixed by the framework — renaming them breaks the override, so
     * the prefix rules do not apply.
     */
    private const FRAMEWORK_METHODS = [
        'newCollection',
        'newModelInstance',
        'newFromBuilder',
        'newInstance',
        'newPivot',
        'newRelatedInstance',
        'replicate',
        'fresh',
        'refresh',
    ];

    private const MESSAGE_BOOLEAN_PROPERTY =
        'Boolean model property "$%s" must read as a yes/no question — prefix it with is, has, should, …';

    private const MESSAGE_BOOLEAN_METHOD =
        'Boolean model method "%s()" must read as a yes/no question — prefix it with has, is, should, …';

    private const MESSAGE_FIND_PREFIX =
        'Model method "%s()" returns a single model instance and must be prefixed "find", e.g. findUserByName()';

    private const MESSAGE_FIND_MODEL_NAME =
        'Model method "%s()" must name the model it returns ("%s"), e.g. find%sByName()';

    private const MESSAGE_GET_PREFIX =
        'Model method "%s()" returns a collection and must be prefixed "get", e.g. getUsersByType()';

    private const MESSAGE_LEGACY_ATTRIBUTE =
        'Model accessor "%s()" uses the legacy attribute style — use the new Attribute implementation instead';

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
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $namespace = $this->currentNamespace($phpcsFile);

        if ($this->isModel($phpcsFile, $stackPtr, $namespace) === false) {
            return;
        }

        $imports = $this->importMap($phpcsFile);
        $end = $tokens[$stackPtr]['scope_closer'];
        $ptr = $tokens[$stackPtr]['scope_opener'] + 1;

        while ($ptr < $end) {
            if ($tokens[$ptr]['code'] === T_FUNCTION) {
                $this->processMethod($phpcsFile, $ptr, $namespace, $imports);

                // Jump the body so locals, closures, and nested anonymous
                // classes are never mistaken for members of this model.
                $ptr = $this->endOfMethod($phpcsFile, $ptr);

                continue;
            }

            if ($tokens[$ptr]['code'] === T_VARIABLE) {
                $this->processProperty($phpcsFile, $ptr);
            }

            $ptr++;
        }
    }

    /**
     * Flags a `bool`-typed property whose name does not read as a yes/no
     * question. An untyped property carries no signal and is skipped.
     */
    private function processProperty(File $phpcsFile, int $stackPtr): void
    {
        try {
            $property = $phpcsFile->getMemberProperties($stackPtr);
        } catch (RuntimeException) {
            // PHPCS throws for any T_VARIABLE that is not a member variable.
            // The walk in process() already keeps those out — method bodies
            // and abstract-method parameter lists are both skipped — so this
            // is the safety net for a shape it has not met: skip the token
            // rather than let an exception abort the whole PHPCS run.
            return;
        }

        if (strtolower($this->normalizeType($property['type'])) !== 'bool') {
            return;
        }

        $name = ltrim($phpcsFile->getTokens()[$stackPtr]['content'], '$');

        if ($this->hasQuestionPrefix($name)) {
            return;
        }

        $phpcsFile->addError(self::MESSAGE_BOOLEAN_PROPERTY, $stackPtr, 'BooleanPropertyPrefix', [$name]);
    }

    /**
     * Applies the method-side rules: legacy accessor style first (it is about
     * the declaration shape, not the return type), then the return-type-driven
     * prefix rules.
     *
     * @param array<string, string> $imports
     */
    private function processMethod(File $phpcsFile, int $stackPtr, string $namespace, array $imports): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || str_starts_with($name, '__')) {
            return;
        }

        if (preg_match('/^(?:get|set)[A-Z][A-Za-z0-9_]*Attribute$/', $name) === 1) {
            $phpcsFile->addError(self::MESSAGE_LEGACY_ATTRIBUTE, $stackPtr, 'LegacyAttributeAccessor', [$name]);

            return;
        }

        if (in_array($name, self::FRAMEWORK_METHODS, true)) {
            return;
        }

        $type = $this->normalizeType($phpcsFile->getMethodProperties($stackPtr)['return_type']);

        if ($type === '') {
            return;
        }

        if (strtolower($type) === 'bool') {
            if ($this->hasQuestionPrefix($name) === false) {
                $phpcsFile->addError(self::MESSAGE_BOOLEAN_METHOD, $stackPtr, 'BooleanMethodPrefix', [$name]);
            }

            return;
        }

        if (in_array(strtolower($this->shortName($type)), self::COLLECTION_TYPES, true)) {
            if ($this->hasPrefix($name, 'get') === false) {
                $phpcsFile->addError(self::MESSAGE_GET_PREFIX, $stackPtr, 'GetMethodPrefix', [$name]);
            }

            return;
        }

        if ($this->isModelType($type, $namespace, $imports) === false) {
            return;
        }

        if ($this->hasPrefix($name, 'find') === false) {
            $phpcsFile->addError(self::MESSAGE_FIND_PREFIX, $stackPtr, 'FindMethodPrefix', [$name]);

            return;
        }

        // `self`/`static`/`$this`/`parent` name no model, so there is nothing
        // to require in the rest of the method name.
        $model = in_array(strtolower($type), self::SELF_TYPES, true) ? '' : $this->shortName($type);

        if ($model !== '' && str_contains(substr($name, strlen('find')), $model) === false) {
            $phpcsFile->addError(
                self::MESSAGE_FIND_MODEL_NAME,
                $stackPtr,
                'FindModelName',
                [$name, $model, $model]
            );
        }
    }

    /**
     * True when the class at $classPtr is an Eloquent model: declared under a
     * `Models` namespace segment, or extending a recognised Eloquent base.
     */
    private function isModel(File $phpcsFile, int $classPtr, string $namespace): bool
    {
        if ($this->hasModelsSegment($namespace)) {
            return true;
        }

        $extends = $phpcsFile->findExtendedClassName($classPtr);

        return $extends !== false && in_array($this->shortName($extends), self::MODEL_BASE_CLASSES, true);
    }

    /**
     * True when the return type denotes a model instance: the enclosing model
     * itself, or a class resolving into a `Models` namespace.
     *
     * @param array<string, string> $imports
     */
    private function isModelType(string $type, string $namespace, array $imports): bool
    {
        $lower = strtolower($type);

        if (in_array($lower, self::SELF_TYPES, true)) {
            return true;
        }

        if (in_array($lower, self::BUILTIN_TYPES, true)) {
            return false;
        }

        return $this->hasModelsSegment($this->resolveType($type, $namespace, $imports));
    }

    /**
     * Expands a return type as written into the namespace it resolves to: an
     * already-qualified name stands as-is, an imported short name resolves
     * through the import, and anything else resolves against the enclosing
     * namespace (PHP's own fallback for an unimported name).
     *
     * @param array<string, string> $imports
     */
    private function resolveType(string $type, string $namespace, array $imports): string
    {
        $type = ltrim($type, '\\');

        if (str_contains($type, '\\')) {
            return $type;
        }

        if (isset($imports[$type])) {
            return $imports[$type];
        }

        return ($namespace === '') ? $type : $namespace . '\\' . $type;
    }

    /**
     * True when any segment of a namespaced name is `Models`.
     */
    private function hasModelsSegment(string $name): bool
    {
        foreach (explode('\\', $name) as $segment) {
            if (strtolower($segment) === self::MODELS_SEGMENT) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduces a declared type to the single type worth checking: nullability is
     * dropped (`?User` and `User|null` both describe a `User`), and a genuine
     * union or an intersection yields '' — no single type to reason about.
     */
    private function normalizeType(string $type): string
    {
        $type = ltrim(trim($type), '?');

        if ($type === '' || str_contains($type, '&')) {
            return '';
        }

        $parts = array_values(array_filter(
            array_map('trim', explode('|', $type)),
            static fn (string $part): bool => $part !== '' && strtolower($part) !== 'null'
        ));

        return (count($parts) === 1) ? $parts[0] : '';
    }

    private function shortName(string $name): string
    {
        $segments = explode('\\', $name);

        return end($segments);
    }

    private function hasQuestionPrefix(string $name): bool
    {
        foreach (self::QUESTION_PREFIXES as $prefix) {
            if ($this->hasPrefix($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when $name starts with $prefix as a whole word — the next character
     * must begin a new word (upper case or a digit), so `island` is not read as
     * the `is` prefix while `isLand` and `is2Fa` are.
     */
    private function hasPrefix(string $name, string $prefix): bool
    {
        if (str_starts_with($name, $prefix) === false) {
            return false;
        }

        $next = substr($name, strlen($prefix), 1);

        return $next === '' || $next === strtoupper($next);
    }

    /**
     * The token after a method declaration: past its body, or past the
     * semicolon when it has none. The second branch is what keeps an abstract
     * method's parameters out of member discovery — they sit at what otherwise
     * looks like class-body level.
     */
    private function endOfMethod(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_closer'])) {
            return $tokens[$stackPtr]['scope_closer'] + 1;
        }

        $semicolon = $phpcsFile->findNext(T_SEMICOLON, $stackPtr + 1);

        return ($semicolon === false) ? ($stackPtr + 1) : ($semicolon + 1);
    }

    /**
     * The namespace the file declares, or '' when it declares none.
     */
    private function currentNamespace(File $phpcsFile): string
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $phpcsFile->findNext(T_NAMESPACE, 0);

        if ($ptr === false) {
            return '';
        }

        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $ptr + 1);

        if ($end === false) {
            return '';
        }

        $namespace = '';

        for ($i = ($ptr + 1); $i < $end; $i++) {
            if (in_array($tokens[$i]['code'], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                $namespace .= $tokens[$i]['content'];
            }
        }

        return trim($namespace, '\\');
    }

    /**
     * Maps every imported short name (or alias) in the file to the name it
     * resolves to, covering both plain and group `use` statements. Class
     * imports only — `use function`/`use const`, trait uses inside a class, and
     * closure `use (…)` clauses are skipped.
     *
     * @return array<string, string>
     */
    private function importMap(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $map = [];
        $ptr = -1;

        while (($ptr = $phpcsFile->findNext(T_USE, $ptr + 1)) !== false) {
            if ($tokens[$ptr]['conditions'] !== []) {
                continue;
            }

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, $ptr + 1, null, true);

            if (
                $next === false
                || in_array($tokens[$next]['code'], [T_OPEN_PARENTHESIS, T_FUNCTION, T_CONST], true)
            ) {
                continue;
            }

            $end = $phpcsFile->findNext(T_SEMICOLON, $ptr + 1);

            if ($end === false) {
                break;
            }

            $statement = '';

            for ($i = ($ptr + 1); $i < $end; $i++) {
                $statement .= $tokens[$i]['content'];
            }

            $map += $this->parseUseStatement($statement);
            $ptr = $end;
        }

        return $map;
    }

    /**
     * Parses the body of one `use` statement (everything between the keyword
     * and its semicolon) into alias => resolved-name pairs.
     *
     * @return array<string, string>
     */
    private function parseUseStatement(string $statement): array
    {
        $statement = trim($statement);
        $prefix = '';

        if (str_contains($statement, '{')) {
            [$prefix, $statement] = explode('{', $statement, 2);
            $prefix = trim(trim($prefix), '\\');
            $statement = rtrim(rtrim($statement), '}');
        }

        $map = [];

        foreach (explode(',', $statement) as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            if (preg_match('/^(.+?)\s+as\s+([A-Za-z0-9_]+)$/i', $name, $matches) === 1) {
                $name = trim($matches[1]);
                $alias = $matches[2];
            } else {
                $alias = $this->shortName($name);
            }

            $resolved = ltrim($name, '\\');
            $map[$alias] = ($prefix === '') ? $resolved : $prefix . '\\' . $resolved;
        }

        return $map;
    }
}
