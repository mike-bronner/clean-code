<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ActionMethodReturnSniff implements Sniff
{
    public array $actionPrefixes = [
        'add', 'apply', 'attach', 'clear', 'delete', 'detach', 'post',
        'remove', 'reset', 'save', 'send', 'set', 'store', 'update',
    ];

    public bool $allowFluentInterface = true;

    private const CLASS_LIKE_TOKENS = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    private const CALLABLE_TOKENS = [
        T_CLOSURE,
        T_FN,
        T_FUNCTION,
    ];

    private const COMMAND_RETURN_TYPES = [
        'never',
        'void',
    ];

    private const FLUENT_RETURN_TYPES = [
        'self',
        'static',
    ];

    private const NO_POINTER = -1;

    private const MESSAGE = <<<MESSAGE
        The %s() method starts with the action verb "%s", so it commands rather than
        answers and should not return a value — return nothing, or rename it for what
        it hands back (see docs/standards/methods-naming.md)
        MESSAGE;

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $name = (string) $phpcsFile->getDeclarationName($stackPtr);
        $prefix = $this->matchedPrefix($name);

        match (true) {
            $this->isMethod($phpcsFile, $stackPtr) === false => null,
            $prefix === null => null,
            $this->returnsValue($phpcsFile, $stackPtr) === false => null,
            default => $phpcsFile->addWarning(
                $this->message(),
                $phpcsFile->findNext(T_STRING, $stackPtr),
                'Found',
                [$name, $prefix]
            ),
        };
    }

    private function isMethod(File $phpcsFile, int $stackPtr): bool
    {
        $classPtr = $this->innermostCondition($phpcsFile, $stackPtr, self::CLASS_LIKE_TOKENS);
        $callablePtr = $this->innermostCondition($phpcsFile, $stackPtr, self::CALLABLE_TOKENS);

        return $classPtr > $callablePtr;
    }

    private function matchedPrefix(string $name): ?string
    {
        $matched = null;

        foreach ($this->actionPrefixes as $prefix) {
            $matched ??= $this->prefixOrNull($name, (string) $prefix);
        }

        return $matched;
    }

    private function prefixOrNull(string $name, string $prefix): ?string
    {
        $rest = substr($name, strlen($prefix));

        return match (true) {
            $prefix === '' => null,
            str_starts_with($name, $prefix) === false => null,
            ctype_lower(substr($rest, 0, 1)) => null,
            default => $prefix,
        };
    }

    private function returnsValue(File $phpcsFile, int $stackPtr): bool
    {
        $declared = $this->declaredReturnType($phpcsFile, $stackPtr);

        return match ($declared) {
            '' => $this->bodyReturnsValue($phpcsFile, $stackPtr),
            default => $this->declarationReturnsValue($phpcsFile, $stackPtr, $declared),
        };
    }

    private function declarationReturnsValue(File $phpcsFile, int $stackPtr, string $declared): bool
    {
        return match (true) {
            in_array($declared, self::COMMAND_RETURN_TYPES, true) => false,
            $this->allowFluentInterface === false => true,
            $this->everyMemberIsFluent($phpcsFile, $stackPtr, $declared) => false,
            default => true,
        };
    }

    private function bodyReturnsValue(File $phpcsFile, int $stackPtr): bool
    {
        $returns = $this->valueReturns($phpcsFile, $stackPtr);
        $closer = $this->boundaryPointer($phpcsFile, $stackPtr, 'scope_closer');

        return match (true) {
            $returns === [] => false,
            $this->allowFluentInterface === false => true,
            $this->everyReturnIsThis($phpcsFile, $returns, $closer) => false,
            default => true,
        };
    }

    private function declaredReturnType(File $phpcsFile, int $stackPtr): string
    {
        $written = (string) $phpcsFile->getMethodProperties($stackPtr)['return_type'];
        $normalized = ltrim(strtolower(preg_replace('/\s+/', '', $written) ?? $written), '?');
        $members = array_values(array_diff(explode('|', $normalized), ['']));

        return implode('|', $this->withoutNullability($members));
    }

    private function withoutNullability(array $members): array
    {
        return match (count($members)) {
            1 => $members,
            default => array_values(array_diff($members, ['null'])),
        };
    }

    private function everyMemberIsFluent(File $phpcsFile, int $stackPtr, string $type): bool
    {
        $className = $this->enclosingClassName($phpcsFile, $stackPtr);
        $members = explode('|', $type);
        $fluent = 0;

        foreach ($members as $member) {
            $fluent += (int) $this->isFluentMember($member, $className);
        }

        return $fluent === count($members);
    }

    private function isFluentMember(string $member, ?string $className): bool
    {
        return match (true) {
            in_array($member, self::FLUENT_RETURN_TYPES, true) => true,
            $className === null => false,
            default => $member === strtolower($className),
        };
    }

    private function enclosingClassName(File $phpcsFile, int $stackPtr): ?string
    {
        $classPtr = $this->innermostCondition($phpcsFile, $stackPtr, self::CLASS_LIKE_TOKENS);

        return match ($classPtr) {
            self::NO_POINTER => null,
            default => $phpcsFile->getDeclarationName($classPtr),
        };
    }

    private function valueReturns(File $phpcsFile, int $stackPtr): array
    {
        $opener = $this->boundaryPointer($phpcsFile, $stackPtr, 'scope_opener');
        $closer = $this->boundaryPointer($phpcsFile, $stackPtr, 'scope_closer');
        $returns = [];
        $pointer = $phpcsFile->findNext(T_RETURN, ($opener + 1), $closer);

        while ($pointer !== false) {
            $returns = $this->withExpression(
                $returns,
                $this->ownReturnedExpression($phpcsFile, $stackPtr, $pointer, $closer)
            );
            $pointer = $phpcsFile->findNext(T_RETURN, ($pointer + 1), $closer);
        }

        return $returns;
    }

    private function ownReturnedExpression(
        File $phpcsFile,
        int $stackPtr,
        int $returnPtr,
        int $closer
    ): ?int {
        $expression = $this->orNull(
            $phpcsFile->findNext(Tokens::$emptyTokens, ($returnPtr + 1), $closer, true)
        );

        return match (true) {
            $this->owningCallable($phpcsFile, $returnPtr) !== $stackPtr => null,
            $this->isToken($phpcsFile, $expression, T_SEMICOLON) => null,
            default => $expression,
        };
    }

    private function withExpression(array $returns, ?int $expression): array
    {
        return match ($expression) {
            null => $returns,
            default => [...$returns, $expression],
        };
    }

    private function owningCallable(File $phpcsFile, int $stackPtr): int
    {
        return $this->innermostCondition($phpcsFile, $stackPtr, self::CALLABLE_TOKENS);
    }

    private function everyReturnIsThis(File $phpcsFile, array $returns, int $closer): bool
    {
        $fluent = 0;

        foreach ($returns as $expression) {
            $fluent += (int) $this->isBareThis($phpcsFile, $expression, $closer);
        }

        return $fluent === count($returns);
    }

    private function isBareThis(File $phpcsFile, int $expression, int $closer): bool
    {
        $end = $this->orNull($phpcsFile->findNext(T_SEMICOLON, $expression, $closer));

        return match ($end) {
            null => false,
            default => $this->isThisExpression($phpcsFile, $expression, $end),
        };
    }

    private function isThisExpression(File $phpcsFile, int $start, int $end): bool
    {
        $first = $this->orNull($phpcsFile->findNext(Tokens::$emptyTokens, $start, $end, true));
        $last = $this->orNull(
            $phpcsFile->findPrevious(Tokens::$emptyTokens, ($end - 1), $start, true)
        );

        return match (true) {
            $first === null => false,
            $this->isThisVariable($phpcsFile, $first) => $first === $last,
            default => $this->isGroupedThis($phpcsFile, $first, $last),
        };
    }

    private function isGroupedThis(File $phpcsFile, int $first, ?int $last): bool
    {
        $closer = $this->boundaryPointer($phpcsFile, $first, 'parenthesis_closer');

        return match ($closer) {
            $last => $this->isThisExpression($phpcsFile, ($first + 1), $closer),
            default => false,
        };
    }

    private function isThisVariable(File $phpcsFile, int $stackPtr): bool
    {
        return $this->contentOf($phpcsFile, $stackPtr) === '$this';
    }

    private function innermostCondition(File $phpcsFile, int $stackPtr, array $types): int
    {
        $innermost = self::NO_POINTER;

        foreach ($types as $type) {
            $innermost = max($innermost, $this->conditionPointer($phpcsFile, $stackPtr, $type));
        }

        return $innermost;
    }

    private function conditionPointer(File $phpcsFile, int $stackPtr, int|string $type): int
    {
        // Hoisted out of the match subject rather than written inline: rules.xml
        // reports an assignment in a condition (#79), and a match subject is one
        // of the conditions it reads.
        $pointer = $phpcsFile->getCondition($stackPtr, $type, false);

        return match ($pointer) {
            false => self::NO_POINTER,
            default => $pointer,
        };
    }

    private function boundaryPointer(File $phpcsFile, int $stackPtr, string $boundary): int
    {
        return $phpcsFile->getTokens()[$stackPtr][$boundary] ?? self::NO_POINTER;
    }

    private function isToken(File $phpcsFile, ?int $stackPtr, array|int|string $types): bool
    {
        return match ($stackPtr) {
            null => false,
            default => $phpcsFile->findNext($types, $stackPtr, ($stackPtr + 1)) !== false,
        };
    }

    private function orNull(int|false $pointer): ?int
    {
        return match ($pointer) {
            false => null,
            default => $pointer,
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
}
