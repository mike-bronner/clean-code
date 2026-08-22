<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\DeadCode;

use MikeBronner\CleanCode\Helpers\TokenStreams;
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
 * - Both calls have to *be* calls, and a call is recognised by its tokens: the
 *   name, immediately followed by an opening parenthesis, and preceded by
 *   nothing that makes it something else. So one spelled out inside a comment,
 *   a string, a heredoc, a nowdoc, a shell string or inline HTML is printed
 *   rather than run, and a method or a static method of the same name —
 *   `$this->compact('x')`, `Helper::compact('x')` — is a different function
 *   altogether. None of them exempts anything. PHPMD reads them the same way,
 *   because it matches a call node rather than a substring.
 * - A read has to *be* a read. Every read PHP compiles as a variable is one
 *   here, and the two constructs that hide a read inside their text — a
 *   double-quoted string and a heredoc — are searched for a live
 *   interpolation, which a backslash cancels: `"\$name"` prints the name and
 *   reads nothing, while `"\\$name"` prints a backslash and reads it.
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
     * Class-like scopes a method can be declared in.
     *
     * T_ANON_CLASS and T_ENUM earn their place the same way the other three do
     * — a method declared in one resolves its own overrides — but they are the
     * two whose exempt half no parity fixture reaches, because PHPMD is silent
     * on an anonymous class for its own unrelated reason and the parity set's
     * one enum implements nothing. passing.php carries a purpose-built shape
     * for each: drop either member and that shape's parameter is reported.
     */
    private const CLASS_LIKE = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    /**
     * The two constructs whose text PHPCS hands back with the reads still
     * inside it. A double-quoted string arrives whole, and a heredoc one token
     * per physical line, so the variables interpolated into either are part of
     * that text and not tokens of their own — which is why these two, and only
     * these two, are searched as text.
     *
     * Every other construct that carries text is left alone: a single-quoted
     * string, a nowdoc and inline HTML do not interpolate at all, and a shell
     * string does but has its variables tokenized apart from its
     * T_ENCAPSED_AND_WHITESPACE text — as T_VARIABLE, or as
     * T_DOLLAR_OPEN_CURLY_BRACES and T_STRING_VARNAME for `${name}` — so its
     * reads are found by the walk like any other.
     */
    private const INTERPOLATING_TEXT = [
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    /**
     * What a name is not a call to a global function after.
     *
     * PHPMD matches `func_get_args()` and `compact()` as a FunctionPostfix —
     * the global function, not something else that happens to share its
     * spelling. The list is the whole enumeration of what can stand in front of
     * a `T_STRING` that an opening parenthesis follows and still not be that
     * call, derived by working through the grammar once rather than by adding a
     * case at a time:
     *
     * - `$this->compact('x')` and `$service?->compact('x')` call a method, and
     *   `Helper::compact('x')` a static one — T_OBJECT_OPERATOR,
     *   T_NULLSAFE_OBJECT_OPERATOR and T_DOUBLE_COLON.
     * - `new Compact('x')` calls a constructor. PHP resolves class names
     *   case-insensitively, so the spelling matches there too — T_NEW.
     * - `function compact()` *declares* something of that name — T_FUNCTION.
     *   The earlier reasoning that PHP refuses to redeclare a built-in holds
     *   only for a bare global function: a method of a nested or anonymous
     *   class may be named either one freely.
     *
     * Two more shapes are settled before this list is consulted, because
     * neither is decided by the token immediately in front of the name:
     *
     * - A qualified reference — `new \Compact('x')`, `new \Vendor\Compact('x')`
     *   — puts a T_NS_SEPARATOR there instead, so isCallTo() steps over the
     *   whole qualified name first and asks what precedes *that*. PHP_CodeSniffer
     *   splits a fully-qualified name back into separators and T_STRINGs, so
     *   this is the shape every such reference arrives in.
     * - An attribute name — `#[Compact('x')]` — is not a call at all, and is
     *   ruled out structurally by the group its token belongs to. An
     *   attribute's arguments are constant expressions, so no genuine call is
     *   lost with it.
     */
    private const NOT_A_FUNCTION_CALL = [
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    /**
     * Matches one interpolated read — `$name`, `${name}`, or the `$name` inside
     * `{$name}` — and captures the name it reads.
     *
     * The leading `(?<!\\)(?:\\\\)*` consumes a complete run of escape pairs, so
     * `"\$name"` — where the backslash cancels the interpolation and PHP prints
     * the name instead of reading it — does not match, while `"\\$name"` — an
     * escaped backslash followed by a live read — still does. The sibling
     * CleanCode.Controversial.Superglobals sniff reads interpolation with the
     * same pattern, and PHPMD agrees with both.
     *
     * The name is captured whole rather than bounded after the fact, so a
     * longer name that merely starts with a parameter's spelling yields
     * `idleTimer` and never `id`.
     */
    private const INTERPOLATION_PATTERN =
        '/(?<!\\\\)(?:\\\\\\\\)*\K\$\{?(?P<name>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/';

    /**
     * The token stream self::$declarations and self::$declarationNamespace were
     * built from, so that both are discarded when the stream changes.
     *
     * TokenStreams::key() — the one implementation the four sniffs with a
     * per-stream index in this package share — changes whenever the pointers
     * held here could mean something else.
     */
    private ?string $declarationsKey = null;

    /**
     * Every named class-like in the file, keyed by namespace and short name.
     *
     * @var array<string, int>
     */
    private array $declarations = [];

    /**
     * The namespace each class-like in the file is declared under, keyed by its
     * pointer.
     *
     * @var array<int, string>
     */
    private array $declarationNamespace = [];

    /**
     * The method names of each class-like the ancestor walk has reached, keyed
     * by its pointer.
     *
     * @var array<int, array<int, string>>
     */
    private array $methodNames = [];

    /**
     * The trait lookup keys of each class-like the ancestor walk has reached,
     * keyed by its pointer.
     *
     * @var array<int, array<int, string>>
     */
    private array $traitNames = [];

    /**
     * How many times each of the three indexes above was built, and how many
     * times its guard answered from what was already built.
     *
     * The scale tests in tests/Standards/UnusedFormalParameterTest.php read
     * these instead of timing the sniff: "built once per file" is what the
     * memoization claims, and a count states it directly, where a wall-clock
     * ratio only states it as far as a shared runner's jitter allows.
     *
     * Every increment sits inside the same branch as the guard it counts, so a
     * guard that stopped working cannot leave the count intact. The totals are
     * cumulative for the life of the sniff instance — tests/Helpers.php's
     * buildRuleset() memoises the instance, so every test in that file shares
     * one — and are read as a delta around a single process() run.
     *
     * @var array<string, int>
     */
    private array $cacheCounts = [
        'declarations.builds' => 0,
        'declarations.hits' => 0,
        'methodNames.builds' => 0,
        'methodNames.hits' => 0,
        'traitNames.builds' => 0,
        'traitNames.hits' => 0,
    ];

    /**
     * The same counts for methodNames() and traitNames(), split by the ancestor
     * pointer each read asked about, which is the granularity "once per
     * ancestor" is stated at.
     *
     * A pointer means something only within one token stream, so these are
     * cleared with the indexes themselves in buildDeclarations() — two files
     * of the same shape hold their classes at the same pointers, and without
     * the clearing one file's counts would be read as another's.
     *
     * @var array<string, array<int, array{builds: int, hits: int}>>
     */
    private array $cacheCountsByClass = [
        'methodNames' => [],
        'traitNames' => [],
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
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $start = $tokens[$stackPtr]['scope_opener'] + 1;
        $end = $tokens[$stackPtr]['scope_closer'] - 1;

        if ($this->isExempt($phpcsFile, $stackPtr, $start, $end) === true) {
            return;
        }

        $reads = $this->namesRead($phpcsFile, $start, $end);

        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            $this->checkParameter($phpcsFile, $stackPtr, $parameter, $reads);
        }
    }

    /**
     * How many times each index was built and how many times its guard
     * answered, cumulative for the life of this instance.
     *
     * @return array<string, int>
     */
    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    /**
     * The same counts for methodNames() and traitNames(), per ancestor pointer,
     * covering the token stream the indexes currently describe — they are
     * cleared whenever those indexes are.
     *
     * @return array<string, array<int, array{builds: int, hits: int}>>
     */
    public function cacheCountsByClass(): array
    {
        return $this->cacheCountsByClass;
    }

    /**
     * Whether the whole declaration is exempt, before any parameter is read.
     *
     * Ordered cheapest-first: the magic-method test is a string compare, the
     * annotation test scans a handful of tokens, and only then is the same-file
     * override resolution attempted. The bodyless test runs before this, in
     * process(), because a declaration with no body has no text to collect.
     */
    private function isExempt(File $phpcsFile, int $stackPtr, int $start, int $end): bool
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

        return $this->callsFuncGetArgs($phpcsFile, $start, $end);
    }

    /**
     * Reports one parameter unless the body reads it.
     *
     * A promoted constructor property is skipped before the read test runs:
     * it is class state whatever the constructor body does with it, and both
     * PHPMD and every candidate wiring stay silent on one.
     *
     * @param array<string, bool> $reads
     */
    private function checkParameter(
        File $phpcsFile,
        int $stackPtr,
        array $parameter,
        array $reads
    ): void {
        if ($this->isPromotedProperty($parameter) === true) {
            return;
        }

        $name = ltrim($parameter['name'], '$');

        if (isset($reads[$name]) === true) {
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
     * Every name the body reads, as a set, collected in one walk of the tokens
     * between the scope opener and the scope closer.
     *
     * The range is PHPCS's own scope pointers, so a nested closure's body is
     * included — a read there is a real read, and PHPMD counts it too — and
     * nothing outside the declaration is.
     *
     * A name gets into the set three ways, and each of the three is decided by
     * token *type* rather than by searching the body as one string:
     *
     * - a T_VARIABLE, which is every read PHP compiles as one, including the
     *   variables inside a shell string and inside `{$name}`;
     * - a T_STRING_VARNAME, the `${name}` spelling of the same;
     * - a name interpolated into the text of a double-quoted string or a
     *   heredoc, which PHPCS leaves inside that text rather than tokenizing
     *   apart, and which INTERPOLATION_PATTERN reads out of it.
     *
     * A `compact('name')` adds the name it names, because PHPMD resolves the
     * string argument of that call rather than exempting the whole signature
     * the way `func_get_args()` does. Every call in the body is inspected, not
     * only the first.
     *
     * Nothing else contributes: a name written in a comment, a single-quoted
     * string, a nowdoc or inline HTML is not a token of any of these types, so
     * it is not a read — and no walk of an attribute's arguments is needed
     * either, since PHP allows only constant expressions there, and a variable
     * is not one.
     *
     * A dynamic read — `${'name'}` — counts in neither tool, and a nested
     * declaration that happens to reuse the name counts in both. Both are
     * recorded in the rule's doc rather than worked around.
     *
     * @return array<string, bool>
     */
    private function namesRead(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_VARIABLE) {
                $names[ltrim($tokens[$pointer]['content'], '$')] = true;

                continue;
            }

            if ($code === T_STRING_VARNAME) {
                $names[$tokens[$pointer]['content']] = true;

                continue;
            }

            if (in_array($code, self::INTERPOLATING_TEXT, true) === true) {
                $names += $this->interpolatedNames($tokens[$pointer]['content']);

                continue;
            }

            if ($this->isCallTo($phpcsFile, $pointer, 'compact') === true) {
                $names += $this->compactedNames($phpcsFile, $pointer);
            }
        }

        return $names;
    }

    /**
     * The names interpolated into one string's text.
     *
     * @return array<string, bool>
     */
    private function interpolatedNames(string $text): array
    {
        preg_match_all(self::INTERPOLATION_PATTERN, $text, $matches);

        return array_fill_keys($matches['name'], true);
    }

    /**
     * Whether this token is a call to the named global function.
     *
     * The name has to be a T_STRING immediately followed by an opening
     * parenthesis, which is what tells a call from every other place the same
     * spelling can appear: inside a comment or a string it is not a T_STRING at
     * all, and as a constant or a property it is followed by something else.
     * What precedes it settles the rest — see NOT_A_FUNCTION_CALL — so neither a
     * method, a static method, a constructor nor a declaration of the same name
     * is mistaken for the global function PHPMD matches.
     *
     * Two things are decided before that list is reached. An attribute name is
     * ruled out by the group it sits in rather than by what precedes it,
     * because `#[Override, Compact('x')]` puts a comma there — the same token a
     * genuine `f($a, compact('b'))` does. And a qualified reference is stepped
     * over whole, together with a return-by-reference `&`, so
     * `new \Vendor\Compact('x')` is read as the constructor it is and
     * `function &compact()` as the declaration it is; the name that remains is
     * the last segment, which is what PHPMD matches `compact` by, leaving
     * `\func_get_args()` the global function spelled out.
     *
     * The comparison is case-insensitive because PHP resolves function names
     * that way, and PHPMD compares with strcasecmp for the same reason.
     */
    private function isCallTo(File $phpcsFile, int $pointer, string $name): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (
            $tokens[$pointer]['code'] !== T_STRING
            || strtolower($tokens[$pointer]['content']) !== $name
            || isset($tokens[$pointer]['attribute_opener']) === true
        ) {
            return false;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        if (
            $next === false
            || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS
            || $this->isFirstClassCallable($phpcsFile, $next) === true
        ) {
            return false;
        }

        $previous = $this->beforeName($phpcsFile, $pointer);

        return $previous === false
            || in_array($tokens[$previous]['code'], self::NOT_A_FUNCTION_CALL, true) === false;
    }

    /**
     * Whether these parentheses hold PHP 8.1's first-class callable syntax
     * rather than an argument list — `func_get_args(...)`, not
     * `func_get_args()`.
     *
     * The two are the same tokens up to the opening parenthesis, so a check
     * that stops there reads `f(...)` as a call to `f`. It is not one: it
     * builds a Closure and calls nothing, so the body never reaches its
     * parameters through it and the exemption must not apply. The parameters
     * are not reached later either — `func_get_args()` and `compact()` both
     * refuse to run from a Closure's scope, so the Closure throws whenever it
     * is invoked (`func_get_args() cannot be called from the global scope`,
     * confirmed on PHP 8.4).
     *
     * The literal `...` on its own is what tells the syntax apart. A spread of
     * a real argument — `f(...$arguments)` — puts a variable after the
     * ellipsis instead of the closer, and that *is* a call, so it is left to
     * exempt as before.
     */
    private function isFirstClassCallable(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $argument = $phpcsFile->findNext(Tokens::$emptyTokens, $opener + 1, null, true);

        if ($argument === false || $tokens[$argument]['code'] !== T_ELLIPSIS) {
            return false;
        }

        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $argument + 1, null, true);

        return $after !== false && $tokens[$after]['code'] === T_CLOSE_PARENTHESIS;
    }

    /**
     * The first significant token in front of a name, with everything that
     * merely decorates the name stepped over.
     *
     * Two decorations sit between a name and the token that says what the name
     * means, and neither says anything itself:
     *
     * - a qualifier. PHP_CodeSniffer hands back a fully-qualified name as
     *   alternating T_NS_SEPARATOR and T_STRING tokens rather than as one name
     *   token, so the token in front of the last segment of
     *   `new \Vendor\Compact()` is a separator and not the `new` that decides
     *   it. Each `separator, segment` pair is stepped over, and a `namespace\`
     *   prefix on the same terms — it is one more way of writing the qualifier,
     *   and the name it qualifies still ends in the segment being matched.
     * - a return-by-reference `&`. `function &compact()` declares something;
     *   `$mask & compact('x')` and `$ref = &compact('x')` call something. The
     *   `&` is common to all three, so it is stepped over and the token behind
     *   it — `function`, a variable, an `=` — is what settles the difference.
     *
     * A `&` cannot appear inside a qualified name, so stepping over the
     * qualifier first and the `&` after it reaches the same token whichever
     * decorations are present.
     */
    private function beforeName(File $phpcsFile, int $pointer): int|false
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);

        while ($previous !== false && $tokens[$previous]['code'] === T_NS_SEPARATOR) {
            $segment = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previous - 1, null, true);

            if (
                $segment === false
                || in_array($tokens[$segment]['code'], [T_NAMESPACE, T_STRING], true) === false
            ) {
                return $segment;
            }

            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $segment - 1, null, true);
        }

        if ($previous !== false && $tokens[$previous]['code'] === T_BITWISE_AND) {
            return $phpcsFile->findPrevious(Tokens::$emptyTokens, $previous - 1, null, true);
        }

        return $previous;
    }

    /**
     * The names one `compact()` call names.
     *
     * Every plain quoted string anywhere inside the call's parentheses counts,
     * at any depth, because `compact()` takes arrays of names as well as names
     * and PHPMD collects the literals of the whole call the same way. Only a
     * plain quoted string is a name: PHP tokenizes a string that interpolates
     * as a T_DOUBLE_QUOTED_STRING instead, and `compact("$name")` is the
     * dynamic read neither tool resolves — though the name interpolated into it
     * is a read of its own, and is collected as one.
     *
     * @return array<string, bool>
     */
    private function compactedNames(File $phpcsFile, int $pointer): array
    {
        $tokens = $phpcsFile->getTokens();
        $opener = (int) $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);
        $closer = $tokens[$opener]['parenthesis_closer'] ?? null;
        $names = [];

        if ($closer === null) {
            return $names;
        }

        for ($argument = $opener + 1; $argument < $closer; $argument++) {
            if ($tokens[$argument]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                $names[trim($tokens[$argument]['content'], '\'"')] = true;
            }
        }

        return $names;
    }

    /**
     * Whether the body reaches every parameter through `func_get_args()`.
     *
     * Measured against PHPMD 2.15.0: `func_get_args()` exempts the whole
     * signature, and does so from inside a nested closure as well, which is
     * why the whole body is walked. `func_num_args()` does *not* exempt
     * anything there, so it is deliberately not matched here.
     *
     * PHPMD only honours the call when it resolves to the global function —
     * inside a namespace it takes an unqualified `func_get_args()` for a
     * namespaced one and reports through it. That is a defect in PHPMD, not a
     * behaviour worth copying: the parameters really are read. The divergence
     * is recorded in the rule's doc.
     */
    private function callsFuncGetArgs(File $phpcsFile, int $start, int $end): bool
    {
        for ($pointer = $start; $pointer <= $end; $pointer++) {
            if ($this->isCallTo($phpcsFile, $pointer, 'func_get_args') === true) {
                return true;
            }
        }

        return false;
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
     * the `function` keyword — so one backward walk looks for both. The walk
     * skips whitespace, comments and the declaration modifiers, and hops each
     * attribute group whole: an attribute group's tokens are not empty tokens,
     * so a plain walk would stop dead at the group's `]` and never reach a
     * docblock above it. PHPCS records `attribute_opener` on the group's
     * closer, which is the pointer the hop uses.
     *
     * The two signals are read from separate material, so neither can be
     * satisfied by the other's: a docblock quoting `#[\Override]` in prose is
     * not an attribute, and an attribute's argument is not a docblock. Only
     * doc-comment tokens contribute to the docblock text, so a plain comment
     * and a `phpcs:` annotation contribute nothing — a `// @inheritdoc` line
     * comment is skipped as whitespace would be, and exempts nothing. PHPMD
     * agrees: it reads the method's doc comment, which is the docblock alone.
     * `Tokens::$emptyTokens` cannot be used as the collection set for that
     * reason: it carries `T_COMMENT` too.
     *
     * `@inheritdoc` is matched case-insensitively and in the `{@inheritdoc}`
     * spelling, exactly as PHPMD matches it. The attribute is found by walking
     * the group's tokens rather than its text — see isOverrideAttribute().
     */
    private function hasInheritanceAnnotation(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $skippable = Tokens::$methodPrefixes + Tokens::$emptyTokens;
        $undocumented = Tokens::$phpcsCommentTokens + [T_COMMENT => T_COMMENT];
        $docText = '';
        $pointer = $stackPtr - 1;

        while ($pointer >= 0) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_ATTRIBUTE_END) {
                $opener = $tokens[$pointer]['attribute_opener'] ?? $pointer;

                if ($this->isOverrideAttribute($phpcsFile, $opener, $pointer) === true) {
                    return true;
                }

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

        return preg_match('/\{?@inheritdoc\b/i', $docText) === 1;
    }

    /**
     * Whether one attribute group carries `#[\Override]`.
     *
     * The group's own tokens answer this, so nothing written inside it can
     * stand in for the attribute: an argument list is skipped whole, by its
     * parenthesis pointers, which leaves out both `#[Listens(handler:
     * Override::class)]` — a class reference — and `#[Listens(handler: 'first,
     * Override(second')]`, a string that spells out the tail of an attribute
     * list. Only where an attribute *name* can stand is the name read.
     *
     * The match is case-insensitive, because PHP resolves attribute names that
     * way and `#[\override]` is the same attribute.
     */
    private function isOverrideAttribute(File $phpcsFile, int $opener, int $closer): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($pointer = $opener + 1; $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] === T_OPEN_PARENTHESIS) {
                $pointer = $tokens[$pointer]['parenthesis_closer'] ?? $closer;

                continue;
            }

            if (
                $tokens[$pointer]['code'] !== T_STRING
                || strtolower($tokens[$pointer]['content']) !== 'override'
            ) {
                continue;
            }

            if ($this->isAttributeName($phpcsFile, $pointer) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this name stands where an attribute name stands: right after the
     * group's `#[`, or after a comma separating one name from the next, in
     * either case with an optional leading `\`.
     *
     * The leading `\` is hopped rather than ignored, so that `#[\Override]`
     * matches and `#[Vendor\Override]` — a different attribute class, in
     * another namespace — does not.
     */
    private function isAttributeName(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);

        if ($previous !== false && $tokens[$previous]['code'] === T_NS_SEPARATOR) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $previous - 1, null, true);
        }

        return $previous !== false
            && in_array($tokens[$previous]['code'], [T_ATTRIBUTE, T_COMMA], true) === true;
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
     * Names are reduced to their last segment and read under the namespace of
     * the class that names them, which is all a same-file lookup can honestly
     * compare: a `Foo\Bar` reference and a `Bar` declaration in the same
     * namespace of this file are the same type whenever the reference resolves
     * at all, while a `Bar` in some *other* namespace of the file is a
     * different type and no longer answers for it. A reference that genuinely
     * points elsewhere finds no declaration here and falls through to being
     * reported, which is the conservative direction.
     *
     * @return array<int, string>
     */
    private function inheritedNames(File $phpcsFile, int $classPtr): array
    {
        return $this->qualifiedNames(
            $phpcsFile,
            $classPtr,
            $this->declaredAncestorNames($phpcsFile, $classPtr)
        );
    }

    /**
     * Every name the class-like's own header lists, across both of its clauses.
     *
     * PHP_CodeSniffer's findExtendedClassName() cannot be used for this: it
     * collects the parent name from separators, strings and whitespace only, so
     * the first comma ends it, and `interface Base extends One, Two` yields
     * `One` alone. An interface is the one class-like whose `extends` takes a
     * list, and it is also the one findImplementedInterfaceNames() refuses —
     * that method answers for T_CLASS, T_ANON_CLASS and T_ENUM — so nothing
     * else covers the ancestors it drops, and every method inherited from them
     * loses its override exemption and is reported as unused.
     *
     * The header is therefore read here instead, from the first clause keyword
     * to the body's opening brace. Both clauses live in that span — a class can
     * carry each at once — and the keywords separate their entries as a comma
     * does, so one walk collects the whole ancestry whichever spelling declares
     * it. Anything in front of the first keyword is left out, which is what
     * keeps an anonymous class's constructor arguments and an enum's backing
     * type from being read as names.
     *
     * A declaration with no ancestors yields nothing, which is the ordinary
     * case and the one the fixtures exercise on every trait and every
     * standalone class. An absent scope opener yields the same, and shares that
     * exit rather than taking one of its own: it cannot be reached by a parsed
     * declaration — the walk only ever reaches a named class-like this file
     * indexed — but without the bound the search would run past the header to
     * the end of the file, so the possibility is not left to chance. The two
     * sibling readers here, methodNames() and usedTraitNames(), guard the same
     * pointers for the same reason.
     *
     * @return array<int, string>
     */
    private function declaredAncestorNames(File $phpcsFile, int $classPtr): array
    {
        $opener = $phpcsFile->getTokens()[$classPtr]['scope_opener'] ?? null;
        $clause = $opener === null
            ? false
            : $phpcsFile->findNext([T_EXTENDS, T_IMPLEMENTS], $classPtr + 1, $opener);

        if ($clause === false) {
            return [];
        }

        return $this->segmentNames($phpcsFile, $clause, $opener, [T_COMMA, T_EXTENDS, T_IMPLEMENTS]);
    }

    /**
     * The lookup keys of the traits a class-like uses.
     *
     * Held per ancestor for the same reason as methodNames():
     * usedTraitNames() scans the ancestor's whole body too, and the walk
     * reaches it once per descendant method whose name the ancestor does not
     * declare. Memoising methodNames() alone leaves that shape — a class whose
     * methods override nothing, which is the ordinary one — still quadratic
     * through this door, measured with that first index already in place at
     * 0.11s for 250 methods, 0.46s for 500, 2.21s for 1,000 and 9.38s for
     * 2,000.
     *
     * @return array<int, string>
     */
    private function traitNames(File $phpcsFile, int $classPtr): array
    {
        $this->buildDeclarations($phpcsFile);

        if (isset($this->traitNames[$classPtr]) === true) {
            $this->countCacheRead('traitNames', $classPtr, 'hits');

            return $this->traitNames[$classPtr];
        }

        $this->countCacheRead('traitNames', $classPtr, 'builds');

        return $this->traitNames[$classPtr] = $this->qualifiedNames(
            $phpcsFile,
            $classPtr,
            $this->usedTraitNames($phpcsFile, $classPtr)
        );
    }

    /**
     * Each name reduced to its last segment and keyed under the namespace of
     * the class-like that refers to it, which is how buildDeclarations() keys
     * what it records.
     *
     * @param array<int, string> $names
     *
     * @return array<int, string>
     */
    private function qualifiedNames(File $phpcsFile, int $classPtr, array $names): array
    {
        $this->buildDeclarations($phpcsFile);
        $namespace = $this->declarationNamespace[$classPtr] ?? '';

        return array_map(
            static fn (string $name): string => $namespace . '\\'
                . strtolower(substr((string) strrchr('\\' . $name, '\\'), 1)),
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

            if ($end !== false && $this->enclosingClass($phpcsFile, $pointer) === $classPtr) {
                $names = array_merge(
                    $names,
                    $this->segmentNames($phpcsFile, $pointer + 1, $end, [T_COMMA])
                );
            }

            $pointer = $phpcsFile->findNext(T_USE, ($end === false ? $pointer : $end) + 1, $closer);
        }

        return $names;
    }

    /**
     * One name per separated entry between two pointers — `use A, B;` names two
     * traits, `implements One, Two` two interfaces.
     *
     * Each entry yields its *last* T_STRING, which is the segment the lookup
     * compares. PHP_CodeSniffer hands a qualified reference back as alternating
     * separators and strings, so collecting every T_STRING instead would read
     * `use \App\Vendor;` as naming two ancestors, `App` and `Vendor` — and
     * qualifiedNames() then keys the qualifier under the referring class's own
     * namespace, where a class genuinely called `App` answers for it and
     * exempts methods it never declared. Only the last segment names the type.
     *
     * @param array<int, int|string> $separators
     *
     * @return array<int, string>
     */
    private function segmentNames(File $phpcsFile, int $start, int $end, array $separators): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];
        $segment = null;

        for ($pointer = $start; $pointer < $end; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_STRING) {
                $segment = $tokens[$pointer]['content'];

                continue;
            }

            if ($segment !== null && in_array($code, $separators, true) === true) {
                $names[] = $segment;
                $segment = null;
            }
        }

        if ($segment !== null) {
            $names[] = $segment;
        }

        return $names;
    }

    /**
     * Indexes every named class-like in the file, once per token stream.
     *
     * Keying by the short name alone let two class-likes of the same name under
     * different `namespace` blocks of one file overwrite each other, so an
     * ancestor resolved by name could be the wrong class entirely — silently,
     * and in the direction that reports a parameter the override exemption
     * covers. The namespace each one is declared under is part of the key, and
     * a reference is looked up under the namespace of the class that makes it.
     *
     * The walk visits namespace declarations and class-likes in the one order
     * they appear, so the namespace in hand is always the one governing the
     * declaration being recorded. It runs once per stream rather than once per
     * method: it used to be re-run for every method of every class, which cost
     * a file of n methods O(n²) — measured at 0.58s for 250 methods, 1.47s for
     * 500 and 5.01s for 1,000, better than 3x per doubling, on shapes the
     * repo's own TooManyMethods sniffs exempt by ignorepattern and real entity
     * classes reach easily.
     *
     * This is the class list only, and caching it did not on its own make the
     * sniff linear: methodNames() and traitNames() each re-read an ancestor's
     * whole body, and each was still doing so once per descendant method. Both
     * are held here too, and discarded with the rest whenever the key changes,
     * so that the three indexes cannot disagree about which stream they
     * describe.
     */
    private function buildDeclarations(File $phpcsFile): void
    {
        $tokens = $phpcsFile->getTokens();
        $key = TokenStreams::key($phpcsFile);

        if ($this->declarationsKey === $key) {
            $this->cacheCounts['declarations.hits']++;

            return;
        }

        $this->cacheCounts['declarations.builds']++;
        $this->declarationsKey = $key;
        $this->declarations = [];
        $this->declarationNamespace = [];
        $this->methodNames = [];
        $this->traitNames = [];
        $this->cacheCountsByClass = ['methodNames' => [], 'traitNames' => []];

        $targets = array_merge([T_NAMESPACE], self::CLASS_LIKE);
        $namespace = '';
        $pointer = $phpcsFile->findNext($targets, 0);

        while ($pointer !== false) {
            if ($tokens[$pointer]['code'] === T_NAMESPACE) {
                $namespace = $this->namespaceName($phpcsFile, $pointer) ?? $namespace;
            } else {
                $name = $phpcsFile->getDeclarationName($pointer);
                $this->declarationNamespace[$pointer] = $namespace;

                if ($name !== null && $name !== '') {
                    $this->declarations[$namespace . '\\' . strtolower($name)] = $pointer;
                }
            }

            $pointer = $phpcsFile->findNext($targets, $pointer + 1);
        }
    }

    /**
     * Records one read of a per-ancestor index, as a total and against the
     * ancestor it asked about.
     *
     * Called from inside the guard branch it describes, so the two counts and
     * the guard's own outcome cannot drift apart.
     */
    private function countCacheRead(string $index, int $classPtr, string $outcome): void
    {
        $this->cacheCounts[$index . '.' . $outcome]++;

        $counts = $this->cacheCountsByClass[$index][$classPtr] ?? ['builds' => 0, 'hits' => 0];
        $counts[$outcome]++;
        $this->cacheCountsByClass[$index][$classPtr] = $counts;
    }

    /**
     * The namespace a `namespace` token declares, lowercased, or null when it
     * declares none.
     *
     * `namespace\f()` is the operator form — a name qualified against the
     * current namespace — and a T_NS_SEPARATOR directly after the keyword is
     * what tells it from a declaration. A braced `namespace {` declares the
     * global namespace and yields the empty string, which is the same key an
     * unnamespaced file's declarations are recorded under.
     */
    private function namespaceName(File $phpcsFile, int $pointer): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

        if ($next === false || $tokens[$next]['code'] === T_NS_SEPARATOR) {
            return null;
        }

        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $next);
        $name = '';

        for ($segment = $next; $end !== false && $segment < $end; $segment++) {
            if (in_array($tokens[$segment]['code'], [T_NS_SEPARATOR, T_STRING], true) === true) {
                $name .= $tokens[$segment]['content'];
            }
        }

        return strtolower($name);
    }

    /**
     * Every named class-like in the file, keyed by namespace and short name.
     *
     * @return array<string, int>
     */
    private function declarationsByName(File $phpcsFile): array
    {
        $this->buildDeclarations($phpcsFile);

        return $this->declarations;
    }

    /**
     * The lowercased names of the methods a class-like declares directly.
     *
     * Nested declarations are filtered out by comparing each `function`'s own
     * innermost class-like condition against this one: a closure assigned
     * inside a method is not a method of the class.
     *
     * Held per ancestor for the life of the token stream, because the walk that
     * calls this reaches the same ancestor again for every method of every
     * class that descends from it. Scanning that ancestor's body once per
     * descendant method cost a `Derived extends Base` pair of n overrides O(n²)
     * — the same shape buildDeclarations() was memoised for, reached through
     * another door, and measured on that pair at 0.15s for 250 methods, 0.60s
     * for 500, 2.94s for 1,000 and 12.21s for 2,000: a clean 4x per doubling.
     *
     * @return array<int, string>
     */
    private function methodNames(File $phpcsFile, int $classPtr): array
    {
        $this->buildDeclarations($phpcsFile);

        if (isset($this->methodNames[$classPtr]) === true) {
            $this->countCacheRead('methodNames', $classPtr, 'hits');

            return $this->methodNames[$classPtr];
        }

        $this->countCacheRead('methodNames', $classPtr, 'builds');
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$classPtr]['scope_opener'] ?? null;
        $closer = $tokens[$classPtr]['scope_closer'] ?? null;
        $names = [];

        if ($opener === null || $closer === null) {
            return $this->methodNames[$classPtr] = $names;
        }

        $pointer = $phpcsFile->findNext(T_FUNCTION, $opener + 1, $closer);

        while ($pointer !== false) {
            $name = $phpcsFile->getDeclarationName($pointer);

            if ($name !== null && $this->enclosingClass($phpcsFile, $pointer) === $classPtr) {
                $names[] = strtolower($name);
            }

            $pointer = $phpcsFile->findNext(T_FUNCTION, $pointer + 1, $closer);
        }

        return $this->methodNames[$classPtr] = $names;
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
