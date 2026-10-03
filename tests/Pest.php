<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

if (method_exists(pest(), 'tia') === true) {
    pest()->tia()->watch(['**/*' => 'tests']);
}

afterEach(function (): void {
    purgeStagedFixtures();
});
