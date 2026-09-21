<?php

/**
 * Tests the custom CleanCode.Models.DisallowChainedPropertyFetch sniff
 * (Models: Relationship Properties, #42). Fixtures live in
 * tests/fixtures/DisallowChainedPropertyFetchSniff/: the remedied shape and
 * every near-miss the sniff must leave alone in passing.php, the flagged chains
 * in failing.php, a chain cut off mid-edit in unterminated.php, the published
 * suppression comment in suppressed.php, and source PHP itself would reject in
 * malformed.php.
 *
 * CleanCode/ruleset.xml scopes the sniff out of test paths, and these fixtures live under
 * tests/ — so processing one in place reports nothing whatever the sniff does.
 * Every assertion about the sniff's own behaviour therefore runs against a copy
 * staged outside the repository ($stagedRun below), and the exclusion itself is
 * pinned separately by the scoped-out-of-test-paths test, which processes the
 * in-repo path and requires the silence to come from the path rather than from
 * the sniff having nothing to say.
 *
 * Most assertions isolate the sniff from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so they stay stable as
 * sibling standards land in CleanCode/ruleset.xml. The whole-master-ruleset test
 * deliberately does not isolate: it is what pins the severity end to end.
 */

declare(strict_types=1);

const CHAINED = 'CleanCode.Models.DisallowChainedPropertyFetch';

const CHAINED_ERROR = CHAINED . '.Found';

/**
 * Every line of failing.php that must carry exactly one diagnostic.
 */
const CHAINED_FAILING_LINES = [
    3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 17, 19, 20, 21, 22, 23, 25, 30,
    36, 41,
];

/**
 * Every line of group-preceders.php, which carries one chain per token a
 * grouping parenthesis may follow. One line per admission, and one admission
 * per line: the sweep below asserts the correspondence both ways.
 */
const CHAINED_PRECEDER_LINES = [
    11, 16, 19, 20, 22, 24, 28, 29, 30, 31, 32, 33, 34, 36, 38, 42, 43, 44, 45,
    46, 47, 48, 49, 52, 56, 61, 66, 71, 76, 82, 83, 84, 85, 91, 92, 93, 94, 95,
    98,
];

/**
 * Every token PHP_CodeSniffer can put before a grouping parenthesis that the
 * sniff refuses, grouped by the reason it is refused. Together with the tokens
 * it admits this accounts for the whole catalogue, which is what the catalogue
 * test asserts — a token in neither list is an unclassified one, and that is
 * the failure this constant exists to make impossible.
 */
const CHAINED_REFUSED_PRECEDERS = [
    // Closers. A parenthesis after one of these invokes what precedes it
    // (`${'fn'}($book)` calls `fn`), so isInvokedOn() has already claimed them.
    'T_CLOSE_PARENTHESIS', 'T_CLOSE_SQUARE_BRACKET', 'T_CLOSE_CURLY_BRACKET',
    'T_CLOSE_SHORT_ARRAY', 'T_CLOSE_USE_GROUP', 'T_ATTRIBUTE_END',
    'T_CLOSE_TAG', 'T_CLOSE_OBJECT',

    // Constructs that write their own subject or body in brackets. Reading one
    // as a group is the false positive the admission set exists to prevent.
    'T_ARRAY', 'T_ISSET', 'T_EMPTY', 'T_EVAL', 'T_EXIT', 'T_LIST', 'T_UNSET',
    'T_MATCH', 'T_IF', 'T_ELSEIF', 'T_WHILE', 'T_FOR', 'T_FOREACH', 'T_SWITCH',
    'T_CATCH', 'T_DECLARE', 'T_FUNCTION', 'T_FN', 'T_CLOSURE', 'T_CLASS',
    'T_ANON_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM', 'T_USE',
    'T_HALT_COMPILER', 'T_TRY', 'T_FINALLY',

    // Keywords a parenthesised expression cannot legally follow. `new` and
    // `instanceof` take a class rather than an expression; the rest take a
    // name, a literal, a block or nothing. Each was put to `php -l` rather
    // than assumed.
    'T_NEW', 'T_INSTANCEOF', 'T_BREAK', 'T_CONTINUE', 'T_STATIC',
    'T_NAMESPACE', 'T_GOTO', 'T_GLOBAL', 'T_DEFAULT', 'T_MATCH_DEFAULT',
    'T_ENUM_CASE', 'T_AS', 'T_INSTEADOF', 'T_EXTENDS', 'T_IMPLEMENTS',
    'T_CONST', 'T_ENDDECLARE', 'T_ENDFOR', 'T_ENDFOREACH', 'T_ENDIF',
    'T_ENDSWITCH', 'T_ENDWHILE',

    // Declaration modifiers and type declarations. A parenthesis after one is
    // part of a signature, not an expression at all.
    'T_ABSTRACT', 'T_FINAL', 'T_VAR', 'T_PUBLIC', 'T_PRIVATE', 'T_PROTECTED',
    'T_READONLY', 'T_PUBLIC_SET', 'T_PRIVATE_SET', 'T_PROTECTED_SET',
    'T_CALLABLE', 'T_ARRAY_HINT', 'T_RETURN_TYPE', 'T_PARAM_NAME',
    'T_PROPERTY', 'T_PROTOTYPE', 'T_NULLABLE', 'T_TYPE_UNION',
    'T_TYPE_INTERSECTION', 'T_TYPE_OPEN_PARENTHESIS',
    'T_TYPE_CLOSE_PARENTHESIS',

    // Expression atoms — names, literals and magic constants. A parenthesis
    // after one invokes it, which again belongs to isInvokedOn().
    'T_STRING', 'T_VARIABLE', 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED',
    'T_NAME_RELATIVE', 'T_LNUMBER', 'T_DNUMBER', 'T_CONSTANT_ENCAPSED_STRING',
    'T_DOUBLE_QUOTED_STRING', 'T_HEREDOC', 'T_NOWDOC', 'T_TRUE', 'T_FALSE',
    'T_NULL', 'T_SELF', 'T_PARENT', 'T_THIS', 'T_CLASS_C', 'T_DIR', 'T_FILE',
    'T_FUNC_C', 'T_LINE', 'T_METHOD_C', 'T_NS_C', 'T_TRAIT_C', 'T_PROPERTY_C',
    'T_BACKTICK',

    // Operators and punctuation that need a name or a variable next, not a
    // parenthesised expression.
    'T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON',
    'T_PAAMAYIM_NEKUDOTAYIM', 'T_NS_SEPARATOR', 'T_INC', 'T_DEC', 'T_DOLLAR',
    'T_ATTRIBUTE', 'T_OPEN_USE_GROUP',
    'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG',
    'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG',

    // Never reached: the walk skips Tokens::$emptyTokens before it asks, and
    // inline HTML cannot sit inside an expression.
    'T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT', 'T_DOC_COMMENT_OPEN_TAG',
    'T_DOC_COMMENT_CLOSE_TAG', 'T_DOC_COMMENT_STAR', 'T_DOC_COMMENT_STRING',
    'T_DOC_COMMENT_TAG', 'T_DOC_COMMENT_WHITESPACE', 'T_PHPCS_DISABLE',
    'T_PHPCS_ENABLE', 'T_PHPCS_IGNORE', 'T_PHPCS_IGNORE_FILE', 'T_PHPCS_SET',
    'T_INLINE_HTML', 'T_NONE', 'T_BAD_CHARACTER',

    // String interpolation internals and heredoc markers, where a parenthesis
    // is content rather than syntax.
    'T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES', 'T_STRING_VARNAME',
    'T_NUM_STRING', 'T_ENCAPSED_AND_WHITESPACE', 'T_START_HEREDOC',
    'T_END_HEREDOC', 'T_START_NOWDOC', 'T_END_NOWDOC',

    // Defined by PHP_CodeSniffer for its JavaScript and CSS tokenizers, and
    // never emitted for a PHP file.
    'T_COLOUR', 'T_URL', 'T_STYLE', 'T_HASH', 'T_TYPEOF', 'T_OBJECT',
    'T_LABEL', 'T_ZSR', 'T_REGULAR_EXPRESSION', 'T_EMBEDDED_PHP',
];

