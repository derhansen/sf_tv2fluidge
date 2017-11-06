<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Torben Hansen <derhansen@gmail.com>, Skyfillers GmbH
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class with methods used for mulitlingual content conversion
 */
class Tx_SfTv2fluidge_Service_ConvertMultilangContentHelper implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * @var Tx_SfTv2fluidge_Service_SharedHelper
     */
    protected $sharedHelper;

    /**
     * @var \TYPO3\CMS\Core\Database\ReferenceIndex
     */
    protected $refIndex;

    /**
     * @var string
     */
    protected $flexformConversionOption = 'merge';

    /**
     * @var string
     */
    protected $insertRecordsConversionOption = 'convertAndTranslate';

    /**
     * @var bool
     */
    protected $allLanguageRecordsInGeToShortcut = false;

    /**
     * @var array
     */
    protected $langIsoCodes = array();

    /**
     * DI for shared helper
     *
     * @param Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper
     * @return void
     */
    public function injectSharedHelper(Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper)
    {
        $this->sharedHelper = $sharedHelper;
    }

    /**
     * DI for \TYPO3\CMS\Core\Database\ReferenceIndex
     *
     * @param \TYPO3\CMS\Core\Database\ReferenceIndex $refIndex
     * @return void
     */
    public function injectRefIndex(\TYPO3\CMS\Core\Database\ReferenceIndex $refIndex)
    {
        $this->refIndex = $refIndex;
    }

    /**
     * @param array $formdata
     */
    public function initFormData($formdata)
    {
        $this->flexformConversionOption = $formdata['convertflexformoption'];
        $this->insertRecordsConversionOption = $formdata['convertinsertrecords'];
        $this->allLanguageRecordsInGeToShortcut = (intval($formdata['alllanguagerecordsingetoshortcut']) === 1);
    }

    /**
     * Clones all GridElements which are configured for "All languages" and creates a single GridElement
     * for each page translation. Also sets the language for the original GridElement to 0 (default language)
     *
     * @param int $pageUid
     * @return int Amount of cloned GridElements
     */
    public function cloneLangAllGEs($pageUid)
    {
        $cloned = 0;

        $pageLanguages = $this->sharedHelper->getAvailablePageTranslations($pageUid);
        $allLanguages = $this->sharedHelper->getAllLanguages();
        $nonPageLanguages = array_diff($allLanguages, $pageLanguages);

        $gridElements = $this->getCeGridElements($pageUid, -1); // All GridElements with language = all
        $this->langIsoCodes = $this->sharedHelper->getLanguagesIsoCodes();

        foreach ($gridElements as $contentElementUid) {
            $origContentElement = $this->sharedHelper->getContentElement($contentElementUid);
            foreach ($pageLanguages as $langUid) {
                $translationContentUid = $this->addTranslationContentElement($origContentElement, $langUid, $origContentElement['uid']);
                $this->updateShortcutElements($contentElementUid, $langUid, $translationContentUid);
                ++$cloned;
            }

            // modify shortcuts for non non page translations (could be other languages available)
            if (!empty($nonPageLanguages)) {
                foreach ($nonPageLanguages as $pageLanguageUid) {
                    $this->updateShortcutElements($contentElementUid, $pageLanguageUid, $contentElementUid);
                }
            }

            $origContentElement['sys_language_uid'] = 0;
            $origUid = $origContentElement['uid'];
            unset ($origContentElement['uid']);
            $tvTemplateUid = (int)$origContentElement['tx_templavoila_to'];
            if (!empty($origContentElement['tx_templavoila_flex'])) {
                $origContentElement['pi_flexform'] = $origContentElement['tx_templavoila_flex'];
            }
            $origContentElement['pi_flexform'] = $this->sharedHelper->cleanFlexform($origContentElement['pi_flexform'], $tvTemplateUid);
            $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, $origContentElement);
            $this->updateSysLanguageOfAllLanguageShortcuts($contentElementUid);
        }
        return $cloned;
    }

    /**
     * Updates shortcut elements
     *
     * @param $contentElementUid
     * @param $langUid
     * @param int $translationContentUid
     */
    protected function updateShortcutElements($contentElementUid, $langUid, $translationContentUid = 0)
    {
        $shortcutElements = $this->getShortcutElements($contentElementUid, $langUid);
        $translationContentUid = (int)$translationContentUid;
        if ($this->insertRecordsConversionOption !== 'keep') {
            foreach ($shortcutElements as $shortcutElement) {
                if (!empty($shortcutElement['records']) && ($shortcutElement['CType'] === 'shortcut')) {
                    $records = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $shortcutElement['records'], TRUE);
                    $recordShortcutString = 'tt_content_' . (int)$contentElementUid;
                    $isShortcutRecord = FALSE;
                    foreach ($records as &$record) {
                        if ($record === $recordShortcutString) {
                            if (($this->insertRecordsConversionOption === 'convertAndTranslate') && ($translationContentUid > 0)) {
                                $record = 'tt_content_' . $translationContentUid;
                            }
                            $isShortcutRecord = TRUE;
                            break;
                        }
                    }

                    if ($isShortcutRecord) {
                        if (!empty($records)) {
                            $shortcutElement['records'] = implode(',', $records);
                        }
                        $languageUid = (int)$shortcutElement['sys_language_uid'];
                        $origUid = NULL;
                        if ($languageUid > 0) {
                            $origUid = (int)$shortcutElement['l18n_parent'];
                        } else {
                            $origUid = (int)$shortcutElement['uid'];
                        }
                        $newUid = (int)$this->addTranslationContentElement($shortcutElement, $langUid, $origUid);
                        if ($newUid > 0) {
                            $this->refIndex->updateRefIndexTable('tt_content', $newUid);
                        }
                    }
                }
            }
        }
    }

    /**
     * Updates the sys_language_uid of all shortcut content elements
     *
     * @param int $contentElementUid
     */
    protected function updateSysLanguageOfAllLanguageShortcuts($contentElementUid)
    {
        if ($this->insertRecordsConversionOption !== 'keep') {
            $contentElementUid = (int)$contentElementUid;
            $shortcutElements = $this->getShortcutElements($contentElementUid);
            foreach ($shortcutElements as $shortcutElement) {
                $shortcutElementUid = (int)$shortcutElement['uid'];
                $shortcutElementLanguage = (int)$shortcutElement['sys_language_uid'];
                if (($shortcutElementUid > 0) && ($shortcutElementLanguage < 0)) {
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $shortcutElementUid, array('sys_language_uid' => 0));
                }
            }
        }
    }

    /**
     * Adds/Updates a translation content element
     *
     * @param $contentElement
     * @param $langUid
     * @param $origUid
     * @return int|null
     */
    protected function addTranslationContentElement($contentElement, $langUid, $origUid)
    {
        $langUid = (int)$langUid;
        $origUid = (int)$origUid;
        unset ($contentElement['uid']);
        $contentElement['sys_language_uid'] = $langUid;
        $contentElement['t3_origuid'] = (int)$origUid;
        $contentElement['l18n_parent'] = (int)$origUid;

        $tvTemplateUid = (int)$contentElement['tx_templavoila_to'];
        if (!empty($contentElement['tx_templavoila_flex'])) {
            $contentElement['pi_flexform'] = $contentElement['tx_templavoila_flex'];
        }
        if ($this->flexformConversionOption !== 'exclude') {
            if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
                $langUid = (int)$contentElement['sys_language_uid'];
                if ($langUid > 0) {
                    $forceLanguage = ($this->flexformConversionOption === 'forceLanguage');
                    if ($origUid <= 0) {
                        $forceLanguage = FALSE;
                    }

                    if (!$this->sharedHelper->isTvDataLangDisabled($tvTemplateUid)) {
                        $contentElement['pi_flexform'] = $this->sharedHelper->convertFlexformForTranslation($contentElement['pi_flexform'], $this->langIsoCodes[$langUid], $forceLanguage);
                    }
                }
            }
        }

        $contentElement['pi_flexform'] = $this->sharedHelper->cleanFlexform($contentElement['pi_flexform'], $tvTemplateUid);

        $existingTranslation = $this->sharedHelper->getTranslationForContentElementAndLanguage($origUid, $langUid);
        $existingTranslationUid = 0;
        if (!empty($existingTranslation) && is_array($existingTranslation)) {
            $existingTranslationUid = (int)$existingTranslation['uid'];
        }

        $contentElementUid = NULL;
        if ($existingTranslationUid > 0) {
            $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $existingTranslationUid, $contentElement);
            $contentElementUid = $existingTranslationUid;
        } else {
            $GLOBALS['TYPO3_DB']->exec_INSERTquery('tt_content', $contentElement);
            $contentElementUid = (int)$GLOBALS['TYPO3_DB']->sql_insert_id();
        }

        return $contentElementUid;
    }

    /**
     * Rearranges content and translated content elements to the cloned/modified GridElements
     *
     * @param int $pageUid
     * @return int Amount of updated content elements
     */
    public function rearrangeContentElementsForGridelementsOnPage($pageUid)
    {
        $updated = 0;
        $gridElements = $this->getCeGridElements($pageUid, 0);
        foreach ($gridElements as $contentElementUid) {
            $contentElements = $this->getChildContentElements($contentElementUid);
            foreach ($contentElements as $contentElement) {
                $childElementUid = (int)$contentElement['uid'];
                $translations = $this->sharedHelper->getTranslationsForContentElement($childElementUid);
                if (!empty($translations)) {
                    foreach ($translations as $translatedContentElement) {
                        $localizedGridElement = $this->getLocalizedGridElement($contentElementUid,
                            $translatedContentElement['sys_language_uid']);
                        if ($localizedGridElement) {
                            $origUid = $translatedContentElement['uid'];
                            unset($translatedContentElement['uid']);
                            $translatedContentElement['colPos'] = -1;
                            $translatedContentElement['tx_gridelements_container'] = $localizedGridElement['uid'];
                            $translatedContentElement['tx_gridelements_columns'] = $contentElement['tx_gridelements_columns'];
                            $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, $translatedContentElement);
                            ++$updated;
                        }
                    }
                }

                if ($contentElement['sys_language_uid'] > 0) {
                    // Rearrage CE to new localized GEs
                    $localizedGridElement = $this->getLocalizedGridElement($contentElementUid,
                        $contentElement['sys_language_uid']);
                    $origUid = $contentElement['uid'];
                    unset($contentElement['uid']);
                    $contentElement['tx_gridelements_container'] = $localizedGridElement['uid'];
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, $contentElement);
                    ++$updated;
                }
                $this->updateInGeAllLangElements($pageUid, $contentElementUid, $contentElement);
                $this->sharedHelper->fixContentElementLocalizationDiffSources($childElementUid);
            }

            $this->sharedHelper->fixContentElementLocalizationDiffSources($contentElementUid);
        }
        return $updated;
    }

    /**
     * @param int $pageUid
     * @param int $geElementUid
     * @param array $childElement
     */
    protected function updateInGeAllLangElements($pageUid, $geElementUid, $childElement)
    {
        $pageUid = (int)$pageUid;
        $geElementUid = (int)$geElementUid;
        $childElementUid = (int)$childElement['uid'];
        if (($this->allLanguageRecordsInGeToShortcut) && ($childElement['sys_language_uid'] < 0)) {
            $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $childElementUid, array('sys_language_uid' => 0));
            $pageLanguages = $this->sharedHelper->getAvailablePageTranslations($pageUid);
            foreach ($pageLanguages as $langUid) {
                $langUid = (int)$langUid;
                if ($langUid > 0) {
                    $localizedGridElement = $this->getLocalizedGridElement($geElementUid, $langUid);
                    $localizedGridElementUid = (int)$localizedGridElement['uid'];
                    if ($localizedGridElementUid > 0) {
                        $translationContentElement = $childElement;
                        $translationContentElement['tx_gridelements_container'] = $localizedGridElementUid;
                        $translationContentElement['CType'] = 'shortcut';
                        $translationContentElement['records'] = 'tt_content_' . $childElementUid;
                        $newUid = (int)$this->addTranslationContentElement($translationContentElement, $langUid, $childElementUid);
                        if ($newUid > 0) {
                            $this->refIndex->updateRefIndexTable('tt_content', $newUid);
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns an array with UIDs of GridElements, which are configured for "All languages"
     *
     * @param int $pageUid
     * @param int $langUid
     * @return array
     */
    public function getCeGridElements($pageUid, $langUid)
    {
        $fields = 'uid';
        $table = 'tt_content';
        $where = 'pid=' . (int)$pageUid . ' AND CType = "gridelements_pi1" AND sys_language_uid = ' . (int)$langUid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

        $gridElements = array();
        if ($res) {
            foreach ($res as $ge) {
                $gridElements[] = $ge['uid'];
            }
        }
        return $gridElements;
    }

    /**
     * Returns all shortcut elements for the given content element uid
     *
     * @param int $uidContentElement
     * @param int $langUid
     * @return array
     */
    public function getShortcutElements($uidContentElement, $langUid = 0)
    {
        $fields = 'tt_content.*';
        $table = 'sys_refindex, tt_content';
        $langUid = (int)$langUid;
        $langWhere = '';
        if ($langUid > 0) {
            $langWhere = ' OR ((tt_content.sys_language_uid = ' . $langUid . ')  AND (tt_content.l18n_parent = 0))';
        }
        $where = '(tt_content.CType = \'shortcut\')' .
            ' AND (tt_content.uid = sys_refindex.recuid)' .
            ' AND (' .
            '(tt_content.sys_language_uid IN (0,-1) AND (tt_content.l18n_parent = 0))' .
            $langWhere .
            ')' .
            ' AND (sys_refindex.ref_uid = ' . (int)$uidContentElement . ')';

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
        if (empty($res)) {
            $res = array();
        }

        return $res;
    }

    /**
     * Returns the uid of the localized content element (grid element) for the given uid
     *
     * @param int $uidContentElement
     * @param int $langUid
     * @return array
     */
    public function getLocalizedGridElement($uidContentElement, $langUid)
    {
        $fields = 'uid';
        $table = 'tt_content';
        $where = 'l18n_parent=' . (int)$uidContentElement . ' AND CType = "gridelements_pi1" AND sys_language_uid = ' . (int)$langUid;

        return $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
    }

    /**
     * Returns an array of tt_content UIDs, which are child elements for the given content element uid
     *
     * @param int $uidContent
     * @return array
     */
    public function getChildContentElements($uidContent)
    {
        $fields = '*';
        $table = 'tt_content';
        $where = 'tx_gridelements_container=' . (int)$uidContent . ' AND l18n_parent=0';

        return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
    }

}