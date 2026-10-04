<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

final class AttributeCasts
{
    private const ATTRIBUTE_CAST = 'illuminate\database\eloquent\casts\attribute';

    private const IMPORT_KEYWORDS = ['function', 'const'];

    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
    ];

    private const MEMBER_ENDS = [T_COMMA, T_CLOSE_USE_GROUP, T_SEMICOLON];

    private ?string $importsKey = null;

    private array $imports = [];

    private array $importCounts = ['builds' => 0, 'hits' => 0];

    public function __construct(
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    public function isReturnedBy(File $phpcsFile, int $functionPtr): bool
    {
        $returnType = $phpcsFile->getMethodProperties($functionPtr)['return_type'];
        $imports = $this->importsOf($phpcsFile);
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

    public function importCounts(): array
    {
        return $this->importCounts;
    }

    private function importsOf(File $phpcsFile): array
    {
        $tokenStreams = $this->tokenStreams;
        $key = $tokenStreams->key($phpcsFile);
        $outcome = match ($this->importsKey) {
            $key => 'hits',
            default => 'builds',
        };

        $this->importCounts[$outcome]++;
        $this->imports = match ($outcome) {
            'hits' => $this->imports,
            default => $this->readImports($phpcsFile),
        };
        $this->importsKey = $key;

        return $this->imports;
    }

    private function resolve(string $name, array $imports): string
    {
        $segments = explode('\\', $name);
        $head = strtolower((string) array_shift($segments));
        $resolved = strtolower($name);

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

    private function readImports(File $phpcsFile): array
    {
        $imports = [];

        foreach ($this->pointersOfType($phpcsFile, T_USE) as $usePtr) {
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
        $segments = explode('\\', $fullyQualified);
        $bound = strtolower((string) end($segments));

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

    private function pointersOfType(File $phpcsFile, int $type): array
    {
        $pointers = [];
        $ptr = $this->orNull($phpcsFile->findNext($type, 0));

        while ($ptr !== null) {
            $pointers[] = $ptr;
            $ptr = $this->orNull($phpcsFile->findNext($type, ($ptr + 1)));
        }

        return $pointers;
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

    private function orNull(int|false $pointer): ?int
    {
        return match ($pointer) {
            false => null,
            default => $pointer,
        };
    }
}
