<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Note as NoteModel;
use App\Models\Tag;
use App\Models\User;

class Note
{
    public function update(User $user, NoteModel $note): bool
    {
        return $user->id === $note->user_id;
    }

    public function save(User $user): bool
    {
        return true;
    }

    public function delete(User $user): bool
    {
        return true;
    }

    public function attachTag(User $user, NoteModel $note, Tag $tag): bool
    {
        return true;
    }

    function detachTag(User $user): bool
    {
        return true;
    }

    protected function setOwner(User $user): bool
    {
        return true;
    }

    private function sendNotice(User $user): bool
    {
        return true;
    }
}

namespace App\Domain\Policies\Billing;

class Invoice
{
    public function update(User $user): bool
    {
        return true;
    }
}

namespace App\Authorization;

class TeamPolicy
{
    public function update(User $user): bool
    {
        return true;
    }

    public function save(User $user): bool
    {
        return true;
    }

    public function delete(User $user): bool
    {
        return true;
    }

    public function attachTag(User $user): bool
    {
        return true;
    }

    protected function addMember(User $user): bool
    {
        return true;
    }

    private function removeMember(User $user): bool
    {
        return true;
    }
}

class PolicyBook
{
    public function update(User $user): bool
    {
        return true;
    }
}

namespace App\PoliciesLegacy;

class Note
{
    public function update(User $user): bool
    {
        return true;
    }
}

namespace App\Policy;

class Team
{
    public function delete(User $user): bool
    {
        return true;
    }
}

namespace App\Services;

class Team
{
    public function update(User $user): bool
    {
        return true;
    }

    public function attachTag(User $user): bool
    {
        return true;
    }
}
