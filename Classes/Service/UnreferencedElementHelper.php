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
 * Helper class for handling unreferenced elements
 */
class Tx_SfTv2fluidge_Service_UnreferencedElementHelper implements t3lib_Singleton {

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
	 * Marks all unreferenced element records as deleted with a recursion level of 99
	 *
	 * @return int Number of records deleted
	 */
	public function markDeletedUnreferencedElementsRecords() {
		$pids = $this->sharedHelper->getPageIds(99);
		$allReferencedElementsArr = array();
		foreach ($pids as $pid) {
			$referencedElementsArr = $this->sharedHelper->getTemplavoilaAPIObj()->flexform_getListOfSubElementUidsRecursively ('pages', $pid, $dummyArr=array());
			$allReferencedElementsArr = array_merge($allReferencedElementsArr, $referencedElementsArr);
		}
		$allReferencedElementsArr = array_unique($allReferencedElementsArr);
		$allRecordUids = $this->getUnreferencedElementsRecords($allReferencedElementsArr);
		$countRecords = count($allRecordUids);

		$this->markDeleted($allRecordUids);

		return $countRecords;
	}

	/**
	 * Returns an array of UIDs which are not referenced on
	 * the page with the given uid (= parent id).
	 *
	 * @param	array		$allReferencedElementsArr: Array with UIDs of referenced elements
	 * @return	array		Array with UIDs of tt_content records
	 * @access	protected
	 */
	function getUnreferencedElementsRecords($allReferencedElementsArr) {
		global $TYPO3_DB, $BE_USER;

		$elementRecordsArr = array();

		$res = $TYPO3_DB->exec_SELECTquery (
			'uid',
			'tt_content',
			'uid NOT IN ('.implode(',',$allReferencedElementsArr).') AND l18n_parent NOT IN ('.implode(',',$allReferencedElementsArr).')'.
			' AND t3ver_wsid='.intval($BE_USER->workspace).
			t3lib_BEfunc::deleteClause('tt_content').
			t3lib_BEfunc::versioningPlaceholderClause('tt_content'),
			'',
			'sorting'
		);

		if ($res) {
			while(($elementRecordArr = $TYPO3_DB->sql_fetch_assoc($res)) !== FALSE) {
				$elementRecordsArr[] = $elementRecordArr['uid'];
			}
		}
		return $elementRecordsArr;
	}

	/**
	 * Marks the records with the given UIDs as deleted
	 *
	 * @param $uids
	 * @return void
	 */
	private function markDeleted($uids) {
		$where = 'uid IN (' . implode(',', $uids) . ')';
		$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', $where, array('deleted' => 1));
	}

}

?>