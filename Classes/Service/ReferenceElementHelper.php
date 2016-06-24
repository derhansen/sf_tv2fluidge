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
class Tx_SfTv2fluidge_Service_ReferenceElementHelper implements \TYPO3\CMS\Core\SingletonInterface
{

	/**
	 * @var Tx_SfTv2fluidge_Service_SharedHelper
	 */
	protected $sharedHelper;

	/**
	 * @var \TYPO3\CMS\Core\Database\ReferenceIndex
	 */
	protected $refIndex;

	/**
	 * @var bool
	 */
	protected $useParentUidForTranslations = false;

	/**
	 * @var bool
	 */
	protected $useAllLangIfDefaultLangIsReferenced = false;

	/**
	 * DI for shared helper
	 *
	 * @param Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper
	 * @return void
	 */
	public function injectSharedHelper(Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper)
	{
		$this->sharedHelper = $sharedHelper;
	}

	/**
	 * DI for \TYPO3\CMS\Core\Database\ReferenceIndex
	 *
	 * @param \TYPO3\CMS\Core\Database\ReferenceIndex \TYPO3\CMS\Core\Database\ReferenceIndex
	 * @return void
	 */
	public function injectRefIndex(\TYPO3\CMS\Core\Database\ReferenceIndex $refIndex)
	{
		$this->refIndex = $refIndex;
	}

	/**
	 * @param array $formdata
	 */
	public function initFormData($formdata)
	{
		$this->useParentUidForTranslations         = (intval($formdata['useparentuidfortranslations']) === 1);
		$this->useAllLangIfDefaultLangIsReferenced = (intval($formdata['usealllangifdefaultlangisreferenced']) === 1);
	}

