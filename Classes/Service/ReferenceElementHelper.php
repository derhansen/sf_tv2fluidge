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
	 * @param bool $useParentUidForTranslatedRecords
	 * @return int Number of records deleted
	 */
	public function convertReferenceElements($useParentUidForTranslatedRecords = false) {
		$GLOBALS['TCA']['tt_content']['ctrl']['hideAtCopy'] = 0;
		$GLOBALS['TCA']['tt_content']['ctrl']['prependAtCopy'] = 0;

		$pids = $this->sharedHelper->getPageIds(99);
		$numRecords = 0;
		foreach ($pids as $pid) {
			$tvContentArray = $this->sharedHelper->getTvContentArrayForPage($pid);
			foreach ($tvContentArray as $field => $contentUidString) {
				$contentUids = t3lib_div::trimExplode(',', $contentUidString);
				$position = 1;
				foreach ($contentUids as $contentUid) {
					$contentElement = $this->sharedHelper->getContentElement($contentUid);
					if ($this->sharedHelper->isContentElementAvailable($contentUid)) {
						if ($contentElement['pid'] != $pid) {
							$newContentUid = $this->convertToLocalCopy($pid, $field, $position);
							$this->convertToShortcut($newContentUid, $contentUid);
							$this->convertTranslatedRecordsToShortcut($newContentUid, $contentUid, $useParentUidForTranslatedRecords);

							++$numRecords;
						}
						++$position;
					}
				}
			}
		}

		return $numRecords;
	}

	/**
	 * Converts reference to local copy
	 *
	 * @param integer $pageUid
	 * @param string $field
	 * @param integer $position
	 * @return integer
	 */
	protected function convertToLocalCopy($pageUid, $field, $position) {
		$flexformPointerString = 'pages:' . $pageUid . ':sDEF:lDEF:' . $field . ':vDEF:' . $position;
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
		$targetUid = (int)$targetUid;
		$contentUid = (int)$contentUid;
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tt_content',
			'uid = ' . $contentUid,
			array(
				'CType'   => 'shortcut',
				'records' => 'tt_content_' . $targetUid,
			)
		);
	}

	/**
	 * Converts translated records to shortcut
	 *
	 * @param integer $contentUid
	 * @param integer $targetUid
	 * @param bool $useParentUidForTranslatedRecords
	 * @return void
	 */
	protected function convertTranslatedRecordsToShortcut($contentUid, $targetUid, $useParentUidForTranslatedRecords = false) {
		if ($useParentUidForTranslatedRecords) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tt_content',
				'(l18n_parent =' . $contentUid . ')' .
				t3lib_BEfunc::deleteClause('tt_content'),
				array(
					'CType'   => 'shortcut',
					'records' => 'tt_content_' . $targetUid,
				)
			);
		} else {
			$translatedRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'uid, sys_language_uid',
				'tt_content',
				'(l18n_parent =' . $targetUid . ')' .
				t3lib_BEfunc::deleteClause('tt_content')
			);
			if (!empty($translatedRecords)) {
				foreach ($translatedRecords as $translatedRecord) {
					$translatedTargetUid = (int)$translatedRecord['uid'];
					$translatedTargetSysLanguageUid = (int)$translatedRecord['sys_language_uid'];
					if (($translatedTargetUid > 0) && ($translatedTargetSysLanguageUid > 0)) {
						$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
							'tt_content',
							'(l18n_parent = ' . $contentUid . ')' .
							' AND (sys_language_uid = '  . $translatedTargetSysLanguageUid . ')' .
							t3lib_BEfunc::deleteClause('tt_content'),
							array(
								'CType'   => 'shortcut',
								'records' => 'tt_content_' . $translatedTargetUid,
							)
						);
					}
				}
			}
		}
	}

}