/**
 * Classified names whose token PHP itself only added in a later version than
 * this package's floor, mapped to the PHP_VERSION_ID that added it.
 *
 * The catalogue test closes the enumeration in both directions, and the second
 * direction — no classified name PHP_CodeSniffer does not define — reads
 * differently on an older interpreter. A refusal recorded for a token PHP has
 * not added yet names nothing there, so it is not a stale entry to be removed;
 * it is an entry the running interpreter cannot see. Without this map the test
 * is red on every PHP below 8.4, which is inside the `^8.1` composer.json
 * declares.
 *
 * `T_PROPERTY_C` is the only such name, and that is measured over the whole
 * supported range rather than assumed from the one failure. Every `T_*`
 * constant a sniff can see was enumerated on php:8.1-cli 8.1.34, php:8.2-cli
 * 8.2.33, php:8.3-cli 8.3.33, php:8.4-cli 8.4.24 and php:8.5-cli 8.5.9, using
 * the command CleanCode/Support/BackportedTokens.php records, against
 * PHP_CodeSniffer 3.13.6. PHP 8.1, 8.2 and 8.3 each answer the same 236 names.
 * PHP 8.4 answers 237, the one addition being `T_PROPERTY_C` — the
 * `__PROPERTY__` magic constant, which PHP_CodeSniffer does not back-port,
 * unlike the `T_PUBLIC_SET` family PHP 8.4 adds beside it. PHP 8.5 answers 239,
 * adding `T_PIPE` and `T_VOID_CAST`. Nothing is removed between any two.
 *
 * The 8.5 pair is deliberately absent from this map: the sniff admits both, so
 * CleanCode/Support/BackportedTokens.php defines them on every version and they
 * are in the catalogue wherever this test runs. Only a refused name — a string
 * that nothing defines — can go missing.
 *
 * A gate only ever subtracts, and only below its own version. On PHP 8.4 and up
 * nothing here is filtered, so a typo or a stale name in either list still
 * reddens the test on the interpreter CI pins and on the newest one.
 */
const CHAINED_TOKENS_ADDED_IN = [
    'T_PROPERTY_C' => 80400,
];

// Fixtures are copied outside the repository before processing, because PHPCS
// decides CleanCode/ruleset.xml's test-path exclusion from the file's path alone. The
// staged copies are removed by the afterEach() hook in tests/Pest.php.
$stagedRun = static fn (string $fixture) => analyzeWithSniffs(
    [CHAINED],
    stageFixtureOutsideTests(fixturePath('DisallowChainedPropertyFetchSniff', $fixture))
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CHAINED);
});

