<?php

namespace App\Models;

class Entry extends BaseModel {}

interface Searchable {}

trait Indexed {}

enum Testament: string {}

$anonymous = new class {};

class Tight{}

class Commented { /* note */ }

class Spaced { }

class OwnLine
{}

class Filled
{
    public function label(): string {}
}

function helper(): void {}
