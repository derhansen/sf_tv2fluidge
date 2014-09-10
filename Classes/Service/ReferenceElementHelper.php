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
	 * @param bool $useParentUidForTranslations
	 * @return int Number of records deleted
	 */
	public function convertReferenceElements($useParentUidForTranslations = false) {
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
							$this->convertTranslationsOfShortcut($newContentUid, $contentUid, $useParentUidForTranslations);

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
	 * @param bool $useParentUidForTranslations
	 * @return void
	 */
	protected function convertTranslationsOfShortcut($contentUid, $targetUid, $useParentUidForTranslations = false) {
		$contentUid = (int)$contentUid;
		$targetUid = (int)$targetUid;
		if ($useParentUidForTranslations) {
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
			$translations = $this->sharedHelper->getTranslationsForContentElement($targetUid);
			if (!empty($translations)) {
				foreach ($translations as $translation) {
					$translationTargetUid = (int)$translation['uid'];
					$translationTargetSysLanguageUid = (int)$translation['sys_language_uid'];
					if (($translationTargetUid > 0) && ($translationTargetSysLanguageUid > 0)) {
						$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
							'tt_content',
							'(l18n_parent = ' . $contentUid . ')' .
							' AND (sys_language_uid = '  . $translationTargetSysLanguageUid . ')' .
							t3lib_BEfunc::deleteClause('tt_content'),
							array(
								'CType'   => 'shortcut',
								'records' => 'tt_content_' . $translationTargetUid,
							)
						);
					}
				}
			}
		}

		$this->fixLocalizationDiffSources($contentUid);
	}

	/**
	 * Fixes localization diff source field for translations of shortcut conversions
	 *
	 * @param integer $contentUid
	 * @param integer $targetUid
	 * @param bool $useParentUidForTranslations
	 * @return void
	 */
	protected function fixLocalizationDiffSources($contentUid) {
		$contentUid = (int)$contentUid;
		$contentElement = $this->sharedHelper->getContentElement($contentUid);
		if (!empty($contentElement) && !empty($contentElement['CType']) && !empty($contentElement['records'])) {
			$translations = $this->sharedHelper->getTranslationsForContentElement($contentUid);

			foreach ($translations as $translation) {
				$translationUid = (int)$translation['uid'];
				$diffSource = $translation['l18n_diffsource'];
				if (!empty($diffSource) && ($translationUid > 0)) {
					$diffSource = unserialize($diffSource);
					$diffSource['CType'] = $contentElement['CType'];
					$diffSource['records'] = $contentElement['records'];
					$diffSource = serialize($diffSource);

					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						'tt_content',
						'uid = ' . $translationUid,
						array(
							'l18n_diffsource' => $diffSource
						)
					);
				}
			}
		}
	}

}
