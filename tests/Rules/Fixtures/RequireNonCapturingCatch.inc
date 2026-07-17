<?php

// Positive: non-capturing catch when the exception is not needed.
try {
    doRiskyThing();
} catch (\Throwable) {
    recover();
}

// Positive: captured and used inside the catch body.
try {
    doRiskyThing();
} catch (\Throwable $usedInBody) {
    report($usedInBody);
}

// Negative: captured but never referenced — must be flagged.
try {
    doRiskyThing();
} catch (\Throwable $neverUsed) {
    recover();
}

// Negative: unused multi-catch capture is flagged.
try {
    doRiskyThing();
} catch (\RuntimeException | \LogicException $neverUsedMulti) {
    recover();
}

// Positive: multi-catch capture used in the body.
try {
    doRiskyThing();
} catch (\RuntimeException | \LogicException $usedMulti) {
    report($usedMulti);
}

// Edge: nested try/catch — inner capture unused (flagged), outer used.
try {
    try {
        doRiskyThing();
    } catch (\Throwable $unusedInner) {
        recover();
    }
} catch (\Throwable $usedOuter) {
    report($usedOuter);
}

// Edge: try/catch inside a closure — unused capture is flagged.
$handler = function (): void {
    try {
        doRiskyThing();
    } catch (\Throwable $unusedInClosure) {
        recover();
    }
};

// Edge: used only via arrow-function auto-capture — not flagged.
try {
    doRiskyThing();
} catch (\Throwable $usedByArrowFn) {
    $describe = fn (): string => $usedByArrowFn->getMessage();
    report($describe());
}

// Edge: used only inside string interpolation — not flagged.
try {
    doRiskyThing();
} catch (\Throwable $usedInString) {
    report("failed: {$usedInString->getMessage()}");
}

// Edge: used after the try/catch in the same scope — not flagged.
function reportAfterwards(): void
{
    try {
        doRiskyThing();
    } catch (\Throwable $usedAfterTry) {
        recover();
    }

    report($usedAfterTry);
}

// Edge: used only in the finally block — not flagged.
try {
    doRiskyThing();
} catch (\Throwable $usedInFinally) {
    recover();
} finally {
    report($usedInFinally);
}

// Edge: captured into a closure via `use` and referenced there — not flagged.
try {
    doRiskyThing();
} catch (\Throwable $usedByClosure) {
    $describe = function () use ($usedByClosure): string {
        return $usedByClosure->getMessage();
    };
    report($describe());
}
