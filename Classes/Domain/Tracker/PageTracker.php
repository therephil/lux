<?php
declare(strict_types = 1);
namespace In2code\Lux\Domain\Tracker;

use In2code\Lux\Domain\Model\Page;
use In2code\Lux\Domain\Model\Pagevisit;
use In2code\Lux\Domain\Model\Visitor;
use In2code\Lux\Domain\Repository\PageRepository;
use In2code\Lux\Domain\Repository\VisitorRepository;
use In2code\Lux\Signal\SignalTrait;
use In2code\Lux\Utility\ObjectUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

/**
 * Class PageTracker
 */
class PageTracker
{
    use SignalTrait;

    /**
     * @var VisitorRepository
     */
    protected $visitorRepository;

    /**
     * Constructor
     *
     * @param VisitorRepository $visitorRepository
     */
    public function __construct(VisitorRepository $visitorRepository)
    {
        $this->visitorRepository = $visitorRepository;
    }

    /**
     * @param Visitor $visitor
     * @param array $arguments
     * @return Pagevisit|null
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidConfigurationTypeException
     * @throws UnknownObjectException
     */
    public function track(Visitor $visitor, array $arguments): ?Pagevisit
    {
        $pageUid = (int)$arguments['pageUid'];
        $languageUid = (int)$arguments['languageUid'];
        $referrer = $arguments['referrer'];
        if ($this->isTrackingActivated($visitor, $pageUid)) {
            $pagevisit = $this->getPageVisit($pageUid, $languageUid, $referrer);
            $visitor->addPagevisit($pagevisit);
            $visitor->setVisits($visitor->getNumberOfUniquePagevisits());
            $this->visitorRepository->update($visitor);
            $this->visitorRepository->persistAll();
            $this->signalDispatch(__CLASS__, __METHOD__, [$visitor]);
            return $pagevisit;
        }
        return null;
    }

    /**
     * @param int $pageUid
     * @param int $languageUid
     * @param string $referrer
     * @return Pagevisit
     */
    protected function getPageVisit(int $pageUid, int $languageUid, string $referrer): Pagevisit
    {
        /** @var Pagevisit $pageVisit */
        $pageVisit = GeneralUtility::makeInstance(Pagevisit::class);
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        /** @var Page $page */
        $page = $pageRepository->findByUid($pageUid);
        $pageVisit->setPage($page)->setLanguage($languageUid)->setReferrer($referrer)->setDomain();
        return $pageVisit;
    }

    /**
     * @param Visitor $visitor
     * @param int $pageUid
     * @return bool
     * @throws InvalidConfigurationTypeException
     */
    protected function isTrackingActivated(Visitor $visitor, int $pageUid): bool
    {
        return $pageUid > 0 && $visitor->isNotBlacklisted() && $this->isTrackingActivatedInSettings();
    }

    /**
     * Check if tracking of pagevisits is turned on via TypoScript
     *
     * @return bool
     * @throws InvalidConfigurationTypeException
     */
    protected function isTrackingActivatedInSettings(): bool
    {
        $configurationService = ObjectUtility::getConfigurationService();
        $settings = $configurationService->getTypoScriptSettings();
        return !empty($settings['tracking']['pagevisits']['_enable'])
            && $settings['tracking']['pagevisits']['_enable'] === '1';
    }
}
