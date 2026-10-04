<?php

namespace App\Models;

class TextualApparatusEntry extends BaseModel {}

interface Searchable {}

trait Indexed {}

enum Testament: string {}

$entry = new class extends BaseModel {};

class Verse extends BaseModel
{
    public function label(): string
    {
        return 'verse';
    }
}
