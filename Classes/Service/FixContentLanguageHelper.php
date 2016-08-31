<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Torben Hansen <derhansen@gmail.com>
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
 * Class with methods to fix the language of content elements
 */
class Tx_SfTv2fluidge_Service_FixContentLanguageHelper implements t3lib_Singleton {

    /**
     * @var Tx_SfTv2fluidge_Service_SharedHelper
     */
    protected $sharedHelper;

    /**
     * DI for shared helper
     *
     * @param Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper
     * @return void
     */
    public function injectSharedHelper(Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper) {
        $this->sharedHelper = $sharedHelper;
    }

    /**
     * @var Tx_SfTv2fluidge_Service_LogHelper
     */
    protected $logHelper;

    /**
     * DI for shared helper
     *
     * @param Tx_SfTv2fluidge_Service_LogHelper $logHelper
     * @return void
     */
    public function injectLogHelper(Tx_SfTv2fluidge_Service_LogHelper $logHelper) {
        $this->logHelper = $logHelper;
    }

    /**
     * Fixes the sys_language_uid for all content elements on the given page, when the content element has
     * a different sys_language_uid that the page translation
     *
     * @param int $pageUid
     * @return int
     */
    public function fixContentLanguageForPage($pageUid) {
        $updated = 0;

        $langIsoCodes = $this->sharedHelper->getLanguagesIsoCodes();
        $contentElementArrayByLang = $this->sharedHelper->getTvContentArrayByLanguageForPage($pageUid);

        $defaultLangContentElements = explode(',', $contentElementArrayByLang['lDEF']);

        // Finds all content elements, that has been used more than 1 time on the given page
        foreach ($contentElementArrayByLang as $language => $contentElementUids) {
            // Only process content elements other than the default language
            if ($language !== 'lDEF' && $contentElementUids !== '') {
                $targetLangUid = array_keys($langIsoCodes, substr($language, 1));
                if (isset($targetLangUid[0])) {
                    $allContentElementUids = explode(',', $contentElementUids);
                    foreach ($allContentElementUids as $singleContentElementUid) {
                        if (in_array($singleContentElementUid, $defaultLangContentElements)) {
                            $message = 'Skipping referenced record on Page UID: ' . $pageUid . ' - Content Element UID: ' . $singleContentElementUid;
                            $this->logHelper->logMessage($message);
                        } else {
                            // Content element with possibly wrong language, set language to current language
                            $updated += $this->fixLanguageForContentElement((int)$singleContentElementUid, (int)$targetLangUid[0], $pageUid);
                        }
                    }
                }
            }
        }

        return $updated;
    }

    /**
     * Searches on the given page for local references in translated page content areas and replaces
     * content elements with translated insert record elements
     *
     * @param $pageUid
     * @return int
     */
    public function replaceLocalReferencesWithShortcuts($pageUid) {
        $created = 0;
        $GLOBALS['TCA']['tt_content']['ctrl']['hideAtCopy'] = 0;
        $GLOBALS['TCA']['tt_content']['ctrl']['prependAtCopy'] = 0;

        $langIsoCodes = $this->sharedHelper->getLanguagesIsoCodes();
        $contentElementArray = $this->sharedHelper->getTvContentArrayByLanguageAndFieldForPage($pageUid);

        $contentElementArrayByLang = $this->sharedHelper->getTvContentArrayByLanguageForPage($pageUid);
        $defaultLangContentElements = explode(',', $contentElementArrayByLang['lDEF']);

        foreach ($contentElementArray as $language => $contentArrayByField) {
            if ($language !== 'lDEF') {
                $targetLangUid = array_keys($langIsoCodes, substr($language, 1));
                foreach ($contentArrayByField as $fieldname => $contentUids) {
                    $allContentElementUids = explode(',', $contentUids);
                    for ($position = 1; $position <= count($allContentElementUids); $position++) {
                        $contentElementUid = $allContentElementUids[$position - 1];
                        if (in_array($contentElementUid, $defaultLangContentElements)) {
                            $newContentUid = $this->convertPageCeToLocalCopy($pageUid, $fieldname, $position, $language);
                            $this->convertToShortcutWithNewLanguage($newContentUid, $contentElementUid, (int)$targetLangUid[0]);
                            $message = 'Page UID: ' . $pageUid . ' - local ref. to content element UID: ' . $contentElementUid . ' migrated to shortcut uid ' . $newContentUid . ' - language: ' . (int)$targetLangUid[0];
                            $this->logHelper->logMessage($message);
                            $created++;
                        }
                    }
                }
            }
        }
        return $created;
    }

