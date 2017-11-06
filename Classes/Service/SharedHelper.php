<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Torben Hansen <derhansen@gmail.com>, Skyfillers GmbH
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
 * Class with methods used in other helpers/controllers
 */
class Tx_SfTv2fluidge_Service_SharedHelper implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * @var int
     */
    const DEFAULT_PAGES_DEPTH_LIMIT = 99;

    /**
     * @var tx_templavoila_api
     */
    protected $templavoilaAPIObj;

    /**
     * @var array
     */
    protected $extConf = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->templavoilaAPIObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_templavoila_api');
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sf_tv2fluidge'])) {
            $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sf_tv2fluidge']);
        }
    }

    /**
     * Returns the TemplaVoila Api Object
     *
     * @return object|tx_templavoila_api
     */
    public function getTemplavoilaAPIObj()
    {
        return $this->templavoilaAPIObj;
    }

    /**
     * Returns if static data structure is enabled
     *
     * @return boolean
     */
    public function getTemplavoilaStaticDsIsEnabled()
    {
        $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['templavoila']);

        return $conf['staticDS.']['enable'];
    }

    /**
     * Returns the depth limit of pages to migrate
     *
     * @return int
     */
    public function getPagesDepthLimit()
    {
        $pagesDepthLimit = (int)$this->extConf['pagesDepthLimit'];
        if ($pagesDepthLimit <= 0) {
            $pagesDepthLimit = self::DEFAULT_PAGES_DEPTH_LIMIT;
        }

        return $pagesDepthLimit;
    }

    /**
     * Returns if non root pages are also to migrate
     *
     * @return bool
     */
    public function getIncludeNonRootPagesIsEnabled()
    {
        return ((int)$this->extConf['includeNonRootPages'] === 1);
    }

    /**
     * If set, returns the root page UID from where the conversion starts
     *
     * @return int|null
     */
    public function getConversionRootPid()
    {
        if ($this->extConf['rootPid'] !== '' && $this->canBeInterpretedAsInteger($this->extConf['rootPid'])) {
            return (int)$this->extConf['rootPid'];
        }
        return null;
    }

    /**
     * sets PHP timeout to unlimited for execution
     *
     * @return void
     */
    public function setUnlimitedTimeout()
    {
        if (function_exists('set_time_limit')) {
            try {
                set_time_limit(0);
            } catch (\Exception $setMaxTimeOutExcpetion) {
            }
        }
    }

    /**
     * Returns an array of page uids up to the given amount recursionlevel
     *
     * @param int $depth if not supplied, the extension setting will be used
     * @return array
     */
    public function getPageIds($depth = 0)
    {
        if ($depth <= 0) {
            $depth = $this->getPagesDepthLimit();
        }

        /**
         * @var \TYPO3\CMS\Core\Database\QueryGenerator $tree
         */
        $tree = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\QueryGenerator::class);

        $conversionRootPid = $this->getConversionRootPid();
        if (($conversionRootPid !== null) && ($conversionRootPid > 0)) {
            $startPages = array(
                array('uid' => $this->getConversionRootPid())
            );
        } elseif ($this->getIncludeNonRootPagesIsEnabled()) {
            $startPages = $this->getFirstLevelPages();
        } else {
            $startPages = $this->getRootPages();
        }

        $allPids = '';
        foreach ($startPages as $startPage) {
            $pids = $tree->getTreeList($startPage['uid'], $depth, 0, 1);
            if ($allPids == '') {
                $allPids = $pids;
            } else {
                $allPids .= ',' . $pids;
            }
        }

        // Fallback: If no RootPage is set, then assume page 1 is the root page
        if ($allPids == '') {
            $allPids = $tree->getTreeList(1, $depth, 0, 1);
        }

        return array_unique(explode(',', $allPids));
    }

    /**
     * Tests if the input can be interpreted as float.
     *
     * Note: Float casting from objects or arrays is considered undefined and thus will return false.
     * @see http://php.net/manual/en/language.types.float.php
     *
     * @param $var mixed Any input variable to test
     * @return boolean Returns TRUE if string is an float
     */
    public static function canBeInterpretedAsFloat($var)
    {
        if ($var === '' || is_object($var) || is_array($var)) {
            return FALSE;
        }

        return (filter_var($var, FILTER_VALIDATE_FLOAT) !== FALSE);
    }

    /**
     * @param string| int $value
     * @return bool
     */
    public function canBeInterpretedAsInteger($value)
    {
        return \TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($value);
    }

    /**
     * Returns the uid of the TemplaVoila page template for the given page uid
     *
     * @param $pageUid
     * @return int
     */
    public function getTvPageTemplateUid($pageUid)
    {
        $pageRecord = $this->getPage($pageUid);
        $tvTemplateObjectUid = 0;
        if ($pageRecord['tx_templavoila_to'] != '' && $pageRecord['tx_templavoila_to'] != 0) {
            $tvTemplateObjectUid = $pageRecord['tx_templavoila_to'];
        } else {
            $rootLine = \TYPO3\CMS\Backend\Utility\BackendUtility::BEgetRootLine($pageRecord['uid'], '', TRUE);
            foreach ($rootLine as $rootLineRecord) {
                $myPageRecord = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecordWSOL('pages', $rootLineRecord['uid']);
                if ($myPageRecord['tx_templavoila_next_to']) {
                    $tvTemplateObjectUid = $myPageRecord['tx_templavoila_next_to'];
                    break;
                }
            }
        }
        return $tvTemplateObjectUid;
    }

    /**
     * Returns an array with names of content columns for the given TemplaVoila Templateobject
     *
     * @param int $uidTvDs
     * @param bool $addSelectLabel
     * @return array
     */
    public function getTvContentCols($uidTvDs, $addSelectLabel = true)
    {
        $flexform = $this->getTvDataStructureSimpleXmlObject($uidTvDs);

        $contentCols = array();

        if (false !== (boolean)$flexform) {
            $elements = $flexform->xpath('ROOT/el/*');
            if ($addSelectLabel) {
                $contentCols[''] = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('label_select', 'sf_tv2fluidge');
            }
            foreach ($elements as $element) {
                if ($element->tx_templavoila->eType == 'ce') {
                    $contentCols[$element->getName()] = (string)$element->tx_templavoila->title;
                }
            }
        }

        return $contentCols;
    }

    /**
     * Returns, if langDisable ist set for the given TV DS UID
     *
     * @param int $uidTvDs
     * @return bool
     */
    public function isTvDataLangDisabled($uidTvDs)
    {
        $langDisabled = FALSE;
        $flexform = $this->getTvDataStructureSimpleXmlObject($uidTvDs);
        if (null !== $flexform) {
            $elements = $flexform->xpath('meta/langDisable');
            if (is_array($elements) && !empty($elements)) {
                $element = current($elements);

                if ($element !== NULL) {
                    try {
                        $langDisabled = ((int)$element->__toString() === 1);
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        return $langDisabled;
    }

    /**
     * Returns the TV Datastructure as a simplexml object
     *
     * @param int $uidTvDs
     * @return SimpleXMLElement
     */
    protected function getTvDataStructureSimpleXmlObject($uidTvDs)
    {
        $flexform = NULL;
        if ($this->getTemplavoilaStaticDsIsEnabled()) {
            $toRecord = $this->getTvTemplateObject($uidTvDs);
            $path = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($toRecord['datastructure']);
            $flexform = simplexml_load_string(file_get_contents($path));
        } else {
            $dsRecord = $this->getTvDatastructure($uidTvDs);
            $flexform = simplexml_load_string($dsRecord['dataprot']);
        }
        return $flexform;
    }

    /**
     * Returns an array with names of content columns for the given backend layout
     *
     * @param int $uidBeLayout
     * @return array
     */
    public function getBeLayoutContentCols($uidBeLayout)
    {
        $beLayoutRecord = $this->getBeLayout($uidBeLayout);
        return $this->getContentColsFromTs($beLayoutRecord['config']);
    }

    /**
     * Returns an array with names of content columns for the given gridelement
     *
     * @param string|int $geKey
     * @return array
     */
    public function getGeContentCols($geKey)
    {
        $geRecord = $this->getGridElement($geKey);
        return $this->getContentColsFromTs($geRecord['config']);
    }

    /**
     * Returns an array of field mappings, where the array key represents the TV field name and the value the
     * BE layout column
     *
     * @param array $formdata
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getFieldMappingArray($formdata, $from, $to)
    {
        $tvIndex = array();
        $beValue = array();
        foreach ($formdata as $key => $data) {
            if (substr($key, 0, 7) == $from) {
                $tvIndex[] = $data;
            }
            if (substr($key, 0, 7) == $to) {
                $beValue[] = $data;
            }
        }
        $fieldMapping = array();
        if (count($tvIndex) == count($beValue)) {
            $countTvIndex = count($tvIndex);
            for ($i = 0; $i <= $countTvIndex; $i++) {
                if ($tvIndex[$i] != '' && $beValue[$i] != '') {
                    $fieldMapping[$tvIndex[$i]] = $beValue[$i];
                }
            }
        }
        return $fieldMapping;
    }

    /**
     * Returns an array of TV FlexForm content fields for the page with the given UID.
     * The content elements are seperated by comma
     *
     * @param int $pageUid
     * @return array
     */
    public function getTvContentArrayForPage($pageUid)
    {
        $fields = 'tx_templavoila_flex';
        $table = 'pages';
        $where = 'uid=' . (int)$pageUid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        $tvTemplateUid = (int)$this->getTvPageTemplateUid($pageUid);
        return $this->getContentArrayFromFlexform($res, $tvTemplateUid);
    }

    /**
     * Returns an array of TV FlexForm content fields for the tt_content element with the given uid.
     * The content elements are seperated by comma
     *
     * @param int $contentUid
     * @return array
     */
    public function getTvContentArrayForContent($contentUid)
    {
        $fields = 'tx_templavoila_flex, tx_templavoila_to';
        $table = 'tt_content';
        $where = 'uid=' . (int)$contentUid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        $tvTemplateUid = (int)$res['tx_templavoila_to'];
        return $this->getContentArrayByLanguageAndFieldFromFlexform($res, $tvTemplateUid);
    }

    /**
     * Checks if content element is still available (not deleted)
     *
     * @param int $contentUid
     * @return boolean
     */
    public function isContentElementAvailable($contentUid)
    {
        $where = 'uid=' . (int)$contentUid . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content');

        $count = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('1', 'tt_content', $where);
        return $count ? TRUE : FALSE;
    }

    /**
     * Sets the given colpos for the content element (and translation) with the given uid
     *
     * @param int $uid
     * @param int $newColPos
     * @param int $sorting
     * @return void
     */
    public function updateContentElementColPos($uid, $newColPos, $sorting = 0)
    {
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . (int)$uid, array(
            'colPos' => $newColPos,
            'sorting' => $sorting
        ));
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'l18n_parent=' . (int)$uid, array(
            'colPos' => $newColPos,
            'sorting' => $sorting
        ));
    }

    /**
     * Sets the given GE Container and Column for the content element with the given uid
     *
     * @param int $uid
     * @param int $geContainerUid
     * @param int $geColPos
     * @param int $sorting
     * @return void
     */
    public function updateContentElementForGe($uid, $geContainerUid, $geColPos, $sorting = 0)
    {
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . (int)$uid, array(
                'tx_gridelements_container' => $geContainerUid,
                'tx_gridelements_columns' => $geColPos,
                'sorting' => $sorting,
                'colPos' => -1
            )
        );
    }

    /**
     * Return the pages element record for the given uid
     *
     * @param int $uid
     * @return array
     */
    public function getPage($uid)
    {
        $fields = '*';
        $table = 'pages';
        $where = 'uid=' . (int)$uid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * gets pages overlay for page with $uid
     *
     * @param $uid
     * @return mixed
     */
    public function getPageOverlays($uid)
    {
        $fields = '*';
        $table = 'pages_language_overlay';
        $where = '(pid = ' . (int)$uid . ')' .
            ' AND (sys_language_uid > 0)' .
            \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('pages_language_overlay');

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Return the tt_content element record for the given uid
     *
     * @param int $uid
     * @return array
     */
    public function getContentElement($uid)
    {
        $fields = '*';
        $table = 'tt_content';
        $where = 'uid=' . (int)$uid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Return the sys_language record for the given uid
     *
     * @return array
     */
    public function getLanguagesIsoCodes()
    {
        $languagesIsoCodes = array();

        if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $fields = 'sys_language.uid AS langUid, static_languages.lg_iso_2 AS isoCode';
            $tables = 'sys_language, static_languages';
            $where = '(sys_language.static_lang_isocode = static_languages.uid)'
                . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('sys_language');

            $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $tables, $where, '', '', '');
            if ($res !== NULL) {
                foreach ($res as $row) {
                    $langUid = (int)$row['langUid'];
                    $isoCode = strtoupper(trim($row['isoCode']));
                    if (($langUid > 0) && !empty($isoCode)) {
                        $languagesIsoCodes[$langUid] = $isoCode;
                    }
                }
            }
        }

        return $languagesIsoCodes;
    }

    /**
     * Returns an array of TV FlexForm content fields for the given flexform
     *
     * @param array $result
     * @param int $tvTemplateUid
     * @return array
     */
    private function getContentArrayFromFlexform($result, $tvTemplateUid)
    {
        $contentArray = array();
        $tvTemplateUid = (int)$tvTemplateUid;
        if ($tvTemplateUid > 0) {
            $contentCols = $this->getTvContentCols($tvTemplateUid, false);
            if ((is_array($contentCols)) && (!empty($contentCols)) && ($result['tx_templavoila_flex'] != '')) {
                $flexFormArray = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($result['tx_templavoila_flex']);
                if (isset($flexFormArray['data']) && is_array($flexFormArray['data'])) {
                    foreach ($flexFormArray['data'] as $flexFormSheet) {
                        if (is_array($flexFormSheet)) {
                            $languageSheets = array('lDEF' => $flexFormSheet['lDEF']);
                            if (!$this->isTvDataLangDisabled($tvTemplateUid)) {
                                $languageSheets = $this->moveDefLanguageToFirstPositionOfFlexformArray($flexFormSheet, 'lDEF');
                            }

                            foreach ($languageSheets as $languageSheet) {
                                if (is_array($languageSheet)) {
                                    foreach ($languageSheet as $fieldName => $values) {
                                        if (!empty($fieldName) && isset($contentCols[$fieldName])) {
                                            if ($this->isTvDataLangDisabled($tvTemplateUid)) {
                                                $values = array('vDEF' => $values['vDEF']);
                                            }
                                            $values = $this->moveDefLanguageToFirstPositionOfFlexformArray($values, 'vDEF');
                                            $this->addValuesToContentArray($contentArray, $fieldName, $values);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $contentArray;
    }

    /**
     * Moves the default language to the first position of the Flexform array
     *
     * @param $flexformArray
     * @param $firstElementKey
     * @return array
     */
    private function moveDefLanguageToFirstPositionOfFlexformArray($flexformArray, $firstElementKey)
    {
        $defLanguageFirstFlexformArray = array();
        if (!empty($firstElementKey) && isset($flexformArray[$firstElementKey])) {
            $defLanguageFirstFlexformArray[$firstElementKey] = $flexformArray[$firstElementKey];
        }
        foreach ($flexformArray as $key => $subArray) {
            if ($key != $firstElementKey) {
                $defLanguageFirstFlexformArray[$key] = $subArray;
            }
        }
        return $defLanguageFirstFlexformArray;
    }

    /**
     * Adds the given values to the given content array
     *
     * @param array $contentArray
     * @param string $fieldName
     * @param array $values
     */
    private function addValuesToContentArray(&$contentArray, $fieldName, $values)
    {
        foreach ($values as $languageValues) {
            $fieldValues = array();
            if (!empty($contentArray[$fieldName])) {
                $fieldValues = explode(',', $contentArray[$fieldName]);
            }

            $languageValues = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $languageValues, TRUE);
            $languageValues = array_values($languageValues);
            $languageValuesCount = count($languageValues);
            for ($languageValueIndex = 0; $languageValueIndex < $languageValuesCount; $languageValueIndex++) {
                if (!empty($languageValues[$languageValueIndex])) {
                    if (!in_array($languageValues[$languageValueIndex], $fieldValues)) {
                        $indexOfExistingValue = FALSE;
                        if ($languageValueIndex >= 1) {
                            $indexOfExistingValue = array_search($languageValues[$languageValueIndex - 1], $fieldValues);
                            if ($indexOfExistingValue !== FALSE) {
                                $fieldValues = array_merge(
                                    array_slice($fieldValues, 0, $indexOfExistingValue + 1),
                                    array($languageValues[$languageValueIndex]),
                                    array_slice($fieldValues, $indexOfExistingValue + 1)
                                );
                            }
                        }

                        if ($indexOfExistingValue === FALSE) {
                            $indexOfExistingValue = array_search($languageValues[$languageValueIndex + 1], $fieldValues);
                            if ($indexOfExistingValue !== FALSE) {
                                $fieldValues = array_merge(
                                    array_slice($fieldValues, 0, $indexOfExistingValue),
                                    array($languageValues[$languageValueIndex]),
                                    array_slice($fieldValues, $indexOfExistingValue)
                                );
                            } else {
                                $fieldValues[] = $languageValues[$languageValueIndex];
                            }
                        }
                    }
                }
            }

            $contentArray[$fieldName] = implode(',', $fieldValues);
        }
    }

    /**
     * Cleans templavoila flexform of unnecessary elements and languages, e.g. remove not default languages during
     * convert all languages gridelements or remove content element references
     *
     * @param string $flexformString
     * @param int $tvTemplateUid
     * @param bool $cleanLanguage
     * @return string
     */
    public function cleanFlexform($flexformString, $tvTemplateUid, $cleanLanguage = true)
    {
        $tvTemplateUid = (int)$tvTemplateUid;
        $flexformArray = NULL;
        if (!empty($flexformString)) {
            $contentCols = $this->getTvContentCols($tvTemplateUid, false);
            if (empty($contentCols)) {
                $contentCols = array();
            }

            $flexformArray = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($flexformString);
            if (isset($flexformArray['data']) && is_array($flexformArray['data'])) {
                foreach ($flexformArray['data'] as &$sheetData) {
                    if (is_array($sheetData)) {
                        if ($cleanLanguage) {
                            $languageSheetKeys = array_keys($sheetData);
                            if (!empty($languageSheetKeys)) {
                                foreach ($languageSheetKeys as $languageSheetKey) {
                                    if ($languageSheetKey !== 'lDEF') {
                                        unset($sheetData[$languageSheetKey]);
                                    }
                                }
                            }
                        }
                        /* @todo check this */
                        $languageSheetKeys = array_keys($sheetData);
                        if (is_array($languageSheetKeys)) {
                            foreach ($languageSheetKeys as $languageSheetKey) {
                                $fieldKeys = array_keys($sheetData[$languageSheetKey]);
                                if (is_array($fieldKeys)) {
                                    foreach ($fieldKeys as $fieldKey) {
                                        if (isset($contentCols[$fieldKey])) {
                                            unset($sheetData[$languageSheetKey][$fieldKey]);
                                        } else {
                                            if ($cleanLanguage) {
                                                if (is_array($sheetData[$languageSheetKey][$fieldKey])) {
                                                    $languageKeys = array_keys($sheetData[$languageSheetKey][$fieldKey]);
                                                    if (!empty($languageKeys)) {
                                                        foreach ($languageKeys as $languageKey) {
                                                            if ($languageKey !== 'vDEF') {
                                                                unset($sheetData[$languageSheetKey][$fieldKey][$languageKey]);
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($flexformArray) && is_array($flexformArray)) {
            /**
             * @var \TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools $flexformTools
             */
            $flexformTools = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools::class);
            $flexformString = $flexformTools->flexArray2Xml($flexformArray, TRUE);
        }

        return $flexformString;
    }

    /**
     * Converts flexform for a translation, to use translated values instead of default values
     *
     * @param $flexformString
     * @param $langIsoCode
     * @param $forceLanguage
     * @return string
     */
    public function convertFlexformForTranslation($flexformString, $langIsoCode, $forceLanguage = FALSE)
    {
        $flexformArray = NULL;
        if (!empty($flexformString)) {
            if (!empty($langIsoCode)) {
                $flexformArray = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($flexformString);
                if (is_array($flexformArray)) {
                    if (is_array($flexformArray['data'])) {
                        foreach ($flexformArray['data'] as &$sheetData) {
                            if (is_array($sheetData)) {
                                foreach ($sheetData['lDEF'] as $fieldName => &$fieldData) {
                                    if (is_array($fieldData)) {
                                        $fieldDataLang = NULL;
                                        $chkFieldDataLang = NULL;
                                        $issetLangValue = FALSE;
                                        $fieldLangArray = $sheetData['l' . $langIsoCode][$fieldName];
                                        if (is_array($fieldLangArray)) {
                                            $chkFieldDataLang = $fieldDataLang = $fieldLangArray['v' . $langIsoCode];
                                            if (isset($fieldLangArray['v' . $langIsoCode])) {
                                                $chkFieldDataLang = $this->parseFieldDataLang($fieldDataLang);
                                            }
                                            if (!empty($chkFieldDataLang)) {
                                                $fieldData['vDEF'] = $fieldDataLang;
                                            } else {
                                                if (isset($fieldLangArray['v' . $langIsoCode]) && $forceLanguage) {
                                                    $fieldDataLang = $fieldLangArray['v' . $langIsoCode];
                                                    $issetLangValue = TRUE;
                                                } else {
                                                    $chkFieldDataLang = $fieldDataLang = $fieldLangArray['vDEF'];
                                                    if (isset($fieldLangArray['vDEF'])) {
                                                        $chkFieldDataLang = $this->parseFieldDataLang($fieldDataLang);
                                                    }
                                                    if (!empty($chkFieldDataLang)) {
                                                        $fieldData['vDEF'] = $fieldDataLang;
                                                    } elseif (isset($fieldLangArray['vDEF'])) {
                                                        $issetLangValue = TRUE;
                                                    }
                                                }
                                            }
                                        }

                                        if (empty($chkFieldDataLang)) {
                                            $chkFieldDataLang = $fieldValueDataLang = $fieldData['v' . $langIsoCode];

                                            if (isset($fieldData['v' . $langIsoCode])) {
                                                $chkFieldDataLang = $this->parseFieldDataLang($fieldValueDataLang);
                                            }
                                            if (!empty($chkFieldDataLang)) {
                                                $fieldDataLang = $fieldValueDataLang;
                                                $fieldData['vDEF'] = $fieldValueDataLang;
                                            } elseif (isset($fieldData['v' . $langIsoCode])) {
                                                $fieldDataLang = $fieldValueDataLang;
                                                $issetLangValue = TRUE;
                                            }
                                        }

                                        if ($issetLangValue && $forceLanguage) {
                                            if ($fieldDataLang === NULL) {
                                                $fieldDataLang = '';
                                            }
                                            $fieldData['vDEF'] = $fieldDataLang;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($flexformArray) && is_array($flexformArray)) {
            /**
             * @var \TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools $flexformTools
             */
            $flexformTools = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools::class);
            $flexformString = $flexformTools->flexArray2Xml($flexformArray, TRUE);
        }

        return $flexformString;
    }

    /**
     * @param mixed $fieldDataLang
     * @return mixed
     */
    private function parseFieldDataLang($fieldDataLang)
    {
        if ($fieldDataLang !== null) {
            if ($this->canBeInterpretedAsInteger($fieldDataLang)) {
                $fieldDataLang = (int)$fieldDataLang;
            } elseif (static::canBeInterpretedAsFloat($fieldDataLang)) {
                $fieldDataLang = (float)$fieldDataLang;
            }
        }
        return $fieldDataLang;
    }

    /**
     * Returns an array with names of content columns for the given TypoScript
     *
     * @param string $typoScript
     * @return array
     */
    private function getContentColsFromTs($typoScript)
    {
        $parser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser::class);
        $parser->parse($typoScript);
        $data = $parser->setup['backend_layout.'];

        $contentCols = array();
        $contentCols[''] = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('label_select', 'sf_tv2fluidge');
        if ($data) {
            foreach ($data['rows.'] as $row) {
                foreach ($row['columns.'] as $column) {
                    $contentCols[$column['colPos']] = $column['name'];
                }
            }
        }
        return $contentCols;
    }

    /**
     * Returns the BE Layout record for the given BE Layout uid
     *
     * @param int $uid
     * @return array mixed
     */
    private function getBeLayout($uid)
    {
        $fields = '*';
        $table = 'backend_layout';
        $where = 'uid=' . (int)$uid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Returns the GridElement record for the given GE uid
     *
     * @param string|int $key
     * @return array mixed
     */
    private function getGridElement($key)
    {
        /* @todo use gridelements api for file based gridelements */
        $fields = '*';
        $table = 'tx_gridelements_backend_layout';
        $key = (int)$key;
        $where = '(uid = ' . (int)$key . ')';
        if (!$this->canBeInterpretedAsInteger($key)) {
            $key = $GLOBALS['TYPO3_DB']->fullQuoteStr($key);
            $where = '(alias = ' . (int)$key . ')';
        }
        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Returns the TO record for the given uid
     *
     * @param $uid
     * @return array mixed
     */
    private function getTvTemplateObject($uid)
    {
        $fields = 'tx_templavoila_tmplobj.*';
        $table = 'tx_templavoila_tmplobj';
        $where = 'tx_templavoila_tmplobj.uid=' . (int)$uid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Returns the DS record for the given TemplateObject uid
     *
     * @param $uid
     * @return array mixed
     */
    private function getTvDatastructure($uid)
    {
        $fields = 'tx_templavoila_datastructure.*';
        $table = 'tx_templavoila_datastructure, tx_templavoila_tmplobj';
        $where = 'tx_templavoila_tmplobj.uid=' . (int)$uid . ' AND tx_templavoila_tmplobj.datastructure = tx_templavoila_datastructure.uid';

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Returns an array with translations for the given content element uid
     *
     * @param int $uidContent
     * @return array
     */
    public function getTranslationsForContentElement($uidContent)
    {
        $fields = '*';
        $table = 'tt_content';
        $where = '(l18n_parent=' . (int)$uidContent . ')' .
            \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content');

        return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', 'sys_language_uid ASC', '');
    }

    /**
     * Returns the translation for a given content element and the given language
     *
     * @param int $uidContent
     * @param int $langUid
     * @return array
     */
    public function getTranslationForContentElementAndLanguage($uidContent, $langUid)
    {
        $fields = '*';
        $table = 'tt_content';
        $where = '(l18n_parent=' . (int)$uidContent . ') AND (sys_language_uid = ' . (int)$langUid . ')' .
            \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content');

        return $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
    }

    /**
     * Fixes localization diff sources for content elements
     *
     * @param integer $contentUid
     * @return void
     */
    public function fixContentElementLocalizationDiffSources($contentUid)
    {
        $contentUid = (int)$contentUid;
        if ($contentUid > 0) {
            $contentElement = $this->getContentElement($contentUid);
            if (!empty($contentElement) && !empty($contentElement['CType'])) {
                $translations = $this->getTranslationsForContentElement($contentUid);
                $this->fixDiffSourcesForTranslationRecords(
                    $contentElement,
                    'tt_content',
                    $translations,
                    array(
                        'CType',
                        'records',
                        'colPos',
                        'sorting',
                        'sys_language_uid'
                    )
                );
            }
        }
    }

    /**
     * Fixes localization diff source field for translations of shortcut conversions
     *
     * @param integer $pageUid
     * @param array $additionalFields
     * @return void
     */
    public function fixPageLocalizationDiffSources($pageUid, $additionalFields = array())
    {
        $pageUid = (int)$pageUid;
        if ($pageUid > 0) {
            if (!is_array($additionalFields)) {
                $additionalFields = array();
            }
            $fields = array_merge(
                array(
                    'backend_layout',
                    'backend_layout_next_level'
                ),
                $additionalFields
            );
            $pageRecord = $this->getPage($pageUid);
            if (!empty($pageRecord) && !empty($fields) && is_array($pageRecord)) {
                $translations = $this->getPageOverlays($pageUid);
                $this->fixDiffSourcesForTranslationRecords(
                    $pageRecord,
                    'pages_language_overlay',
                    $translations,
                    $fields
                );
            }
        }
    }

    /**
     * Fixes l18n diffsource fields
     *
     * @param $origRecord
     * @param $table
     * @param $translations
     * @param $fields
     */
    public function fixDiffSourcesForTranslationRecords($origRecord, $table, $translations, $fields)
    {
        if (!empty($origRecord) && !empty($table) && !empty($translations) && !empty($fields) &&
            is_array($origRecord) && is_string($table) && is_array($translations) && is_array($fields)) {
            foreach ($translations as $translation) {
                $translationUid = (int)$translation['uid'];
                $diffSource = $translation['l18n_diffsource'];
                if (!empty($diffSource) && ($translationUid > 0)) {
                    $diffSource = unserialize($diffSource);
                    foreach ($fields as $field) {
                        $diffSource[$field] = $origRecord[$field];
                    }
                    $diffSource = serialize($diffSource);

                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        $table,
                        'uid = ' . $translationUid,
                        array(
                            'l18n_diffsource' => $diffSource
                        )
                    );
                }
            }
        }
    }

    /**
     * Returns an array with UIDs of languages for the given page (default language not included)
     *
     * @param int $pageUid
     * @return array
     */
    public function getAvailablePageTranslations($pageUid)
    {
        $fields = '*';
        $table = 'pages_language_overlay';
        $where = '(pid=' . (int)$pageUid . ') ' .
            ' AND (sys_language_uid > 0)' .
            \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('pages_language_overlay');

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

        $languages = array();
        if ($res) {
            foreach ($res as $lang) {
                $languageUid = (int)$lang['sys_language_uid'];
                if ($languageUid > 0) {
                    $languages[$languageUid] = $languageUid;
                }
            }
        }
        ksort($languages);
        return $languages;
    }

    /**
     * Returns an array with UIDs of all available languages (default language not included)
     *
     * @return array
     */
    public function getAllLanguages()
    {
        $fields = 'uid';
        $table = 'sys_language';
        $where = '(1=1)' . \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('sys_language');

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

        $languages = array();
        if ($res) {
            foreach ($res as $lang) {
                $languageUid = (int)$lang['uid'];
                if ($languageUid > 0) {
                    $languages[$languageUid] = $languageUid;
                }
            }
        }
        ksort($languages);
        return $languages;
    }

    /**
     * Returns an array with UIDs of root pages
     *
     * @return array
     */
    private function getRootPages()
    {
        $fields = 'uid';
        $table = 'pages';
        $where = 'is_siteroot=1';

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Returns an array with UIDs of first level pages
     *
     * @return mixed
     */
    private function getFirstLevelPages()
    {
        $fields = 'uid';
        $table = 'pages';
        $where = 'pid=0';

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
        return $res;
    }

    /**
     * Returns an array of TV FlexForm content fields for the page with the given UID.
     * The content elements are seperated by comma
     *
     * @param int $pageUid
     * @return array
     */
    public function getTvContentArrayByLanguageAndFieldForPage($pageUid)
    {
        $fields = 'tx_templavoila_flex';
        $table = 'pages';
        $where = 'uid=' . (int)$pageUid;

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
        $tvTemplateUid = (int)$this->getTvPageTemplateUid($pageUid);
        return $this->getContentArrayByLanguageAndFieldFromFlexform($res, $tvTemplateUid);
    }

    /**
     * Returns an array of TV FlexForm content fields for the given flexform ordered by language
     *
     * @param array $result
     * @param int $tvTemplateUid
     * @return array
     */
    protected function getContentArrayByLanguageAndFieldFromFlexform($result, $tvTemplateUid)
    {
        $contentArray = array();
        $tvTemplateUid = (int)$tvTemplateUid;
        if ($tvTemplateUid > 0) {
            $contentCols = $this->getTvContentCols($tvTemplateUid, false);
            if (($result['tx_templavoila_flex'] != '') && is_array($contentCols) && !empty($contentCols)) {
                $flexFormArray = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($result['tx_templavoila_flex']);
                if (isset($flexFormArray['data']) && is_array($flexFormArray['data'])) {
                    foreach ($flexFormArray['data'] as $flexFormSheet) {
                        if (is_array($flexFormSheet)) {
                            $languageSheets = array('lDEF' => $flexFormSheet['lDEF']);
                            if (!$this->isTvDataLangDisabled($tvTemplateUid)) {
                                $languageSheets = $this->moveDefLanguageToFirstPositionOfFlexformArray($flexFormSheet, 'lDEF');
                            }

                            foreach ($languageSheets as $languageKey => $languageSheet) {
                                $contentElementArray = array();
                                foreach ($languageSheet as $fieldName => $values) {
                                    if (!empty($fieldName) && isset($contentCols[$fieldName])) {
                                        if ($this->isTvDataLangDisabled($tvTemplateUid)) {
                                            $values = array('vDEF' => $values['vDEF']);
                                        }
                                        $values = $this->moveDefLanguageToFirstPositionOfFlexformArray($values, 'vDEF');
                                        if ($values['vDEF'] !== '') {
                                            $contentElementArray[$fieldName] = $values['vDEF'];
                                        }
                                    }
                                }
                                $contentArray[$languageKey] = $contentElementArray;
                            }
                        }
                    }
                }
            }
        }

        return $contentArray;
    }

}