<?php

declare(strict_types=1);

namespace Remind\Extbase\PageTitle;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

class ExtbasePageTitleProvider extends AbstractPageTitleProvider
{
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
