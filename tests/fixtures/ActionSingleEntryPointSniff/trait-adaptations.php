<?php

namespace App\Actions;

// A trait-adaptation block is the other brace that opens at a class's own top
// level. It declares no method of its own, and the walk has to step over it and
// carry on reading the class rather than stopping at it.
//
// One entry point, so silence.
class PublishPost
{
    use LogsEvents, TracksTime {
        LogsEvents::record insteadof TracksTime;
        TracksTime::record as recordDuration;
    }

    public function __invoke(Post $post): void
    {
    }
}

// An *empty* adaptation block, which carries no internal semicolon at all, and
// a genuine second entry point after it. `undo()` is reported, which is what
// proves the walk resumed at the block's closing brace instead of running past
// the rest of the class.
class ArchivePost
{
    use LogsEvents, TracksTime {}

    public function __invoke(Post $post): void
    {
    }

    public function undo(Post $post): void
    {
    }
}
