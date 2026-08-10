<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags the creation of a mock for a first-party class inside test files.
 *
 * Testing: Guidelines (#54) — docs/standards/testing-guidelines.md — says to
 * mock external interfaces you do not control, and not to mock classes you do.
 * "Control" is a semantic judgement no token scan can make, but the common
 * violation has a token-visible shape: handing a class reference that resolves
 * into the project's own namespace to a mock-creation call (#146).
 *
 * The sniff is a **no-op until configured**. $firstPartyNamespaces ships empty
 * because nothing in a single file says which namespace roots a project owns;
 * the master ruleset configures `App` for the Laravel layout this standard is
 * written against, and a consuming ruleset replaces that with its own roots.
 *
 * Detection has three parts, each driven by its own public property:
 *
 * - $mockCreators — the called member names that create a mock: PHPUnit's
 *   `createMock()`, `createPartialMock()` and `getMockBuilder()`, Mockery's
 *   `mock()` and `spy()`, and Laravel's `mock()`, `partialMock()` and `spy()`
 *   test helpers. Matched on `->`, `?->` and `::` alike, so `$this->mock()`
 *   and `Mockery::mock()` both report.
 * - $firstPartyNamespaces — the namespace roots the project owns. A call is
 *   reported when its first argument resolves to a name equal to one of these
 *   or sitting beneath it, compared segment-wise and case-insensitively so
 *   `App` never matches `Application\Order`.
 * - $testFilePatterns — the paths the rule applies to at all, exactly as in
 *   CleanCode.Testing.NoReflectionAccess. Production code is never inspected.
 *
 * The first argument is resolved to a fully-qualified name from the file's own
 * `namespace` declaration and `use` imports:
 *
 * - `User::class` — resolved through the imports, then through the current
 *   namespace.
 * - `\App\Models\User::class` — already fully qualified.
 * - `namespace\User::class` — relative to the current namespace.
 * - `'App\Models\User'` — a string literal, which PHP never resolves through
 *   imports, so it is read as fully qualified whatever the file imports.
 * - `User` written bare, with no `::class` — the same resolution as the first
 *   case. It is not valid PHP for a mock argument, but it costs nothing to
 *   resolve and the AC names the shape.
 * - `self::class`, `static::class` and `parent::class` — the same `::class`
 *   constant naming the enclosing scope instead of writing a name out.
 *   `$this->createPartialMock(static::class, [...])` is the idiomatic way to
 *   partial-mock the class a test file is about, so it has to resolve or the
 *   commonest first-party partial mock of all goes unseen. `self` and `static`
 *   both resolve to the class the call sits in, and `parent` to the name in
 *   that class's `extends` clause, resolved like any other written name.
 *
 * Anything else is left alone: a variable (`Mockery::mock($class)`), a
 * concatenation, a constant (`$this->mock(Config::DRIVER)`), a call, or an
 * empty argument list. A name the file's own tokens cannot resolve is not
 * guessed at.
 *
 * Known limits, three by design and all named in #146:
 *
 * - $mockCreators matches on member *name*, not on receiver type, which a
 *   single-file token scan cannot resolve. `$this->spy(User::class)` and
 *   `$surveillance->spy(User::class)` are indistinguishable here, and a
 *   project hitting that often narrows the property instead.
 * - A facade or contract that *wraps* a genuinely external service lives in
 *   the project's own namespace and so reports, even though the thing being
 *   mocked is external. That gray area is why the rule warns rather than
 *   errors, and it takes the standard per-line suppression.
 * - `self`, `static` and `parent` only resolve inside a *named class*. In a
 *   trait or an anonymous class they name a class the file never writes down,
 *   and `parent` in a class with no `extends` names nothing at all, so each of
 *   those stays silent rather than being guessed at. `static` resolves to the
 *   class the call is written in, which is what the file can see; a subclass
 *   binding it to something else at run time is beyond a single-file scan.
 *
 * Warnings, not errors, matching CleanCode.Testing.NoReflectionAccess and the
 * rest of Testing: Guidelines: the standard is advisory and the two limits
 * above have known false positives, so a violation must not fail a consumer's
 * build. Detection only — replacing a mock of a class you own with the real
 * collaborator is a redesign of the test, with no mechanical rewrite.
 *
 * Fixtured in tests/fixtures/NoFirstPartyMocksSniff/ and covered by
 * tests/Standards/NoFirstPartyMocksTest.php.
 */
class NoFirstPartyMocksSniff implements Sniff
{
    /**
     * Every token a class name can be made of, in either tokenisation: the
     * pre-8.0 spelling PHP_CodeSniffer backfills to (T_STRING joined by
     * T_NS_SEPARATOR, with T_NAMESPACE leading a relative name) and PHP 8's
     * single qualified-name tokens, in case a future PHPCS stops backfilling.
     * PHP's floor here is 8.1, so all three T_NAME_* constants are defined.
     */
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAMESPACE,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    /**
     * The keywords that name a class through the scope the call sits in rather
     * than by writing the name out. PHP_CodeSniffer gives each its own token,
     * none of them in NAME_TOKENS, so the token-run walk cannot read them.
     */
    private const SCOPE_KEYWORDS = [
        T_SELF,
        T_STATIC,
        T_PARENT,
    ];

    /**
     * Every scope a class-like declaration opens. `self` and friends resolve
     * against the innermost of these, and only a named class among them gives
     * a name the file actually writes down.
     *
     * @var array<int, int|string>
     */
    private const CLASS_LIKE_SCOPES = [
        T_CLASS,
        T_ANON_CLASS,
        T_TRAIT,
        T_INTERFACE,
        T_ENUM,
    ];

    /**
     * Tokens the header walk steps over one at a time, carrying no name of
     * their own: the opening tag, and the empty statement a stray `;` makes.
     */
    private const HEADER_FILLER = [
        T_OPEN_TAG,
        T_SEMICOLON,
    ];

    /**
     * Statement keywords that may precede the `use` imports without ending the
     * file's header. Anything else stops the header walk.
     */
    private const HEADER_STATEMENTS = [
        T_DECLARE,
        T_NAMESPACE,
        T_USE,
    ];

    /**
     * Path globs (fnmatch syntax) that mark a file as a test file. The sniff
     * inspects nothing outside them. Configurable from a ruleset via
     * <property name="testFilePatterns" type="array" .../>.
     *
     * @var array<string>
     */
    public array $testFilePatterns = [
        '*/tests/*',
        '*/Tests/*',
        '*Test.php',
    ];

    /**
     * The namespace roots the project owns. Ships empty on purpose: a file's
     * tokens never say which roots are first-party, so an unconfigured sniff
     * cannot tell a project's own class from a vendor one and stays silent
     * rather than guessing. Configurable via
     * <property name="firstPartyNamespaces" type="array" .../>.
     *
     * @var array<string>
     */
    public array $firstPartyNamespaces = [];

    /**
     * Member names whose call creates a mock. Configurable via
     * <property name="mockCreators" type="array" .../>.
     *
     * @var array<string>
     */
    public array $mockCreators = [
        'createMock',
        'createPartialMock',
        'getMockBuilder',
        'mock',
        'partialMock',
        'spy',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->firstPartyNamespaces === []) {
            return;
        }

        if ($this->isTestFile($phpcsFile->getFilename()) === false) {
            return;
        }

        $argumentPtr = $this->mockArgumentPointer($phpcsFile, $stackPtr);

        if ($argumentPtr === null) {
            return;
        }

        $written = $this->classReference($phpcsFile, $argumentPtr);

        if ($written === null) {
            return;
        }

        $resolved = $this->resolve($phpcsFile, $written);

        if ($this->isFirstParty($resolved) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'Mocking %s, a first-party class; mock only interfaces you do not control'
                . ' (see docs/standards/testing-guidelines.md)',
            $argumentPtr,
            'Found',
            [$resolved]
        );
    }

    /**
     * Whether the file's path matches one of the configured test-file globs.
     *
     * Windows separators are normalised to forward slashes first, so one glob
     * spelling matches on either platform. The match itself is case-sensitive,
     * for the reason CleanCode.Testing.NoReflectionAccess records: folding case
     * would make `*Test.php` swallow `latest.php`, so the two directory
     * spellings actually in use are listed out in $testFilePatterns instead.
     */
    private function isTestFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->testFilePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The pointer to the first argument of the mock-creation call reached
     * through the operator at $stackPtr, or null when that operator does not
     * open one.
     *
     * Only a real call qualifies: a property read of a configured name
     * (`$builder->mock`) creates nothing, and a dynamic member name
     * (`$this->{$creator}()`) is unknowable at token level, so both fall out
     * on the T_STRING check.
     */
    private function mockArgumentPointer(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($memberPtr === false || $tokens[$memberPtr]['code'] !== T_STRING) {
            return null;
        }

        if ($this->matches($tokens[$memberPtr]['content'], $this->mockCreators) === false) {
            return null;
        }

        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if ($openPtr === false || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS) {
            return null;
        }

        // An empty argument list needs no guard of its own: the token found
        // here is then the closing parenthesis, which is neither a string
        // literal nor the start of a name, so classReference() rejects it.
        $argumentPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        return $argumentPtr === false ? null : $argumentPtr;
    }

    /**
     * The class reference written as the first argument at $argumentPtr, as
     * source text, or null when the argument is not one the file's own tokens
     * can resolve.
     *
     * The name is read as a *run* of tokens rather than one token, because
     * PHP_CodeSniffer 3.x backfills PHP 8's single qualified-name tokens into
     * the pre-8.0 spelling — `\App\Models\User` arrives as T_NS_SEPARATOR and
     * T_STRING alternating, and `namespace\User` as T_NAMESPACE followed by
     * the same. Reading one token there yields `\` or `namespace`.
     *
     * Whatever the argument is, it has to *be* the whole argument: the token
     * after it must close the call or separate the next argument. Without that
     * check `$this->mock(User::class . $suffix)` and `$this->mock(FIRST_PARTY)`
     * — a concatenation and a constant, neither of them a class reference —
     * would read as one.
     */
    private function classReference(File $phpcsFile, int $argumentPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$argumentPtr]['code'] === T_CONSTANT_ENCAPSED_STRING) {
            // PHP never resolves a class name given as a string through the
            // file's imports or its namespace, so the literal is already
            // fully qualified whatever it is written as. Handing it on with a
            // leading separator is what says so to resolve().
            $written = '\\' . ltrim($this->literalValue($tokens[$argumentPtr]['content']), '\\');

            return $this->endsTheArgument($phpcsFile, ($argumentPtr + 1)) === true ? $written : null;
        }

        if (in_array($tokens[$argumentPtr]['code'], self::SCOPE_KEYWORDS, true) === true) {
            return $this->scopeReference($phpcsFile, $argumentPtr);
        }

        [$written, $pointer] = $this->nameRun($tokens, $argumentPtr);

        if ($written === '') {
            return null;
        }

        $pointer = $this->skipClassConstant($phpcsFile, $pointer);

        if ($pointer === null) {
            return null;
        }

        return $this->endsTheArgument($phpcsFile, $pointer) === true ? $written : null;
    }

    /**
     * The run of name tokens starting at $pointer as source text, paired with
     * the pointer just past it. Empty text when the first token carries no name.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array{0: string, 1: int}
     */
    private function nameRun(array $tokens, int $pointer): array
    {
        $written = '';

        for (; isset($tokens[$pointer]) === true; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return [$written, $pointer];
    }

    /**
     * The class the scope keyword at $argumentPtr names, already fully
     * qualified, or null when the file's own tokens do not say which class
     * that is.
     *
     * A leading separator is what tells resolve() the name is qualified
     * already, the same handoff the string-literal branch makes: the name comes
     * from a declaration in this file, not from a written reference, so it must
     * not be sent back through the imports.
     *
     * The keyword has to carry a `::class` suffix to name a class at all.
     * Without one it is some other use of the same word — `self::DRIVER` names
     * a constant, and `static fn () => null` a closure — so skipClassConstant()
     * leaving the pointer where it started is a rejection here, not a bare name
     * as it is on the written-name path.
     */
    private function scopeReference(File $phpcsFile, int $argumentPtr): ?string
    {
        $afterKeyword = ($argumentPtr + 1);
        $pointer = $this->skipClassConstant($phpcsFile, $afterKeyword);

        if ($pointer === null || $pointer === $afterKeyword) {
            return null;
        }

        if ($this->endsTheArgument($phpcsFile, $pointer) === false) {
            return null;
        }

        $name = $this->scopeClassName($phpcsFile, $argumentPtr);

        return $name === null ? null : '\\' . $name;
    }

    /**
     * The fully-qualified name the scope keyword at $keywordPtr resolves to.
     *
     * `self` and `static` both give the class the keyword is written in. They
     * differ only at run time, where `static` binds to the subclass actually
     * called — which no single-file scan can see, so the class in front of it
     * is what both resolve to. `parent` gives the `extends` clause instead,
     * which *is* a written reference and so resolves through the imports.
     */
    private function scopeClassName(File $phpcsFile, int $keywordPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $this->enclosingClass($tokens, $keywordPtr);

        if ($classPtr === null) {
            return null;
        }

        if ($tokens[$keywordPtr]['code'] === T_PARENT) {
            return $this->parentName($phpcsFile, $classPtr);
        }

        $declared = $phpcsFile->getDeclarationName($classPtr);

        if ($declared === null) {
            return null;
        }

        [$namespace] = $this->fileScope($phpcsFile);

        return $this->join($namespace, $declared);
    }

    /**
     * The pointer to the named class the token at $pointer sits directly
     * inside, or null when the innermost class-like scope around it is not one.
     *
     * Innermost is the whole point: `self` inside an anonymous class declared
     * in a method names that anonymous class, not the method's own class, so an
     * enclosing named class further out must not be reached for. A trait and an
     * anonymous class have no name the file writes down, an interface and an
     * enum cannot be mocked into existence, and outside every class-like scope
     * the keyword names nothing — all four end here rather than being guessed.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function enclosingClass(array $tokens, int $pointer): ?int
    {
        $conditions = array_reverse($tokens[$pointer]['conditions'], true);

        foreach ($conditions as $conditionPtr => $code) {
            if (in_array($code, self::CLASS_LIKE_SCOPES, true) === false) {
                continue;
            }

            return $code === T_CLASS ? $conditionPtr : null;
        }

        return null;
    }

    /**
     * The fully-qualified name in the `extends` clause of the class at
     * $classPtr, or null when it has none.
     *
     * Both searches are bounded by the class's own opening brace, so a class
     * without an `extends` clause cannot reach into the next declaration's and
     * borrow its parent. A class whose brace never arrives has no bound to
     * search within and so returns null rather than scanning to the end of the
     * file — unreachable from here, since a keyword can only sit inside a body
     * the opener starts, but it is what makes the bound safe to read.
     */
    private function parentName(File $phpcsFile, int $classPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$classPtr]['scope_opener']) === false) {
            return null;
        }

        $openerPtr = $tokens[$classPtr]['scope_opener'];
        $extendsPtr = $phpcsFile->findNext(T_EXTENDS, ($classPtr + 1), $openerPtr);

        if ($extendsPtr === false) {
            return null;
        }

        // Searching for the name itself rather than skipping the whitespace in
        // front of it also settles an `extends` with no name after it at all.
        $namePtr = $phpcsFile->findNext(self::NAME_TOKENS, ($extendsPtr + 1), $openerPtr);

        if ($namePtr === false) {
            return null;
        }

        [$written] = $this->nameRun($tokens, $namePtr);

        return $this->resolve($phpcsFile, $written);
    }

    /**
     * $pointer advanced past a `::class` suffix, unchanged when there is no
     * `::` there at all, or null when the `::` reaches something other than
     * `class` — `Config::DRIVER` names a constant, not the class it hangs off.
     *
     * PHP_CodeSniffer tokenises the `class` in `User::class` as T_STRING, not
     * T_CLASS, so the content is what identifies it. PHP's own lexer accepts
     * any casing there.
     */
    private function skipClassConstant(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $doubleColonPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if ($doubleColonPtr === false || $tokens[$doubleColonPtr]['code'] !== T_DOUBLE_COLON) {
            return $pointer;
        }

        $constantPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($doubleColonPtr + 1), null, true);

        if ($constantPtr === false) {
            return null;
        }

        if (strtolower($tokens[$constantPtr]['content']) !== 'class') {
            return null;
        }

        return ($constantPtr + 1);
    }

    /**
     * Whether the next meaningful token from $pointer ends the first argument,
     * either by closing the call or by separating the argument that follows.
     */
    private function endsTheArgument(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundaryPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if ($boundaryPtr === false) {
            return false;
        }

        return in_array($tokens[$boundaryPtr]['code'], [T_COMMA, T_CLOSE_PARENTHESIS], true);
    }

    /**
     * The class name a string literal spells. Both quote styles escape the
     * separator the same way — PHP's own lexer collapses `\\` to one backslash
     * in a single-quoted literal just as it does in a double-quoted one — so
     * `'App\\Models\\User'` and `"App\\Models\\User"` both name
     * `App\Models\User`. A single separator is already what it says and comes
     * through untouched.
     */
    private function literalValue(string $content): string
    {
        return str_replace('\\\\', '\\', trim($content, '\'"'));
    }

    /**
     * The fully-qualified name $written refers to, read through the file's own
     * `namespace` declaration and `use` imports.
     *
     * A leading separator says the name is already qualified. `namespace\X` is
     * relative to the declared namespace. Otherwise the first segment is an
     * import alias when the file imports one by that name, and a name in the
     * current namespace when it does not — which is PHP's own resolution
     * order for a class name in a namespaced file.
     */
    private function resolve(File $phpcsFile, string $written): string
    {
        if (str_starts_with($written, '\\') === true) {
            return ltrim($written, '\\');
        }

        [$namespace, $imports] = $this->fileScope($phpcsFile);
        $segments = explode('\\', $written);
        $first = strtolower($segments[0]);

        if ($first === 'namespace') {
            array_shift($segments);

            return $this->join($namespace, implode('\\', $segments));
        }

        if (array_key_exists($first, $imports) === true) {
            $segments[0] = $imports[$first];

            return implode('\\', $segments);
        }

        return $this->join($namespace, $written);
    }

    /**
     * The file's declared namespace and its import aliases, keyed by the
     * lowercased alias because PHP resolves them case-insensitively.
     *
     * The walk stops at the first token that cannot belong to a file header,
     * so it reads a few dozen tokens rather than the whole file however many
     * mock calls follow — `use` imports may only appear before the first
     * statement, and a trait's `use` or a closure's `use` is past that point
     * by construction.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function fileScope(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $namespace = '';
        $imports = [];
        $pointer = 0;

        while (isset($tokens[$pointer]) === true) {
            $code = $tokens[$pointer]['code'];
            $isFiller = in_array($code, Tokens::$emptyTokens, true)
                || in_array($code, self::HEADER_FILLER, true);

            if ($isFiller === true) {
                $pointer++;

                continue;
            }

            if (in_array($code, self::HEADER_STATEMENTS, true) === false) {
                break;
            }

            $end = $this->statementEnd($phpcsFile, $pointer);

            if ($code === T_NAMESPACE) {
                $declared = $this->declaredNamespace($tokens, $pointer, $end);

                if ($declared === null) {
                    break;
                }

                $namespace = $declared;
            }

            if ($code === T_USE) {
                $imports = array_merge($imports, $this->importedAliases($tokens, $pointer, $end));
            }

            $pointer = ($end ?? count($tokens));
        }

        return [$namespace, $imports];
    }

    /**
     * The pointer just past the statement starting at $pointer, or null when
     * the file ends first. A `namespace X { }` block and a `use` group both
     * end at the first semicolon or open brace after the keyword, and neither
     * a namespace name nor an import list can contain either.
     */
    private function statementEnd(File $phpcsFile, int $pointer): ?int
    {
        $terminator = $phpcsFile->findNext(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET],
            ($pointer + 1)
        );

        return $terminator === false ? null : ($terminator + 1);
    }

    /**
     * The namespace a `namespace` statement declares, or null when the keyword
     * is not a declaration at all — `namespace\User` in an expression starts
     * with a separator, and the header walk has to stop there rather than read
     * a namespace out of it.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function declaredNamespace(array $tokens, int $pointer, ?int $end): ?string
    {
        $written = $this->statementText($tokens, $pointer, $end);

        return str_starts_with($written, '\\') === true ? null : trim($written, '\\');
    }

    /**
     * The alias => fully-qualified name pairs a `use` statement imports, keyed
     * by lowercased alias. Empty for a `use function` or `use const` import,
     * which brings no class name into scope.
     *
     * Both spellings are handled from the statement's own text: the plain list
     * (`use A\B, C\D as E;`) and the group (`use A\{B, C as D};`), the latter
     * prefixing each clause with the text before the brace. The function and
     * const keywords are rejected per *clause* rather than per statement,
     * because a group import may mix them in beside classes — and a whole-file
     * `use function` reaches the same check as its own single clause.
     *
     * An empty clause is dropped before the group prefix is applied, not after:
     * a trailing comma inside a group (`use A\{B, C,};`, legal since PHP 8.0)
     * leaves one, and prefixing it first would turn it into the non-empty
     * `A\` and import a phantom `a => A` alias that no `use` statement wrote.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array<string, string>
     */
    private function importedAliases(array $tokens, int $pointer, ?int $end): array
    {
        $written = $this->statementText($tokens, $pointer, $end);
        $prefix = '';

        if (str_contains($written, '{') === true) {
            [$prefix, $written] = explode('{', $written, 2);
            $written = rtrim(explode('}', $written, 2)[0]);
        }

        $prefix = trim($prefix);
        $imports = [];

        foreach (explode(',', $written) as $clause) {
            $clause = trim($clause);

            if ($clause === '') {
                continue;
            }

            $import = $this->importedAlias($prefix . $clause);

            $imports = ($import === null ? $imports : array_merge($imports, $import));
        }

        return $imports;
    }

    /**
     * One import clause as an alias => fully-qualified name pair, or null when
     * the clause names a function or a constant, which a group import may mix
     * in beside classes. The clause is never empty: the caller drops an empty
     * one before it prefixes the group.
     *
     * @return array<string, string>|null
     */
    private function importedAlias(string $clause): ?array
    {
        if (preg_match('/^(function|const)\s/i', $clause) === 1) {
            return null;
        }

        $parts = preg_split('/\s+as\s+/i', $clause, 2);
        $qualified = trim((string) $parts[0], '\\');
        $segments = explode('\\', $qualified);
        $alias = $parts[1] ?? end($segments);

        return [strtolower(trim((string) $alias)) => $qualified];
    }

    /**
     * The source text of the statement starting at $pointer, keyword dropped
     * and whitespace and comments collapsed to one space, so the `as` in
     * `use A\B as C` stays separated from the names on either side of it.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function statementText(array $tokens, int $pointer, ?int $end): string
    {
        $written = '';
        $last = ($end ?? count($tokens)) - 1;

        for ($current = ($pointer + 1); $current < $last; $current++) {
            $isEmpty = in_array($tokens[$current]['code'], Tokens::$emptyTokens, true);
            $written .= ($isEmpty === true ? ' ' : $tokens[$current]['content']);
        }

        return trim((string) preg_replace('/\s+/', ' ', $written));
    }

    /**
     * $tail placed inside $namespace, or on its own when the file declares no
     * namespace.
     */
    private function join(string $namespace, string $tail): string
    {
        return $namespace === '' ? $tail : ($namespace . '\\' . $tail);
    }

    /**
     * Whether $resolved is the project's own. The comparison is segment-wise:
     * a configured `App` covers `App` itself and everything under `App\`, and
     * never `Application\Order`, which a plain string prefix would swallow.
     * Case is folded because PHP resolves namespaces case-insensitively, and a
     * configured root is trimmed of separators so `App\`, `\App` and `App`
     * all mean the same root.
     */
    private function isFirstParty(string $resolved): bool
    {
        $lowered = strtolower($resolved);

        foreach ($this->firstPartyNamespaces as $prefix) {
            $root = strtolower(trim($prefix, '\\'));

            if ($root === '') {
                continue;
            }

            if ($lowered === $root || str_starts_with($lowered, $root . '\\') === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Case-insensitive membership, because PHP method names are themselves
     * case-insensitive.
     *
     * @param array<string> $candidates
     */
    private function matches(string $name, array $candidates): bool
    {
        return in_array(strtolower($name), array_map('strtolower', $candidates), true);
    }
}
