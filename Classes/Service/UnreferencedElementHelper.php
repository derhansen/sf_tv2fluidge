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
 * Description
 */
class Tx_SfTvtools_Service_UnreferencedElementHelper implements t3lib_Singleton {

	/**
	 * @var tx_templavoila_api
	 */
	protected $templavoilaAPIObj;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->templavoilaAPIObj = t3lib_div::makeInstance ('tx_templavoila_api');
	}

	/**
	 * Marks all unreferenced element records as deleted with a recursion level of 99
	 *
	 * @todo: make level configurable in Module
	 * @return int Number of records deleted
	 */
	public function markDeletedUnreferencedElementsRecords() {
		$allRecordUids = array();
		$pids = $this->getPageIds(99);
		foreach ($pids as $pid) {
			$records = $this->getUnreferencedElementsRecords($pid);
			$allRecordUids = array_merge($allRecordUids, $records);
		}
		$countRecords = count($allRecordUids);

		$this->markDeleted($allRecordUids);

		return $countRecords;
	}

	/**
	 * Returns an array of UIDs which are not referenced on
	 * the page with the given uid (= parent id).
	 *
	 * @param	integer		$pid: Parent id of the content elements (= uid of the page)
	 * @return	array		Array with UIDs of tt_content records
	 * @access	protected
	 */
	function getUnreferencedElementsRecords($pid) {
		global $TYPO3_DB;

		$elementRecordsArr = array();
		$referencedElementsArr = $this->templavoilaAPIObj->flexform_getListOfSubElementUidsRecursively ('pages', $pid, $dummyArr=array());

		$res = $TYPO3_DB->exec_SELECTquery (
			'uid',
			'tt_content',
			'pid='.intval($pid).
			(count($referencedElementsArr) ? ' AND uid NOT IN ('.implode(',',$referencedElementsArr).')' : '').
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

	/**
	 * Returns an array of page IDs up to the given amount recursionlevel
	 *
	 * @param int $depth
	 * @return array
	 */
	private function getPageIds($depth) {
		$tree = t3lib_div::makeInstance('t3lib_queryGenerator');
		$pids = $tree->getTreeList(1, $depth, 0, 1);
		return explode(',', $pids);
	}

}

?>