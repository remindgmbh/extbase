<?php

declare(strict_types=1);

namespace Remind\Extbase\Context;

use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;

class PageAspect implements AspectInterface
{
    protected array $page;

    public function __construct(array $page)
    {
        $this->page = $page;
    }

    public function get(string $name)
    {
        switch ($name) {
            case 'uid':
                return $this->page['uid'];
            case 'l10n_parent':
                return $this->page['l10n_parent'];
        }
        throw new AspectPropertyNotFoundException(
            'Property "' . $name . '" not found in Aspect "' . __CLASS__ . '".',
            1678257079
        );
    }
}
