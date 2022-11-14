<?php

namespace Remind\Extbase\Domain\Repository;

use PDO;
use Throwable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository as BasePageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * Taken from TYPO3 v12, used to get recursive page IDs in \Remind\Extbase\Backend\ItemsProc
 */
class PageRepository extends BasePageRepository
{
    public function getPageIdsRecursive(array $pageIds, int $depth): array
    {
        if ($pageIds === []) {
            return [];
        }
        $pageIds = array_map('intval', $pageIds);
        if ($depth === 0) {
            return $pageIds;
        }
        $allPageIds = [];
        foreach ($pageIds as $pageId) {
            $allPageIds = array_merge($allPageIds, [$pageId], $this->getDescendantPageIdsRecursive($pageId, $depth));
        }
        return array_unique($allPageIds);
    }

    public function getDescendantPageIdsRecursive(
        int $startPageId,
        int $depth,
        int $begin = 0,
        array $excludePageIds = [],
        bool $bypassEnableFieldsCheck = false
    ): array {
        if (!$startPageId) {
            return [];
        }

        // Check the cache
        $parameters = [
            $startPageId,
            $depth,
            $begin,
            $excludePageIds,
            $bypassEnableFieldsCheck,
            $this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]),
        ];
        $cacheIdentifier = md5(serialize($parameters));
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('cache_treelist');
        $cacheEntry = $queryBuilder->select('treelist')
            ->from('cache_treelist')
            ->where(
                $queryBuilder->expr()->eq(
                    'md5hash',
                    $queryBuilder->createNamedParameter($cacheIdentifier)
                ),
                $queryBuilder->expr()->gt(
                    'expires',
                    $queryBuilder->createNamedParameter($GLOBALS['EXEC_TIME'], PDO::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        // Cache hit
        if (!empty($cacheEntry)) {
            return GeneralUtility::intExplode(',', $cacheEntry);
        }

        // Check if the page actually exists
        if (!$this->getRawRecord('pages', $startPageId, 'uid')) {
            // Return blank if the start page was NOT found at all!
            return [];
        }
        // Find mount point if any
        $mount_info = $this->getMountPointInfo($startPageId);
        $includePageId = false;
        if (is_array($mount_info)) {
            $startPageId = (int)$mount_info['mount_pid'];
            // In Overlay mode, use the mounted page uid as added ID!
            if ($mount_info['overlay']) {
                $includePageId = true;
            }
        }

        $descendantPageIds = $this->getSubpagesRecursive(
            $startPageId,
            $depth,
            $begin,
            $excludePageIds,
            $bypassEnableFieldsCheck
        );
        if ($includePageId) {
            $descendantPageIds = array_merge([$startPageId], $descendantPageIds);
        }
        // Only add to cache if not logged into TYPO3 Backend
        if (!$this->context->getPropertyFromAspect('backend.user', 'isLoggedIn', false)) {
            $cacheEntry = [
                'md5hash' => $cacheIdentifier,
                'pid' => $startPageId,
                'treelist' => implode(',', $descendantPageIds),
                'tstamp' => $GLOBALS['EXEC_TIME'],
            ];
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('cache_treelist');
            try {
                $connection->transactional(static function ($connection) use ($cacheEntry) {
                    $connection->insert('cache_treelist', $cacheEntry);
                });
            } catch (Throwable $e) {
            }
        }
        return $descendantPageIds;
    }

        /**
     * This is an internal (recursive) method which returns the Page IDs for a given $pageId.
     * and also checks for permissions of the pages AND resolves mountpoints.
     *
     * @param int $pageId must be a valid page record (this is not checked)
     * @param int $depth
     * @param int $begin
     * @param array $excludePageIds
     * @param bool $bypassEnableFieldsCheck
     * @param array $prevId_array
     * @return int[]
     */
    protected function getSubpagesRecursive(
        int $pageId,
        int $depth,
        int $begin,
        array $excludePageIds,
        bool $bypassEnableFieldsCheck,
        array $prevId_array = []
    ): array {
        $descendantPageIds = [];
        // if $depth is 0, then we do not fetch subpages
        if ($depth === 0) {
            return [];
        }
        // Add this ID to the array of IDs
        if ($begin <= 0) {
            $prevId_array[] = $pageId;
        }
        // Select subpages
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->versioningWorkspaceId));
        $queryBuilder->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, PDO::PARAM_INT)
                ),
                // tree is only built by language=0 pages
                $queryBuilder->expr()->eq('sys_language_uid', 0)
            )
            ->orderBy('sorting');

        if ($excludePageIds !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->notIn(
                    'uid',
                    $queryBuilder->createNamedParameter($excludePageIds, Connection::PARAM_INT_ARRAY)
                )
            );
        }

        $result = $queryBuilder->executeQuery();
        while ($row = $result->fetchAssociative()) {
            $versionState = VersionState::cast($row['t3ver_state']);
            $this->versionOL('pages', $row, false, $bypassEnableFieldsCheck);
            if (
                $row === false
                || (int)$row['doktype'] === self::DOKTYPE_RECYCLER
                || (int)$row['doktype'] === self::DOKTYPE_BE_USER_SECTION
                || $versionState->indicatesPlaceholder()
            ) {
                // falsy row means Overlay prevents access to this page.
                // Doing this after the overlay to make sure changes
                // in the overlay are respected.
                // However, we do not process pages below of and
                // including of type recycler and BE user section
                continue;
            }
            // Find mount point if any:
            $next_id = (int)$row['uid'];
            $mount_info = $this->getMountPointInfo($next_id, $row);
            // Overlay mode:
            if (is_array($mount_info) && $mount_info['overlay']) {
                $next_id = (int)$mount_info['mount_pid'];
                // @todo: check if we could use $mount_info[mount_pid_rec] and check against $excludePageIds?
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('pages');
                $queryBuilder->getRestrictions()
                    ->removeAll()
                    ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
                    ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->versioningWorkspaceId));
                $queryBuilder->select('*')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'uid',
                            $queryBuilder->createNamedParameter($next_id, PDO::PARAM_INT)
                        )
                    )
                    ->orderBy('sorting')
                    ->setMaxResults(1);

                if ($excludePageIds !== []) {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->notIn(
                            'uid',
                            $queryBuilder->createNamedParameter($excludePageIds, Connection::PARAM_INT_ARRAY)
                        )
                    );
                }

                $row = $queryBuilder->executeQuery()->fetchAssociative();
                $this->versionOL('pages', $row);
                $versionState = VersionState::cast($row['t3ver_state']);
                if (
                    $row === false
                    || (int)$row['doktype'] === self::DOKTYPE_RECYCLER
                    || (int)$row['doktype'] === self::DOKTYPE_BE_USER_SECTION
                    || $versionState->indicatesPlaceholder()
                ) {
                    // Doing this after the overlay to make sure
                    // changes in the overlay are respected.
                    // see above
                    continue;
                }
            }
            // Add record:
            // Add ID to list:
            if ($begin <= 0) {
                $descendantPageIds[] = $next_id;
            }
            // Next level
            if (!$row['php_tree_stop']) {
                // Normal mode:
                if (is_array($mount_info) && !$mount_info['overlay']) {
                    $next_id = (int)$mount_info['mount_pid'];
                }
                // Call recursively, if the id is not in prevID_array:
                if (!in_array($next_id, $prevId_array, true)) {
                    $descendantPageIds = array_merge(
                        $descendantPageIds,
                        $this->getSubpagesRecursive(
                            $next_id,
                            $depth - 1,
                            $begin - 1,
                            $excludePageIds,
                            $bypassEnableFieldsCheck,
                            $prevId_array
                        )
                    );
                }
            }
            // }
        }
        return $descendantPageIds;
    }
}
