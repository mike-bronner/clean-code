<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute as Cast;
use Illuminate\Database\Eloquent\Model;

class Verse extends Model
{
    public function getWitnessVersionAbbreviationAttribute(): ?string
    {
        return $this->textualApparatusWitness
            ?->version
            ?->abbreviation;
    }

    protected function witnessVersionName(): Cast
    {
        return Cast::make(
            get: fn (): ?string => $this->textualApparatusWitness
                ?->version
                ?->name,
        );
    }

    protected function witnessVersionTitle(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return Cast::make(
            get: fn (): ?string => $this->textualApparatusWitness->version->title,
        );
    }
}
