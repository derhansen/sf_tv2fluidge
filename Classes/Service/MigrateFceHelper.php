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
 * Description
 */
class Tx_SfTvtools_Service_MigrateFceHelper implements t3lib_Singleton {

	public function getAllFce() {
		$fields = 'tx_templavoila_tmplobj.uid, tx_templavoila_tmplobj.title';
		$table = 'tx_templavoila_datastructure, tx_templavoila_tmplobj';
		$where = 'tx_templavoila_datastructure.scope=2 AND tx_templavoila_datastructure.uid = tx_templavoila_tmplobj.datastructure
			AND tx_templavoila_datastructure.deleted=0 AND tx_templavoila_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$fces = array();
		foreach($res as $fce) {
			$fces[$fce['uid']] = $fce['title'];
		}

		return $fces;
	}


	public function getAllGe() {
		$fields = 'uid, title';
		$table = 'tx_gridelements_backend_layout';
		$where = 'deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$gridElements = array();
		foreach($res as $ge) {
			$gridElements[$ge['uid']] = $ge['title'];
		}

		return $gridElements;
	}

	public function getContentElementByUid($uid) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'uid='  . intval($uid);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');

		return $res;
	}


	public function getContentElementsByFce($uidFce) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'CType = "templavoila_pi1" AND tx_templavoila_to=' . intval($uidFce);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		return $res;
	}

	public function convertFceToGe($contentElement, $uidGe) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . intval($contentElement['uid']),
			array(
				'CType' => 'gridelements_pi1',
				'pi_flexform' => $contentElement['tx_templavoila_flex'],
				'tx_gridelements_backend_layout' => $uidGe
			)
		);
	}

	public function markFceDeleted($uidFce) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_templavoila_tmplobj', 'uid=' . intval($uidFce),
			array('deleted' => 1)
		);
	}
}

?>