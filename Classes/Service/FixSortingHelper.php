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

	const SORTING_OFFSET = 25;

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
	 * @param int $pageUid
	 * @return int
	 */
	public function fixSortingForPage($pageUid) {
		$updated = 0;
		$sorting = 0;

		$contentElements = $this->getPageContentElementsForLang($pageUid, 0);
		$contentElementList = $this->sharedHelper->getTvContentArrayForPage($pageUid);
		foreach ($contentElements as $origContentElement) {
			$sorting += self::SORTING_OFFSET;
			$origUid = (int)$origContentElement['uid'];
			$translations = $this->sharedHelper->getTranslationsForContentElement($origContentElement['uid']);
			foreach($translations as $translation) {
				$translationUid = $translation['uid'];
				unset($translation['uid']);
				$translation['sorting'] = $sorting;
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $translationUid, $translation);
				$updated += 1;
			}
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $origUid, array('sorting' => $sorting));
		}

		$pageLanguages = $this->sharedHelper->getAvailablePageTranslations($pageUid);
		foreach ($pageLanguages as $pageLanguage) {
			$contentElements = $this->getPageContentElementsForLang($pageUid, $pageLanguage);
			foreach ($contentElements as $contentElement) {
				$sorting += self::SORTING_OFFSET;
				$contentElementUid = (int)$contentElement['uid'];
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $contentElementUid, array('sorting' => $sorting));
			}
		}

		return $updated;
	}

	/**
	 * Returns all content elements for the given page, where the language is set to $langUid
	 *
	 * @param int $pageUid
	 * @param int $langUid
	 * @return mixed
	 */
	public function getPageContentElementsForLang($pageUid, $langUid) {
		$fields = '*';
		$table = 'tt_content';
		$where = '(pid=' . (int)$pageUid . ')' .
					' AND (sys_language_uid = ' . $langUid . ')' .
					t3lib_BEfunc::deleteClause('tt_content');

		return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', 'sorting ASC, uid ASC', '');
	}

}
?>