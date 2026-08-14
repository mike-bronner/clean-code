<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\DeadCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a declared parameter that the body it belongs to never reads.
 *
 * Replicates PHPMD's UnusedCode UnusedFormalParameter rule
 * (docs/phpmd/unusedcode-unusedformalparameter.md, #120). A parameter nothing
 * reads is dead weight in the signature: every caller still has to supply it,
 * and every reader still has to work out what it is for.
 *
 * ## Why this is a custom sniff and not a wiring
 *
 * Both candidate wirings were built, measured against a live PHPMD 2.15.0, and
 * rejected — each stays silent on shapes PHPMD reports, which is the one
 * direction that would put `phpmd` back in the pipeline:
 *
 * - `Generic.CodeAnalysis.UnusedFunctionParameter` decides the inherited-
 *   signature exemption *per class*, by switching its error code whenever the
 *   enclosing class extends or implements anything. Excluding those codes to
 *   buy the exemption also silences the class's own non-inherited methods. It
 *   additionally exempts an empty or comment-only body and `__unserialize()`,
 *   both of which PHPMD reports.
 * - `SlevomatCodingStandard.Functions.UnusedParameter` has no inherited-
 *   signature exemption at all, so it reports every override — the false
 *   positive #120's acceptance criteria explicitly forbid.
 *
 * ## How the inherited-signature exemption is decided here
 *
 * PHPMD exempts a parameter that only exists to satisfy an inherited
 * signature, and resolves that through PDepend's *whole-project* type map:
 * `PHPMD\Node\MethodNode::isDeclaration()` asks PDepend for the parent class
 * and each interface, then looks the method name up in `getAllMethods()`. A
 * PHPCS sniff sees one file at a time and has no such map, so it cannot
 * reproduce that resolution in general. Measured on PHPMD 2.15.0, the
 * exemption is genuinely cross-file: a child analyzed *without* its parent is
 * reported by PHPMD too.
 *
 * Three signals stand in, and between them they cover every override an author
 * can actually declare:
 *
 * - **A same-file resolvable override.** When the parent class or interface is
 *   declared in this same file, the lookup PHPMD does is available here, so it
 *   is done — transitively, and including methods a resolved parent draws from
 *   a trait, because PDepend's `getAllMethods()` includes those too.
 * - **`@inheritdoc`.** PHPMD honours it on its own
 *   (`Rule/UnusedFormalParameter.php::isInheritedSignature()`), in the bare,
 *   mixed-case, and `{@inheritdoc}` spellings. Confirmed against a live run.
 * - **`#[\Override]`.** PHPMD does *not* honour this — it reports a parameter
 *   under an `#[\Override]` whose parent it cannot see. Honouring it loses no
 *   coverage on code that runs, because PHP 8.3 itself rejects the attribute at
 *   compile time unless the method genuinely overrides something; the attribute
 *   is a compiler-checked proof of the very fact PHPMD needs a type map for.
 *
 * What is left over is the deliberate cost #120 accepted: an override of a
 * parent this file cannot see, carrying neither annotation, is reported here
 * and not by PHPMD. Annotating it is the fix, and the annotation is worth
 * having on its own.
 *
 * ## Everything else was matched to the tool, not to its prose
 *
 * Each of these was measured, not assumed:
 *
 * - A **bodyless** declaration — an interface method, an `abstract` method —
 *   cannot use anything, and PHPMD stays silent on it.
 * - A **promoted constructor property** becomes class state, so a body that
 *   never mentions it is not a defect. PHPMD stays silent.
 * - `func_get_args()` anywhere in the body, including inside a nested closure,
 *   exempts *every* parameter, because the body can reach them all through it.
 *   `func_num_args()` does not — PHPMD reports through it, and so does this.
 * - `compact('name')` exempts only the parameter it names, not its siblings.
 * - Both calls have to *be* calls. One spelled out inside a comment, a string,
 *   a heredoc, a nowdoc, a shell string or inline HTML is printed rather than
 *   run, and exempts nothing — PHPMD matches a call node rather than a
 *   substring, and reads them the same way.
 * - The magic methods whose signature PHP fixes are skipped. `__invoke`,
 *   `__construct` and `__unserialize` are *not* on that list in either tool —
 *   their signatures are the author's own.
 *
 * ## Where this is deliberately stricter than PHPMD
 *
 * PHPMD's rule is FunctionAware and MethodAware only, so PDepend never hands it
 * a closure, an arrow function, or a method of an anonymous class. All three
 * are reported here: each is the same defect in a construct PHPMD cannot see,
 * and suppressing a true defect to match a gap is not parity worth having. The
 * same call is already made by CleanCode.Functions.ExcessiveParameterList.
 *
 * ## The one shape PHPMD reports and this does not
 *
 * An unqualified `func_get_args()` inside a namespace. PHPMD matches the call
 * by its resolved name, so it takes that one for a namespaced function and
 * reports through it, while `\func_get_args()` exempts. The parameters really
 * are read, so reporting them would be a false positive on correct code — the
 * divergence is recorded in the rule's doc rather than copied.
 *
 * Detection only, matching PHPMD. Deleting a parameter changes the signature
 * and breaks every caller, so there is nothing safe for `phpcbf` to write.
 */
class UnusedFormalParameterSniff implements Sniff
{
    /**
     * Methods whose signature PHP fixes, so an unused parameter is the
     * language's requirement rather than the author's dead weight. Read off
     * PHPMD's own behaviour on a live 2.15.0 run, not off its prose:
     * `__invoke`, `__construct` and `__unserialize` are absent because PHPMD
     * reports them.
     */
    private const FIXED_SIGNATURE_METHODS = [
        '__call',
        '__callstatic',
        '__get',
        '__isset',
        '__set',
        '__set_state',
        '__unset',
    ];

    /**
     * Class-like scopes a method can be declared in. T_ANON_CLASS is present
     * so that a method of an anonymous class resolves its own overrides;
     * PHPMD never sees one at all, which is a documented divergence.
     */
    private const CLASS_LIKE = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    /**
     * Every token that carries text rather than code. Nothing written inside
     * one runs: a parameter name in one is a mention and not a read, and a
     * `func_get_args()` or `compact('name')` spelled out in one is prose and
     * not a call. The same restriction to code tokens is what
     * CleanCode.Classes.UnusedPrivateElements applies when it collects the
     * names a file uses.
     *
     * The two texts that keep part of this set are named below, and each keeps
     * it for one reason only.
     */
    private const TEXT_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
        T_ENCAPSED_AND_WHITESPACE,
        T_HEREDOC,
        T_INLINE_HTML,
        T_NOWDOC,
    ];

    /**
     * The text tokens the read text keeps, because both interpolate and
     * `"$name"` inside either is a real read.
     *
     * A shell string interpolates too and is absent all the same: PHPCS
     * tokenizes the variables inside one apart from its
     * T_ENCAPSED_AND_WHITESPACE text — as T_VARIABLE, or as
     * T_DOLLAR_OPEN_CURLY_BRACES and T_STRING_VARNAME for `${name}` — so every
     * read in a shell string survives without its text being kept.
     */
    private const INTERPOLATING_TEXT = [
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    /**
     * The text token the compact text keeps, because a `compact()` argument is
     * written in one.
     *
     * A double-quoted argument is tokenized as one of these too: PHP produces
     * T_DOUBLE_QUOTED_STRING only for a string that interpolates, and
     * `compact("$name")` is the dynamic read neither tool resolves.
     */
    private const NAME_TEXT = [
        T_CONSTANT_ENCAPSED_STRING,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLOSURE, T_FN, T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if (isset($phpcsFile->getTokens()[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $code = $this->bodyText($phpcsFile, $stackPtr, self::codeExclusions());

        if ($this->isExempt($phpcsFile, $stackPtr, $code) === true) {
            return;
        }

        $reads = $this->bodyText($phpcsFile, $stackPtr, self::readExclusions());
        $names = $this->bodyText($phpcsFile, $stackPtr, self::nameExclusions());

        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            $this->checkParameter($phpcsFile, $stackPtr, $parameter, $reads, $names);
        }
    }

    /**
     * Whether the whole declaration is exempt, before any parameter is read.
     *
     * Ordered cheapest-first: the magic-method test is a string compare, the
     * annotation test scans a handful of tokens, and only then is the same-file
     * override resolution attempted. The bodyless test runs before this, in
     * process(), because a declaration with no body has no text to collect.
     */
    private function isExempt(File $phpcsFile, int $stackPtr, string $code): bool
    {
        if ($this->hasFixedSignature($phpcsFile, $stackPtr) === true) {
            return true;
        }

        if ($this->hasInheritanceAnnotation($phpcsFile, $stackPtr) === true) {
            return true;
        }

        if ($this->overridesSameFileMethod($phpcsFile, $stackPtr) === true) {
            return true;
        }

        return $this->callsFuncGetArgs($code);
    }

    /**
     * Reports one parameter unless the body reads it.
     *
     * A promoted constructor property is skipped before the read test runs:
     * it is class state whatever the constructor body does with it, and both
     * PHPMD and every candidate wiring stay silent on one.
     */
    private function checkParameter(
        File $phpcsFile,
        int $stackPtr,
        array $parameter,
        string $reads,
        string $names
    ): void {
        if ($this->isPromotedProperty($parameter) === true) {
            return;
        }

        $name = ltrim($parameter['name'], '$');

        if ($this->bodyReads($reads, $names, $name) === true) {
            return;
        }

        $phpcsFile->addError(
            'The %s never reads its parameter %s; remove it from the signature, or mark the '
                . 'method as an override with #[\\Override] or @inheritdoc if the signature is '
                . 'imposed from outside (see docs/phpmd/unusedcode-unusedformalparameter.md)',
            $parameter['token'],
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $parameter['name']]
        );
    }

    /**
     * Whether the parameter is promoted into a constructor property.
     *
     * Both keys are read because they are set independently: a `readonly`
     * promotion carries `property_readonly` and a visibility modifier carries
     * `property_visibility`, and PHPCS does not derive either from the other.
     */
    private function isPromotedProperty(array $parameter): bool
    {
        if (isset($parameter['property_visibility']) === true) {
            return true;
        }

        return ($parameter['property_readonly'] ?? false) === true;
    }

    /**
     * The body's source text, from the scope opener to the scope closer, with
     * the listed token codes left out.
     *
     * Taken as text rather than walked as tokens because every read this rule
     * recognises — a plain `$name`, an interpolated `"$name"` or `"{$name}"`,
     * a `${name}` in a heredoc — is the same substring, and a token walk would
     * have to re-join the per-physical-line pieces a multi-line string is
     * tokenized into. The tokens are still the source, in two ways: the range
     * is bounded by PHPCS's own scope pointers, so a nested closure's body is
     * included (a read there is a real read, and PHPMD counts it too) and
     * nothing outside the declaration is; and each token's *type* decides
     * whether its text is code at all, which is what keeps a comment or a
     * single-quoted string from passing as a read.
     *
     * @param array<int, int|string> $excluded
     */
    private function bodyText(File $phpcsFile, int $stackPtr, array $excluded): string
    {
        $tokens = $phpcsFile->getTokens();

        return $this->textBetween(
            $phpcsFile,
            $tokens[$stackPtr]['scope_opener'] + 1,
            $tokens[$stackPtr]['scope_closer'] - 1,
            $excluded
        );
    }

    /**
     * Every comment token code. No text this sniff searches keeps one: a
     * commented-out call is not a call, and a name written in a comment is not
     * read. PHPCS's own union is taken whole so that a `phpcs:` annotation,
     * which is tokenized apart from the comment carrying it, is excluded with
     * the rest.
     *
     * @return array<int, int|string>
     */
    private static function commentTokens(): array
    {
        return array_values(Tokens::$commentTokens);
    }

    /**
     * The exclusion set for the code text, which is the one searched for a
     * call: every comment and every token carrying text, so what is left is
     * code. `func_get_args()` written into a heredoc, a string, a shell string
     * or a comment is a mention of the call and exempts nothing — which is how
     * PHPMD reads it too, since it matches a call node rather than a substring.
     *
     * @return array<int, int|string>
     */
    private static function codeExclusions(): array
    {
        return array_merge(self::commentTokens(), self::TEXT_TOKENS);
    }

    /**
     * The exclusion set for the read text: the code exclusions, less the
     * strings that interpolate, because `"$name"` inside one is a real read.
     *
     * @return array<int, int|string>
     */
    private static function readExclusions(): array
    {
        return array_values(array_diff(self::codeExclusions(), self::INTERPOLATING_TEXT));
    }

    /**
     * The exclusion set for the compact text: the code exclusions, less the
     * plain quoted string a `compact()` argument is written in.
     *
     * @return array<int, int|string>
     */
    private static function nameExclusions(): array
    {
        return array_values(array_diff(self::codeExclusions(), self::NAME_TEXT));
    }

    /**
     * Whether the body reads the named parameter.
     *
     * The word boundary is what keeps `$id` from being satisfied by `$idle`.
     * `\$\{?` covers the plain and `${name}` spellings; `{$name}` contains the
     * plain one. A `compact('name')` counts as a read of *that* name only,
     * matching PHPMD, which resolves the string argument rather than exempting
     * the whole signature the way `func_get_args()` does. Every `compact()`
     * call in the body is inspected, not just the first: a parameter named by
     * the second call is read exactly as much as one named by the first.
     *
     * A dynamic read — `${'name'}` — counts in neither tool, and a nested
     * declaration that happens to reuse the name counts in both. Both are
     * recorded in the rule's doc rather than worked around.
     *
     * The two texts are separate because they keep different things: the read
     * text keeps the strings that interpolate, so `"$name"` counts, and the
     * compact text keeps the plain quoted string a `compact()` argument is
     * written in. Neither keeps what the other does, so a name interpolated
     * into a heredoc cannot pass as a compact argument, and a `compact()`
     * spelled out inside a heredoc is not a call.
     */
    private function bodyReads(string $reads, string $names, string $name): bool
    {
        $quoted = preg_quote($name, '/');

        if (preg_match('/\$\{?' . $quoted . '\b/', $reads) === 1) {
            return true;
        }

        preg_match_all('/\bcompact\s*\(([^)]*)\)/i', $names, $matches);

        foreach ($matches[1] as $arguments) {
            if (preg_match('/([\'"])' . $quoted . '\1/', $arguments) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the body reaches every parameter through `func_get_args()`.
     *
     * Measured against PHPMD 2.15.0: `func_get_args()` exempts the whole
     * signature, and does so from inside a nested closure as well, which is
     * why the whole body text is searched. `func_num_args()` does *not* exempt
     * anything there, so it is deliberately not matched here.
     *
     * PHPMD only honours the call when it resolves to the global function —
     * inside a namespace it takes an unqualified `func_get_args()` for a
     * namespaced one and reports through it. That is a defect in PHPMD, not a
     * behaviour worth copying: the parameters really are read. The divergence
     * is recorded in the rule's doc.
     */
    private function callsFuncGetArgs(string $code): bool
    {
        return preg_match('/\bfunc_get_args\s*\(/i', $code) === 1;
    }

    /**
     * Whether this is a magic method whose signature PHP fixes.
     *
     * Only a method can be one: a plain function named `__get` is the author's
     * own signature, and PHPMD reports it. The name is lowercased because PHP
     * method names are case-insensitive.
     */
    private function hasFixedSignature(File $phpcsFile, int $stackPtr): bool
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] !== T_FUNCTION) {
            return false;
        }

        if ($this->enclosingClass($phpcsFile, $stackPtr) === null) {
            return false;
        }

        $name = strtolower((string) $phpcsFile->getDeclarationName($stackPtr));

        return in_array($name, self::FIXED_SIGNATURE_METHODS, true);
    }

    /**
     * Whether the declaration is annotated as inheriting its signature.
     *
     * Both signals sit in the same place — between the previous statement and
     * the `function` keyword — so one backward walk collects both, into two
     * separate texts. The walk skips whitespace, comments and the declaration
     * modifiers, and hops each attribute group whole: an attribute group's
     * tokens are not empty tokens, so a plain walk would stop dead at the
     * group's `]` and never reach a docblock above it. PHPCS records
     * `attribute_opener` on the group's closer, which is the pointer the hop
     * uses.
     *
     * The two texts are kept apart so that neither signal can be satisfied by
     * the other's material: a docblock quoting `#[\Override]` in prose is not
     * an attribute, and an attribute argument is not a docblock. The attribute
     * text is collected as code for the same reason the body text is, so an
     * argument that spells out the tail of an attribute list in a string —
     * `#[Route('a, Override(b')]` — carries no weight either. For the same
     * reason a plain comment and a `phpcs:` annotation contribute no text — a
     * `// @inheritdoc` line comment is skipped as whitespace would be, and
     * exempts nothing. PHPMD agrees: it reads the method's doc comment, which
     * is the docblock alone. `Tokens::$emptyTokens` cannot be used as the
     * collection set for that reason: it carries `T_COMMENT` too.
     *
     * `@inheritdoc` is matched case-insensitively and in the `{@inheritdoc}`
     * spelling, exactly as PHPMD matches it. `Override` is matched only where
     * an attribute *name* can stand — right after the group's `#[` or after a
     * comma separating names — and case-insensitively, because PHP resolves
     * attribute names case-insensitively and `#[\override]` is the same
     * attribute.
     */
    private function hasInheritanceAnnotation(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $skippable = Tokens::$methodPrefixes + Tokens::$emptyTokens;
        $undocumented = Tokens::$phpcsCommentTokens + [T_COMMENT => T_COMMENT];
        $docText = '';
        $attributeText = '';
        $pointer = $stackPtr - 1;

        while ($pointer >= 0) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_ATTRIBUTE_END) {
                $opener = $tokens[$pointer]['attribute_opener'] ?? $pointer;
                $attributeText = $this->textBetween(
                    $phpcsFile,
                    $opener,
                    $pointer,
                    self::codeExclusions()
                ) . $attributeText;
                $pointer = $opener - 1;

                continue;
            }

            if (isset($skippable[$code]) === false) {
                break;
            }

            if (isset($undocumented[$code]) === false) {
                $docText = $tokens[$pointer]['content'] . $docText;
            }

            $pointer--;
        }

        return preg_match('/\{?@inheritdoc\b/i', $docText) === 1
            || preg_match('/(?:#\[|,)\s*\\\\?Override\s*[],(]/i', $attributeText) === 1;
    }

    /**
     * The source text between two pointers, inclusive, with the listed token
     * codes left out.
     *
     * @param array<int, int|string> $excluded
     */
    private function textBetween(File $phpcsFile, int $start, int $end, array $excluded): string
    {
        $tokens = $phpcsFile->getTokens();
        $skip = array_flip($excluded);
        $text = '';

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            if (isset($skip[$tokens[$pointer]['code']]) === true) {
                continue;
            }

            $text .= $tokens[$pointer]['content'];
        }

        return $text;
    }

    /**
     * Whether a parent class or interface declared in this same file declares a
     * method of this name.
     *
     * This is the half of PHPMD's `MethodNode::isDeclaration()` that a
     * single-file sniff can honestly answer. The ancestry is walked
     * transitively, so a grandparent in the same file resolves too, and each
     * resolved ancestor contributes the methods of any trait it uses, because
     * PDepend's `getAllMethods()` includes those. The class's *own* traits are
     * deliberately not consulted: PHP gives a class's own method precedence
     * over a trait's, and PHPMD asks only about the parent chain.
     */
    private function overridesSameFileMethod(File $phpcsFile, int $stackPtr): bool
    {
        $classPtr = $this->enclosingClass($phpcsFile, $stackPtr);

        if ($classPtr === null) {
            return false;
        }

        $name = strtolower((string) $phpcsFile->getDeclarationName($stackPtr));
        $declarations = $this->declarationsByName($phpcsFile);
        $seen = [];
        $queue = $this->inheritedNames($phpcsFile, $classPtr);

        while ($queue !== []) {
            $ancestor = array_shift($queue);

            if (isset($seen[$ancestor]) === true || isset($declarations[$ancestor]) === false) {
                continue;
            }

            $seen[$ancestor] = true;
            $pointer = $declarations[$ancestor];

            if (in_array($name, $this->methodNames($phpcsFile, $pointer), true) === true) {
                return true;
            }

            $queue = array_merge(
                $queue,
                $this->inheritedNames($phpcsFile, $pointer),
                $this->traitNames($phpcsFile, $pointer)
            );
        }

        return false;
    }

    /**
     * The short, lowercased names this class-like extends or implements.
     *
     * Traits are deliberately absent, and are added only for an ancestor
     * already resolved: PHP gives a class's own method precedence over one it
     * draws from a trait, so a trait the class uses itself says nothing about
     * whether the class's own method overrides anything. PDepend's
     * `getAllMethods()` reaches a *parent's* trait methods, which is why the
     * queue picks those up as it walks.
     *
     * Names are reduced to their last namespace segment because that is all a
     * same-file lookup can compare: the file declares its class-likes under one
     * namespace, so a `Foo\Bar` reference and a `Bar` declaration in this file
     * are the same type whenever the reference resolves at all. A reference
     * that genuinely points elsewhere simply finds no declaration here and
     * falls through to being reported, which is the conservative direction.
     *
     * @return array<int, string>
     */
    private function inheritedNames(File $phpcsFile, int $classPtr): array
    {
        $parent = $phpcsFile->findExtendedClassName($classPtr);
        $names = $phpcsFile->findImplementedInterfaceNames($classPtr);
        $names = $names === false ? [] : $names;

        if ($parent !== false) {
            $names[] = $parent;
        }

        return $this->shortNames($names);
    }

    /**
     * The short, lowercased names of the traits a class-like uses.
     *
     * @return array<int, string>
     */
    private function traitNames(File $phpcsFile, int $classPtr): array
    {
        return $this->shortNames($this->usedTraitNames($phpcsFile, $classPtr));
    }

    /**
     * Each name reduced to its last namespace segment, lowercased.
     *
     * @param array<int, string> $names
     *
     * @return array<int, string>
     */
    private function shortNames(array $names): array
    {
        return array_map(
            static fn (string $name): string => strtolower(substr((string) strrchr('\\' . $name, '\\'), 1)),
            $names
        );
    }

    /**
     * The names of the traits a class-like `use`s in its body.
     *
     * A `use` inside a class body is a trait import; the same token at file
     * scope is an import statement and belongs to no class, which is why the
     * search is bounded by the class's own scope pointers.
     *
     * Those pointers span any nested declaration too, so each `use` is checked
     * against its own innermost class-like — the same guard methodNames() makes
     * — or a trait used by an anonymous class inside a method would be read as
     * the outer class's.
     *
     * @return array<int, string>
     */
    private function usedTraitNames(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$classPtr]['scope_opener'] ?? null;
        $closer = $tokens[$classPtr]['scope_closer'] ?? null;
        $names = [];

        if ($opener === null || $closer === null) {
            return $names;
        }

        $pointer = $phpcsFile->findNext(T_USE, $opener + 1, $closer);

        while ($pointer !== false) {
            $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $pointer + 1, $closer);

            if ($this->enclosingClass($phpcsFile, $pointer) === $classPtr) {
                $names = array_merge($names, $this->namesBetween($phpcsFile, $pointer, $end));
            }

            $pointer = $phpcsFile->findNext(T_USE, ($end === false ? $pointer : $end) + 1, $closer);
        }

        return $names;
    }

    /**
     * The T_STRING names between two pointers, as one name per comma-separated
     * entry — `use A, B;` imports two traits.
     *
     * @return array<int, string>
     */
    private function namesBetween(File $phpcsFile, int $start, int|false $end): array
    {
        if ($end === false) {
            return [];
        }

        $tokens = $phpcsFile->getTokens();
        $names = [];

        for ($pointer = $start + 1; $pointer < $end; $pointer++) {
            if ($tokens[$pointer]['code'] === T_STRING) {
                $names[] = $tokens[$pointer]['content'];
            }
        }

        return $names;
    }

    /**
     * Every named class-like in the file, keyed by its short lowercased name.
     *
     * @return array<string, int>
     */
    private function declarationsByName(File $phpcsFile): array
    {
        $declarations = [];
        $pointer = $phpcsFile->findNext(self::CLASS_LIKE, 0);

        while ($pointer !== false) {
            $name = $phpcsFile->getDeclarationName($pointer);

            if ($name !== null && $name !== '') {
                $declarations[strtolower($name)] = $pointer;
            }

            $pointer = $phpcsFile->findNext(self::CLASS_LIKE, $pointer + 1);
        }

        return $declarations;
    }

    /**
     * The lowercased names of the methods a class-like declares directly.
     *
     * Nested declarations are filtered out by comparing each `function`'s own
     * innermost class-like condition against this one: a closure assigned
     * inside a method is not a method of the class.
     *
     * @return array<int, string>
     */
    private function methodNames(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$classPtr]['scope_opener'] ?? null;
        $closer = $tokens[$classPtr]['scope_closer'] ?? null;
        $names = [];

        if ($opener === null || $closer === null) {
            return $names;
        }

        $pointer = $phpcsFile->findNext(T_FUNCTION, $opener + 1, $closer);

        while ($pointer !== false) {
            $name = $phpcsFile->getDeclarationName($pointer);

            if ($name !== null && $this->enclosingClass($phpcsFile, $pointer) === $classPtr) {
                $names[] = strtolower($name);
            }

            $pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $closer);
        }

        return $names;
    }

    /**
     * The innermost class-like scope enclosing this declaration, or null when
     * a function or closure scope comes first.
     *
     * Walking outwards and stopping at the first scope of either kind is what
     * keeps a named function declared inside a method from being read as a
     * method — the same distinction CleanCode.Functions.ExcessiveParameterList
     * makes for its diagnostic.
     */
    private function enclosingClass(File $phpcsFile, int $stackPtr): ?int
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $pointer => $code) {
            if (in_array($code, self::CLASS_LIKE, true) === true) {
                return $pointer;
            }

            if (in_array($code, [T_CLOSURE, T_FN, T_FUNCTION], true) === true) {
                return null;
            }
        }

        return null;
    }

    /**
     * Names the declaration for the diagnostic, matching the split PHPMD's own
     * message makes between a method and a function, and naming the two
     * constructs PHPMD cannot see for what they are.
     */
    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $code = $phpcsFile->getTokens()[$stackPtr]['code'];

        if ($code === T_CLOSURE) {
            return 'closure';
        }

        if ($code === T_FN) {
            return 'arrow function';
        }

        $subject = $this->enclosingClass($phpcsFile, $stackPtr) === null ? 'function' : 'method';

        return $subject . ' ' . $phpcsFile->getDeclarationName($stackPtr) . '()';
    }
}
