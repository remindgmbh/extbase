<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend;

use Remind\Extbase\Domain\Repository\PageRepository;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ItemsProc
{
    public const PARAMETERS = 'itemsProcFuncParameters';
    public const PARAMETER_EXTENSION_NAME = 'extensionName';
    public const PARAMETER_TABLE_NAME = 'tableName';

    private ?PageRepository $pageRepository = null;

    public function __construct()
    {
        $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class);
    }

    public function getDetailSources(array &$params): void
    {
        $extensionName = $params['config'][self::PARAMETERS][self::PARAMETER_EXTENSION_NAME];
        $sources = PluginUtility::getDetailSources($extensionName);
        foreach ($sources as $pluginSignature => $config) {
            $params['items'][] = [$config['label'] ?? $pluginSignature, $pluginSignature];
        }
    }

    public function getRecordsInPages(array &$params): void
    {
        $tableName = $params['config'][self::PARAMETERS][self::PARAMETER_TABLE_NAME];
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $pages = $flexParentDatabaseRow['pages'];
        $pageIds = GeneralUtility::intExplode(',', $pages, true);
        $recursive = $flexParentDatabaseRow['recursive'];
        $pageIds = $this->pageRepository->getPageIdsRecursive($pageIds, $recursive);

        if (count($pageIds) > 0) {
            $pageIds = implode(',', $pageIds);

            $fieldList = BackendUtility::getCommonSelectFields($tableName, $tableName . '.');
            $fieldList = GeneralUtility::trimExplode(',', $fieldList, true);

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($tableName);

            $queryBuilder->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
                ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()->workspace));

            $queryBuilder
                ->select(...$fieldList)
                ->from($tableName)
                ->where('FIND_IN_SET(`' . $tableName . '`.`pid`, \'' . $pageIds . '\')');

            $queryResult = $queryBuilder->executeQuery();
            $rows = $queryResult->fetchAllAssociative();

            foreach ($rows as $row) {
                $title = BackendUtility::getRecordTitle($tableName, $row);
                $params['items'][] = [$title, $row['uid']];
            }
        }
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
