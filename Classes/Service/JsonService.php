<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use JsonSerializable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\Service\Dto\FilterableListResult;
use Remind\Extbase\Service\Dto\ListResult;
use Remind\Headless\Service\JsonService as HeadlessJsonService;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Web\RequestBuilder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

class JsonService
{
    private array $settings = [];

    public function __construct(
        private readonly UriBuilder $uriBuilder,
        private readonly LoggerInterface $logger,
        private readonly HeadlessJsonService $headlessJsonService,
        RequestBuilder $requestBuilder,
        ConfigurationManagerInterface $configurationManager
    ) {
        $extbaseRequest = $requestBuilder->build($this->getRequest());
        $this->uriBuilder->setRequest($extbaseRequest);
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
        );
    }

    public function serializeList(
        ListResult $listResult,
        int $page,
        string $detailActionName,
        string $detailUidArgument,
    ): array {
        $paginationJson = null;
        $pagination = $listResult->getPagination();
        if ($pagination) {
            $paginationJson = $this->headlessJsonService->serializePagination(
                $pagination,
                'page',
                $page,
            );
        }

        $items = iterator_to_array($listResult->getPaginatedItems());
        $itemsJson = $this->serializeListItems($items, $detailActionName, $detailUidArgument);

        return [
            'count' => $listResult->getCount(),
            'countWithoutLimit' => $listResult->getCountWithoutLimit(),
            'items' => $itemsJson,
            'pagination' => $paginationJson,
        ];
    }

    public function serializeFilterableList(
        FilterableListResult $listResult,
        int $page,
        string $detailActionName,
        string $detailUidArgument
    ): array {
        $result = $this->serializeList($listResult, $page, $detailActionName, $detailUidArgument);
        $filters = $listResult->getFrontendFilters();
        $filtersJson = $this->serializeFilters($filters);
        $result['filters'] = $filtersJson;
        return $result;
    }

    /**
     * @param \Remind\Extbase\Service\Dto\FrontendFilter[] $filters
     * @return array
     */
    private function serializeFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $filter) {
            $filterJson = [
                'name' => $filter->getFilterName(),
                'label' => $filter->getLabel(),
                'prefix' => $filter->getPrefix(),
                'suffix' => $filter->getSuffix(),
                'allValues' => [
                    'label' => $filter->getAllValues()->getLabel(),
                    'link' => $filter->getAllValues()->getLink(),
                ],
                'values' => [],
            ];

            foreach ($filter->getValues() as $filterValue) {
                $filterValueJson = [
                    'value' => $filterValue->getValue(),
                    'label' => $filterValue->getLabel(),
                    'link' => $filterValue->getLink(),
                    'count' => $filterValue->getCount(),
                    'active' => $filterValue->isActive(),
                ];

                $filterJson['values'][] = $filterValueJson;
            }

            $result[] = $filterJson;
        }
        return $result;
    }

    private function serializeListItems(array $items, string $detailActionName, string $detailUidArgument): array
    {
        return array_map(function (AbstractEntity $item) use ($detailActionName, $detailUidArgument) {
            if (!($item instanceof JsonSerializable)) {
                $this->logger->warning(
                    'Class "{class}" does not implement "{interface}" Interface.',
                    [
                        'class' => get_class($item),
                        'interface' => JsonSerializable::class,
                    ]
                );
            }
            $itemJson = json_decode(json_encode($item), true);
            $link = $this->uriBuilder
                ->reset()
                ->setTargetPageUid((int) ($this->settings[ListSheets::DETAIL_PAGE] ?? null))
                ->uriFor($detailActionName, [$detailUidArgument => $item->getUid()]);
            $itemJson['link'] = $link;
            return $itemJson;
        }, $items);
    }

    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
