<?php

declare(strict_types=1);

namespace App\Models {
    use App\Contracts\UserRepositoryInterface;
    use App\Support\Repositories\CachesQueries;

    class User extends EloquentRepository implements UserRepositoryInterface
    {
        use CachesQueries;

        public function repository(): string
        {
            return (new UserRepository())->name();
        }
    }

    class UserRepositoryFactory
    {
    }

    class UserRepositories
    {
    }
}

namespace App\Repository {
    class Ledger
    {
    }
}

namespace App\RepositoriesLegacy {
    class Ledger
    {
    }
}

namespace App\Support {
    class Formatter
    {
        public function present(): string
        {
            return namespace\Repositories\present();
        }
    }
}

namespace App\Repositories {
    $users = new class {
        public function all(): array
        {
            return [];
        }
    };
}

namespace {
    class Ledger
    {
    }
}
