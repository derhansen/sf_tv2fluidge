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
 * Helper class for handling TV FCE to Grid Element content migration
 */
class Tx_SfTv2fluidge_Service_MigrateFceHelper implements t3lib_Singleton {

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
	 * Returns an array of all TemplaVoila flexible content elements stored as file
	 *
	 * @return array
	 */
	public function getAllFileFce() {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['templavoila']);
		tx_templavoila_staticds_tools::readStaticDsFilesIntoArray($extConf);
		$staticDsFiles = array();
		foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['templavoila']['staticDataStructures'] as $staticDataStructure) {
			if ($staticDataStructure['scope'] == tx_templavoila_datastructure::SCOPE_FCE) {
				$staticDsFiles[] = $staticDataStructure['path'];
			}
		}
		$quotedStaticDsFiles = $GLOBALS['TYPO3_DB']->fullQuoteArray($staticDsFiles, 'tx_templavoila_tmplobj');

		$fields = 'tx_templavoila_tmplobj.uid, tx_templavoila_tmplobj.title';
		$table = 'tx_templavoila_tmplobj';
		$where = 'tx_templavoila_tmplobj.datastructure IN(' . implode(',', $quotedStaticDsFiles) . ')
			AND tx_templavoila_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$fces = array();
		foreach($res as $fce) {
			$fces[$fce['uid']] = $fce['title'];
		}

		return $fces;
	}

	/**
	 * Returns an array of all TemplaVoila flexible content elements stored in database
	 *
	 * @return array
	 */
	public function getAllDbFce() {
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

	/**
	 * Returns an array of all Grid Elements
	 *
	 * @return array
	 */
	public function getAllGe() {
		$fields = 'uid, title, alias';
		$table = 'tx_gridelements_backend_layout';
		$where = 'deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$gridElements = array();
		foreach($res as $ge) {
			$geKey = $ge['uid'];
			if (!empty($ge['alias'])) {
				$geKey = $ge['alias'];
			}

			$gridElements[$geKey] = $ge['title'];
		}

		return $gridElements;
	}

	/**
	 * Returns the tt_content record by uid
	 *
	 * @param int $uid
	 * @return mixed
	 */
	public function getContentElementByUid($uid) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'uid='  . intval($uid);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');

		return $res;
	}

	/**
	 * Returns all tt_content elements which contains a TemplaVoila FCE with the given uid
	 *
	 * @param int $uidFce
	 * @return mixed
	 */
	public function getContentElementsByFce($uidFce) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'CType = "templavoila_pi1" AND tx_templavoila_to=' . intval($uidFce);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		return $res;
	}

	/**
	 * Migrated the content from a TemplaVoila FCE to the given Grid Element
	 *
	 * @param array $contentElement
	 * @param string|int $geKey
	 * @return void
	 */
	public function migrateFceFlexformContentToGe($contentElement, $geKey) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . intval($contentElement['uid']),
			array(
				'CType' => 'gridelements_pi1',
				'pi_flexform' => $contentElement['tx_templavoila_flex'],
				'tx_gridelements_backend_layout' => $geKey
			)
		);
	}

	/**
	 * Marks the TemplaVoila FCE with the given uid as deleted
	 *
	 * @param int $uidFce
	 * @return void
	 */
	public function markFceDeleted($uidFce) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_templavoila_tmplobj', 'uid=' . intval($uidFce),
			array('deleted' => 1)
		);
	}

	/**
	 * Migrates all content elements for the FCE with the given uid to the selected column positions
	 *
	 * @param array $contentElement
	 * @param array $formdata
	 * @return int Number of Content elements updated
	 */
	public function migrateContentElementsForFce($contentElement, $formdata) {
		$fieldMapping = $this->sharedHelper->getFieldMappingArray($formdata, 'tv_col_', 'ge_col_');
		$tvContentArray = $this->sharedHelper->getTvContentArrayForContent($contentElement['uid']);
		$pageUid = $contentElement['pid'];

		$count = 0;
		$sorting = 0;
		foreach ($tvContentArray as $key => $contentUidString) {
			if (array_key_exists($key, $fieldMapping) && $contentUidString != '') {
				$contentUids = explode(',', $contentUidString);
				foreach ($contentUids as $contentUid) {
					$myContentElement = $this->sharedHelper->getContentElement($contentUid);
					if ($myContentElement['pid'] == $pageUid) {
						$this->sharedHelper->updateContentElementForGe($contentUid, $contentElement['uid'], $fieldMapping[$key], $sorting);
					}
					$sorting += 25;
					$count++;
				}
			}
		}

		return $count;
	}
}

?>