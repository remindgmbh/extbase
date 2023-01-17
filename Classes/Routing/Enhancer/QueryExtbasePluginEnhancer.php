<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Enhancer;

use InvalidArgumentException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use TYPO3\CMS\Core\Routing\Aspect\LocaleModifier;
use TYPO3\CMS\Core\Routing\Aspect\MappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Routing\Enhancer\AbstractEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\InflatableEnhancerInterface;
use TYPO3\CMS\Core\Routing\Enhancer\ResultingInterface;
use TYPO3\CMS\Core\Routing\Enhancer\RoutingEnhancerInterface;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\RouteCollection;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;

class QueryExtbasePluginEnhancer extends AbstractEnhancer implements
    RoutingEnhancerInterface,
    ResultingInterface,
    InflatableEnhancerInterface
{
    protected string $namespace;

    protected string $controllerName;

    protected string $actionName;

    protected array $defaults;

    protected array $arguments;

    protected array $types;

    protected bool $matching = false;

    public function __construct(array $configuration)
    {
        $this->arguments = $configuration['_arguments'] ?? [];
        $this->defaults = $configuration['defaults'] ?? [];
        $this->types = $configuration['types'] ?? [0];

        if (!isset($configuration['limitToPages']) || empty($configuration['limitToPages'])) {
            throw new InvalidArgumentException(
                'QueryExtbase route enhancer required \'limitToPages\' configuration option to be set!',
                1663321859
            );
        }

        if (isset($configuration['extension'], $configuration['plugin'])) {
            $extensionName = $configuration['extension'];
            $pluginName = $configuration['plugin'];
            $extensionName = str_replace(' ', '', ucwords(str_replace('_', ' ', $extensionName)));
            $pluginSignature = strtolower($extensionName . '_' . $pluginName);
            $this->namespace = 'tx_' . $pluginSignature;
        } elseif (isset($configuration['namespace'])) {
            $this->namespace = $configuration['namespace'];
        } else {
            throw new InvalidArgumentException(
                'QueryExtbase route enhancer configuration is missing options ' .
                '\'extension\' and \'plugin\' or \'namespace\'!',
                1663320190
            );
        }
        if (isset($configuration['_controller'])) {
            [$this->controllerName, $this->actionName] = explode('::', $configuration['_controller']);
        } else {
            throw new InvalidArgumentException(
                'QueryExtbase route enhancer configuration is missing option \'_controller\'!',
                1663320227
            );
        }
    }

    public function enhanceForMatching(RouteCollection $collection): void
    {
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        $variant = $this->getVariant($defaultPageRoute);
        // $priority has to be > 0 because default route will be matched otherwise
        $collection->add('enhancer_' . $this->namespace . spl_object_hash($variant), $variant, 1);
        $this->matching = true;
    }

    public function enhanceForGeneration(RouteCollection $collection, array $originalParameters): void
    {
        if (!is_array($originalParameters[$this->namespace] ?? null)) {
            return;
        }

        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');

        $parameters = [];

        $variant = $this->getVariant($defaultPageRoute);

        foreach ($this->arguments as $mappedKey => $key) {
            $defaultValue = $this->defaults[$mappedKey] ?? null;
            try {
                $originalValue = ArrayUtility::getValueByPath($originalParameters[$this->namespace], $key);
                $aspect = $variant->getAspect($mappedKey);
                if ($originalValue && $aspect instanceof MappableAspectInterface) {
                    $value = is_array($originalValue) ? json_encode($originalValue) : $originalValue;
                    $value = $aspect->generate($value);
                    $value = is_array($originalValue) ? json_decode($value, true) : $value;
                }
            } catch (MissingArrayPathException $e) {
                $value = null;
            }
            if ($defaultValue !== $value) {
                $labelAspect = $variant->getAspect($mappedKey . 'Label');
                if ($labelAspect instanceof LocaleModifier) {
                    $mappedKey = $labelAspect->modify();
                }
                $parameters[$mappedKey] = $value;
            }
        }

        $deflatedParameters = $this->deflateParameters($variant, $parameters);
        $variant->addOptions(['deflatedParameters' => $deflatedParameters]);

        $collection->add('enhancer_' . $this->namespace . spl_object_hash($variant), $variant);
        $this->matching = false;
    }

    public function buildResult(Route $route, array $results, array $remainingQueryParameters = []): PageArguments
    {
        $page = $route->getOption('_page');
        $pageId = (int)(isset($page['t3ver_oid']) && $page['t3ver_oid'] > 0 ? $page['t3ver_oid'] : $page['uid']);
        $pageId = (int)($page['l10n_parent'] > 0 ? $page['l10n_parent'] : $pageId);
        $type = $this->resolveType($route, $remainingQueryParameters);

        if (!in_array($type, $this->types)) {
            return new PageArguments($pageId, $type, $remainingQueryParameters);
        }

        $staticArguments = [
            $this->namespace => [
                'action' => $this->actionName,
                'controller' => $this->controllerName,
            ],
        ];

        $arguments = array_merge([], $staticArguments);

        foreach ($this->arguments as $mappedKey => $key) {
            $static = false;
            $defaultValue = $this->defaults[$mappedKey] ?? null;
            try {
                $labelAspect = $route->getAspect($mappedKey . 'Label');
                $label = null;
                if ($labelAspect instanceof LocaleModifier) {
                    $label = $labelAspect->modify();
                }

                $originalValue = $remainingQueryParameters[$label ?? $mappedKey] ?? null;
                $value = null;
                $aspect = $route->getAspect($mappedKey);
                if ($originalValue && $aspect instanceof MappableAspectInterface) {
                    $value = is_array($originalValue) ? json_encode($originalValue) : $originalValue;
                    $value = $aspect->resolve($value);
                    $value = (is_array($originalValue) && $value) ? json_decode($value, true) : $value;
                    if ($aspect instanceof StaticMappableAspectInterface) {
                        $static = true;
                    }
                    if (!$value) {
                        throw new ResourceNotFoundException(
                            sprintf('No routes found for "%s".', $route->getPath()),
                            1663233000
                        );
                    }
                }
            } catch (MissingArrayPathException $e) {
                // Do nothing and use null for $value
            }
            if ($defaultValue !== $value) {
                if ($static) {
                    $staticArguments[$this->namespace] = ArrayUtility::setValueByPath(
                        $staticArguments[$this->namespace],
                        $key,
                        $value
                    );
                }
                $arguments[$this->namespace] = ArrayUtility::setValueByPath($arguments[$this->namespace], $key, $value);
            }
        }

        $result = new PageArguments($pageId, $type, $arguments, $this->matching ? $staticArguments : $arguments);
        return $result;
    }

    public function inflateParameters(array $parameters, array $internals = []): array
    {
        return $parameters;
    }

    protected function deflateParameters(Route $route, array $parameters): array
    {
        $flattenParameters = $this->flattenArray($parameters);
        return $this->getVariableProcessor()->deflateNamespaceParameters(
            $flattenParameters,
            $this->namespace,
            $route->getArguments()
        );
    }

    private function flattenArray(array $array, array &$result = [], ?string $currentKey = null): array
    {
        foreach ($array as $key => $value) {
            $key = $currentKey ? $currentKey . '[' . $key . ']' : $key;
            if (is_array($value)) {
                $this->flattenArray($value, $result, $key);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function getVariant(Route $defaultPageRoute): Route
    {
        $variant = clone $defaultPageRoute;
        $variant->setOption('_enhancer', $this);
        $variant->setAspects($this->aspects);
        return $variant;
    }
}
