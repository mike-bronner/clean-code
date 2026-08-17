<?php

namespace App\Actions;

// Three declarations written *inside* the entry point, each of which a scan
// that merely walked every T_FUNCTION between the class braces would count as a
// second entry point.
class PublishPost
{
    public function __invoke(Post $post): object
    {
        function normalisePostPath(string $path): string
        {
            return trim($path, '/');
        }

        $format = static function (Post $post): string {
            return $post->title;
        };

        return new class {
            public function make(): void
            {
            }

            public function reset(): void
            {
            }
        };
    }
}

// The same three shapes, this time in a class that really does have a second
// entry point — so the assertion is two-sided and a sniff that had fallen
// silent altogether fails too.
class ArchivePost
{
    public function __invoke(Post $post): object
    {
        function normaliseArchivePath(string $path): string
        {
            return trim($path, '/');
        }

        return new class {
            public function make(): void
            {
            }
        };
    }

    public function undo(Post $post): void
    {
    }
}
