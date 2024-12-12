<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Aspect;

use TYPO3\CMS\Core\Routing\Aspect\MappableAspectInterface;

class DynamicValueMapper implements MappableAspectInterface {
    public function generate(string $value): ?string {
        return $value;
    }

    public function resolve(string $value): ?string {
        return $value;
    }
}
