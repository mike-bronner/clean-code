<?php

/**
 * The three test phase markers, written exactly as the Laravel and Pest skills
 * shipped by mike-bronner/laravel-development-settings write them, followed by
 * every other spelling, which is still a section label. Inside a test file only
 * the three markers are silent. Outside one, every comment here reports.
 */

declare(strict_types=1);

namespace Section\PhaseMarkers;

it('marks each phase', function (): void {
    // 🧪 Arrange
    $payload = ['Key' => 'value'];

    // 🧪 Act
    $result = array_change_key_case($payload);

    // 🧪 Assert
    expect($result)->toBe(['key' => 'value']);
});

it('reports a combination of phases', function (): void {
    // 🧪 Arrange & Act
    $result = array_change_key_case(['Key' => 'value']);

    // 🧪 Act & Assert
    expect(array_keys($result))->toBe(['key']);
});

it('reports a marker with anything after it', function (): void {
    // 🧪 Assert — document what currently happens, not what should happen
    $result = array_change_key_case(['Key' => 'value']);

    // 🧪 Assert: the keys are lower-cased.
    $result = array_change_key_case($result);

    // 🧪 Act - normalise once more
    $result = array_change_key_case($result);

    // 🧪 Assert below.
    expect($result)->toBe(['key' => 'value']);
});

it('reports a marker spelled any other way', function (): void {
    // Arrange
    $payload = ['Key' => 'value'];

    // ✅ Act
    $result = array_change_key_case($payload);

    // 🧪 assert
    $result = array_change_key_case($result);

    //🧪 Arrange
    $result = array_change_key_case($result);

    // 🧪  Act
    $result = array_change_key_case($result);

    # 🧪 Act
    $result = array_change_key_case($result);

    /* 🧪 Assert */
    expect($result)->toBe(['key' => 'value']);
});

it('reports a label that only starts like a phase', function (): void {
    // 🧪 Arrange the payload.
    $payload = ['Key' => 'value'];

    // 🧪 Actions
    $result = array_change_key_case($payload);

    // 🧪 Assertions
    expect($result)->toBe(['key' => 'value']);
});

it('reports a label written under a marker', function (): void {
    // 🧪 Setup
    $payload = ['Key' => 'value'];

    // 🧪 Act
    // Normalise the keys.
    $result = array_change_key_case($payload);

    // 🧪 Assert
    expect($result)->toBe(['key' => 'value']);
});

it('reports a marker with anything around it', function (): void {
    // 🧪 Act 
    $payload = ['Key' => 'value'];

    // 🧪 Act	
    $result = array_change_key_case($payload);

    # // 🧪 Act
    expect($result)->toBe(['key' => 'value']);
});
