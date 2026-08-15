<?php

class UserController
{
    public function __construct(private UserRepository $users)
    {
    }

    public function index(): View
    {
        return view('users.index');
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        return redirect()->route('users.index');
    }

    public function show(User $user): View
    {
        return view('users.show');
    }

    public function edit(User $user): View
    {
        return view('users.edit');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        return redirect()->route('users.show', $user);
    }

    public function destroy(User $user): RedirectResponse
    {
        return redirect()->route('users.index');
    }

    protected function authorizeUser(User $user): void
    {
    }

    private function breadcrumbs(): array
    {
        return [];
    }
}

class PublishPostController
{
    public function __invoke(Post $post): RedirectResponse
    {
        return redirect()->route('posts.show', $post);
    }
}

class UserService
{
    public function activate(User $user): void
    {
    }
}

class ControllerFactory
{
    public function build(string $name): object
    {
        return new $name();
    }
}

interface DownloadController
{
    public function export(): Response;
}

trait ArchiveController
{
    public function flush(): void
    {
    }
}

class LegacyCaseController
{
    public function Index(): View
    {
        return view('legacy.index');
    }

    public function DESTROY(User $user): RedirectResponse
    {
        return redirect()->route('legacy.index');
    }
}
