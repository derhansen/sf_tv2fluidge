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
 * Helper class for handling TV content column migration to Fluid backend layouts
 */
class Tx_SfTv2fluidge_Service_MigrateContentHelper implements t3lib_Singleton {

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
	 * Returns an array of all TemplaVoila page templates stored as file
	 *
	 * @return array
	 */
	public function getAllFileTvTemplates() {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['templavoila']);
		tx_templavoila_staticds_tools::readStaticDsFilesIntoArray($extConf);
		$staticDsFiles = array();
		foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['templavoila']['staticDataStructures'] as $staticDataStructure) {
			if ($staticDataStructure['scope'] == tx_templavoila_datastructure::SCOPE_PAGE) {
				$staticDsFiles[] = $staticDataStructure['path'];
			}
		}
		$quotedStaticDsFiles = $GLOBALS['TYPO3_DB']->fullQuoteArray($staticDsFiles, 'tx_templavoila_tmplobj');

		$fields = 'tx_templavoila_tmplobj.uid, tx_templavoila_tmplobj.title';
		$table = 'tx_templavoila_tmplobj';
		$where = 'tx_templavoila_tmplobj.datastructure IN(' . implode(',', $quotedStaticDsFiles) . ')
			AND tx_templavoila_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$templates = array();
		foreach($res as $fce) {
			$templates[$fce['uid']] = $fce['title'];
		}

		return $templates;
	}

	/**
	 * Returns an array of all TemplaVoila page templates stored in database
	 *
	 * @return array
	 */
	public function getAllDbTvTemplates() {
		$fields = 'tx_templavoila_tmplobj.uid, tx_templavoila_tmplobj.title';
		$table = 'tx_templavoila_datastructure, tx_templavoila_tmplobj';
		$where = 'tx_templavoila_datastructure.scope=1 AND tx_templavoila_datastructure.uid = tx_templavoila_tmplobj.datastructure
			AND tx_templavoila_datastructure.deleted=0 AND tx_templavoila_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$templates = array();
		foreach($res as $fce) {
			$templates[$fce['uid']] = $fce['title'];
		}

		return $templates;
	}

	/**
	 * Returns an array of all Grid Elements
	 *
	 * @return array
	 */
	public function getAllBeLayouts() {
		$fields = 'uid, title';
		$table = 'backend_layout';
		$where = 'deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$beLayouts = array();
		foreach($res as $ge) {
			$beLayouts[$ge['uid']] = $ge['title'];
		}

		return $beLayouts;
	}

	/**
	 * Returns the uid of the DS for the given template
	 *
	 * @param int $uidTemplate
	 * @return int
	 */
	public function getTvDsUidForTemplate($uidTemplate) {
		$fields = 'datastructure';
		$table = 'tx_templavoila_tmplobj';
		$where = 'uid=' . (int)$uidTemplate;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $res['datastructure'];
	}

	/**
	 * Migrates templavoila flexform of page to db fields with the given pageUid to the selected column positions
	 *
	 * @param array $formdata
	 * @param int $pageUid
	 * @return int Number of Content elements updated
	 */
	public function migrateTvFlexformForPage($formdata, $pageUid) {
		$pageUid = (int)$pageUid;
		$localizationDiffSourceFields = array();
		$flexformConversionOption = $formdata['convertflexformoption'];
		$flexformFieldPrefix = $formdata['flexformfieldprefix'];
		$pageRecord = $this->sharedHelper->getPage($pageUid);
		$tvTemplateUid = (int)$this->sharedHelper->getTvPageTemplateUid($pageUid);
		$isTvDataLangDisabled = $this->sharedHelper->isTvDataLangDisabled($tvTemplateUid);
		$pageFlexformString = $pageRecord['tx_templavoila_flex'];

		if (!empty($pageFlexformString)) {
			$langIsoCodes = $this->sharedHelper->getLanguagesIsoCodes();
			$allAvailableLanguages = $this->sharedHelper->getAvailablePageTranslations($pageUid);
			if (empty($allAvailableLanguages)) {
				$allAvailableLanguages = array();
			}
			array_unshift($allAvailableLanguages, 0);

			foreach ($allAvailableLanguages as $langUid) {
				$flexformString = $pageFlexformString;
				$langUid = (int)$langUid;
				if (($flexformConversionOption !== 'exclude')) {
					if (t3lib_extMgm::isLoaded('static_info_tables')) {
						if ($langUid > 0) {
							$forceLanguage = ($flexformConversionOption === 'forceLanguage');
							if (!$isTvDataLangDisabled) {
								$flexformString = $this->sharedHelper->convertFlexformForTranslation($flexformString, $langIsoCodes[$langUid], $forceLanguage);
							}
						}
					}
				}

				$flexformString = $this->sharedHelper->cleanFlexform($flexformString, $tvTemplateUid);

				if (!empty($flexformString)) {
					$flexformArray = t3lib_div::xml2array($flexformString);
					if (is_array($flexformArray['data'])) {
						foreach ($flexformArray['data'] as $sheetData) {
							if (is_array($sheetData['lDEF'])) {
								foreach ($sheetData['lDEF'] as $fieldName => $fieldData) {
									if (isset($fieldData['vDEF'])) {
										$fieldValue = (string)$fieldData['vDEF'];
										$fullFieldName = $flexformFieldPrefix . $fieldName;
										if ($langUid === 0) {
											$fields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages');
											if (!empty($fields[$fullFieldName])) {
												if ($GLOBALS['TYPO3_DB']->quoteStr($fullFieldName, 'pages') === $fullFieldName) {
													$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
														'pages',
														'uid=' . intval($pageUid),
														array(
															$fullFieldName => $fieldValue
														)
													);
												}
											}
										} elseif ($langUid > 0) {
											$fields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages_language_overlay');
											if (!empty($fields[$fullFieldName])) {
												if ($GLOBALS['TYPO3_DB']->quoteStr($fullFieldName, 'pages_language_overlay') === $fullFieldName) {
													$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
														'pages_language_overlay',
														'(pid=' . intval($pageUid) . ')'
														. ' AND (sys_language_uid = ' . $langUid . ')' .
														t3lib_BEfunc::deleteClause('pages_language_overlay'),

														array(
															$fullFieldName => $fieldValue
														)
													);
												}
												$localizationDiffSourceFields[] = $fullFieldName;
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
		$this->sharedHelper->fixPageLocalizationDiffSources($pageUid, $localizationDiffSourceFields);
	}

	/**
	 * Migrates all content elements for the page with the given pageUid to the selected column positions
	 *
	 * @param array $formdata
	 * @param int $pageUid
	 * @return int Number of Content elements updated
	 */
	public function migrateContentForPage($formdata, $pageUid) {
		$fieldMapping = $this->sharedHelper->getFieldMappingArray($formdata, 'tv_col_', 'be_col_');
		$tvContentArray = $this->sharedHelper->getTvContentArrayForPage($pageUid);

		$count = 0;
		$sorting = 0;
		foreach ($tvContentArray as $key => $contentUidString) {
			if (array_key_exists($key, $fieldMapping) && $contentUidString != '') {
				$contentUids = explode(',', $contentUidString);
				foreach ($contentUids as $contentUid) {
					$contentElement = $this->sharedHelper->getContentElement($contentUid);
					if ($contentElement['pid'] == $pageUid) {
						$this->sharedHelper->updateContentElementColPos($contentUid, $fieldMapping[$key], $sorting);
						$this->sharedHelper->fixContentElementLocalizationDiffSources($contentUid);
					}
					$sorting += 25;
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * Marks the TemplaVoila Template with the given uid as deleted
	 *
	 * @param int $uidTvTemplate
	 * @return void
	 */
	public function markTvTemplateDeleted($uidTvTemplate) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_templavoila_tmplobj', 'uid=' . intval($uidTvTemplate),
			array('deleted' => 1)
		);
	}

	/**
	 * Sets the backend layout uid for the page with the given uid if the value of the TV template matches
	 * the uid of the given uidTvTemplate
	 *
	 * @param int $pageUid
	 * @param int $UidTvTemplate
	 * @param int $uidBeLayout
	 * @return int Number of page templates updated
	 */
	public function updatePageTemplate($pageUid, $UidTvTemplate, $uidBeLayout) {
		$pageRecord = $this->sharedHelper->getPage($pageUid);
		$updateFields = array();
		$count = 0;
		if ($pageRecord['tx_templavoila_to'] > 0 && $pageRecord['tx_templavoila_to'] == $UidTvTemplate) {
			$updateFields['backend_layout'] = $uidBeLayout;
			$updateFields['backend_layout_next_level'] = $uidBeLayout;
		}
		if ($pageRecord['tx_templavoila_next_to'] > 0 && $pageRecord['tx_templavoila_next_to'] == $UidTvTemplate) {
			$updateFields['backend_layout_next_level'] = $uidBeLayout;
		}
		if (count($updateFields) > 0) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('pages', 'uid=' . intval($pageUid), $updateFields);
			$count++;
		}
		return $count;
	}

}

?>
