<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\NameTokens;

it('reads the last segment of every name spelling', function (): void {
    $nameTokens = new NameTokens();

    expect($nameTokens->lastSegment('Collection'))->toBe('Collection')
        ->and($nameTokens->lastSegment('Support\Collection'))->toBe('Collection')
        ->and($nameTokens->lastSegment('\Illuminate\Support\Collection'))->toBe('Collection')
        ->and($nameTokens->lastSegment('namespace\Collection'))->toBe('Collection');
});

it('drops only the namespace keyword of a relative name', function (): void {
    $nameTokens = new NameTokens();

    expect($nameTokens->withoutNamespaceKeyword(['code' => T_NAME_RELATIVE, 'content' => 'namespace\Sub\Name']))
        ->toBe('\Sub\Name')
        ->and($nameTokens->withoutNamespaceKeyword(['code' => T_NAME_FULLY_QUALIFIED, 'content' => '\Sub\Name']))
        ->toBe('\Sub\Name')
        ->and($nameTokens->withoutNamespaceKeyword(['code' => T_NAME_QUALIFIED, 'content' => 'namespace\Name']))
        ->toBe('namespace\Name')
        ->and($nameTokens->withoutNamespaceKeyword(['code' => T_STRING, 'content' => 'Name']))
        ->toBe('Name');
});

it('lists exactly the three qualified name tokens', function (): void {
    expect(NameTokens::QUALIFIED)->toBe([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE]);
});
