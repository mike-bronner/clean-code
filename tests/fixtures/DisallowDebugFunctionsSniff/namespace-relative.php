<?php

// The `namespace\` relative qualifier resolves against the namespace in force
// where it is written, with no fallback to the global one. Inside a declared
// namespace it therefore names a different symbol, and the sniff stays silent —
// PHP itself reports `Acme\dump()` as undefined rather than calling PHP's own.
//
// The rule needs both halves to mean anything: in a file that declares no
// namespace the same spelling *is* PHP's function and is flagged (failing.php),
// so silence here has to come from the declaration rather than from the
// qualifier alone.

namespace Acme;

namespace\dump($value);
namespace\print_r($value);

// A leading separator qualifies the global namespace whatever namespace is in
// force, so this stays a call to PHP's own function and is still flagged. It
// keeps the fixture discriminating: the silence above is about the relative
// qualifier, not about the file having a namespace at all.
\dump($value);
