<?php

namespace App\Models;

class Verse extends BaseModel { use Indexed; 
}

class Annotated extends BaseModel { /* kept for the importer */ 
}

interface Searchable { public function search(): array; 
}

class Chapter extends BaseModel
{
    public function label(): string
    {
        return 'chapter'; 
    }
}

class Book extends BaseModel
{
    public function label(): string
    {
        return 'book';
    }
}
