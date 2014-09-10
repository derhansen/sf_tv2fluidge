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
class Tx_SfTv2fluidge_Service_ReferenceElementHelper implements t3lib_Singleton {

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
	 * Converts all reference elements to 'insert records' elements with a recursion level of 99
	 *
	 * @return int Number of records deleted
	 */
	public function convertReferenceElements() {
		$GLOBALS['TCA']['tt_content']['ctrl']['hideAtCopy'] = 0;
		$GLOBALS['TCA']['tt_content']['ctrl']['prependAtCopy'] = 0;

		$pids = $this->sharedHelper->getPageIds(99);
		$numRecords = 0;
		foreach ($pids as $pid) {
			$tvContentArray = $this->sharedHelper->getTvContentArrayForPage($pid);
			$numRecords += $this->convertTvContentArrayToReferenceElements($tvContentArray, $pid);
		}

		return $numRecords;
	}

	/**
	 * converts an array of content elements to references, if they are references
	 * also handles references inside fce
	 *
	 * @param array $tvContentArray
	 * @param $pid
	 * @return int
	 */
	protected function convertTvContentArrayToReferenceElements($tvContentArray, $pid, $fceUid = 0) {
		$numRecords = 0;
		$pid = (int)$pid;
		$fceUid = (int)$fceUid;
		foreach ($tvContentArray as $field => $contentUidString) {
			$contentUids = t3lib_div::trimExplode(',', $contentUidString);
			$position = 1;
			foreach ($contentUids as $contentUid) {
				$contentUid = (int)$contentUid;
				$contentElement = $this->sharedHelper->getContentElement($contentUid);
				if ($this->sharedHelper->isContentElementAvailable($contentUid)) {
					if (intval($contentElement['pid']) != $pid) {
						$newContentUid = NULL;
						if ($fceUid > 0) {
							$newContentUid = $this->convertFceToLocalCopy($fceUid, $field, $position);
						} else {
							$newContentUid = $this->convertPageCeToLocalCopy($pid, $field, $position);
						}

						$newContentUid = (int)$newContentUid;
						if ($newContentUid > 0) {
							$this->convertToShortcut($newContentUid, $contentUid);
							++$numRecords;
						}
					} else {
						$fceContentElements = $this->sharedHelper->getTvContentArrayForContent($contentUid);
						if (count($fceContentElements) > 0) {
							$numRecords += $this->convertTvContentArrayToReferenceElements($fceContentElements, $pid, $contentUid);
						}
					}
					++$position;
				}
			}
		}

		return $numRecords;
	}

	/**
	 * Converts page content element reference to local copy
	 *
	 * @param integer $pageUid
	 * @param string $field
	 * @param integer $position
	 * @return integer
	 */
	protected function convertPageCeToLocalCopy($pageUid, $field, $position) {
		$flexformPointerString = 'pages:' . (int)$pageUid . ':sDEF:lDEF:' . $field . ':vDEF:' . (int)$position;
		return $this->convertFlexformPointerStringToLocalCopy($flexformPointerString);
	}

	/**
	 * Converts fce reference to local copy
	 *
	 * @param integer $pageUid
	 * @param string $field
	 * @param integer $position
	 * @return integer
	 */
	protected function convertFceToLocalCopy($contentUid, $field, $position) {
		$flexformPointerString = 'tt_content:' . (int)$contentUid . ':sDEF:lDEF:' . $field . ':vDEF:' . (int)$position;
		return $this->convertFlexformPointerStringToLocalCopy($flexformPointerString);
	}

	/**
	 * converts flexform pointer string to local copy
	 *
	 * @param string $flexformPointerString
	 * @return mixed
	 */
	protected function convertFlexformPointerStringToLocalCopy($flexformPointerString) {
		$sourcePointer = $this->sharedHelper->getTemplavoilaAPIObj()->
			flexform_getPointerFromString($flexformPointerString);

		$contentUid = $this->sharedHelper->getTemplavoilaAPIObj()->
			copyElement($sourcePointer, $sourcePointer);
		$this->sharedHelper->getTemplavoilaAPIObj()->
			unlinkElement($sourcePointer);

		return $contentUid;
	}

	/**
	 * Converts element to shortcut
	 *
	 * @param integer $contentUid
	 * @param integer $targetUid
	 * @return void
	 */
	protected function convertToShortcut($contentUid, $targetUid) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tt_content',
			'uid = ' . (int)$contentUid,
			array(
				'CType'   => 'shortcut',
				'records' => 'tt_content_' . (int)$targetUid,
			)
		);
	}

}
