<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ModelMagicMethodLocationSniff implements Sniff
{
    private const ATTRIBUTE_CAST = 'illuminate\database\eloquent\casts\attribute';

    private const SCOPE_ATTRIBUTE = 'scope';

    private const ATTRIBUTES = 'Attributes';

    private const QUERIES = 'Queries';

    private const ACCESSOR_PATTERN = '/^(?:get|set)[A-Z].*Attribute$/';

    private const SCOPE_PATTERN = '/^scope[A-Z]/';

    private const IMPORT_KEYWORDS = ['function', 'const'];

    private const NAME_TOKENS = [T_STRING, T_NS_SEPARATOR];

    private const NAME_STARTERS = [T_ATTRIBUTE, T_COMMA];

    private const MEMBER_ENDS = [T_COMMA, T_CLOSE_USE_GROUP, T_SEMICOLON];

    private const NESTED_SCOPES = [T_ANON_CLASS, T_CLOSURE, T_FN, T_FUNCTION];

    private const NO_POINTER = -1;

    private const MESSAGE = <<<'MESSAGE'
        Model method %s() is declared in the class body; extract it to the model's %s trait
        (e.g. App\Concerns\%s\Book) so the model stays lean
        (see docs/standards/models-structure-attributes-queries-traits.md)
        MESSAGE;

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $imports = $this->readImports($phpcsFile);

        // Walked to the end of the file rather than to the class's closing
        // brace: PHP_CodeSniffer publishes a scope's bounds on the token array
        // only, and isOwnMethod() already answers the question the bound would
        // — a declaration belonging to anything other than this class is
        // rejected whether it sits inside the body or after it.
        $bodyPtr = ($stackPtr + 1);

        foreach ($this->pointersOfType($phpcsFile, T_FUNCTION, $bodyPtr, null) as $functionPtr) {
            $this->inspect($phpcsFile, $functionPtr, $stackPtr, $imports);
        }
    }

    private function inspect(File $phpcsFile, int $functionPtr, int $classPtr, array $imports): void
    {
        $name = $this->declarationName($phpcsFile, $functionPtr);
        $destination = $this->destinationTrait($phpcsFile, $functionPtr, $name, $imports);

        // One code per destination trait, so a ruleset can silence either half
        // on its own.
        $code = match ($destination) {
            self::ATTRIBUTES => 'AttributeMethod',
            default => 'ScopeMethod',
        };

        match (true) {
            $this->isOwnMethod($phpcsFile, $functionPtr, $classPtr) === false => null,
            $destination === null => null,
            default => $phpcsFile->addWarning(
                $this->message(),
                $functionPtr,
                $code,
                [$name, $destination, $destination]
            ),
        };
    }

    private function isOwnMethod(File $phpcsFile, int $functionPtr, int $classPtr): bool
    {
        $ownerPtr = $this->conditionPointer($phpcsFile, $functionPtr, T_CLASS);
        $nestedPtr = $this->innermostConditionPointer(
            $phpcsFile,
            $functionPtr,
            self::NESTED_SCOPES
        );

        return match ($ownerPtr) {
            $classPtr => $classPtr > ($nestedPtr ?? self::NO_POINTER),
            default => false,
        };
    }

    private function destinationTrait(
        File $phpcsFile,
        int $functionPtr,
        ?string $name,
        array $imports
    ): ?string {
        return match (true) {
            $name === null => null,
            preg_match(self::ACCESSOR_PATTERN, $name) === 1 => self::ATTRIBUTES,
            $this->hasAttributeCastReturn($phpcsFile, $functionPtr, $imports) === true
                => self::ATTRIBUTES,
            preg_match(self::SCOPE_PATTERN, $name) === 1 => self::QUERIES,
            $this->hasScopeAttribute($phpcsFile, $functionPtr) === true => self::QUERIES,
            default => null,
        };
    }

    private function declarationName(File $phpcsFile, int $functionPtr): ?string
    {
        $afterPtr = $this->nextSignificant($phpcsFile, $functionPtr);

        // Steps the `&` of a by-reference declaration, which sits between the
        // `function` keyword and the name.
        $namePtr = match ($this->isToken($phpcsFile, $afterPtr, T_BITWISE_AND)) {
            true => $this->nextSignificant($phpcsFile, $afterPtr),
            default => $afterPtr,
        };
        $openPtr = $this->nextSignificant($phpcsFile, $namePtr);

        return match (true) {
            $this->isToken($phpcsFile, $namePtr, T_STRING) === false => null,
            $this->isToken($phpcsFile, $openPtr, T_OPEN_PARENTHESIS) === false => null,
            default => $this->contentOf($phpcsFile, (int) $namePtr),
        };
    }

    private function hasAttributeCastReturn(File $phpcsFile, int $functionPtr, array $imports): bool
    {
        $returnType = $phpcsFile->getMethodProperties($functionPtr)['return_type'];
        $found = false;

        foreach (explode('|', str_replace('&', '|', $returnType)) as $member) {
            $name = trim($member, "? \t\n\r\0\x0B()");
            $found = match (true) {
                $found === true => true,
                $name === '' => false,
                default => $this->resolve($name, $imports) === self::ATTRIBUTE_CAST,
            };
        }

        return $found;
    }

    private function resolve(string $name, array $imports): string
    {
        $segments = explode('\\', $name);
        $head = strtolower((string) array_shift($segments));
        $resolved = strtolower($name);

        // No import binds the empty name readNamedImport() refuses to record,
        // so a fully-qualified spelling — whose first segment is empty — passes
        // through this fold untouched and is answered by the return below.
        foreach ($imports as $alias => $fullyQualified) {
            $resolved = match ($alias) {
                $head => strtolower(implode('\\', array_merge([$fullyQualified], $segments))),
                default => $resolved,
            };
        }

        return match (str_starts_with($name, '\\')) {
            true => strtolower(ltrim($name, '\\')),
            default => $resolved,
        };
    }

    private function hasScopeAttribute(File $phpcsFile, int $functionPtr): bool
    {
        $closerPtr = $this->attributeCloserBefore($phpcsFile, $functionPtr);
        $found = false;

        while ($closerPtr !== null) {
            $openerPtr = $this->orNull($phpcsFile->findPrevious(T_ATTRIBUTE, ($closerPtr - 1)));
            $found = match (true) {
                $found === true => true,
                $openerPtr === null => false,
                default => in_array(
                    self::SCOPE_ATTRIBUTE,
                    $this->attributeNames($phpcsFile, $openerPtr, $closerPtr),
                    true
                ),
            };
            $closerPtr = $this->attributeCloserBefore($phpcsFile, $openerPtr);
        }

        return $found;
    }

    private function attributeCloserBefore(File $phpcsFile, ?int $stackPtr): ?int
    {
        // The previous token that is neither empty nor one of the keywords a
        // declaration may carry, which is where an attribute group's `]` sits.
        $skippable = array_merge(Tokens::$emptyTokens, Tokens::$methodPrefixes);
        $previousPtr = match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findPrevious($skippable, ($stackPtr - 1), null, true)
            ),
        };

        return match ($this->isToken($phpcsFile, $previousPtr, T_ATTRIBUTE_END)) {
            false => null,
            default => $previousPtr,
        };
    }

    private function attributeNames(File $phpcsFile, int $openerPtr, int $closerPtr): array
    {
        $names = [];
        $first = ($openerPtr + 1);

        foreach ($this->pointersOfType($phpcsFile, self::NAME_TOKENS, $first, $closerPtr) as $ptr) {
            $previousPtr = $this->orNull(
                $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true)
            );
            [$name] = $this->readName($phpcsFile, $ptr);

            // Depth is counted rather than read from the token array's
            // nested_parenthesis map, which File does not publish. An attribute
            // group holds no construct that can put an unbalanced parenthesis
            // in front of a name, so every argument list opened before the
            // pointer and not yet closed leaves the two counts apart.
            $opened = $this->pointersOfType($phpcsFile, T_OPEN_PARENTHESIS, $openerPtr, $ptr);
            $closed = $this->pointersOfType($phpcsFile, T_CLOSE_PARENTHESIS, $openerPtr, $ptr);

            $names = array_merge($names, match (true) {
                $this->isToken($phpcsFile, $previousPtr, self::NAME_STARTERS) === false => [],
                count($opened) !== count($closed) => [],
                default => [$this->shortName($name)],
            });
        }

        return $names;
    }

    private function shortName(string $name): string
    {
        $segments = explode('\\', $name);

        return strtolower((string) end($segments));
    }

    private function readImports(File $phpcsFile): array
    {
        $imports = [];

        foreach ($this->pointersOfType($phpcsFile, T_USE, 0, null) as $usePtr) {
            $imports += $this->readFileLevelImport($phpcsFile, $usePtr);
        }

        return $imports;
    }

    private function readFileLevelImport(File $phpcsFile, int $usePtr): array
    {
        $firstPtr = $this->nextSignificant($phpcsFile, $usePtr);

        return match (true) {
            $phpcsFile->hasCondition($usePtr, Tokens::$scopeOpeners) === true => [],
            $this->isToken($phpcsFile, $firstPtr, self::NAME_TOKENS) === false => [],
            $this->isImportKeyword($phpcsFile, (int) $firstPtr) === true => [],
            default => $this->readImportBody($phpcsFile, (int) $firstPtr),
        };
    }

    private function readImportBody(File $phpcsFile, int $firstPtr): array
    {
        [$prefix, $endPtr] = $this->readName($phpcsFile, $firstPtr);
        $groupPtr = $this->nextSignificant($phpcsFile, $endPtr);
        $memberPtr = $this->nextSignificant($phpcsFile, $groupPtr);

        return match ($this->isToken($phpcsFile, $groupPtr, T_OPEN_USE_GROUP)) {
            true => $this->readMembers($phpcsFile, $memberPtr, $prefix),
            default => $this->readMembers($phpcsFile, $firstPtr, ''),
        };
    }

    private function readMembers(File $phpcsFile, ?int $startPtr, string $prefix): array
    {
        $imports = [];
        $ptr = $startPtr;

        while ($ptr !== null) {
            $imports += $this->readMember($phpcsFile, $ptr, $prefix);
            $ptr = $this->nextMemberPointer($phpcsFile, $ptr);
        }

        return $imports;
    }

    private function readMember(File $phpcsFile, int $startPtr, string $prefix): array
    {
        // Read before the keyword test rather than after it, so the whole
        // member is one expression. readName() walks a `function b` member's
        // two name tokens into one glued name, which is exactly why that
        // member must not be recorded; the keyword arm below discards it.
        [$name, $endPtr] = $this->readName($phpcsFile, $startPtr);
        $fullyQualified = ltrim($prefix . $name, '\\');
        $alias = $this->aliasOf($phpcsFile, $endPtr, $fullyQualified);

        return match (true) {
            $this->isImportKeyword($phpcsFile, $startPtr) === true => [],
            $alias === '' => [],
            default => [$alias => $fullyQualified],
        };
    }

    private function aliasOf(File $phpcsFile, int $endPtr, string $fullyQualified): string
    {
        $asPtr = $this->nextSignificant($phpcsFile, $endPtr);
        $aliasPtr = $this->nextSignificant($phpcsFile, $asPtr);
        $bound = $this->shortName($fullyQualified);

        return match (true) {
            $this->isToken($phpcsFile, $asPtr, T_AS) === false => $bound,
            $this->isToken($phpcsFile, $aliasPtr, T_STRING) === false => $bound,
            default => strtolower($this->contentOf($phpcsFile, (int) $aliasPtr)),
        };
    }

    private function nextMemberPointer(File $phpcsFile, int $startPtr): ?int
    {
        $endPtr = $this->orNull($phpcsFile->findNext(self::MEMBER_ENDS, ($startPtr + 1)));

        return match ($this->isToken($phpcsFile, $endPtr, T_COMMA)) {
            false => null,
            default => $this->nextSignificant($phpcsFile, $endPtr),
        };
    }

    private function isImportKeyword(File $phpcsFile, int $stackPtr): bool
    {
        return in_array(
            strtolower($this->contentOf($phpcsFile, $stackPtr)),
            self::IMPORT_KEYWORDS,
            true
        );
    }

    private function readName(File $phpcsFile, int $startPtr): array
    {
        $name = '';
        $endPtr = $startPtr;
        $ptr = $startPtr;

        while ($this->isToken($phpcsFile, $ptr, self::NAME_TOKENS) === true) {
            $name .= $this->contentOf($phpcsFile, (int) $ptr);
            $endPtr = (int) $ptr;
            $ptr = $this->nextSignificant($phpcsFile, $ptr);
        }

        return [$name, $endPtr];
    }

    private function pointersOfType(
        File $phpcsFile,
        array|int|string $types,
        int $startPtr,
        ?int $endPtr
    ): array {
        $pointers = [];
        $ptr = $this->orNull($phpcsFile->findNext($types, $startPtr, $endPtr));

        while ($ptr !== null) {
            $pointers[] = $ptr;
            $ptr = $this->orNull($phpcsFile->findNext($types, ($ptr + 1), $endPtr));
        }

        return $pointers;
    }

    private function innermostConditionPointer(File $phpcsFile, int $stackPtr, array $types): ?int
    {
        $innermost = self::NO_POINTER;

        foreach ($types as $type) {
            $innermost = max(
                $innermost,
                $this->conditionPointer($phpcsFile, $stackPtr, $type) ?? self::NO_POINTER
            );
        }

        return match ($innermost) {
            self::NO_POINTER => null,
            default => $innermost,
        };
    }

    private function conditionPointer(File $phpcsFile, int $stackPtr, int|string $type): ?int
    {
        // The assignment is hoisted out of the match subject rather than
        // written inline: rules.xml reports an assignment in a condition (#79),
        // and a match subject is one of the conditions it reads.
        $pointer = $phpcsFile->getCondition($stackPtr, $type, false);

        return $this->orNull($pointer);
    }

    private function nextSignificant(File $phpcsFile, ?int $stackPtr): ?int
    {
        return match ($stackPtr) {
            null => null,
            default => $this->orNull(
                $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true)
            ),
        };
    }

    private function isToken(File $phpcsFile, ?int $stackPtr, array|int|string $types): bool
    {
        return match ($stackPtr) {
            null => false,
            default => $phpcsFile->findNext($types, $stackPtr, ($stackPtr + 1)) !== false,
        };
    }

    private function contentOf(File $phpcsFile, int $stackPtr): string
    {
        return $phpcsFile->getTokensAsString($stackPtr, 1);
    }

    private function message(): string
    {
        return str_replace("\n", ' ', self::MESSAGE);
    }

    private function orNull(int|false $pointer): ?int
    {
        return match ($pointer) {
            false => null,
            default => $pointer,
        };
    }
}
