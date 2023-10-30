<?php

declare(strict_types=1);

namespace Remind\Extbase\Event\Listener;

use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteConfigurationLoaded
{
    public function __invoke(SiteConfigurationLoadedEvent $event)
    {
        $config = $event->getConfiguration();

        foreach ($config['routeEnhancers'] as &$routeEnhancer) {
            $limitToPages = $routeEnhancer['limitToPages'] ?? null;
            if (is_string($limitToPages)) {
                $routeEnhancer['limitToPages'] = GeneralUtility::trimExplode(',', $limitToPages, true);
            }
        }

        $event->setConfiguration($config);
    }
}
