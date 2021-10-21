<?php
declare(strict_types = 1);
namespace In2code\Lux\Controller;

use Doctrine\DBAL\DBALException;
use In2code\Lux\Domain\Factory\VisitorFactory;
use In2code\Lux\Domain\Model\Visitor;
use In2code\Lux\Domain\Service\Email\SendAssetEmail4LinkService;
use In2code\Lux\Domain\Tracker\AttributeTracker;
use In2code\Lux\Domain\Tracker\DownloadTracker;
use In2code\Lux\Domain\Tracker\FrontenduserAuthenticationTracker;
use In2code\Lux\Domain\Tracker\LinkClickTracker;
use In2code\Lux\Domain\Tracker\LuxletterlinkAttributeTracker;
use In2code\Lux\Domain\Tracker\NewsTracker;
use In2code\Lux\Domain\Tracker\PageTracker;
use In2code\Lux\Domain\Tracker\SearchTracker;
use In2code\Lux\Exception\ActionNotAllowedException;
use In2code\Lux\Exception\ConfigurationException;
use In2code\Lux\Exception\EmailValidationException;
use In2code\Lux\Signal\SignalTrait;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;

/**
 * Class FrontendController
 * Todo: Return type ": ResponseInterface" and "return $this->htmlResponse();" when TYPO3 10 support is dropped
 *       for all actions
 */
class FrontendController extends ActionController
{
    use SignalTrait;

    /**
     * Check for allowed actions
     *
     * @return void
     * @throws NoSuchArgumentException
     * @throws ActionNotAllowedException
     * @noinspection PhpUnused
     */
    public function initializeDispatchRequestAction(): void
    {
        $allowedActions = [
            'pageRequest',
            'fieldListeningRequest',
            'formListeningRequest',
            'email4LinkRequest',
            'downloadRequest',
            'linkClickRequest',
            'redirectRequest'
        ];
        $action = $this->request->getArgument('dispatchAction');
        if (!in_array($action, $allowedActions)) {
            throw new ActionNotAllowedException('Action not allowed', 1518815149);
        }
    }

    /**
     * @param string $dispatchAction
     * @param string $identificator Fingerprint or Local storage hash
     * @param array $arguments
     * @return void
     * @throws StopActionException
     * @noinspection PhpUnused
     */
    public function dispatchRequestAction(string $dispatchAction, string $identificator, array $arguments): void
    {
        $this->forward($dispatchAction, null, null, ['identificator' => $identificator, 'arguments' => $arguments]);
    }

