<?php

namespace App\Actions {
    // In scope through the namespace segment. `handle()` is the entry point;
    // `undo()` is a second one.
    class PublishPost
    {
        public function __construct(private Clock $clock)
        {
        }

        public function handle(Post $post): void
        {
        }

        public function undo(Post $post): void
        {
        }
    }
}

namespace App\Support {
    // In scope through the class-name suffix. Every public method past the
    // first is reported, whatever shape it takes: an ordinary method, a static
    // one, one with no visibility modifier at all (PHP defaults that to
    // public), and an abstract declaration with no body.
    abstract class ImportUsersAction
    {
        public function __invoke(string $path): void
        {
        }

        public function fromArray(array $rows): void
        {
        }

        public static function fromCsv(string $path): void
        {
        }

        function fromJson(string $json): void
        {
        }

        abstract public function fromStream($stream): void;

        protected function normalise(array $row): array
        {
            return $row;
        }

        private function validate(array $row): bool
        {
            return true;
        }
    }

    // The accessor case the standard's severity exists for. `getResult()` is a
    // public method past the entry point, so it is reported; the sniff makes no
    // exception for an accessor handing back what the entry point computed.
    class SummariseReportAction
    {
        private array $result = [];

        public function __invoke(Report $report): void
        {
            $this->result = [];
        }

        public function getResult(): array
        {
            return $this->result;
        }
    }

    // The constructor is not the entry point, so the first *reported* method
    // here is the second public one — proving `__construct` is skipped rather
    // than merely counted and forgiven.
    class RenameAction
    {
        public function __construct(private Filesystem $files)
        {
        }

        public function __invoke(string $from, string $to): void
        {
        }

        public function preview(string $from, string $to): string
        {
            return $to;
        }
    }
}
