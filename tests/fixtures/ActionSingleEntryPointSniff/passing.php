<?php

namespace App\Actions {
    // In scope through the namespace segment. One public entry point besides
    // the constructor, which is never counted.
    class ArchivePost
    {
        public function __construct(private PostRepository $posts)
        {
        }

        public function handle(Post $post): void
        {
        }
    }

    // Zero public methods. There is no entry point to duplicate, so there is
    // nothing to report — the rule is about a *second* way in, not about a
    // missing first one.
    class Reindex
    {
        protected function collect(): array
        {
            return [];
        }

        private function flush(): void
        {
        }
    }

    // No methods at all.
    class Placeholder
    {
    }

    // The single entry point may be static, and it may be named anything at
    // all. `__invoke` and `handle` are the doc's convention, not this sniff's
    // rule.
    class Dispatch
    {
        public static function fire(string $event): void
        {
        }
    }

    // Non-public siblings never count towards the entry points, however many
    // of them there are.
    class NotifySubscribers
    {
        public function __invoke(Post $post): void
        {
        }

        protected function recipients(Post $post): array
        {
            return [];
        }

        private function message(Post $post): string
        {
            return '';
        }

        private static function channel(): string
        {
            return 'mail';
        }
    }
}

namespace App\Actions\Billing {
    // A namespace *below* App\Actions is still in scope: the rule is that an
    // `Actions` segment is present, not that it is the last one.
    class ChargeCard
    {
        public function __invoke(Invoice $invoice): void
        {
        }
    }
}

namespace app\actions {
    // The namespace half is an exact segment match and is compared
    // case-insensitively, so a project spelling its directory in lower case is
    // covered too.
    class Refund
    {
        public function handle(Payment $payment): void
        {
        }
    }
}

namespace App\Support {
    // In scope through the class-name suffix, outside any Actions namespace.
    class PublishPostAction
    {
        public function __construct(private Clock $clock)
        {
        }

        public function __invoke(Post $post): Post
        {
            return $post;
        }
    }

    // The near misses a case-insensitive suffix match would swallow. Each ends
    // in "action" but not in "Action", each declares several public methods,
    // and none is an Action class.
    class Transaction
    {
        public function begin(): void
        {
        }

        public function commit(): void
        {
        }

        public function rollback(): void
        {
        }
    }

    class Reaction
    {
        public function add(string $emoji): void
        {
        }

        public function remove(string $emoji): void
        {
        }
    }

    class Interaction
    {
        public function open(): void
        {
        }

        public function close(): void
        {
        }
    }

    // "Action" appears in the name without ending it, so the class is out of
    // scope. A substring match would report `resolve()` here.
    class ActionFactory
    {
        public function register(string $name): void
        {
        }

        public function resolve(string $name): object
        {
            return new $name();
        }
    }

    // Nothing about this class matches either half of the convention, however
    // many public methods it declares.
    class ReportBuilder
    {
        public function withColumns(array $columns): self
        {
            return $this;
        }

        public function withRows(array $rows): self
        {
            return $this;
        }

        public function build(): string
        {
            return '';
        }
    }

    // Only T_CLASS is registered. All three of these carry the `Action` suffix
    // and declare two public methods each; none is examined.
    interface ExportAction
    {
        public function __invoke(): void;

        public function describe(): string;
    }

    trait LogsAction
    {
        public function __invoke(): void
        {
        }

        public function log(string $line): void
        {
        }
    }

    enum StatusAction: string
    {
        case Draft = 'draft';

        case Live = 'live';

        public function __invoke(): string
        {
            return $this->value;
        }

        public function label(): string
        {
            return ucfirst($this->value);
        }
    }
}

namespace App\ActionsArchive {
    // The segment is `ActionsArchive`, not `Actions`. An exact segment match
    // leaves this class alone; a substring match over the namespace would
    // report `restore()`.
    class Restore
    {
        public function __invoke(): void
        {
        }

        public function restore(): void
        {
        }
    }
}
