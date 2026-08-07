<?php

// Positive: catching \Throwable is the form the standard mandates.
try {
    doRiskyThing();
} catch (\Throwable $caught) {
    report($caught);
}

// Positive: a non-capturing \Throwable catch is equally compliant — the rule
// is about the *type* referenced, not about capturing it.
try {
    doRiskyThing();
} catch (\Throwable) {
    recover();
}

// Positive: a specific exception type other than the general \Exception.
try {
    doRiskyThing();
} catch (\RuntimeException $caught) {
    report($caught);
}

// Positive: a multi-catch naming only specific types.
try {
    doRiskyThing();
} catch (\RuntimeException | \LogicException $caught) {
    report($caught);
}

// Positive: \Throwable beside a specific type in a multi-catch.
try {
    doRiskyThing();
} catch (\Throwable | \RuntimeException $caught) {
    report($caught);
}

// Positive: an application exception type is not the general \Exception.
try {
    doRiskyThing();
} catch (\App\Exceptions\PaymentFailed $caught) {
    report($caught);
}

// Positive: try/finally with a compliant catch in between.
try {
    doRiskyThing();
} catch (\Throwable $caught) {
    report($caught);
} finally {
    cleanUp();
}