/**
 * The remedied shape and every near-miss stay silent. Each group pins one of
 * the sniff's early returns, and a false positive on any of them makes the rule
 * unusable at error severity:
 *
 * - lines 3-5, `$book->authorName`, `$book->author`, `$this->title` — a single
 *   hop. The accessor attribute the standard mandates reads exactly like this,
 *   so flagging one hop would flag the remedy.
 * - lines 7-11, a method call anywhere in the chain — `->save()`, `->name()`,
 *   `->getAuthor()->name`, `->find($id)->name`, `->author()->name`. A call is a
 *   different access pattern, and only one property hop is left on either side
 *   of it in each of these.
 * - lines 13-15, `$a->b['x']->c`, `$a->b()['x']->c`, `$a->b->{$c}` — an array
 *   subscript ends the segment (its receiver is `]`, not a property name), and
 *   a braced member name is not a property name at all.
 * - lines 17-20, `Book::query()->first()->author->name`,
 *   `static::make()->author->name`, `self::$instance->author->name`,
 *   `Book::$registry->author->name` — static-rooted chains, out of scope for
 *   this issue. Each walks back past calls and property hops to a name preceded
 *   by `::`, and all four are needed: the last two prove the root test is not
 *   simply "the walk ended on a T_VARIABLE", since `$instance`/`$registry` are
 *   variables.
 * - lines 22-25, the negative half of the grouping-parenthesis walk.
 *   `($book)->author` is a single hop however it is parenthesised.
 *   `(new Book())->author->name` and `(Book::query()->first())->author->name`
 *   are an instantiation and a static root, neither of which becomes a variable
 *   root by being wrapped — the group is walked into, and what is found inside
 *   still decides. `foo($book)->author->name` is the discriminating pair to
 *   failing.php:14: the parentheses there hold the root, the parentheses here
 *   are a call's argument list, so walking into them (and finding `$book`)
 *   would flag a function-call root the sniff has never claimed.
 * - lines 27-31, groups holding more than one expression, which have no single
 *   root and are out of scope. Lines 27-28 are the pair that forces the rule:
 *   `($condition ? Book::first() : $fallback)->author->name` and the same
 *   ternary with its arms swapped say the same thing, one arm static-rooted and
 *   one variable-rooted, so a verdict read off the last arm alone would flag one
 *   and stay silent on the other purely from the order they are written in.
 *   Refusing the group instead makes both silent. Line 29 is the price: a
 *   ternary whose arms are both variables is a chain the sniff could in
 *   principle name, and it is given up to keep the rule order-independent — the
 *   doc's limitations section publishes this. Lines 30-31 are the other two
 *   multi-expression groups PHP allows in this position, `??` and `match`.
 *   Removing the single-expression check flags all of 27, 29, 30 and 31 — 28
 *   stays silent even then, which is exactly the asymmetry being removed.
 * - lines 33-35, `$a->{$b}->c`, `$a->{'b'}->c`, `$a->$b->c` — a dynamic member
 *   name is not a property-fetch hop, because which property is read is
 *   unknowable at token level, so the single plain hop after it is not a chain.
 *   Both tokens a dynamic name can produce are covered: `{` for the two braced
 *   forms, T_VARIABLE for the plain-variable one. failing.php:12 is the other
 *   half of this — two plain hops after a dynamic one *are* a chain.
 * - lines 37-39, the three constructs that write a bracketed subject or body in
 *   front of an operator and are legal PHP: `array($book)[0]->author->name`,
 *   `(clone $book)->author->name` and an immediately-invoked closure. None is a
 *   receiver the sniff models, and each reaches a different arm of the root
 *   walk — the subscript's own opener, the single-expression rule, and the
 *   call-argument-list step respectively. Their unparseable siblings (`match`,
 *   `eval`, `isset`, `list`, `exit`) are in malformed.php, which is the only
 *   file that can hold them.
 * - lines 41-47, an accessor declaration returning `$this->authorName` — the
 *   shape the standard asks for, in situ.
 */
it('produces no violations on the compliant fixture', function () use ($stagedRun): void {
    $file = $stagedRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every chained fetch is flagged, once, at its own line:
 *
 * - line 3, `$book->author->name` — the standard's own example.
 * - lines 4-5, `$book?->author?->name` and `$book->author?->name` — the
 *   nullsafe operator is a separate token, so it has to be registered alongside
 *   the ordinary one, and mixing the two still counts.
 * - line 6, `$this->author->name` — a chain rooted in `$this` is still a chain.
 *   This is also the accessor's own body: the one place the standard's remedy
 *   has to traverse the relationship, so it is a known false positive that the
 *   doc's suppression path covers.
 * - line 7, `$book->author->address->city` — three hops earn one diagnostic,
 *   not two. Which pair is named is asserted separately below.
 * - line 8, `$order->customer->name ?? ''` — a null-coalescing default at the
 *   call site is the very duplication the accessor removes.
 * - line 9, `$a->b()->c->d` — a method call ends only its own segment; the two
 *   property hops after it form a chain of their own.
 * - line 10, `$a->b['x']->c->d` — the same reset via an array subscript.
 * - line 11, `$books[0]->author->name` — a subscripted variable is still a
 *   variable root; the walk has to step over the subscript to see it.
 * - line 12, `$book->{$relation}->address->city` — a braced member name is not
 *   itself a property hop, but the two hops after it are, and the root is still
 *   `$book`. Same rule as line 9: the unreadable hop ends its own segment only.
 * - lines 14-17, roots held inside a grouping parenthesis: `($book)`,
 *   `($books[0])`, `(($book))` and `($book->author())`. A parenthesised
 *   expression is still what it was, so wrapping the root must not silence the
 *   chain — the walk has to look inside the group rather than at whatever
 *   precedes its opener, and has to keep doing so through nesting. Line 15 is
 *   what keeps the single-expression rule from collapsing into "the group holds
 *   one token": a subscripted variable is several tokens and still one
 *   expression, so it is flagged, while the multi-expression groups in
 *   passing.php:27-31 are not. Line 17 is the same point for a group whose one
 *   expression ends in a *call* — `($book->author())->name->city` — the shape
 *   the group family's other fixtures all left out.
 * - lines 19-23, the positive half of the same walk: every other bracketed
 *   group is stepped over, not walked into, and each line pins one of the ways
 *   that decision is reached. Lines 19-22 are argument lists, one per token that
 *   can sit in front of a call's opener — `$fn('author')` (T_VARIABLE),
 *   `($fn)()` (T_CLOSE_PARENTHESIS), `$handlers['x']()`
 *   (T_CLOSE_SQUARE_BRACKET), `$book->{$method}()` (T_CLOSE_CURLY_BRACKET) —
 *   all chains of two plain hops on a call result whose own root is a variable,
 *   the same reason line 9 is flagged. Dropping any one of those tokens reverts
 *   its line to walking into the argument list, where the root found is the
 *   argument rather than the callable. Line 23,
 *   `$book->{Book::KEY}->address->city`, is why walking in is restricted to
 *   parentheses in the first place: the braces hold a member name, not a
 *   receiver, so reading a root out of them finds `Book::KEY` and loses the real
 *   root, `$book`.
 * - line 25, `foo(($book)->author->name)` — a group opening straight after
 *   another opener. One of the admitted grouping-parenthesis preceders, and the
 *   one that shows the admission is about the *token before the group*, not
 *   about the group starting a statement.
 * - line 30, a chain broken across lines with a comment in the middle — the
 *   layout the operator-line-break standard mandates must not hide it. The
 *   diagnostic lands on the member, so it is reported against the last line of
 *   the chain rather than the first.
 * - line 36, the shape inside a controller that the standard is aimed at.
 * - line 41, `return ($book)->author->city` — a group after `return`, the other
 *   admitted preceder that is a keyword rather than punctuation.
 */
it('flags every violation at its own line with the expected code', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]));
});

