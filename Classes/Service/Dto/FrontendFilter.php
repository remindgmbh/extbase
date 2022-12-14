<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class FrontendFilter
{
    private string $filterName = '';

    private string $label = '';

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(string $filterName, string $label, string $jsonValues)
    {
        $this->label = $label;
        $this->filterName = $filterName;
        $filterValues = json_decode($jsonValues, true);
        $fieldNames = GeneralUtility::trimExplode(',', $filterName);
        foreach ($filterValues as $filterValue) {
            $filterValueLabel = $filterValue['label'];
            $filterValueValue = count($fieldNames) > 1 ?
                json_decode(htmlspecialchars_decode($filterValue['value']), true) :
                $filterValue['value'];
            $this->addValue(new FilterValue($filterValueValue, $filterValueLabel));
        }
    }

    public function getFilterName(): string
    {
        return $this->filterName;
    }

    public function setFilterName(string $filterName): self
    {
        $this->filterName = $filterName;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return FilterValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function addValue(FilterValue $value): self
    {
        $this->values[] = $value;

        return $this;
    }
}
