<?php

// Positive: the capture is omitted where the exception is not needed.
try {
    doRiskyThing();
} catch (\Throwable) {
    recover();
}

// Positive: captured and used inside the catch body.
try {
    doRiskyThing();
} catch (\Throwable $caught) {
    report($caught);
}

// Positive: a multi-catch omitting the capture it does not use.
try {
    doRiskyThing();
} catch (\RuntimeException | \LogicException) {
    recover();
}

// Positive: a multi-catch whose capture is used.
try {
    doRiskyThing();
} catch (\RuntimeException | \LogicException $caught) {
    report($caught);
}

// Positive: the capture is used only to rethrow.
try {
    doRiskyThing();
} catch (\Throwable $caught) {
    throw new \RuntimeException('wrapped', 0, $caught);
}

// Positive: the capture is used in a nested closure rather than directly.
try {
    doRiskyThing();
} catch (\Throwable $caught) {
    defer(static function () use ($caught): void {
        report($caught);
    });
}

// Positive: non-capturing catch alongside finally.
try {
    doRiskyThing();
} catch (\Throwable) {
    recover();
} finally {
    cleanUp();
}
