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
    /**
     * @var mixed[]
     */
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

    /**
     * @return mixed[]
     */
    public function serializeList(
        ListResult $listResult,
        int $page,
        string $detailActionName,
        string $detailUidArgument,
    ): array {
        $serializedPagination = null;
        $pagination = $listResult->getPagination();
        if ($pagination) {
            $serializedPagination = $this->headlessJsonService->serializePagination(
                $pagination,
                'page',
                $page,
            );
        }

        $items = iterator_to_array($listResult->getPaginatedItems() ?? []);
        $serializedItems = $this->serializeListItems($items, $detailActionName, $detailUidArgument);

        return [
            'count' => $listResult->getCount(),
            'countWithoutLimit' => $listResult->getCountWithoutLimit(),
            'items' => $serializedItems,
            'pagination' => $serializedPagination,
            'properties' => $listResult->getProperties(),
        ];
    }

    /**
     * @return mixed[]
     */
    public function serializeFilterableList(
        FilterableListResult $listResult,
        int $page,
        string $detailActionName,
        string $detailUidArgument
    ): array {
        $result = $this->serializeList($listResult, $page, $detailActionName, $detailUidArgument);
        $result['filters'] = $listResult->getFrontendFilters();
        $result['resetFilters'] = $listResult->getResetFilters();
        return $result;
    }

    /**
     * @param AbstractEntity[] $items
     * @return mixed[]
     */
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
            $serializedItem = json_decode(json_encode($item) ?: '', true) ?? [];
            $link = $this->uriBuilder
                ->reset()
                ->setTargetPageUid((int) ($this->settings[ListSheets::DETAIL_PAGE] ?? null))
                ->uriFor($detailActionName, [$detailUidArgument => $item->getUid()]);
            $serializedItem['link'] = $link;
            return $serializedItem;
        }, $items);
    }

    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
