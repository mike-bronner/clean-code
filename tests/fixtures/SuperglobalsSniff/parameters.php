<?php

/**
 * The parameter list, where a superglobal's name can mean two different things.
 *
 * PHPCS opens a method's own scope at its `{`, so a parameter is still inside
 * the class's brace scope and reaches the sniff carrying the class as its
 * innermost condition — the same condition a member declared in the class body
 * carries. Position alone cannot tell them apart, and the two mean opposite
 * things: a *promoted* parameter declares a property, while a *plain* one
 * declares nothing and is an ordinary local binding. Only property_visibility,
 * from getMethodParameters(), separates them.
 *
 * That is why this fixture exists rather than a line in passing.php or
 * failing.php: it carries both verdicts at once, so an exemption that keyed on
 * position alone would silently swallow the plain half while every other
 * fixture stayed green.
 *
 * Measured against PHPMD 2.15.0 rather than read off its documentation, and the
 * two tools do not agree line for line here, which is the second reason this is
 * a fixture of its own — failing.php's exact-parity claim stays untouched:
 *
 *   shape                             this sniff        PHPMD 2.15.0
 *   plain parameter, read in body     both lines        the method, once
 *   promoted parameter                silent            silent
 *   plain parameter, never read       the declaration   silent
 *
 * PHPMD's rule is method-level: it reports the enclosing method once, at the
 * method's own line, however many accesses the body holds, and it keys on reads
 * alone, so a parameter nothing reads is invisible to it. This sniff reports
 * each occurrence at its own position. The two therefore agree on the verdict
 * wherever the parameter is read, and diverge on a parameter that is not —
 * where this sniff is the stricter of the two, the posture it already takes for
 * the file-scope reads in divergences.php.
 *
 * Every name here is a PHP 4 long-form alias, because that is the only half of
 * the sniff's name list this shape exists in at all: PHP refuses to compile a
 * parameter named after one of the nine real superglobals, promoted or not
 * ("Cannot re-assign auto-global variable").
 */

class ParameterHandler
{
    /**
     * Promoted: the parameter list *is* the declaration, so this is the same
     * member declaration passing.php carries in its class-body spelling, and
     * neither tool reports it. Both the typed and the untyped spelling are
     * here, because promotion allows either.
     */
    public function __construct(
        public $HTTP_POST_VARS = [],
        protected array $HTTP_SERVER_VARS = []
    ) {
    }

    /**
     * Plain: declares no property. The name is an ordinary local, exactly as it
     * is in the global function below, so both the parameter and the read are
     * reported.
     */
    public function readsItsParameter($HTTP_GET_VARS): array
    {
        return $HTTP_GET_VARS;
    }

    /**
     * Plain and never read, which is the shape that proves the parameter is
     * reported on its own merits rather than only through a later read. PHPMD
     * is silent here; this sniff is not.
     */
    public function ignoresItsParameter($HTTP_ENV_VARS): int
    {
        return 1;
    }
}

/**
 * The same plain shape outside a class. It has no class condition to be
 * confused by, so it was always reported — and it is what the in-class case
 * above has to agree with, since a parameter means the same thing in both.
 */
function readsItsParameter($HTTP_COOKIE_VARS): array
{
    return $HTTP_COOKIE_VARS;
}