/**
 * The message names the offending pair so the developer knows which two models
 * to decouple, and names the remedy the standard prescribes — an accessor
 * attribute on the first model — rather than only stating that something is
 * wrong.
 *
 * Line 9 is asserted alongside line 3 because the pair in the message is
 * computed from the flagged hop, not from the start of the expression: a
 * message built from the whole statement would read `a->b` there.
 *
 * Line 7 is the one that pins *which* pair a chain of three or more plain hops
 * reports. `$book->author->address->city` completes at `author->address` and
 * again at `address->city`, and only the first is reported, so the developer is
 * pointed at the first model rather than the last. Asserting the code alone
 * cannot see that: a regression reporting the last completing pair instead
 * leaves the line, the count and the source identical, and line 9's chain is a
 * method-call reset with only one possible pair, so it cannot discriminate
 * either. The absence of `address->city` is asserted as well as the presence of
 * `author->address` — the message quotes source text, so a message naming both
 * pairs would satisfy a presence check on its own.
 *
 * The operator between the two is quoted from the matched token, so lines 4 and
 * 5 read back as `author?->name`: the sniff registers on both operators, and a
 * message that wrote `->` out as a literal would misquote every nullsafe hop it
 * reported. Line 5 is the one that discriminates hardest — only its second
 * operator is nullsafe, so a message taking the operator from anywhere but the
 * flagged hop still reads `author->name`.
 */
it('names the first completing pair and the accessor remedy', function () use ($stagedRun): void {
    $messages = violationMessagesByLine($stagedRun('failing.php')->getErrors());

    expect($messages[3][0])->toContain('author->name')
        ->and($messages[4][0])->toContain('author?->name')
        ->and($messages[5][0])->toContain('author?->name')
        ->and($messages[9][0])->toContain('c->d')
        ->and($messages[7][0])->toContain('author->address')
        ->and($messages[7][0])->not->toContain('address->city')
        ->and($messages[3][0])->toContain('getAuthorNameAttribute()')
        ->and($messages[3][0])->toContain('accessor attribute on the first model');
});

/**
 * A grouping parenthesis is recognised from the token in front of it, against a
 * closed admission set — so the set being short by one token is a chain that
 * goes unreported, silently and for exactly one shape. `!($book)->author->name`
 * reporting nothing while `($book)->author->name` reported correctly is how
 * that reads from outside, and it is not a shape a reader would think to try.
 *
 * group-preceders.php therefore carries one chain per admitted token, and this
 * asserts every one of them is reported. It is the coverage half of the pair:
 * the file exists so that no member of the set can be removed — or fail to be
 * added — without a line here going quiet. The sniff lists 35 tokens; the 33
 * that every supported PHP tokenizes have a line here, one apiece, as does each
 * of the five PHP_CodeSniffer unions it defers to. Each was checked the same
 * way: deleting it silences its own line and no other, so no line is carried by
 * a neighbour and no member is dead weight. `=>` was the one that was:
 * Tokens::$assignmentTokens already supplied it, and the list no longer repeats
 * it.
 *
 * The other two, T_VOID_CAST and T_PIPE, cannot be written in this file at all
 * — `|>` does not parse before PHP 8.5 and `(void) (…)` parses there as
 * something else — so they carry the same one-line-apiece proof in
 * group-preceders-php85.php, under the test that PHP 8.5 gates.
 *
 * The catalogue test below is the other half. Between them, a token is either
 * admitted with a line proving it, or refused with a reason recorded.
 */