    /**
     * @param string $identificator
     * @param array $arguments
     * @return string
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function pageRequestAction(string $identificator, array $arguments): string
    {
        try {
            $visitor = $this->getVisitor($identificator);
            $this->callAdditionalTrackers($visitor, $arguments);
            $pageTracker = GeneralUtility::makeInstance(PageTracker::class);
            $pagevisit = $pageTracker->track($visitor, $arguments);
            $newsTracker = GeneralUtility::makeInstance(NewsTracker::class);
            $newsTracker->track($visitor, $arguments, $pagevisit);
            $searchTracker = GeneralUtility::makeInstance(SearchTracker::class);
            $searchTracker->track($visitor, $arguments);
            return json_encode($this->afterAction($visitor));
        } catch (\Exception $exception) {
            return json_encode($this->getError($exception));
        }
    }

    /**
     * @param string $identificator
     * @param array $arguments
     * @return string
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function fieldListeningRequestAction(string $identificator, array $arguments): string
    {
        try {
            $visitor = $this->getVisitor($identificator);
            $attributeTracker = GeneralUtility::makeInstance(
                AttributeTracker::class,
                $visitor,
                AttributeTracker::CONTEXT_FIELDLISTENING,
                (int)$arguments['pageUid']
            );
            $attributeTracker->addAttribute($arguments['key'], $arguments['value']);
            return json_encode($this->afterAction($visitor));
        } catch (\Exception $exception) {
            return json_encode($this->getError($exception));
        }
    }

    /**
     * @param string $identificator
     * @param array $arguments
     * @return string
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function formListeningRequestAction(string $identificator, array $arguments): string
    {
        try {
            $visitor = $this->getVisitor($identificator);
            $values = json_decode($arguments['values'], true);
            $attributeTracker = GeneralUtility::makeInstance(
                AttributeTracker::class,
                $visitor,
                AttributeTracker::CONTEXT_FORMLISTENING,
                (int)$arguments['pageUid']
            );
            $attributeTracker->addAttributes($values);
            return json_encode($this->afterAction($visitor));
        } catch (\Exception $exception) {
            return json_encode($this->getError($exception));
        }
    }

    /**
     * @param string $identificator
     * @param array $arguments
     * @return string
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function email4LinkRequestAction(string $identificator, array $arguments): string
    {
        try {
            $visitor = $this->getVisitor($identificator, true);
            $attributeTracker = GeneralUtility::makeInstance(
                AttributeTracker::class,
                $visitor,
                AttributeTracker::CONTEXT_EMAIL4LINK,
                (int)$arguments['pageUid']
            );
            $values = json_decode((string)$arguments['values'], true);
            $allowedFields = GeneralUtility::trimExplode(
                ',',
                $this->settings['identification']['email4link']['form']['fields']['enabled'],
                true
            );
            $attributeTracker->addAttributes($values, $allowedFields);

            $downloadTracker = GeneralUtility::makeInstance(DownloadTracker::class, $visitor);
            $downloadTracker->addDownload($arguments['href'], (int)$arguments['pageUid']);
            if ($arguments['sendEmail'] === 'true') {
                GeneralUtility::makeInstance(SendAssetEmail4LinkService::class, $visitor, $this->settings)
                    ->sendMail($arguments['href']);
            }
            return json_encode($this->afterAction($visitor));
        } catch (\Exception $exception) {
            return json_encode($this->getError($exception));
        }
    }

    /**
     * @param string $identificator
     * @param array $arguments
     * @return string
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function downloadRequestAction(string $identificator, array $arguments): string
    {
        try {
            $visitor = $this->getVisitor($identificator);
            $downloadTracker = GeneralUtility::makeInstance(DownloadTracker::class, $visitor);
            $downloadTracker->addDownload($arguments['href'], (int)$arguments['pageUid']);
            return json_encode($this->afterAction($visitor));
        } catch (\Exception $exception) {
            return json_encode($this->getError($exception));
        }
    }

    /**
     * @param string $identificator
     * @param array $arguments
     * @return string
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function linkClickRequestAction(string $identificator, array $arguments): string
    {
        try {
            $visitor = $this->getVisitor($identificator);
            $linkClickTracker = GeneralUtility::makeInstance(LinkClickTracker::class, $visitor);
            $linkClickTracker->addLinkClick((int)$arguments['linklistenerIdentifier'], (int)$arguments['pageUid']);
            return json_encode($this->afterAction($visitor));
        } catch (\Exception $exception) {
            return json_encode($this->getError($exception));
        }
    }

    /**
     * @param string $identificator empty means no opt-in yet
     * @return string
     * @throws Exception
     */
    public function redirectRequestAction(string $identificator): string
    {
        try {
            $visitor = $this->getVisitor($identificator);
            return json_encode($this->afterAction($visitor));
        } catch (\Exception $exception) {
            try {
                // Empty fingerprint, create visitor on the fly
                $visitor = new Visitor();
                return json_encode($this->afterAction($visitor));
            } catch (\Exception $exception) {
                return json_encode($this->getError($exception));
            }
        }
    }

    /**
     * @return void
     * @noinspection PhpUnused
     */
    public function trackingOptOutAction(): void
    {
    }

    /**
     * Track visitors with
     * - frontendlogin or from a
     * - luxletter-link
     *
     * @param Visitor $visitor
     * @param array $arguments
     * @return void
     * @throws Exception
     * @throws DBALException
     * @throws EmailValidationException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     */
    protected function callAdditionalTrackers(Visitor $visitor, array $arguments): void
    {
        $authTracker = GeneralUtility::makeInstance(
            FrontenduserAuthenticationTracker::class,
            $visitor,
            (int)$arguments['pageUid']
        );
        $authTracker->trackByFrontenduserAuthentication();

        $luxletterTracker = GeneralUtility::makeInstance(
            LuxletterlinkAttributeTracker::class,
            $visitor,
            AttributeTracker::CONTEXT_LUXLETTERLINK,
            (int)$arguments['pageUid']
        );
        $luxletterTracker->trackFromLuxletterLink();
    }

    /**
     * This method will be called after normal frontend actions.
     * Pass four parameters to slot. The first is the visitor to use this data. The second is the action name from
     * where the signal came from. The third is an array, which could be returned for passing an array as json to the
     * javascript of the visitor. The last one is mandatory and in this case useless.
     *
     * @param Visitor $visitor
     * @return array
     * @throws Exception
     */
    protected function afterAction(Visitor $visitor): array
    {
        $result = $this->signalDispatch(__CLASS__, 'afterTracking', [$visitor, $this->actionMethodName, [], []]);
        return $result[2];
    }

    /**
     * @param \Exception $exception
     * @return array
     * @throws Exception
     */
    protected function getError(\Exception $exception): array
    {
        $this->signalDispatch(__CLASS__, 'afterTracking', [new Visitor(), 'error', [], ['error' => $exception]]);
        return [
            'error' => true,
            'exception' => [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage()
            ]
        ];
    }

    /**
     * @param string $identificator
     * @param bool $tempVisitor
     * @return Visitor
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws ConfigurationException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function getVisitor(string $identificator, bool $tempVisitor = false): Visitor
    {
        $visitorFactory = GeneralUtility::makeInstance(VisitorFactory::class, $identificator, $tempVisitor);
        return $visitorFactory->getVisitor();
    }
}
