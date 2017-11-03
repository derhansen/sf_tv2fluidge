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
class Tx_SfTv2fluidge_Service_FixSortingHelper implements \TYPO3\CMS\Core\SingletonInterface
{

    const SORTING_OFFSET = 25;

    /**
     * @var Tx_SfTv2fluidge_Service_SharedHelper
     */
    protected $sharedHelper;

    /**
     * @var t3lib_refindex
     */
    protected $refIndex;

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
     * @param \TYPO3\CMS\Core\Database\ReferenceIndex t3lib_refindex
     * @return void
     */
    public function injectRefIndex(\TYPO3\CMS\Core\Database\ReferenceIndex $refIndex)
    {
        $this->refIndex = $refIndex;
    }

    /**
     * Fixes the sorting of all translated content elements for the given page uid
     *
     * @param int $pageUid
     * @return int
     */
    public function fixSortingForPage($pageUid)
    {
        $sorting = 0;
        $pageUid = (int)$pageUid;

        $contentElementArray = $this->sharedHelper->getTvContentArrayForPage($pageUid);
        $this->sharedHelper->fixPageLocalizationDiffSources($pageUid);
        $modifiedSortingCeUids = $this->fixSortingForContentArray($contentElementArray, $pageUid, $sorting);
        $updated = count($modifiedSortingCeUids);

        $remainingContentElements = $this->getRemainingPageContentElements($pageUid, $modifiedSortingCeUids);
        foreach ($remainingContentElements as $remainingContentElement) {
            $remainingContentElementUid = (int)$remainingContentElement['uid'];
            if ($remainingContentElementUid > 0) {
                $sorting += self::SORTING_OFFSET;
                $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $remainingContentElementUid, array('sorting' => $sorting));
                $this->refIndex->updateRefIndexTable('tt_content', $remainingContentElementUid);
                $this->sharedHelper->fixContentElementLocalizationDiffSources($remainingContentElementUid);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param array $contentArray
     * @param int $pageUid
     * @param int $sorting
     * @return array
     */
    protected function fixSortingForContentArray($contentArray, $pageUid, &$sorting)
    {
        $pageUid = (int)$pageUid;
        $modifiedSortingCeUids = array();

        if (is_array($contentArray)) {
            $contentElementUids = $this->getContentElementUids($contentArray);
            foreach ($contentElementUids as $ceUid) {
                $contentElement = $this->sharedHelper->getContentElement($ceUid);
                $contentTvFlexform = NULL;

                $contentElementPageUid = (int)$contentElement['pid'];
                if ($contentElementPageUid === $pageUid) {
                    $contentTvFlexform = $contentElement['tx_templavoila_flex'];
                    $contentElementUid = (int)$contentElement['uid'];
                    if ($contentElementUid > 0) {
                        $sorting += self::SORTING_OFFSET;
                        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $contentElementUid, array('sorting' => $sorting));
                        $modifiedSortingCeUids[] = $contentElementUid;

                        $translations = $this->sharedHelper->getTranslationsForContentElement($contentElementUid);
                        if (!empty($translations)) {
                            foreach ($translations as $translation) {
                                $translationUid = $translation['uid'];
                                if ($translationUid > 0) {
                                    $sorting += self::SORTING_OFFSET;
                                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . $translationUid, array('sorting' => $sorting));
                                    $modifiedSortingCeUids[] = $translationUid;
                                    $this->refIndex->updateRefIndexTable('tt_content', $translationUid);
                                }
                            }
                        }

                        $this->refIndex->updateRefIndexTable('tt_content', $contentElementUid);
                        $this->sharedHelper->fixContentElementLocalizationDiffSources($contentElementUid);

                        if (!empty($contentTvFlexform)) {
                            $contentArrayForFce = $this->sharedHelper->getTvContentArrayForContent($contentElementUid);
                            $fceModifiedCeUids = $this->fixSortingForContentArray($contentArrayForFce, $pageUid, $sorting);
                            $modifiedSortingCeUids = array_merge($modifiedSortingCeUids, $fceModifiedCeUids);
                        }
                    }
                }
            }
        }
        return $modifiedSortingCeUids;
    }

    /**
     * @param array $contentArray
     * @return array<int>
     */
    protected function getContentElementUids($contentArray)
    {
        $contentElementUidValues = array();
        foreach ($contentArray as $contentElementList) {
            $fieldContentUidValues = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $contentElementList, TRUE);
            if (is_array($fieldContentUidValues)) {
                foreach ($fieldContentUidValues as $fieldContentUid) {
                    $fieldContentUid = (int)$fieldContentUid;
                    if ($fieldContentUid > 0) {
                        $contentElementUidValues[] = $fieldContentUid;
                    }
                }
            }
        }
        return $contentElementUidValues;
    }

    /**
     * Returns all content elements for the given page
     *
     * @param int $pageUid
     * @param array $sortedContentElements
     * @return mixed
     */
    public function getRemainingPageContentElements($pageUid, $sortedContentElements)
    {
        $sortedContentElements = array_map('intval', $sortedContentElements);
        $contentElements = array();
        if (is_array($sortedContentElements)) {
            $notInWhere = '';
            if (!empty($sortedContentElements)) {
                $notInWhere = ' AND (uid NOT IN (' . implode(',', $sortedContentElements) . '))';
            }

            $fields = '*';
            $table = 'tt_content';
            $where = '(pid=' . (int)$pageUid . ')' .
                $notInWhere .
                \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content');

            $contentElements = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', 'sorting ASC, sys_language_uid ASC, uid ASC', '');
        }
        return $contentElements;
    }

}