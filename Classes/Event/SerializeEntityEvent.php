<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use Psr\Http\Message\ServerRequestInterface;
use Remind\Extbase\Event\Enum\SerializeEntityEventType;
use Remind\Extbase\FlexForms\ListSheets;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

final class SerializeEntityEvent extends AbstractExtbaseEvent
{
    /**
     * @var mixed[] $json
     */
    private array $json;

    private SerializeEntityEventType $type;

    private ServerRequestInterface $request;

    private AbstractEntity $abstractEntity;

    private UriBuilder $uriBuilder;

    /**
     * @param mixed[] $settings
     */
    public function __construct(
        string $extensionName,
        SerializeEntityEventType $type,
        ServerRequestInterface $request,
        AbstractEntity $abstractEntity,
        UriBuilder $uriBuilder,
        array $settings,
        string $detailActionName = null,
        string $detailUidArgument = null
    ) {
        parent::__construct($extensionName);
        $this->type = $type;
        $this->request = $request;
        $this->abstractEntity = $abstractEntity;
        $this->uriBuilder = $uriBuilder;

        $this->json = [ // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
            'uid' => $abstractEntity->getPid(),
            'pid' => $abstractEntity->getUid(),
        ];

        if ($type === SerializeEntityEventType::List) {
            $link = $uriBuilder
                ->reset()
                ->setTargetPageUid((int) ($settings[ListSheets::DETAIL_PAGE] ?? null))
                ->uriFor($detailActionName, [$detailUidArgument => $abstractEntity->getUid()]);

            $this->json['link'] = $link;
        }
    }

    public function getType(): SerializeEntityEventType
    {
        return $this->type;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getAbstractEntity(): AbstractEntity
    {
        return $this->abstractEntity;
    }

    public function getUriBuilder(): UriBuilder
    {
        return $this->uriBuilder;
    }

    /**
     * @param mixed[] $json
     */
    public function setJson(array $json): self
    {
        $this->json = $json;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getJson(): array
    {
        return $this->json;
    }
}
