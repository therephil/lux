<?php

declare(strict_types = 1);

namespace In2code\Lux\Domain\Cache;

use GuzzleHttp\Cookie\CookieJar;
use In2code\Lux\Domain\Service\SiteService;
use In2code\Lux\Exception\EnvironmentException;
use In2code\Lux\Exception\UnexpectedValueException;
use In2code\Lux\Utility\UrlUtility;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CacheWarmup
 */
final class CacheWarmup
{
    /**
     * @var BackendSessionFaker
     */
    protected $backendSessionFaker;

    /**
     * @var string
     */
    protected $domain = '';

    /**
     * Constructor
     *
     * @throws EnvironmentException
     */
    public function __construct()
    {
        $this->backendSessionFaker = GeneralUtility::makeInstance(BackendSessionFaker::class);
        $this->backendSessionFaker->fake();
    }

    /**
     * @param string $route
     * @param string $domain
     * @param OutputInterface $output
     * @return void
     * @throws RouteNotFoundException
     * @throws UnexpectedValueException
     */
    public function warmup(string $route, string $domain, OutputInterface $output): void
    {
        $this->domain = $domain;
        $output->writeln('Start: Cache warmup for route ' . $route . ' ...');
        $this->sendRequestToBackendModule($route);
        $output->writeln('Done: Cache for route ' . $route . ' successfully warmed up!');
    }

    /**
     * Example request
     * curl -Ik --cookie "be_typo_user=sessid" https://domain.org/typo3/index.php\?route\=%2Fmodule%2Flux%2FLuxLeads\&token\=tokenid
     *
     * @param string $route
     * @return void
     * @throws RouteNotFoundException
     * @throws UnexpectedValueException
     */
    protected function sendRequestToBackendModule(string $route): void
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $path = $uriBuilder->buildUriFromRoute($route);
        $url = $this->getDomain() . $path->__toString();

        $jar = CookieJar::fromArray(
            ['be_typo_user' => $this->backendSessionFaker->getSessionIdentifier()],
            $this->getDomain(false)
        );
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $response = $requestFactory->request($url, 'GET', ['allow_redirects' => true, 'cookies' => $jar]);
        if ($response->getStatusCode() === 200) {
            if (stristr($response->getBody()->getContents(), 'lux-backend') === false) {
                throw new UnexpectedValueException('Could not warmup cache', 1645131190);
            }
        }
    }

    /**
     * @param bool $withProtocol
     * @return string
     */
    protected function getDomain(bool $withProtocol = true): string
    {
        $domain = $this->domain;
        if ($domain === '') {
            $siteService = GeneralUtility::makeInstance(SiteService::class);
            $domain = $siteService->getFirstDomain();
        }
        $domain = UrlUtility::removeSlashPrefixAndPostfix($domain);
        if ($withProtocol === false) {
            return UrlUtility::removeProtocolFromDomain($domain);
        }
        return $domain;
    }
}
