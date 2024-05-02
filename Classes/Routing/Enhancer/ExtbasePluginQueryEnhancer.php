<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Enhancer;

use InvalidArgumentException;
use Remind\Extbase\Context\ExtbaseAspect;
use Remind\Extbase\Service\DatabaseService;
use TYPO3\CMS\Core\Context\ContextAwareInterface;
use TYPO3\CMS\Core\Routing\Aspect\MappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\ModifiableAspectInterface;
use TYPO3\CMS\Core\Routing\Enhancer\AbstractEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\ResultingInterface;
use TYPO3\CMS\Core\Routing\Enhancer\RoutingEnhancerInterface;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\RouteCollection;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExtbasePluginQueryEnhancer extends AbstractEnhancer implements RoutingEnhancerInterface, ResultingInterface
{
    protected string $namespace;

    protected string $controllerName;

    protected string $actionName;

    protected array $defaults;

    protected array $types;

    protected array $parameters;

    protected DatabaseService $databaseService;

    protected string $cType;

    public function __construct(array $configuration)
    {
        $this->defaults = $configuration['defaults'] ?? [];
        $this->types = $configuration['types'] ?? [0];
        $this->parameters = $configuration['parameters'] ?? [];
        $this->databaseService = GeneralUtility::makeInstance(DatabaseService::class);
        $this->cType = strtolower($configuration['extension'] . '_' . $configuration['plugin']);

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

        if (!$this->cTypeExistsOnPage($defaultPageRoute)) {
            return;
        }

        $parameters = $GLOBALS['_GET'];

        $originalKeys = [];

        foreach ($this->parameters['keys'] ?? [] as $originalKey => $key) {
            $keyAspect = $this->aspects[$key] ?? null;
            $modifiedKey = $keyAspect instanceof ModifiableAspectInterface ? $keyAspect->modify() : $key;
            $originalKeys[$modifiedKey] = $originalKey;
        }

        $availableKeys = array_map(function (string $key) use ($originalKeys) {
            $originalKey = array_search($key, $originalKeys);
            return $originalKey ? $originalKey : $key;
        }, array_keys($this->parameters['values']));

        $usedKeys = array_keys($parameters);

        if (count(array_diff($availableKeys, $usedKeys)) === count($availableKeys)) {
            return;
        }

        $defaultPageRoute->setOption('_enhancer', $this);

        $deflatedParameters = [];

        foreach ($parameters as $key => $value) {
            $key = $originalKeys[$key] ?? $key;
            $valueAspectName = $this->parameters['values'][$key] ?? null;
            $aspect = $this->getAspect($valueAspectName, $defaultPageRoute);
            $resolvedValue = $aspect?->resolve(is_string($value) ? $value : json_encode($value));
            if (!$resolvedValue) {
                throw new RouteNotFoundException(
                    sprintf(
                        'No aspect found for parameter \'%s\' with value \'%s\' or resolved to null',
                        $key,
                        is_string($value) ? $value : json_encode($value),
                    ),
                    1678258126
                );
            }
            $deflatedParameters[$key] = is_string($value) ? $resolvedValue : json_decode($resolvedValue, true);
        }

        $defaultPageRoute->setOption('deflatedParameters', $deflatedParameters);

        // $priority has to be > 0 because default route will be matched otherwise
        $collection->add('enhancer_' . $this->namespace . spl_object_hash($defaultPageRoute), $defaultPageRoute, 1);
    }

    public function enhanceForGeneration(RouteCollection $collection, array $parameters): void
    {
        if (!is_array($parameters[$this->namespace] ?? null)) {
            return;
        }

        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');

        if (!$this->cTypeExistsOnPage($defaultPageRoute)) {
            return;
        }

        $namespaceParameters = $parameters[$this->namespace];
        unset($namespaceParameters['action']);
        unset($namespaceParameters['controller']);

        $deflatedParameters = [];

        foreach ($namespaceParameters as $key => $value) {
            $valueAspectName = $this->parameters['values'][$key];
            $aspect = $this->getAspect($valueAspectName, $defaultPageRoute);
            $generatedValue = $aspect?->generate(is_string($value) ? $value : json_encode($value));
            if (!$generatedValue) {
                throw new InvalidArgumentException(
                    sprintf(
                        'No aspect found for parameter \'%s\' with value \'%s\' or generated null',
                        $key,
                        is_string($value) ? $value : json_encode($value),
                    ),
                    1678262293
                );
            }
            $generatedValue = is_string($value) ? $generatedValue : json_decode($generatedValue, true);
            $defaultValue = $this->defaults[$key] ?? null;
            if ($defaultValue !== $generatedValue) {
                $newKey = $this->parameters['keys'][$key] ?? null;
                $keyAspect = $this->aspects[$newKey] ?? null;
                $key = $keyAspect instanceof ModifiableAspectInterface ? $keyAspect->modify() : $newKey ?? $key;
                $deflatedParameters[$key] = $generatedValue;
            }
        }

        $defaultPageRoute->setOption('_enhancer', $this);
        $defaultPageRoute->setOption('deflatedParameters', $deflatedParameters);

        $collection->add('enhancer_' . $this->namespace . spl_object_hash($defaultPageRoute), $defaultPageRoute);
    }

    public function buildResult(Route $route, array $results, array $remainingQueryParameters = []): PageArguments
    {
        $deflatedParameters = $route->getOption('deflatedParameters');

        $arguments = [
            $this->namespace => [
                'action' => $this->actionName,
                'controller' => $this->controllerName,
                ...$deflatedParameters,
            ],
        ];

        $page = $route->getOption('_page');
        $pageId = (int)(isset($page['t3ver_oid']) && $page['t3ver_oid'] > 0 ? $page['t3ver_oid'] : $page['uid']);
        $pageId = (int)($page['l10n_parent'] > 0 ? $page['l10n_parent'] : $pageId);
        // See PageSlugCandidateProvider where this is added.
        if ($page['MPvar'] ?? '') {
            $arguments['MP'] = $page['MPvar'];
        }
        $type = $this->resolveType($route, $remainingQueryParameters);
        return new PageArguments($pageId, $type, $arguments, $arguments);
    }

    private function getAspect(?string $name, Route $route): ?MappableAspectInterface
    {
        $aspect = $this->aspects[$name] ?? null;
        $aspect = $aspect instanceof MappableAspectInterface ? $aspect : null;
        if ($aspect instanceof ContextAwareInterface) {
            $page = $route->getOption('_page');
            $aspect->getContext()->setAspect('extbase', new ExtbaseAspect($page, $this->cType));
        }
        return $aspect;
    }

    private function cTypeExistsOnPage(Route $route): bool
    {
        $page = $route->getOption('_page');
        return (bool) $this->databaseService->getFieldByPageUidAndCType('uid', $page['uid'], $this->cType, $page['sys_language_uid']);
    }
}
