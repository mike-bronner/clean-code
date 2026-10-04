<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use MikeBronner\CleanCode\Helpers\AttributeCasts;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ModelMagicMethodLocationSniff implements Sniff
{
    private const SCOPE_ATTRIBUTE = 'scope';

    private const ATTRIBUTES = 'Attributes';

    private const QUERIES = 'Queries';

    private const ACCESSOR_PATTERN = '/^(?:get|set)[A-Z].*Attribute$/';

    private const SCOPE_PATTERN = '/^scope[A-Z]/';

    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
    ];

    private const NAME_STARTERS = [T_ATTRIBUTE, T_COMMA];

    private const NESTED_SCOPES = [T_ANON_CLASS, T_CLOSURE, T_FN, T_FUNCTION];

    private const NO_POINTER = -1;

    private const MESSAGE = <<<MESSAGE
        Model method %s() is declared in the class body; extract it to the model's %s trait
        (e.g. App\\Concerns\\%s\\Book) so the model stays lean
        (see resources/boost/guidelines/models-structure-attributes-queries-traits.md)
        MESSAGE;

    public function __construct(
        private AttributeCasts $attributeCasts = new AttributeCasts()
    ) {
    }

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $bodyPtr = ($stackPtr + 1);

        foreach ($this->pointersOfType($phpcsFile, T_FUNCTION, $bodyPtr, null) as $functionPtr) {
            $this->inspect($phpcsFile, $functionPtr, $stackPtr);
        }
    }

    private function inspect(File $phpcsFile, int $functionPtr, int $classPtr): void
    {
        $name = $this->declarationName($phpcsFile, $functionPtr);
        $destination = $this->destinationTrait($phpcsFile, $functionPtr, $name);

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
        ?string $name
    ): ?string {
        $attributeCasts = $this->attributeCasts;

        return match (true) {
            $name === null => null,
            preg_match(self::ACCESSOR_PATTERN, $name) === 1 => self::ATTRIBUTES,
            $attributeCasts->isReturnedBy($phpcsFile, $functionPtr) === true => self::ATTRIBUTES,
            preg_match(self::SCOPE_PATTERN, $name) === 1 => self::QUERIES,
            $this->hasScopeAttribute($phpcsFile, $functionPtr) === true => self::QUERIES,
            default => null,
        };
    }

    private function declarationName(File $phpcsFile, int $functionPtr): ?string
    {
        $afterPtr = $this->nextSignificant($phpcsFile, $functionPtr);

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
