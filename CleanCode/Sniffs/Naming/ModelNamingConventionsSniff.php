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
 * (`Model`, `Authenticatable`, `Pivot`, `MorphPivot`). Everything else in the
 * tree is left alone — the naming rules below describe how models expose data
 * and would be noise anywhere else.
 *
 * Every name the sniff interprets — the base class, and every return type — is
 * put through resolveType() first and only then reduced to a short name, so an
 * aliased import is followed the way PHP itself follows it. Reading a name as
 * written is the one mistake this sniff cannot afford: `extends EloquentModel`
 * would stop looking like a model, `extends Model` aliased onto a value object
 * would start looking like one, an aliased `Collection` would slip the `get`
 * rule, and `findUserByName(): Client` would be told to rename itself after the
 * alias rather than the model.
 *
 * Six checks, all reporting-only (renaming an identifier is never safe for a
 * fixer, and rewriting a legacy accessor is a semantic change):
 *
 * - **BooleanPropertyPrefix** — a `bool`-typed property must read as a yes/no
 *   question (`isActive`, `hasQuota`, `shouldQueue`), whether it is declared in
 *   the class body or promoted from a constructor parameter.
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
     * Eloquent base classes that mark their subclass as a model when the
     * namespace does not already say so. Held fully qualified, and compared
     * against what `extends` resolves to, because the short name is not a
     * reliable signal in either direction: `Authenticatable` is a conventional
     * *alias* of `Illuminate\Foundation\Auth\User` rather than any class's real
     * name, and a bare `Model` is whatever the file's imports and namespace say
     * it is — which, outside `Illuminate\Database\Eloquent`, is Eloquent's
     * `Model` only when an import or a leading `\` makes it so.
     */
    private const MODEL_BASE_CLASSES = [
        'illuminate\database\eloquent\model',
        'illuminate\foundation\auth\user',
        'illuminate\database\eloquent\relations\pivot',
        'illuminate\database\eloquent\relations\morphpivot',
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
        $imports = $this->importMap($phpcsFile);

        if ($this->isModel($phpcsFile, $stackPtr, $namespace, $imports) === false) {
            return;
        }

        $end = $tokens[$stackPtr]['scope_closer'];
        $ptr = $tokens[$stackPtr]['scope_opener'] + 1;

        while ($ptr < $end) {
            if ($tokens[$ptr]['code'] === T_FUNCTION) {
                $this->processMethod($phpcsFile, $ptr, $namespace, $imports);
                $this->processPromotedProperties($phpcsFile, $ptr);

                // Jump the parameter list and the body in one step, so locals,
                // closures, and nested anonymous classes are never mistaken for
                // members of this model. Promoted properties live in the part
                // being jumped, which is why they are collected above rather
                // than by the T_VARIABLE branch below.
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

        $name = ltrim($phpcsFile->getTokens()[$stackPtr]['content'], '$');

        $this->reportBooleanProperty($phpcsFile, $stackPtr, $property['type'], $name);
    }

    /**
     * Flags constructor-promoted properties, which declare a property in the
     * parameter list rather than the class body. They are as much a member as
     * a classically declared one, and the package's own ruleset references
     * SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion — so
     * running the fixer rewrites class-body properties into this shape, and a
     * check blind to it would let `composer fix` launder its own findings away.
     *
     * A plain parameter carries no visibility modifier and declares no
     * property, so it is left alone.
     *
     * Unlike processProperty(), this needs no exception guard: PHPCS raises
     * only for a token that is not a function/closure/arrow-function, and the
     * caller reaches this exclusively from the T_FUNCTION branch.
     */
    private function processPromotedProperties(File $phpcsFile, int $stackPtr): void
    {
        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            if (isset($parameter['property_visibility']) === false) {
                continue;
            }

            $this->reportBooleanProperty(
                $phpcsFile,
                $parameter['token'],
                $parameter['type_hint'],
                ltrim($parameter['name'], '$')
            );
        }
    }

    /**
     * The BooleanPropertyPrefix check itself, shared by both ways a model can
     * declare a property. An untyped property carries no signal and is skipped.
     */
    private function reportBooleanProperty(File $phpcsFile, int $stackPtr, string $type, string $name): void
    {
        if (strtolower($this->normalizeType($type)) !== 'bool') {
            return;
        }

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

        // Every check below reads the name the return type *resolves* to, never
        // the name as written. A declared name means nothing until it has been
        // through the import map — `CollectionAlias` may be a collection and
        // `Client` may be `User` — and interpreting the alias itself both misses
        // violations and invents them.
        $resolved = $this->resolveType($type, $namespace, $imports);

        if (in_array(strtolower($this->shortName($resolved)), self::COLLECTION_TYPES, true)) {
            if ($this->hasPrefix($name, 'get') === false) {
                $phpcsFile->addError(self::MESSAGE_GET_PREFIX, $stackPtr, 'GetMethodPrefix', [$name]);
            }

            return;
        }

        if ($this->isModelType($type, $resolved) === false) {
            return;
        }

        if ($this->hasPrefix($name, 'find') === false) {
            $phpcsFile->addError(self::MESSAGE_FIND_PREFIX, $stackPtr, 'FindMethodPrefix', [$name]);

            return;
        }

        // `self`/`static`/`$this`/`parent` name no model, so there is nothing
        // to require in the rest of the method name.
        $model = in_array(strtolower($type), self::SELF_TYPES, true) ? '' : $this->shortName($resolved);

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
     *
     * The base class is read through resolveType() rather than as written:
     * `extends EloquentModel` under `use Illuminate\Database\Eloquent\Model as
     * EloquentModel;` is an Eloquent model, and `extends Model` under
     * `use App\Support\ValueObject as Model;` is not. Judging the name as
     * written gets both backwards.
     *
     * The comparison is case-insensitive because PHP class names are.
     *
     * @param array<string, string> $imports
     */
    private function isModel(File $phpcsFile, int $classPtr, string $namespace, array $imports): bool
    {
        if ($this->hasModelsSegment($namespace)) {
            return true;
        }

        $extends = $phpcsFile->findExtendedClassName($classPtr);

        if ($extends === false) {
            return false;
        }

        $base = strtolower($this->resolveType($extends, $namespace, $imports));

        return in_array($base, self::MODEL_BASE_CLASSES, true);
    }

    /**
     * True when the return type denotes a model instance: the enclosing model
     * itself, or a class resolving into a `Models` namespace.
     *
     * Takes both the type as written — `self`/`static` and the builtins are
     * keywords, not names an import could ever redirect — and the name it
     * resolves to, which is the only thing that can be tested for a `Models`
     * segment.
     */
    private function isModelType(string $type, string $resolved): bool
    {
        $lower = strtolower($type);

        if (in_array($lower, self::SELF_TYPES, true)) {
            return true;
        }

        if (in_array($lower, self::BUILTIN_TYPES, true)) {
            return false;
        }

        return $this->hasModelsSegment($resolved);
    }

    /**
     * Expands a return type as written into the name it resolves to, following
     * PHP's own resolution rules:
     *
     * - a **fully qualified** name (leading `\`) stands as written, bypassing
     *   the import map entirely — so `\DateTime` is `DateTime` even in a file
     *   that imports something else under that alias;
     * - otherwise the **first segment** is resolved through the imports, which
     *   covers both a short name (`User`) and a qualified one (`Relations\HasMany`
     *   under `use Illuminate\Database\Eloquent\Relations;`);
     * - anything left resolves against the enclosing namespace, PHP's fallback
     *   for an unimported name.
     *
     * @param array<string, string> $imports
     */
    private function resolveType(string $type, string $namespace, array $imports): string
    {
        if (str_starts_with($type, '\\')) {
            // The trim is normalisation, so every branch returns the same
            // shape; only the early return itself carries behaviour.
            return ltrim($type, '\\');
        }

        [$head, $rest] = array_pad(explode('\\', $type, 2), 2, null);

        if (isset($imports[$head])) {
            return ($rest === null) ? $imports[$head] : $imports[$head] . '\\' . $rest;
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
     * imports only — trait uses inside a class and closure `use (…)` clauses
     * are screened here, and `use function`/`use const` by parseUseStatement(),
     * whose docblock explains why the token stream leaves it no choice.
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

            // A closure declared at file level carries no enclosing condition,
            // so its `use (…)` clause reaches here. This keeps that clause out
            // of the map rather than fixing a violation: the check above
            // catches every closure inside a class, and the keys a file-level
            // one would contribute always hold a parenthesis or a space, which
            // no declared type can match. A guard, not a behaviour — no fixture
            // can tell its removal apart.
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, $ptr + 1, null, true);

            if ($next === false || $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
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
     * Function and constant imports are dropped here rather than by the caller,
     * because PHPCS retokenises the `function`/`const` marker of a `use`
     * statement as a plain `T_STRING` in every form — there is no token type
     * left to screen on, and the marker arrives as ordinary leading text.
     *
     * It can appear in either of two places, and neither check subsumes the
     * other:
     *
     * - **before the prefix**, marking the whole statement
     *   (`use function App\Support\{helper, tally};`) — screened first, since
     *   the split below would otherwise read `function App\Support` as a
     *   namespace and import every item under it;
     * - **on an individual item** of a group
     *   (`use App\Support\{ClassA, function helper};`) — screened per item, so
     *   the class beside it still imports.
     *
     * @return array<string, string>
     */
    private function parseUseStatement(string $statement): array
    {
        $statement = trim($statement);

        if ($this->isSymbolImport($statement)) {
            return [];
        }

        $prefix = '';

        if (str_contains($statement, '{')) {
            [$prefix, $statement] = explode('{', $statement, 2);
            $prefix = trim(trim($prefix), '\\');
            $statement = rtrim(rtrim($statement), '}');
        }

        $map = [];

        foreach (explode(',', $statement) as $name) {
            $name = trim($name);

            if ($name === '' || $this->isSymbolImport($name)) {
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

    /**
     * True when a `use` statement — or one item of a group `use` — is marked
     * `function` or `const`, and so imports from PHP's function or constant
     * table rather than importing a type. Neither can ever be what a return
     * type or an `extends` clause names.
     */
    private function isSymbolImport(string $name): bool
    {
        return preg_match('/^(?:function|const)\s/i', $name) === 1;
    }
}
