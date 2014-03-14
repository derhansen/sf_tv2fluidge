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
	 * Returns the TemplaVoila Api Object
	 *
	 * @return object|tx_templavoila_api
	 */
	public function getTemplavoilaAPIObj() {
		return $this->templavoilaAPIObj;
	}

	/**
	 * Returns an array of page uids up to the given amount recursionlevel
	 *
	 * @param int $depth
	 * @return array
	 */
	public function getPageIds($depth) {
		$tree = t3lib_div::makeInstance('t3lib_queryGenerator');
		$pids = $tree->getTreeList(1, $depth, 0, 1);
		return explode(',', $pids);
	}

	/**
	 * Returns an array with names of content columns for the given TemplaVoila Templateobject
	 *
	 * @param int $uidTvDs
	 * @return array
	 */
	public function getTvContentCols($uidTvDs) {
		$dsRecord = $this->getTvDatastructure($uidTvDs);
		$flexform = simplexml_load_string($dsRecord['dataprot']);
		$elements = $flexform->xpath("ROOT/el/*");

		$contentCols = array();
		$contentCols[''] = Tx_Extbase_Utility_Localization::translate('label_select', 'sf_tv2fluidge');
		foreach ($elements as $element) {
			if ($element->tx_templavoila->eType == 'ce') {
				$contentCols[$element->getName()] = (string)$element->tx_templavoila->title;
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
	 * @param int $uidGe
	 * @return array
	 */
	public function getGeContentCols($uidGe) {
		$geRecord = $this->getGridElement($uidGe);
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
		return $this->getContentArrayFromFlexform($res);
	}

	/**
	 * Returns an array of TV FlexForm content fields for the tt_content element with the given uid.
	 * The content elements are seperated by comma
	 *
	 * @param int $contentUid
	 * @return array
	 */
	public function getTvContentArrayForContent($contentUid) {
		$fields = 'tx_templavoila_flex';
		$table = 'tt_content';
		$where = 'uid=' . (int)$contentUid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $this->getContentArrayFromFlexform($res);
	}

	/**
	 * Creates a shortcut tt_content record for the given contentUid
	 *
	 * @param int $pageUid
	 * @param int $contentUid
	 * @param int $colPos
	 * @param int $sorting
	 * @return void
	 */
	public function createShortcutToContent($pageUid, $contentUid, $colPos, $sorting = 0) {
		$fields = array();
		$fields['pid'] = $pageUid;
		$fields['tstamp'] = time();
		$fields['sorting'] = $sorting;
		$fields['CType'] = 'shortcut';
		$fields['records'] = 'tt_content_' . $contentUid;
		$fields['colPos'] = $colPos;

		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tt_content', $fields);
	}

	/**
	 * Creates a shortcut tt_content record for the given contentUid
	 *
	 * @param int $pageUid
	 * @param int $contentUid
	 * @param int $geContainer
	 * @param int $colPos
	 * @param int $sorting
	 * @return void
	 */
	public function createShortcutToContentForGe($pageUid, $contentUid, $geContainer, $colPos, $sorting = 0) {
		$fields = array();
		$fields['pid'] = $pageUid;
		$fields['tstamp'] = time();
		$fields['CType'] = 'shortcut';
		$fields['records'] = 'tt_content_' . $contentUid;
		$fields['colPos'] = -1;
		$fields['sorting'] = $sorting;
		$fields['tx_gridelements_container'] = $geContainer;
		$fields['tx_gridelements_columns'] = $colPos;

		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tt_content', $fields);
	}

	/**
	 * Sets the given colpos for the content element with the given uid
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
	 * Returns an array of TV FlexForm content fields for the given flexform
	 *
	 * @param $result
	 * @return array
	 */
	private function getContentArrayFromFlexform($result) {
		$contentArray = array();

		if ($result['tx_templavoila_flex'] != '') {
			$localFlexform = simplexml_load_string($result['tx_templavoila_flex']);
			$elements = $localFlexform->xpath("data/sheet/language/*");

			foreach($elements as $element) {
				$contentArray[(string)$element->attributes()->index] = (string)$element->value;
			}
		}
		return $contentArray;
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
	 * @param int $uid
	 * @return array mixed
	 */
	private function getGridElement($uid) {
		$fields = '*';
		$table = 'tx_gridelements_backend_layout';
		$where = 'uid=' . (int)$uid;

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
}

?>