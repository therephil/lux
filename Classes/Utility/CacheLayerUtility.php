<?php
declare(strict_types = 1);
namespace In2code\Lux\Utility;

use In2code\Lux\Exception\ConfigurationException;
use In2code\Lux\Exception\UnexpectedValueException;

/**
 * Class ConfigurationUtility
 */
class CacheLayerUtility
{
    /**
     * @return void
     */
    public static function registerCacheLayers(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['lux']['cachelayer'][\In2code\Lux\Controller\AnalysisController::class . '->dashboardAction'] = [
            'lifetime' => 86400,
            'route' => 'lux_LuxAnalysis',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['lux']['cachelayer'][\In2code\Lux\Controller\LeadController::class . '->dashboardAction'] = [
            'lifetime' => 86400,
            'route' => 'lux_LuxLeads',
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['lux']['cachelayer'][\In2code\Lux\Hooks\PageOverview::class . '->render'] = [
            'lifetime' => 86400,
            'route' => '',
        ];
    }

    /**
     * @param string $cacheName class->method
     * @return int
     * @throws ConfigurationException
     * @throws UnexpectedValueException
     */
    public static function getCachelayerLifetimeByCacheName(string $cacheName): int
    {
        $configuration = self::getCachelayerConfiguration();
        if (isset($configuration[$cacheName]) === false) {
            throw new ConfigurationException('class ' . $cacheName . ' is not registered', 1636367764);
        }
        if (isset($configuration[$cacheName]['lifetime']) === false) {
            throw new UnexpectedValueException('No lifetime given in cachelayer configuration', 1636367766);
        }
        return $configuration[$cacheName]['lifetime'];
    }

    /**
     * @return array
     */
    public static function getCachelayerRoutes(): array
    {
        $layers = self::getCachelayerConfiguration();
        $routes = [];
        foreach ($layers as $configuration) {
            $routes[] = $configuration['route'];
        }
        return $routes;
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected static function getCachelayerConfiguration(): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXT']['lux']['cachelayer'] ?? [];
    }
}
