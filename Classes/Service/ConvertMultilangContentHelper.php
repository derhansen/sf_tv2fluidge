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
class Tx_SfTv2fluidge_Service_ConvertMultilangContentHelper implements t3lib_Singleton {

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
	 * Clones all GridElements which are configured for "All languages" and creates a single GridElement
	 * for each page translation. Also sets the language for the original GridElement to 0 (default language)
	 *
	 * @param int $pageUid
	 * @return int Amount of cloned GridElements
	 */
	public function cloneLangAllGEs($pageUid) {
		$cloned = 0;
		$pageLanguages = $this->getAvailablePageTranslations($pageUid);
		$gridElements = $this->getCeGridElements($pageUid, -1); // All GridElements with language = all

		foreach ($gridElements as $contentElementUid) {
			$origContentElement = $this->sharedHelper->getContentElement($contentElementUid);
			foreach ($pageLanguages as $langUid) {
				$newContentElement = $origContentElement;
				unset ($newContentElement['uid']);
				$newContentElement['sys_language_uid'] = $langUid;
				$newContentElement['t3_origuid'] = $origContentElement['uid'];
				$newContentElement['l18n_parent'] = $origContentElement['uid'];
				$GLOBALS['TYPO3_DB']->exec_INSERTquery('tt_content', $newContentElement);
				$cloned += 1;
			}
			$origContentElement['sys_language_uid'] = 0;
			$origUid = $origContentElement['uid'];
			unset ($origContentElement['uid']);
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, $origContentElement);
		}
		return $cloned;
	}

	/**
	 * Rearranges content and translated content elements to the cloned/modified GridElements
	 *
	 * @param int $pageUid
	 * @return int Amount of updated content elements
	 */
	public function rearrangeContentElementsForGridelementsOnPage($pageUid) {
		$updated = 0;
		$gridElements = $this->getCeGridElements($pageUid, 0);
		foreach ($gridElements as $contentElementUid) {
			$contentElements = $this->getChildContentElements($contentElementUid);
			foreach ($contentElements as $contentElement) {
				$translations = $this->sharedHelper->getTranslationsForContentElement($contentElement['uid']);
				if (!empty($translations)) {
					foreach($translations as $translatedContentElement) {
						$localizedGridElement = $this->getLocalizedGridElement($contentElementUid,
							$translatedContentElement['sys_language_uid']);
						if ($localizedGridElement) {
							$origUid = $translatedContentElement['uid'];
							unset($translatedContentElement['uid']);
							$translatedContentElement['colPos'] = -1;
							$translatedContentElement['tx_gridelements_container'] = $localizedGridElement['uid'];
							$translatedContentElement['tx_gridelements_columns'] = $contentElement['tx_gridelements_columns'];
							$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, $translatedContentElement);
							$updated += 1;
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
					$updated += 1;
				}
			}
		}
		return $updated;
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
		$where = 'pid=' . (int)$pageUid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$languages = array();
		if ($res) {
			foreach($res as $lang) {
				$languages[] = $lang['sys_language_uid'];
			}
		}
		return $languages;
	}

	/**
	 * Returns an array with UIDs of GridElements, which are configured for "All languages"
	 *
	 * @param int $pageUid
	 * @param int $langUid
	 * @return array
	 */
	public function getCeGridElements($pageUid, $langUid) {
		$fields = 'uid';
		$table = 'tt_content';
		$where = 'pid=' . (int)$pageUid . ' AND CType = "gridelements_pi1" AND sys_language_uid = ' . (int)$langUid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$gridElements = array();
		if ($res) {
			foreach($res as $ge) {
				$gridElements[] = $ge['uid'];
			}
		}
		return $gridElements;
	}

	/**
	 * Returns the uid of the localized content element (grid element) for the given uid
	 *
	 * @param int $uidContentElement
	 * @param int $langUid
	 * @return array
	 */
	public function getLocalizedGridElement($uidContentElement, $langUid) {
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
	public function getChildContentElements($uidContent) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'tx_gridelements_container=' . (int)$uidContent . ' AND l18n_parent=0';

		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');
	}

}