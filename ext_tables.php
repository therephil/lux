<?php
if (!defined('TYPO3')) {
    die('Access denied.');
}

call_user_func(
    function () {

        /**
         * Register Icons
         */
        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Imaging\IconRegistry::class
        );
        $iconRegistry->registerIcon(
            'extension-lux-module',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:lux/Resources/Public/Icons/lux_white.svg']
        );
        $iconRegistry->registerIcon(
            'extension-lux',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:lux/Resources/Public/Icons/lux.svg']
        );
        $iconRegistry->registerIcon(
            'extension-lux-turquoise',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:lux/Resources/Public/Icons/Extension.svg']
        );

        /**
         * Include Modules
         */
        // Add Main module "LUX".
        // Acces to a main module is implicit, as soon as a user has access to at least one of its submodules.
        if (\In2code\Lux\Utility\ConfigurationUtility::isAnalysisModuleDisabled() === false
            || \In2code\Lux\Utility\ConfigurationUtility::isWorkflowModuleDisabled() === false) {
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
                'lux',
                '',
                '',
                null,
                [
                    'name' => 'lux',
                    'labels' => 'LLL:EXT:lux/Resources/Private/Language/locallang_mod.xlf',
                    'iconIdentifier' => 'extension-lux-module'
                ]
            );
        }
        // Add module for analysis
        if (\In2code\Lux\Utility\ConfigurationUtility::isAnalysisModuleDisabled() === false) {
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'Lux',
                'lux',
                'analysis',
                '',
                [
                    \In2code\Lux\Controller\AnalysisController::class =>
                        'dashboard,content,news,linkListener,search,deleteLinkListener,detailPage' .
                        ',detailNews,detailSearch,detailDownload,detailLinkListener,resetFilter',
                    \In2code\Lux\Controller\LeadController::class =>
                        'dashboard,list,detail,downloadCsv,remove,deactivate,resetFilter',
                    \In2code\Lux\Controller\GeneralController::class => 'information'
                ],
                [
                    'access' => 'user,group',
                    'icon' => 'EXT:lux/Resources/Public/Icons/lux_module_analysis.svg',
                    'labels' => 'LLL:EXT:lux/Resources/Private/Language/locallang_mod_analysis.xlf',
                ]
            );
        }
        // Add module for leads
        if (\In2code\Lux\Utility\ConfigurationUtility::isLeadModuleDisabled() === false) {
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'Lux',
                'lux',
                'leads',
                '',
                [
                    \In2code\Lux\Controller\LeadController::class =>
                        'dashboard,list,detail,downloadCsv,remove,deactivate,resetFilter',
                    \In2code\Lux\Controller\AnalysisController::class =>
                        'dashboard,content,linkClicks,detailPage,detailDownload,resetFilter',
                    \In2code\Lux\Controller\GeneralController::class => 'information'
                ],
                [
                    'access' => 'user,group',
                    'icon' => 'EXT:lux/Resources/Public/Icons/lux_module_lead.svg',
                    'labels' => 'LLL:EXT:lux/Resources/Private/Language/locallang_mod_lead.xlf',
                ]
            );
        }
        // Add module for campaigns
        if (\In2code\Lux\Utility\ConfigurationUtility::isWorkflowModuleDisabled() === false) {
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'Lux',
                'lux',
                'workflow',
                '',
                [
                    \In2code\Lux\Controller\WorkflowController::class =>
                        'list,new,create,edit,update,delete,disable,enable,resetFilter',
                    \In2code\Lux\Controller\ShortenerController::class => 'list,delete,detail,resetFilter,qr',
                    \In2code\Lux\Controller\AbTestingController::class => 'list',
                    \In2code\Lux\Controller\GeneralController::class => 'information'
                ],
                [
                    'access' => 'user,group',
                    'icon' => 'EXT:lux/Resources/Public/Icons/lux_module_workflow.svg',
                    'labels' => 'LLL:EXT:lux/Resources/Private/Language/locallang_mod_workflow.xlf',
                ]
            );
        }
    }
);
