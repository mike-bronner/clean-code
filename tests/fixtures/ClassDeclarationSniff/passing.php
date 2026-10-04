<?php

namespace App\Models;

class TextualApparatusEntry extends BaseModel {}

interface Searchable {}

trait Indexed {}

enum Testament: string {}

final class Chapter extends BaseModel implements Searchable {}

class Verse extends BaseModel
{
    use Indexed;
}