	/**
	 * Converts all reference elements to 'insert records' elements with a recursion level of 99
	 *
	 * @param bool $useParentUidForTranslations
	 * @return int Number of records deleted
	 */
	public function convertReferenceElements()
	{
		$GLOBALS['TCA']['tt_content']['ctrl']['hideAtCopy']    = 0;
		$GLOBALS['TCA']['tt_content']['ctrl']['prependAtCopy'] = 0;

		$pids       = $this->sharedHelper->getPageIds();
		$numRecords = 0;
		foreach ($pids as $pid)
		{
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
	 * @param int $pid
	 * @param int $fceUid
	 * @return int
	 */
	protected function convertTvContentArrayToReferenceElements($tvContentArray, $pid, $fceUid = 0)
	{
		$numRecords = 0;
		$pid        = (int)$pid;
		$fceUid     = (int)$fceUid;
		foreach ($tvContentArray as $field => $contentUidString)
		{
			$contentUids = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $contentUidString);
			$position    = 1;
			foreach ($contentUids as $contentUid)
			{
				$contentUid        = (int)$contentUid;
				$contentElement    = $this->sharedHelper->getContentElement($contentUid);
				$contentElementPid = (int)$contentElement['pid'];
				if ($this->sharedHelper->isContentElementAvailable($contentUid))
				{
					$numRecords += $this->convertReferencesToShortcut($contentUid, $contentElementPid, $pid, $field, $position, $fceUid);
					++$position;
				}
			}
		}

		return $numRecords;
	}

	/**
	 * converts reference content elements, either current content element or sub content elements (FCE)
	 * including translations to a insert record element
	 *
	 * @param int $contentUid
	 * @param int $contentElementPid
	 * @param int $pid
	 * @param string $field
	 * @param int $position
	 * @param int $fceUid
	 * @return int
	 */
	protected function convertReferencesToShortcut($contentUid, $contentElementPid, $pid, $field, $position, $fceUid = 0)
	{
		$numRecords        = 0;
		$contentElementPid = (int)$contentElementPid;
		$pid               = (int)$pid;
		$fceUid            = (int)$fceUid;
		if ($contentElementPid !== $pid)
		{
			$numRecords += $this->convertReferenceToShortcut($contentUid, $pid, $field, $position, $fceUid);
		}
		else
		{
			$numRecords += $this->convertReferencesInsideFceToShortcut($contentUid, $pid);
		}

		return $numRecords;
	}

	/**
	 * converts a reference content element - either current content element or sub content elements (FCE)
	 * including translations to a insert record element
	 *
	 * @param int $contentUid
	 * @param int $pid
	 * @param string $field
	 * @param int $position
	 * @param int $fceUid
	 * @return int
	 */
	protected function convertReferenceToShortcut($contentUid, $pid, $field, $position, $fceUid = 0)
	{
		$numRecords    = 0;
		$newContentUid = null;
		if ($fceUid > 0)
		{
			$newContentUid = $this->convertFceToLocalCopy($fceUid, $field, $position);
		}
		else
		{
			$newContentUid = $this->convertPageCeToLocalCopy($pid, $field, $position);
		}

		$newContentUid = (int)$newContentUid;
		if ($newContentUid > 0)
		{
			$this->convertToShortcut($newContentUid, $contentUid);

			if (!$this->convertShortcutToAllLangShortCut($newContentUid, $contentUid))
			{
				$this->convertTranslationsOfShortcut($newContentUid, $contentUid);
			}

			$this->refIndex->updateRefIndexTable('tt_content', $newContentUid);
			++$numRecords;
		}

		return $numRecords;
	}

	protected function convertShortcutToAllLangShortCut($contentUid, $targetUid)
	{
		$contentUid         = (int)$contentUid;
		$targetUid          = (int)$targetUid;
		$convertedToAllLang = false;
		if (($contentUid > 0) && ($targetUid > 0))
		{
			$contentElement = $this->sharedHelper->getContentElement($targetUid);

			if ($this->useAllLangIfDefaultLangIsReferenced && !empty($contentElement))
			{
				$ceSysLanguageUid = (int)$contentElement['sys_language_uid'];
				$languageParent   = (int)$contentElement['l18n_parent'];
				if ((($ceSysLanguageUid === -1) || ($ceSysLanguageUid === 0))
					&& ($languageParent === 0)
				)
				{

					$this->convertToAllLang($contentUid);
					$this->deleteTranslations($contentUid);
					$this->sharedHelper->fixContentElementLocalizationDiffSources($contentUid);
					$convertedToAllLang = true;
				}
			}
		}


		return $convertedToAllLang;
	}

	/**
	 * converts a references inside FCE to insert record elements
	 *
	 * @param int $contentUid
	 * @param int $pid
	 * @return int
	 */
	protected function convertReferencesInsideFceToShortcut($contentUid, $pid)
	{
		$numRecords         = 0;
		$fceContentElements = $this->sharedHelper->getTvContentArrayForContent($contentUid);
		if (count($fceContentElements) > 0)
		{
			$numRecords += $this->convertTvContentArrayToReferenceElements($fceContentElements, $pid, $contentUid);
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
	protected function convertPageCeToLocalCopy($pageUid, $field, $position)
	{
		$flexformPointerString = 'pages:'.(int)$pageUid.':sDEF:lDEF:'.$field.':vDEF:'.(int)$position;

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
	protected function convertFceToLocalCopy($contentUid, $field, $position)
	{
		$flexformPointerString = 'tt_content:'.(int)$contentUid.':sDEF:lDEF:'.$field.':vDEF:'.(int)$position;

		return $this->convertFlexformPointerStringToLocalCopy($flexformPointerString);
	}

	/**
	 * converts flexform pointer string to local copy
	 *
	 * @param string $flexformPointerString
	 * @return mixed
	 */
	protected function convertFlexformPointerStringToLocalCopy($flexformPointerString)
	{
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
	protected function convertToShortcut($contentUid, $targetUid)
	{
		$targetUid  = (int)$targetUid;
		$contentUid = (int)$contentUid;
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tt_content',
			'uid = '.$contentUid,
			array(
				'CType'   => 'shortcut',
				'records' => 'tt_content_'.$targetUid,
			)
		);
	}

	/**
	 * converts content element to all language content element
	 *
	 * @param int $contentUid
	 */
	protected function convertToAllLang($contentUid)
	{
		$contentUid = (int)$contentUid;
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tt_content',
			'uid = '.$contentUid,
			array(
				'sys_language_uid' => -1,
			)
		);
	}

	/**
	 * deletes translation of content element
	 *
	 * @param int $contentUid
	 */
	protected function deleteTranslations($contentUid)
	{
		$contentUid = (int)$contentUid;
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'tt_content',
			'(l18n_parent ='.$contentUid.')'.
			\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content')
		);
	}

	/**
	 * Converts translated records to shortcut
	 *
	 * @param integer $contentUid
	 * @param integer $targetUid
	 * @return void
	 */
	protected function convertTranslationsOfShortcut($contentUid, $targetUid)
	{
		if ($this->useParentUidForTranslations)
		{
			$this->convertTranslationsToShortCutUsingParentUid($contentUid, $targetUid);
		}
		else
		{
			$this->convertTranslationsToShortCutUsingTranslationUid($contentUid, $targetUid);
		}

		$this->sharedHelper->fixContentElementLocalizationDiffSources($contentUid);
	}

	/**
	 * Converts translated records to shortcut using uid of parent content element as record reference
	 *
	 * @param integer $contentUid
	 * @param integer $targetUid
	 * @return void
	 */
	protected function convertTranslationsToShortCutUsingParentUid($contentUid, $targetUid)
	{
		$contentUid = (int)$contentUid;
		$targetUid  = (int)$targetUid;
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tt_content',
			'(l18n_parent ='.$contentUid.')'.
			\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content'),
			array(
				'CType'   => 'shortcut',
				'records' => 'tt_content_'.$targetUid,
			)
		);
	}

	/**
	 * Converts translated records to shortcut using uid of translation content element as record reference
	 *
	 * @param integer $contentUid
	 * @param integer $targetUid
	 * @return void
	 */
	protected function convertTranslationsToShortCutUsingTranslationUid($contentUid, $targetUid)
	{
		$contentUid   = (int)$contentUid;
		$targetUid    = (int)$targetUid;
		$translations = $this->sharedHelper->getTranslationsForContentElement($targetUid);
		if (!empty($translations))
		{
			foreach ($translations as $translation)
			{
				$translationTargetUid            = (int)$translation['uid'];
				$translationTargetSysLanguageUid = (int)$translation['sys_language_uid'];
				if (($translationTargetUid > 0) && ($translationTargetSysLanguageUid > 0))
				{
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						'tt_content',
						'(l18n_parent = '.$contentUid.')'.
						' AND (sys_language_uid = '.$translationTargetSysLanguageUid.')'.
						\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content'),
						array(
							'CType'   => 'shortcut',
							'records' => 'tt_content_'.$translationTargetUid,
						)
					);
				}
			}

			$this->updateRefIndexTranslations($contentUid);
		}
	}

	/**
	 * updates sys_refindex for translation content elements
	 *
	 * @param $contentUid
	 */
	protected function updateRefIndexTranslations($contentUid)
	{
		$updateRefIndexTranslations = $this->sharedHelper->getTranslationsForContentElement($contentUid);
		if (!empty($updateRefIndexTranslations))
		{
			foreach ($updateRefIndexTranslations as $updateRefIndexTranslation)
			{
				$updateRefIndexTranslationUid = (int)$updateRefIndexTranslation['uid'];
				if ($updateRefIndexTranslationUid > 0)
				{
					$this->refIndex->updateRefIndexTable('tt_content', $updateRefIndexTranslationUid);
				}
			}
		}
	}
}
