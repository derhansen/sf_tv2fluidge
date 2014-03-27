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
 * Class with methods for fixing the sorting of translated elements
 */
class Tx_SfTv2fluidge_Service_FixSortingHelper implements t3lib_Singleton {

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
	 * Fixes the sorting of all translated content elements for the given page uid
	 *
	 * @todo Add check if new sorting value of translated content element would exceeds int field limit.
	 *
	 * @param int $pageUid
	 * @return int
	 */
	public function fixSortingForPage($pageUid) {
		$updated = 0;

		$contentElements = $this->getPageContentElementsForDefaultLang($pageUid);
		foreach ($contentElements as $origContentElement) {
			$translations = $this->sharedHelper->getTranslationsForContentElement($origContentElement['uid']);
			foreach($translations as $translation) {
				$origUid = $translation['uid'];
				unset($translation['uid']);
				$translation['sorting'] = $origContentElement['sorting'] * $translation['sys_language_uid'];
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, $translation);
				$updated += 1;
			}
		}
		return $updated;
	}

	/**
	 * Returns all content elements for the given page, where the language is set to the default language
	 *
	 * @param int $pageUid
	 * @return mixed
	 */
	public function getPageContentElementsForDefaultLang($pageUid) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'pid=' . (int)$pageUid . ' AND sys_language_uid = 0';

		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

	}

}
?>