    /**
     * Converts page content element reference to local copy
     *
     * @param integer $pageUid
     * @param string $field
     * @param integer $position
     * @param string $language
     * @return integer
     */
    protected function convertPageCeToLocalCopy($pageUid, $field, $position, $language) {
        $flexformPointerString = 'pages:' . (int)$pageUid . ':sDEF:' . $language . ':' . $field . ':vDEF:' . (int)$position;
        return $this->convertFlexformPointerStringToLocalCopy($flexformPointerString);
    }

    /**
     * Converts flexform pointer string to local copy
     *
     * @param string $flexformPointerString
     * @return mixed
     */
    protected function convertFlexformPointerStringToLocalCopy($flexformPointerString) {
        $sourcePointer = $this->sharedHelper->getTemplavoilaAPIObj()->
        flexform_getPointerFromString($flexformPointerString);

        $contentUid = $this->sharedHelper->getTemplavoilaAPIObj()->
        copyElement($sourcePointer, $sourcePointer);

        $this->sharedHelper->getTemplavoilaAPIObj()->
        unlinkElement($sourcePointer);

        return $contentUid;
    }

    /**
     * Converts element to shortcut
     *
     * @param integer $contentUid
     * @param integer $targetUid
     * @param integer $sys_language_uid
     * @return void
     */
    protected function convertToShortcutWithNewLanguage($contentUid, $targetUid, $sys_language_uid) {
        $targetUid = (int)$targetUid;
        $contentUid = (int)$contentUid;
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tt_content',
            'uid = ' . $contentUid,
            array(
                'CType'   => 'shortcut',
                'sys_language_uid' => $sys_language_uid,
                'records' => 'tt_content_' . $targetUid,
            )
        );
        $this->logHelper->logMessage('===== ' . __CLASS__ . ' - ' . __FUNCTION__ . ' =====');
        $this->logHelper->logMessage($GLOBALS['TYPO3_DB']->debug_lastBuiltQuery);
    }

    /**
     * Compares for the given content element UID the sys_language_uid with the given languageUid and if
     * different, updates the sys_language_uid to the given one.
     *
     * @param int $contentElementUid
     * @param int $languageUid
     * @param int $pageUid
     * @return int
     */
    protected function fixLanguageForContentElement($contentElementUid, $languageUid, $pageUid) {
        $updated = 0;
        $contentElement = $this->sharedHelper->getContentElement($contentElementUid);
        if ($contentElement !== false && (int)$contentElement['sys_language_uid'] !== $languageUid) {
            $message = 'Page UID: ' . $pageUid . ' - Content Element UID: ' . $contentElementUid . ' - Lang: ' . (int)$contentElement['sys_language_uid'] . ' => ' . $languageUid;
            $this->logHelper->logMessage($message);
            $this->setLanguageForContentElement($contentElementUid, $languageUid);
            $updated += 1;
        }
        return $updated;
    }

    /**
     * Updates the language of the given content element uid
     *
     * @param $contentElementUid
     * @param $language
     * @return void
     */
    protected function setLanguageForContentElement($contentElementUid, $language) {
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tt_content',
            'uid=' . intval($contentElementUid),
            array(
                'sys_language_uid' => $language
            )
        );
        $this->logHelper->logMessage('===== ' . __CLASS__ . ' - ' . __FUNCTION__ . ' =====');
        $this->logHelper->logMessage($GLOBALS['TYPO3_DB']->debug_lastBuiltQuery);
    }

}
?>