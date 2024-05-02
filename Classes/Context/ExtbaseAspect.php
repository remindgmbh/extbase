<?php

declare(strict_types=1);

namespace Remind\Extbase\Context;

use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;

class ExtbaseAspect implements AspectInterface
{
    protected array $page;

    protected string $cType;

    public function __construct(array $page, string $cType)
    {
        $this->page = $page;
        $this->cType = $cType;
    }

    public function get(string $name)
    {
        switch ($name) {
            case 'CType':
                return $this->cType;
            case 'page.uid':
                return $this->page['uid'];
            case 'page.l10n_parent':
                return $this->page['l10n_parent'];
        }
        throw new AspectPropertyNotFoundException(
            'Property "' . $name . '" not found in Aspect "' . __CLASS__ . '".',
            1678257079
        );
    }
}
