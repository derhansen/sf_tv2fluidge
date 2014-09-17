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

require_once(t3lib_extMgm::extPath('templavoila').'class.tx_templavoila_api.php');

/**
 * Class with methods used in other helpers/controllers
 */
class Tx_SfTv2fluidge_Service_SharedHelper implements t3lib_Singleton {

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
	public function __construct() {
		$this->templavoilaAPIObj = t3lib_div::makeInstance ('tx_templavoila_api');
		if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sf_tv2fluidge'])) {
			$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sf_tv2fluidge']);
		}
	}

	/**
	 * Returns the TemplaVoila Api Object
	 *
	 * @return object|tx_templavoila_api
	 */
	public function getTemplavoilaAPIObj() {
		return $this->templavoilaAPIObj;
	}

	/**
	 * Returns if static data structure is enabled
	 *
	 * @return boolean
	 */
	public function getTemplavoilaStaticDsIsEnabled() {
		$conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['templavoila']);

		return $conf['staticDS.']['enable'];
	}

	/**
	 * Returns the depth limit of pages to migrate
	 *
	 * @return int
	 */
	public function getPagesDepthLimit() {
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
	public function getIncludeNonRootPagesIsEnabled() {
		return (intval($this->extConf['includeNonRootPages']) === 1);
	}

	/**
	 * Returns an array of page uids up to the given amount recursionlevel
	 *
	 * @param int $depth	if not supplied, the extension setting will be used
	 * @return array
	 */
	public function getPageIds($depth = 0) {
		if ($depth <= 0) {
			$depth = $this->getPagesDepthLimit();
		}

		/**
		 * @var t3lib_queryGenerator $tree
		 */
		$tree = t3lib_div::makeInstance('t3lib_queryGenerator');

		$startPages = array();

		if ($this->getIncludeNonRootPagesIsEnabled()) {
			$startPages = $this->getFirstLevelPages();
		} else {
			$startPages = $this->getRootPages();
		}

		$allPids = '';
		foreach($startPages as $startPage) {
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

		$result = array_unique(explode(',', $allPids));
		return $result;
	}

	/**
	 * @param string| int $value
	 * @return bool
	 */
	public function canBeInterpretedAsInteger($value) {
		$canBeInterpretedAsInteger = NULL;

		if (class_exists('t3lib_utility_Math')) {
			$canBeInterpretedAsInteger = t3lib_utility_Math::canBeInterpretedAsInteger($value);
		} else {
			$canBeInterpretedAsInteger= t3lib_div::testInt($value);
		}
		return $canBeInterpretedAsInteger;
	}

	/**
	 * Returns the uid of the TemplaVoila page template for the given page uid
	 *
	 * @param $pageUid
	 * @return int
	 */
	public function getTvPageTemplateUid($pageUid) {
		$pageRecord = $this->getPageRecord($pageUid);
		$tvTemplateObjectUid = 0;
		if ($pageRecord['tx_templavoila_to'] != '' && $pageRecord['tx_templavoila_to'] != 0) {
			$tvTemplateObjectUid = $pageRecord['tx_templavoila_to'];
		} else {
			$rootLine = t3lib_beFunc::BEgetRootLine($pageRecord['uid'],'', TRUE);
			foreach($rootLine as $rootLineRecord) {
				$myPageRecord = t3lib_beFunc::getRecordWSOL('pages', $rootLineRecord['uid']);
				if ($myPageRecord['tx_templavoila_next_to']) {
					$tvTemplateObjectUid = $myPageRecord['tx_templavoila_next_to'];
					break;
				}
			}
		}
		return $tvTemplateObjectUid;
	}

	/**
	 * Returns the page record for the given page uid
	 *
	 * @param $uid
	 * @return array mixed
	 */
	public function getPageRecord($uid) {
		$fields = '*';
		$table = 'pages';
		$where = 'uid=' . (int)$uid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $res;
	}

	/**
	 * Returns an array with names of content columns for the given TemplaVoila Templateobject
	 *
	 * @param int $uidTvDs
	 * @param bool $addSelectLabel
	 * @return array
	 */
	public function getTvContentCols($uidTvDs, $addSelectLabel = true) {
		if ($this->getTemplavoilaStaticDsIsEnabled()) {
			$toRecord = $this->getTvTemplateObject($uidTvDs);
			$path = t3lib_div::getFileAbsFileName($toRecord['datastructure']);
			$flexform = simplexml_load_file($path);
		}
		else {
			$dsRecord = $this->getTvDatastructure($uidTvDs);
			$flexform = simplexml_load_string($dsRecord['dataprot']);
		}

		$contentCols = array();

		if (!empty($flexform)) {
			$elements = $flexform->xpath("ROOT/el/*");
			if ($addSelectLabel) {
				$contentCols[''] = Tx_Extbase_Utility_Localization::translate('label_select', 'sf_tv2fluidge');
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
	 * Returns an array with names of content columns for the given backend layout
	 *
	 * @param int $uidBeLayout
	 * @return array
	 */
	public function getBeLayoutContentCols($uidBeLayout) {
		$beLayoutRecord = $this->getBeLayout($uidBeLayout);
		return $this->getContentColsFromTs($beLayoutRecord['config']);
	}

	/**
	 * Returns an array with names of content columns for the given gridelement
	 *
	 * @param string|int $geKey
	 * @return array
	 */
	public function getGeContentCols($geKey) {
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
	public function getFieldMappingArray($formdata, $from, $to) {
		$tvIndex = array();
		$beValue = array();
		foreach($formdata as $key => $data) {
			if (substr($key, 0, 7) == $from) {
				$tvIndex[] = $data;
			}
			if (substr($key, 0, 7) == $to) {
				$beValue[] = $data;
			}
		}
		$fieldMapping = array();
		if (count($tvIndex) == count($beValue)) {
			for ($i=0; $i<=count($tvIndex); $i++) {
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
	public function getTvContentArrayForPage($pageUid) {
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
	public function getTvContentArrayForContent($contentUid) {
		$fields = 'tx_templavoila_flex, tx_templavoila_to';
		$table = 'tt_content';
		$where = 'uid=' . (int)$contentUid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		$tvTemplateUid = (int)$res['tx_templavoila_to'];
		return $this->getContentArrayFromFlexform($res, $tvTemplateUid);
	}

	/**
	 * Checks if content element is still available (not deleted)
	 *
	 * @param int $contentUid
	 * @return boolean
	 */
	public function isContentElementAvailable($contentUid) {
		$where = 'uid=' . (int)$contentUid . t3lib_BEfunc::deleteClause('tt_content');

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
	public function updateContentElementColPos($uid, $newColPos, $sorting = 0) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . intval($uid), array(
			'colPos' => $newColPos,
			'sorting' => $sorting
		));
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'l18n_parent=' . intval($uid), array(
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
	public function updateContentElementForGe($uid, $geContainerUid, $geColPos, $sorting = 0) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . intval($uid), array(
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
	public function getPage($uid) {
		$fields = '*';
		$table = 'pages';
		$where = 'uid=' . (int)$uid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $res;
	}

	/**
	 * Return the tt_content element record for the given uid
	 *
	 * @param int $uid
	 * @return array
	 */
	public function getContentElement($uid) {
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
	public function getLanguagesIsoCodes() {
		$languagesIsoCodes = array();

		if (t3lib_extMgm::isLoaded('static_info_tables')) {
			$fields = 'sys_language.uid AS langUid, static_languages.lg_iso_2 AS isoCode';
			$tables = 'sys_language, static_languages';
			$where = '(sys_language.static_lang_isocode = static_languages.uid)'
						. t3lib_BEfunc::deleteClause('sys_language');

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
	private function getContentArrayFromFlexform($result, $tvTemplateUid) {
		$contentArray = array();
		$tvTemplateUid = (int)$tvTemplateUid;
		if ($tvTemplateUid > 0) {
			$contentCols = $this->getTvContentCols($tvTemplateUid, false);
			if (($result['tx_templavoila_flex'] != '') && is_array($contentCols) && !empty($contentCols)) {
				$flexFormArray = t3lib_div::xml2array($result['tx_templavoila_flex']);
				$languageSheets = $this->moveDefLanguageFirstFlexformArray($flexFormArray['data']['sDEF'], 'lDEF');

				foreach ($languageSheets as $languageSheet) {
					if (is_array($languageSheet)) {
						foreach ($languageSheet as $fieldName => $values) {
							if (!empty($fieldName) && isset($contentCols[$fieldName])) {
								$values = $this->moveDefLanguageFirstFlexformArray($values, 'vDEF');
								$this->addValuesToContentArray($contentArray, $fieldName, $values);
							}
						}
					}
				}
			}
		}

		return $contentArray;
	}

	private function moveDefLanguageFirstFlexformArray($flexformArray, $firstElementKey) {
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
	 * @param array $contentArray
	 * @param string $fieldName
	 * @param array $values
	 */
	private function addValuesToContentArray(&$contentArray, $fieldName, $values) {

		foreach($values as $languageValues) {
			$fieldValues = array();
			if (!empty($contentArray[$fieldName])) {
				$fieldValues = explode(',', $contentArray[$fieldName]);
			}

			$languageValues = t3lib_div::trimExplode(',', $languageValues, TRUE);
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
	 * Returns an array with names of content columns for the given TypoScript
	 *
	 * @param string $typoScript
	 * @return array
	 */
	private function getContentColsFromTs($typoScript) {
		$parser = t3lib_div::makeInstance('t3lib_TSparser');
		$parser->parse($typoScript);
		$data = $parser->setup['backend_layout.'];

		$contentCols = array();
		$contentCols[''] = Tx_Extbase_Utility_Localization::translate('label_select', 'sf_tv2fluidge');
		foreach($data['rows.'] as $row) {
			foreach($row['columns.'] as $column) {
				$contentCols[$column['colPos']] = $column['name'];
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
	private function getBeLayout($uid) {
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
	private function getGridElement($key) {
		$fields = '*';
		$table = 'tx_gridelements_backend_layout';
		$where = '';
		if ($this->canBeInterpretedAsInteger($key)) {
			$key = (int)$key;
			$where = "(uid = " . $key . ")";
		} else {
			$key = $GLOBALS['TYPO3_DB']->fullQuoteStr($key);
			$where = "(alias = " . $key . ")";
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
	private function getTvTemplateObject($uid) {
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
	private function getTvDatastructure($uid) {
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
	public function getTranslationsForContentElement($uidContent) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'l18n_parent=' . (int)$uidContent;

		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
	}

	/**
	 * @param int $uidContent
	 * @param int $langUid
	 * @return array
	 */
	public function getTranslationForContentElementAndLanguage($uidContent, $langUid) {
		$fields = '*';
		$table = 'tt_content';
		$where = '(l18n_parent=' . (int)$uidContent . ') AND (sys_language_uid = ' . (int)$langUid . ')' .
					t3lib_BEfunc::deleteClause('tt_content');

		return $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
	}

	/**
	 * Fixes localization diff source field for translations of shortcut conversions
	 *
	 * @param integer $contentUid
	 * @return void
	 */
	public function fixLocalizationDiffSources($contentUid) {
		$contentUid = (int)$contentUid;
		$contentElement = $this->getContentElement($contentUid);
		if (!empty($contentElement) && !empty($contentElement['CType'])) {
			$translations = $this->getTranslationsForContentElement($contentUid);

			foreach ($translations as $translation) {
				$translationUid = (int)$translation['uid'];
				$diffSource = $translation['l18n_diffsource'];
				if (!empty($diffSource) && ($translationUid > 0)) {
					$diffSource = unserialize($diffSource);
					$diffSource['CType'] = $contentElement['CType'];
					$diffSource['records'] = $contentElement['records'];
					$diffSource['colPos'] = $contentElement['colPos'];
					$diffSource['sorting'] = $contentElement['sorting'];
					$diffSource = serialize($diffSource);

					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						'tt_content',
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
	public function getAvailablePageTranslations($pageUid) {
		$fields = '*';
		$table = 'pages_language_overlay';
		$where = '(pid=' . (int)$pageUid . ') '.
			' AND (sys_language_uid > 0)' . t3lib_BEfunc::deleteClause('pages_language_overlay');;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$languages = array();
		if ($res) {
			foreach($res as $lang) {
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
	 * @param int $pageUid
	 * @return array
	 */
	public function getAllLanguages() {
		$fields = 'uid';
		$table = 'sys_language';
		$where = '(1=1)' . t3lib_BEfunc::deleteClause('sys_language');

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$languages = array();
		if ($res) {
			foreach($res as $lang) {
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
	private function getRootPages() {
		$fields = 'uid';
		$table = 'pages';
		$where = 'is_siteroot=1';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
		return $res;
	}

	private function getFirstLevelPages() {
		$fields = 'uid';
		$table = 'pages';
		$where = 'pid=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
		return $res;
	}
}

?>