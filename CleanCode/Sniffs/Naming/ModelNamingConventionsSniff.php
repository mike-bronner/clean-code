<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ModelNamingConventionsSniff implements Sniff
{
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

    private const MODEL_BASE_CLASSES = [
        'illuminate\database\eloquent\model',
        'illuminate\foundation\auth\user',
        'illuminate\database\eloquent\relations\pivot',
        'illuminate\database\eloquent\relations\morphpivot',
    ];

    private const MODELS_SEGMENT = 'models';

    private const COLLECTION_TYPES = [
        'collection',
        'lazycollection',
        'enumerable',
    ];

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

    private const SELF_TYPES = [
        'self',
        'static',
        'parent',
    ];

    private const FRAMEWORK_METHODS = [
        'newcollection',
        'newmodelinstance',
        'newfrombuilder',
        'newinstance',
        'newpivot',
        'newrelatedinstance',
        'replicate',
        'fresh',
        'refresh',
    ];

    private const MESSAGE_BOOLEAN_PROPERTY
        = "Boolean model property \"\$%s\" must read as a yes/no question — prefix it with is, has, should, …";

    private const MESSAGE_BOOLEAN_METHOD
        = "Boolean model method \"%s()\" must read as a yes/no question — prefix it with has, is, should, …";

    private const MESSAGE_FIND_PREFIX
        = "Model method \"%s()\" returns a single model instance and must be prefixed \"find\", e.g. findUserByName()";

    private const MESSAGE_FIND_MODEL_NAME
        = "Model method \"%s()\" must name the model it returns (\"%s\"), e.g. find%sByName()";

    private const MESSAGE_GET_PREFIX
        = "Model method \"%s()\" returns a collection and must be prefixed \"get\", e.g. getUsersByType()";

    private const MESSAGE_LEGACY_ATTRIBUTE
        = "Model accessor \"%s()\" uses the legacy attribute style — use the new Attribute implementation instead";

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
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

    private function processMethod(File $phpcsFile, int $stackPtr, string $namespace, array $imports): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || str_starts_with($name, '__')
        ) {
            return;
        }

        // Matched case-insensitively because Eloquent finds an accessor with
        // method_exists($this, 'get' . Str::studly($key) . 'Attribute'), and
        // method_exists() folds case — so `getFooattribute()` is a live legacy
        // accessor for `foo`, and nothing else in the ruleset would catch it
        // (it is valid PSR-1 camelCase).
        if (preg_match('/^(?:get|set)[A-Za-z0-9_]+Attribute$/i', $name) === 1) {
            $phpcsFile->addError(self::MESSAGE_LEGACY_ATTRIBUTE, $stackPtr, 'LegacyAttributeAccessor', [$name]);

            return;
        }

        if (in_array(strtolower($name), self::FRAMEWORK_METHODS, true)) {
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

        // `self`/`static`/`parent` name no model, so there is nothing to
        // require in the rest of the method name.
        $model = in_array(strtolower($type), self::SELF_TYPES, true) ? '' : $this->shortName($resolved);

        if (
            $model !== ''
            && str_contains(substr($name, strlen('find')), $model) === false
        ) {
            $phpcsFile->addError(
                self::MESSAGE_FIND_MODEL_NAME,
                $stackPtr,
                'FindModelName',
                [$name, $model, $model]
            );
        }
    }

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

    private function resolveType(string $type, string $namespace, array $imports): string
    {
        if (str_starts_with($type, '\\')) {
            // The trim is normalisation, so every branch returns the same
            // shape; only the early return itself carries behaviour.
            return ltrim($type, '\\');
        }

        [$head, $rest] = array_pad(explode('\\', $type, 2), 2, null);

        // Lower-cased, because the map is keyed that way: PHP matches a
        // reference to its `use` statement case-insensitively, so
        // `use App\Models\APIToken;` is reached by `ApiToken` as well. Missing
        // the map would silently fall through to namespace-qualification, which
        // both hides violations and invents them.
        $head = strtolower($head);

        if (isset($imports[$head])) {
            return ($rest === null) ? $imports[$head] : "{$imports[$head]}\\{$rest}";
        }

        return ($namespace === '') ? $type : "{$namespace}\\{$type}";
    }

    private function hasModelsSegment(string $name): bool
    {
        foreach (explode('\\', $name) as $segment) {
            if (strtolower($segment) === self::MODELS_SEGMENT) {
                return true;
            }
        }

        return false;
    }

    private function normalizeType(string $type): string
    {
        $type = ltrim(trim($type), '?');

        if (
            $type === ''
            || str_contains($type, '&')
        ) {
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

    private function hasPrefix(string $name, string $prefix): bool
    {
        if (str_starts_with($name, $prefix) === false) {
            return false;
        }

        $next = substr($name, strlen($prefix), 1);

        return $next === '' || $next === strtoupper($next);
    }

    private function endOfMethod(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_closer'])) {
            return $tokens[$stackPtr]['scope_closer'] + 1;
        }

        $semicolon = $phpcsFile->findNext(T_SEMICOLON, $stackPtr + 1);

        return ($semicolon === false) ? ($stackPtr + 1) : ($semicolon + 1);
    }

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

            if (
                $next === false
                || $tokens[$next]['code'] === T_OPEN_PARENTHESIS
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

            if (
                $name === ''
                || $this->isSymbolImport($name)
            ) {
                continue;
            }

            $aliased = preg_match('/^(.+?)\s+as\s+([A-Za-z0-9_]+)$/i', $name, $matches) === 1;

            if ($aliased === true) {
                $name = trim($matches[1]);
            }

            $alias = $aliased === true ? $matches[2] : $this->shortName($name);

            $resolved = ltrim($name, '\\');

            // The key is lower-cased so a reference cased differently from its
            // `use` still finds it, the way PHP does. The *value* keeps its
            // source casing: shortName() feeds it into message text, where the
            // model's real spelling is the whole point of the advice.
            $map[strtolower($alias)] = ($prefix === '') ? $resolved : "{$prefix}\\{$resolved}";
        }

        return $map;
    }

    private function isSymbolImport(string $name): bool
    {
        return preg_match('/^(?:function|const)\s/i', $name) === 1;
    }
}