it('reports a chain behind every admitted grouping-parenthesis preceder', function () use ($stagedRun): void {
    $file = $stagedRun('group-preceders.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))
        ->toBe(array_fill_keys(CHAINED_PRECEDER_LINES, [CHAINED_ERROR]));
});

/**
 * The same sweep for the two preceders PHP 8.5 adds, in a fixture of its own
 * because `(void)` and `|>` are parse errors before 8.5 — putting them in
 * group-preceders.php would change how that file tokenises on every older
 * version, and this package supports PHP 8.1 upward.
 *
 * Which is also why this skips rather than adapts below 8.5: the tokens cannot
 * occur there, so there is no weaker assertion to fall back to. The catalogue
 * test below is what covers those versions — it runs everywhere and fails if
 * either token is left unclassified — and CI pins PHP 8.4, so the run that
 * proves these two lines is a local one, recorded in the pull request.
 *
 * Non-vacuous by mutation rather than by reading: deleting T_VOID_CAST from
 * GROUP_PRECEDERS silences line 14 and nothing else, deleting T_PIPE silences
 * line 18 and nothing else, and deleting both empties the report.
 */
it('reports a chain behind the grouping-parenthesis preceders PHP 8.5 adds', function () use ($stagedRun): void {
    $file = $stagedRun('group-preceders-php85.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))
        ->toBe([14 => [CHAINED_ERROR], 18 => [CHAINED_ERROR]]);
})->skip(PHP_VERSION_ID < 80500, 'T_VOID_CAST and T_PIPE need PHP 8.5');

/**
 * The admission set is only as good as its completeness, and completeness is
 * not something a reader can see by looking at a list of plausible tokens. This
 * sniff has already been short by one twice — once for `!`, `~`, `.`, `yield`,
 * `yield from` and `case` at the same time — because each round added the
 * tokens that had been reported and left the rest of the catalogue unexamined.
 *
 * So the enumeration is closed against PHP_CodeSniffer's own catalogue rather
 * than against judgement: every T_* token it defines, whether from PHP's
 * tokenizer or its own, is either admitted (the list in the sniff, plus the
 * five unions it defers to) or refused with its reason recorded in
 * CHAINED_REFUSED_PRECEDERS. A token in neither is unclassified, and that is
 * what fails here — including a token a future PHP_CodeSniffer adds, which is
 * precisely the case no fixture can anticipate.
 *
 * The admitted half is read out of the sniff's own source rather than restated,
 * so the two cannot drift apart: a member deleted from the constant leaves its
 * token in neither list and reddens this test as an unclassified one. Read by
 * tokenizing the file with PHP_CodeSniffer, because this package forbids
 * Reflection in its own tests (CleanCode.Testing.NoReflectionAccess) and a test
 * that broke the standard it ships would be an odd thing to ship.
 */
it('classifies every token in PHP_CodeSniffer\'s catalogue', function (): void {
    $catalogue = [];

    foreach (['tokenizer', 'user'] as $group) {
        foreach (array_keys(get_defined_constants(true)[$group] ?? []) as $name) {
            if (str_starts_with($name, 'T_') === true) {
                $catalogue[$name] = constant($name);
            }
        }
    }

    // Combined with `+` rather than array_merge(), which renumbers the integer
    // keys these are looked up by and would leave every union-sourced token
    // reading as unclassified.
    $unions = \PHP_CodeSniffer\Util\Tokens::$assignmentTokens
        + \PHP_CodeSniffer\Util\Tokens::$operators
        + \PHP_CodeSniffer\Util\Tokens::$comparisonTokens
        + \PHP_CodeSniffer\Util\Tokens::$booleanOperators
        + \PHP_CodeSniffer\Util\Tokens::$castTokens;

    $admitted = array_merge(
        tokenNamesInConstant(
            cleanCodeRoot() . '/CleanCode/Sniffs/Models/DisallowChainedPropertyFetchSniff.php',
            'GROUP_PRECEDERS',
            [CHAINED]
        ),
        array_keys(array_filter($catalogue, static fn ($code): bool => isset($unions[$code]) === true))
    );

    // Names for tokens this interpreter is too old to define at all. They are
    // classified, and on a newer PHP this test proves it; here there is nothing
    // for them to name, so they are held out of both directions rather than
    // read as naming a token PHP_CodeSniffer lacks.
    $premature = array_keys(array_filter(
        CHAINED_TOKENS_ADDED_IN,
        static fn (int $addedIn): bool => PHP_VERSION_ID < $addedIn
    ));

    $classified = array_diff(array_merge($admitted, CHAINED_REFUSED_PRECEDERS), $premature);

    sort($classified);
    $expected = array_keys($catalogue);
    sort($expected);

    expect(array_values(array_diff(array_keys($catalogue), $classified)))
        ->toBe([], 'every token PHP_CodeSniffer defines is admitted or refused')
        ->and(array_values(array_diff($classified, array_keys($catalogue))))
        ->toBe([], 'neither list names a token PHP_CodeSniffer does not define')
        ->and(array_values(array_intersect($admitted, CHAINED_REFUSED_PRECEDERS)))
        ->toBe([], 'no token is both admitted and refused')
        ->and($classified)->toBe($expected);
});

/**
 * The gate the catalogue test reads is held to the interpreter it claims to
 * describe, so it cannot quietly widen into an excuse.
 *
 * Each entry says two things: PHP defines the name from the stated version, and
 * PHP does not define it before. Both are asserted here against the running
 * interpreter, whichever one that is, so the pair of runs CI and a developer's
 * 8.5 checkout make between them puts each entry to the test from both sides. A
 * name that no PHP ever defines — a typo, or one PHP_CodeSniffer back-ports
 * after all — reddens this on the version that was supposed to have it, rather
 * than being filtered out of the catalogue test in silence on every version.
 *
 * Membership in the refused list is asserted for the same reason: the gate
 * subtracts from what the catalogue test classifies, and subtracting a name
 * that was never classified would be filtering nothing while looking like
 * coverage.
 */
it('gates a token name only for the PHP versions that predate it', function (): void {
    expect(CHAINED_TOKENS_ADDED_IN)->not->toBe([]);

    foreach (CHAINED_TOKENS_ADDED_IN as $name => $addedIn) {
        expect(defined($name))
            ->toBe(PHP_VERSION_ID >= $addedIn, $name . ' is defined from PHP ' . $addedIn . ' onward')
            ->and(CHAINED_REFUSED_PRECEDERS)->toContain($name);
    }
});

/**
 * The standard mandates the accessor, so violations are errors — and they have
 * to survive the master ruleset as errors, not just leave the sniff as one.
 * This is the only test that runs the whole of CleanCode/ruleset.xml unedited: a
 * `<severity>` or `<type>` override on the rule, or an exclude-pattern broader
 * than the intended test-path one, would silence or demote the sniff without
 * touching the addError() call the other tests exercise.
 *
 * Filtering by source keeps the assertion about this sniff while the rest of
 * the ruleset reports whatever it likes about the same fixture.
 */
it('errors through the whole master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(
        stageFixtureOutsideTests(fixturePath('DisallowChainedPropertyFetchSniff', 'failing.php'))
    );

    $onlyChained = static fn (array $messages): array => array_filter(
        array_map(
            static fn (array $sources): array => array_values(
                array_filter($sources, static fn (string $source): bool => $source === CHAINED_ERROR)
            ),
            violationSourcesByLine($messages)
        ),
        static fn (array $sources): bool => $sources !== []
    );

    expect($onlyChained($file->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]))
        ->and($onlyChained($file->getWarnings()))->toBe([]);
});

/**
 * Test suites build object graphs inline and read straight through them, so
 * CleanCode/ruleset.xml scopes the sniff out of test paths. The exclusion is a path match,
 * so processing failing.php where it actually lives — under tests/ — must report
 * nothing, even though the same bytes produce an error on every line of
 * CHAINED_FAILING_LINES from outside the repository.
 *
 * Both halves are asserted together. The in-repo run alone would pass just as
 * well against a sniff that never fires at all, which is precisely the failure
 * mode the exclusion makes easy to ship unnoticed.
 */
