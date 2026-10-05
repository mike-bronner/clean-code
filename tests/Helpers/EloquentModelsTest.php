<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\EloquentModels;

it('counts a class as a model by its Models namespace segment or its Eloquent parent', function (): void {
    $file = parseFixture('EloquentModels', 'classes.php');
    $eloquentModels = new EloquentModels();
    $verdicts = [];

    foreach ($file->getTokens() as $pointer => $token) {
        if ($token['code'] === T_CLASS) {
            $verdicts[$file->getDeclarationName($pointer)] = $eloquentModels->isModel($file, $pointer);
        }
    }

    expect($verdicts)->toBe([
        'InModels' => true,
        'Imported' => true,
        'Aliased' => true,
        'Qualified' => true,
        'Lookalike' => false,
        'Plain' => false,
    ]);
});
