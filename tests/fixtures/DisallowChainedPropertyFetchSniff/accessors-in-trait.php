<?php

declare(strict_types=1);

namespace App\Concerns\Verse;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait Attributes
{
    public function getWitnessVersionAbbreviationAttribute(): ?string
    {
        return $this->textualApparatusWitness
            ?->version
            ?->abbreviation;
    }

    protected function witnessVersionName(): Attribute
    {
        return Attribute::make(
                get: fn (): ?string => $this->textualApparatusWitness
                    ?->version
                    ?->name,
                set: function (string $value): array {
                    return ['name' => $this->textualApparatusWitness->version->name . $value];
                },
            );
    }
}