it('is scoped out of test paths', function () use ($stagedRun): void {
    $inRepo = analyzeWithSniffs(
        [CHAINED],
        fixturePath('DisallowChainedPropertyFetchSniff', 'failing.php')
    );

    expect($inRepo->getErrors())->toBe([])
        ->and($stagedRun('failing.php')->getErrors())->toHaveCount(count(CHAINED_FAILING_LINES));
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so a chain can end at the operator
 * with no member after it at all (line 4). The sniff has to pass over it rather
 * than fall over or invent a diagnostic for it.
 *
 * The `$memberPtr === false` guard that reads as what prevents this is in fact
 * defensive only, and removing it changes no result: PHP resolves
 * `$tokens[false]` to `$tokens[0]`, the open tag, which fails the T_STRING check
 * on the next line anyway. It is kept for saying so outright instead of leaning
 * on that coercion. Stated here because no fixture can pin it — this test covers
 * the truncated chain, not the guard.
 *
 * That leaves the root walk's own guards, and every one of them is accounted for
 * rather than left to inference. The ones that decide a result are pinned by
 * fixtures that go red without them: the unmatched opener, the non-identifier
 * receiver and the refused constructs, all in malformed.php, and the
 * single-expression rule for a grouping parenthesis, in passing.php:27-31
 * against failing.php:14-17. The rest are defensive, and are named here rather
 * than claimed as covered:
 *
 * - isInvokedOn() and isGroupingParenthesis() returning false for an opener with
 *   nothing before it cannot be reached — a file starts with its open tag, so
 *   some token always precedes. That is equally why no unbounded findPrevious()
 *   in the walk or in process() can return false, and why the walk cannot end by
 *   stepping off the start of the file.
 * - rootInsideGroup() finding nothing between the opener and the closer needs an
 *   empty group, and `()->a->b` is not an expression PHP accepts.
 * - The brace branch refusing a `}` whose opener does not follow an object
 *   operator. Its positive half — the braced member name — is reached by
 *   passing.php:15 and 33-34 and by failing.php:12 and 23, but nothing reaches
 *   the refusal *and depends on it*: every brace pair that can sit in front of
 *   an operator without being a member name belongs to a body whose construct
 *   is already refused a step earlier, at the parenthesis around its subject
 *   (`match`) or by the receiver being neither a name nor a variable (an
 *   anonymous class, a closure, `${$name}`). Measured, not assumed — removing
 *   the refusal changes no result on any of those shapes. It is kept because it
 *   is what makes the brace case a closed admission set rather than a second
 *   list of things to exclude, which is the property that stops the next
 *   construct nobody has thought of from being read as a receiver.
 *
 * Removing any of those changes no result on any fixture here.
 *
 * Line 3 keeps the assertion honest: the file still has to report the complete
 * chain that precedes the truncation, so a sniff that fell silent on the whole
 * file would fail here rather than pass.
 */
it('handles a truncated chain without falling over', function () use ($stagedRun): void {
    $file = $stagedRun('unterminated.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))->toBe([3 => [CHAINED_ERROR]]);
});

/**
 * PHP_CodeSniffer tokenizes whatever it is handed, so the root walk can be given
 * source PHP itself would reject. Each line here refuses one such shape, and
 * each pins a guard that no well-formed fixture reaches:
 *
 * - line 3, `$a->b)->c->d;` — a stray closer. An unmatched parenthesis carries
 *   no parenthesis_opener, so openerOf() returns false and the
 *   `$openerPtr === false` guard ends the walk. That guard decides the result
 *   rather than merely reading defensively: without it the false opener is used
 *   as a bound instead (`false + 1`), the walk reads back into `b`, steps over
 *   its hop to `$a`, and reports `c->d`.
 * - lines 4-5, `$a->b]->c->d;` and `$a->b}->c->d;` — the bracket forms of line
 *   3. These are the only fixtures anywhere that reach openerOf()'s
 *   bracket_opener branch with an *unmatched* closer (the matched case is
 *   reached by every subscript fixture, e.g. failing.php:10), and both return
 *   false from it.
 * - line 6, `$a->5->b->c;` — a numeric member name. The walk lands on a token
 *   that is neither a name nor a variable, and the catch-all guard refuses it.
 *   Without that guard the walk falls through to the object-operator branch
 *   below, reaches `$a`, and reports `b->c`.
 * - lines 7-11, the five constructs that write a bracketed subject or body in
 *   front of an operator and are *not* legal PHP in that position — `eval`,
 *   `isset`, `list`, `exit` and a bare `match` block. This file is the only one
 *   that can hold them: PHP rejects every one, so they cannot sit in passing.php
 *   and still let it parse, while PHPCS tokenizes them intact and hands them
 *   straight to the root walk. Each is refused by a closed admission set rather
 *   than by being listed as an exclusion, which is what makes the *next*
 *   construct nobody thought of silent too. Before that set, `match` and `eval`
 *   read as grouping parentheses, so the walk went into the subject and reported
 *   a chain against it; `isset`, `list` and `exit` did the same. Removing the
 *   parenthesis admission set reddens this test on all five lines — including
 *   line 11, whose `match` block is refused at the parenthesis around its
 *   subject rather than at its braces.
 *
 * All are false positives on source that cannot run, which at error severity is
 * a broken build over a typo mid-edit.
 *
 * Line 12 keeps the assertion honest: a well-formed chain in the same file must
 * still be reported, so a sniff that gave up on the file at the first malformed
 * line would fail here rather than pass.
 */
it('refuses malformed source rather than guessing at it', function () use ($stagedRun): void {
    $file = $stagedRun('malformed.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationSourcesByLine($file->getErrors()))->toBe([12 => [CHAINED_ERROR]]);
});

/**
 * At error severity an unsuppressed false positive breaks the build, so the
 * standard's doc publishes an inline suppression for the one traversal it cannot
 * avoid — the accessor's own body. Pins that the published comment really does
 * silence the sniff (line 8), and the unsuppressed accessor below it (line 14)
 * proves the silence comes from the comment rather than from the sniff having
 * nothing to say about the file.
 */
it('is silenced by the published inline suppression', function () use ($stagedRun): void {
    $file = $stagedRun('suppressed.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([14 => [CHAINED_ERROR]]);
});

/**
 * Pins the detection-only decision: fixing a flagged chain means authoring an
 * accessor method on the first model and choosing its default when the
 * relationship is absent, which cannot be synthesised from the tokens. No
 * violation is auto-fixable.
 */
it('reports detection-only errors', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getErrorCount())->toBe(count(CHAINED_FAILING_LINES))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * Deciding a chain means walking back over its whole receiver. Run once per hop
 * that walk costs O(n²) on a file of n hops, which is a CPU-exhaustion denial of
 * service and not merely slow: this package is a required `phpcs` check on
 * pull requests, including from forks, so the file is attacker-supplied.
 *
 * The fixtures are generated rather than committed for the same reason
 * ArrayAccessorsTest's linear-time test generates its own — the shapes only
 * separate a linear implementation from a quadratic one in the thousands.
 *
 * Two things changed to bound it, and they are not equally load-bearing. Said
 * plainly, because each was measured on its own by reverting it here:
 *
 * - Recording what the walk passed over, so a later walk reaching a token
 *   already decided stops there. This is what bounds the complexity, and it
 *   covers both shapes on its own.
 * - Asking the cheap "was this chain already reported" test *before* the walk
 *   rather than after, so an already-reported hop never starts one. This is a
 *   constant-factor gain, not a bound: it covers `plain` alone, and reverting it
 *   with the record in place costs 0.21s -> 0.51s at n=16,000 rather than
 *   failing.
 *
 * Which is why `interleaved` is here, and why it is the discriminating shape. It
 * breaks the chain into segments with a method call every third hop, so each
 * segment starts a chain of its own, gets past the already-reported test, and
 * walks the whole receiver again. With the record disabled it takes 63.3s at
 * n=16,000 against this budget while `plain` still passes in 0.48s — so a scale
 * test pinned to the plain shape alone would have gone green against a sniff
 * that is still quadratic on attacker-supplied input. `plain` is kept as the
 * reproduction of the originally reported shape (2.4s at n=2,000, 9.4s at
 * n=4,000, 36.8s at n=8,000 before either change — ~4x per doubling).
 *
 * Those readings are kept as provenance for what the record is worth; nothing
 * here is timed any more. The claim is counted rather than timed (#354,
 * extending #321). A wall-clock budget states an asymptotic bound only as far
 * as a shared CI runner allows — #321 recorded the same assertion shape failing
 * twice and passing on a third run with no code change — so the record is read
 * from DisallowChainedPropertyFetchSniff::walkSteps(): how many tokens
 * rootFrom()'s walk stepped over for this file. The increment is the first
 * statement of that walk's loop body, placed after the record's own
 * array_key_exists() guard has had its turn, so a step is counted exactly when
 * the record did not answer — which is the coupling that makes the number the
 * record's own and not the file's.
 *
 * The count is read straight rather than as a delta: it is cleared with the
 * record itself, in discardRootsOfOtherStreams(), so it already describes only
 * the file just processed. That coupling is pinned separately by
 * `it('keeps no record across files')` below.
 *
 * The two shapes are pinned at the exact counts they produce, which are what
 * make them different tests rather than one test run twice:
 *
 * - `plain` walks once and steps once. Its whole chain is reported from the
 *   first hop, and the cheap already-reported test then keeps every later hop
 *   from starting a walk at all — so this shape says nothing about the record
 *   and everything about that test, which is why it cannot stand alone.
 * - `interleaved` breaks the chain every third hop, so each of its 5,333
 *   segments gets past the already-reported test and asks for its own root.
 *   With the record answering, the file's tokens are stepped over about four
 *   times per segment and never re-walked: 21,329 steps for 16,000 hops.
 *   Without it, each segment re-walks its whole receiver and the count is
 *   quadratic — measured at 56,876,445 steps for the same file, three orders of
 *   magnitude more, which is the 62.1s above stated as a number. `plain` still
 *   passes that same reversion, which is the whole reason `interleaved` is
 *   here.
 *
 * No replacement bound is derived from the old 3.0s cap, because none is
 * needed: both counts are exact, deterministic consequences of the fixture the
 * test generates, so they are asserted as equalities rather than as a budget
 * with headroom.
 *
 * Mutation-checked by deleting the record's array_key_exists() answer from
 * rootFrom(); the diff hunk and the resulting failure are in this PR's
 * description. The error-count assertion is what stops the counts passing
 * vacuously: a walk that stopped resolving roots would step cheaply and report
 * nothing.
 */
it('decides a chain in time linear in its length', function (string $shape, int $size, int $steps): void {
    $source = "<?php\n\n\$a";

    for ($hop = 0; $hop < $size; $hop++) {
        $source .= $shape === 'interleaved' && $hop % 3 === 2 ? '->m()' : "->p{$hop}";
    }

    $path = stageGeneratedFixture("chained-{$shape}.php", $source . ";\n");

    $file = analyzeWithSniffs([CHAINED], $path);

    // Read straight, not as a delta: the counter is cleared with the record it
    // belongs to, so it already describes only the file just processed.
    expect($file->getErrorCount())->toBeGreaterThan(0, 'the chain is still reported')
        ->and(sniffInstance(CHAINED)->walkSteps())->toBe(
            $steps,
            "{$shape} at n={$size}: the record answers every re-entry into a receiver already walked"
        );
})->with([
    'n consecutive hops' => ['plain', 16000, 1],
    'n hops in call-separated segments' => ['interleaved', 16000, 21329],
]);

/**
 * The walk's record of where it has been holds pointers into one token stream,
 * and the sniff instance outlives any one file — tests/Helpers.php memoises the
 * ruleset, so every test above is answered by the same instance. A record kept
 * across files would answer the second file from the first file's pointers.
 *
 * Asserted by interleaving: the same instance processes failing.php, then
 * passing.php, then failing.php again, and each verdict has to match the one the
 * dedicated tests above establish. The passing run in the middle is what makes
 * it discriminating — it is the file whose pointers would be wrong, and a record
 * that survived into it reports chains against a file that has none.
 *
 * The walk-step count is asserted alongside the verdicts, and it is the half
 * that pins the *counter's* own reset rather than the record's. The scale test
 * above reads that counter straight, which is only sound while it is cleared
 * with the record; leaving it standing across files would let one file's count
 * be read as another's. Cleared, the third run steps exactly what the first did
 * — the same file, walked the same way. Left standing, it would read the first,
 * second and third runs added together, so this equality is what the reset is
 * pinned by. The middle run's own count is asserted non-zero for the same
 * reason the middle run exists: a file that contributed nothing could not
 * contaminate anything, and the equality would hold vacuously.
 */
it('keeps no record across files', function () use ($stagedRun): void {
    $first = $stagedRun('failing.php');
    $firstSteps = sniffInstance(CHAINED)->walkSteps();
    $between = $stagedRun('passing.php');
    $betweenSteps = sniffInstance(CHAINED)->walkSteps();
    $second = $stagedRun('failing.php');
    $secondSteps = sniffInstance(CHAINED)->walkSteps();

    expect(violationSourcesByLine($first->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]))
        ->and($between->getErrors())->toBe([])
        ->and(violationSourcesByLine($second->getErrors()))
        ->toBe(array_fill_keys(CHAINED_FAILING_LINES, [CHAINED_ERROR]))
        ->and($betweenSteps)->toBeGreaterThan(
            0,
            'the middle file walks, so a count left standing would carry into the third run'
        )
        ->and($secondSteps)->toBe(
            $firstSteps,
            'the walk-step count is cleared with the record, so the same file steps the same'
        );
});

/**
 * The same verdict through the shipped, installed package.
 *
 * Every test above drives PHPCS in process through ConfigDouble, which supplies
 * the registration Composer would have supplied — so a package that never
 * registered itself with the installed standards passes all of them. This one
 * executes the real vendor/bin/phpcs as a separate process from outside the
 * package, against CleanCode/ruleset.xml, the file a consumer points --standard at. The
 * shared sweep in tests/Contract/ShippedPackageSmokeTest.php cannot reach this
 * sniff: it drives each fixture where it lives, under tests/, and this sniff's
 * <exclude-pattern> makes that path report nothing whatever the sniff does.
 *
 * Staged exactly as $stagedRun stages it, and asserted in the same paired shape
 * as the scoped-out-of-test-paths test above rather than only on the positive
 * half:
 *
 * - the staged copy reports every line of CHAINED_FAILING_LINES, all under this
 *   sniff's own code and as errors, at status 1 — violations, none of them
 *   fixable, which is what this detection-only rule owes. Status 2 would mean
 *   phpcbf had been offered a fix, and 3 is what a broken install exits with.
 * - the in-repo copy of the same bytes reports nothing and exits 0, so the
 *   reporting half cannot be coming from a run that ignores CleanCode/ruleset.xml's
 *   exclusion.
 * - passing.php staged the same way reports nothing and exits 0 — the negative
 *   control, without which a shell-out that always reported would satisfy the
 *   first.
 */
it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('DisallowChainedPropertyFetchSniff', 'failing.php');

    $staged = installedSniffRun(CHAINED, stageFixtureOutsideTests($failing));
    $inRepo = installedSniffRun(CHAINED, $failing);
    $passing = installedSniffRun(
        CHAINED,
        stageFixtureOutsideTests(fixturePath('DisallowChainedPropertyFetchSniff', 'passing.php'))
    );

    expect(array_column($staged['messages'], 'line'))->toBe(CHAINED_FAILING_LINES)
        ->and(array_unique(array_column($staged['messages'], 'source')))->toBe([CHAINED_ERROR])
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['ERROR'])
        ->and($staged['status'])->toBe(1)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});

/**
 * The record of walked chain roots this sniff builds once per token stream must
 * not answer one analysis with another analysis's pointers (#343).
 *
 * The record used to be keyed by file name, token count and fixer-loop counter.
 * Two sources analysed as STDIN report the same name, so two of them that also
 * tokenise to the same count shared one key — and a single `Ruleset` reused
 * across several analyses, which is what buildRuleset()'s memoisation gives
 * every call below, hands them one sniff instance and one record.
 *
 * The two sources here tokenise to 15 tokens each — `self::` in A and `!!` in B
 * are two tokens either way, so the chain's receiver sits at the same pointer
 * in both — and differ in exactly what the record holds for that pointer: A's
 * chain hangs off a static property, which the standard leaves alone, so the
 * walk records "not variable-rooted" there, while B's hangs off a plain
 * variable. Under the old key B read A's verdict for that pointer, was ruled
 * not variable-rooted, and its violation was never reported.
 *
 * CleanCode/ruleset.xml scopes this sniff out of test paths, and that exclusion is decided
 * from the analysed file's own path; STDIN has none, so these three analyses
 * reach the sniff where a fixture under tests/ would not.
 *
 * The third call is what separates a working key from no cache at all: it
 * re-analyses A and requires its silence back, which a sniff that had simply
 * stopped caching would also give — but a sniff whose record leaked between
 * streams would not, since B's stream would by then have overwritten it.
 */
it('keeps its root record from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        $flag = self::$book->author->name;

        PHP;

    $sourceB = <<<'PHP'
        <?php

        $flag = !!$book->author->name;

        PHP;

    $first = analyzeStdinSource([CHAINED], $sourceA);
    $second = analyzeStdinSource([CHAINED], $sourceB);
    $third = analyzeStdinSource([CHAINED], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 3, 'column' => 26, 'source' => CHAINED_ERROR],
        ])
        ->and(violationMessagesByLine($second->getErrors()))->toBe([
            3 => [
                'Chained property fetch author->name; expose the value as an accessor '
                    . 'attribute on the first model instead (e.g. getAuthorNameAttribute() so '
                    . 'callers read $book->authorName rather than $book->author->name) '
                    . '(see docs/standards/models-relationship-properties.md)',
            ],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([]);
});

/**
 * The record of walked chain roots is kept for the whole of a token stream and
 * emptied only when the stream changes, rather than on every read (#343).
 *
 * The test above proves the key never answers one analysis with another's
 * pointers. It cannot prove the other half of what a key is for, and neither
 * can any other black-box test: a sniff that emptied the record on every single
 * read would report exactly the same violations, only slower — which is the
 * repeated backward walk the record exists to remove. Every analysis there also
 * constructs its own DummyFile, so all three get their own identity from
 * TokenStreams::key() and are emptied by design.
 *
 * Reuse is observable only from inside the sniff, so the sniff counts it, the
 * way UnusedFormalParameterSniff already counts its own indexes. Both numbers
 * are pinned, and each rules out a different failure:
 *
 * - one emptying per stream, at any size, is the claim itself;
 * - n-1 kept keeps it from passing vacuously, since a sniff that stopped
 *   consulting the record at all would report one emptying and nothing kept.
 *   The check is made in rootFrom(), which each fetch below reaches once: a
 *   fetch holds two object operators, and process() refuses the first before
 *   any walk, since nothing precedes it. So n fetches total n checks, of which
 *   one empties and n-1 leave the record standing. The emptying is one the
 *   guard never had to answer.
 *
 * Mutation-checked by deleting the `$this->rootsKey === $key` guard, so every
 * read empties: `composer test` then fails here at the smallest size, n=2,
 * reading 2 builds / 0 hits against the 1 / 1 asserted; n=4 reads 4 / 0 against
 * 1 / 3, and n=8 reads 8 / 0 against 1 / 7.
 */
it('keeps its walked-root record for the whole stream, not one read', function (): void {
    $sniff = sniffInstance(CHAINED);

    foreach ([2, 4, 8] as $size) {
        $fetches = '';

        for ($index = 0; $index < $size; $index++) {
            $fetches .= "        \$one{$index} = \$this->alpha{$index}->beta;\n";
        }

        $source = "<?php\n\nclass Consumer\n{\n    public function read(): void\n    {\n"
            . $fetches . "    }\n}\n";

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([CHAINED], $source);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe($size, "n={$size}: every fetch is still reported")
            ->and($counted['roots.builds'])->toBe(
                1,
                "n={$size}: the record is emptied once for the stream, not once per read"
            )
            ->and($counted['roots.hits'])->toBe(
                $size - 1,
                "n={$size}: every read after the first finds the record already standing"
            );
    }
});
