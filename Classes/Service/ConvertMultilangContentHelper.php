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
	 * Returns an array with UIDs of GridElements, which are configured for "All languages"
	 *
	 * @param int $pageUid
	 * @return array
	 */
	public function getGEsWithLangAll($pageUid) {
		$fields = 'uid';
		$table = 'tt_content';
		$where = 'pid=' . (int)$pageUid . ' AND CType = "gridelements_pi1" AND sys_language_uid = -1';

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
	 * Clones all GridElements which are configured for "All languages" and creates a single GridElement
	 * for each page translation. Also sets the language for the original GridElement to 0 (default language)
	 *
	 * @param $pageUid
	 */
	public function cloneLangAllGEs($pageUid) {
		$pageLanguages = $this->getAvailablePageTranslations($pageUid);
		$gridElements = $this->getGEsWithLangAll($pageUid);

		foreach ($gridElements as $contentElement) {
			$origContentElement = $this->sharedHelper->getContentElement($contentElement);
			foreach ($pageLanguages as $langUid) {
				$newContentElement = $origContentElement;
				unset ($newContentElement['uid']);
				$newContentElement['sys_language_uid'] = $langUid;
				$newContentElement['t3_origuid'] = $origContentElement['uid'];
				$newContentElement['l18n_parent'] = $origContentElement['uid'];
				$GLOBALS['TYPO3_DB']->exec_INSERTquery('tt_content', $newContentElement);
			}
			$origContentElement['sys_language_uid'] = 0;
			$origUid = $origContentElement['uid'];
			unset ($origContentElement['uid']);
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, $origContentElement);
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

}