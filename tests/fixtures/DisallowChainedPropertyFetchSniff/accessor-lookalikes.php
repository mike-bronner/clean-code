<?php

declare(strict_types=1);

namespace App\Concerns\Verse;

use App\Support\Attribute as LocalAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait Attributes
{
    public function witnessVersionAbbreviation(): ?string
    {
        return $this->textualApparatusWitness
            ?->version
            ?->abbreviation;
    }

    public function getAttribute($key)
    {
        return $this->witness->version;
    }

    public function getWitnessName(): string
    {
        return $this->witness->version->name;
    }

    public function setWitnessNameAttribute(string $value): void
    {
        $this->attributes['name'] = $this->witness->version->name . $value;
    }

    public function witnessTitle(): LocalAttribute
    {
        return new LocalAttribute($this->witness->version->title);
    }

    public function witnessCode(): \App\Support\Attribute
    {
        return new LocalAttribute($this->witness->version->code);
    }
}

class DrawerItem
{
    public function label(): ?string
    {
        return $this->textualApparatusWitness
            ?->version
            ?->abbreviation;
    }
}

class Verse
{
    public function getWitnessLabelAttribute(): object
    {
        return new class ($this) {
            public function label(): string
            {
                return $this->verse->witness->name;
            }
        };
    }

    public function getWitnessCodeAttribute(): string
    {
        function witnessCodeOf(object $verse): string
        {
            return $verse->witness->code;
        }

        return witnessCodeOf($this);
    }
}

function getWitnessNameAttribute(object $verse): string
{
    return $verse->witness->name;
}
