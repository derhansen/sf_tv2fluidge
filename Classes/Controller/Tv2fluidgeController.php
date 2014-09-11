<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Torben Hansen <derhansen@gmail.com>, Skyfillers GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
 * TV Tv2fluidge Backend Controller
 */
class Tx_SfTv2fluidge_Controller_Tv2fluidgeController extends Tx_Extbase_MVC_Controller_ActionController {

	/**
	 * UnreferencedElementHelper
	 *
	 * @var Tx_SfTv2fluidge_Service_UnreferencedElementHelper
	 */
	protected $unreferencedElementHelper;

	/**
	 * DI for UnreferencedElementHelper
	 *
	 * @param Tx_SfTv2fluidge_Service_UnreferencedElementHelper $unreferencedElementHelper
	 * @return void
	 */
	public function injectUnreferencedElementHelper(Tx_SfTv2fluidge_Service_UnreferencedElementHelper $unreferencedElementHelper) {
		$this->unreferencedElementHelper = $unreferencedElementHelper;
	}

	/**
	 * ReferenceElementHelper
	 *
	 * @var Tx_SfTv2fluidge_Service_ReferenceElementHelper
	 */
	protected $referenceElementHelper;

	/**
	 * DI for ReferenceElementHelper
	 *
	 * @param Tx_SfTv2fluidge_Service_ReferenceElementHelper $referenceElementHelper
	 * @return void
	 */
	public function injectReferenceElementHelper(Tx_SfTv2fluidge_Service_ReferenceElementHelper $referenceElementHelper) {
		$this->referenceElementHelper = $referenceElementHelper;
	}

	/**
	 * MigrateFceHelper
	 *
	 * @var Tx_SfTv2fluidge_Service_MigrateFceHelper
	 */
	protected $migrateFceHelper;

	/**
	 * DI for MigrateFceHelper
	 *
	 * @param Tx_SfTv2fluidge_Service_MigrateFceHelper $migrateFceHelper
	 * @return void
	 */
	public function injectUpdateFceHelper(Tx_SfTv2fluidge_Service_MigrateFceHelper $migrateFceHelper) {
		$this->migrateFceHelper = $migrateFceHelper;
	}

	/**
	 * MigrateContentHelper
	 *
	 * @var Tx_SfTv2fluidge_Service_MigrateContentHelper
	 */
	protected $migrateContentHelper;

	/**
	 * DI for MigrateContentHelper
	 *
	 * @param Tx_SfTv2fluidge_Service_MigrateContentHelper $migrateContentHelper
	 * @return void
	 */
	public function injectContentFceHelper(Tx_SfTv2fluidge_Service_MigrateContentHelper $migrateContentHelper) {
		$this->migrateContentHelper = $migrateContentHelper;
	}

	/**
	 * @var Tx_SfTv2fluidge_Service_FixSortingHelper
	 */
	protected $fixSortingHelper;

	/**
	 * DI for fix sorting helper
	 *
	 * @param Tx_SfTv2fluidge_Service_FixSortingHelper $fixSortingHelper
	 * @return void
	 */
	public function injectFixSortingHelper(Tx_SfTv2fluidge_Service_FixSortingHelper $fixSortingHelper) {
		$this->fixSortingHelper = $fixSortingHelper;
	}

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
	 * @var Tx_SfTv2fluidge_Service_ConvertMultilangContentHelper
	 */
	protected $convertMultilangContentHelper;

	/**
	 * DI for shared helper
	 *
	 * @param Tx_SfTv2fluidge_Service_ConvertMultilangContentHelper $convertMultilangContentHelper
	 * @return void
	 */
	public function injectConvertMultilangContentHelper(Tx_SfTv2fluidge_Service_ConvertMultilangContentHelper $convertMultilangContentHelper) {
		$this->convertMultilangContentHelper = $convertMultilangContentHelper;
	}

	/**
	 * Default index action for module
	 *
	 * @return void
	 */
	public function indexAction() {

	}

	/**
	 * Index action for unreferenced Elements module
	 *
	 * @return void
	 */
	public function IndexDeleteUnreferencedElementsAction() {

	}

	/**
	 * Sets all unreferenced Elements to deleted
	 *
	 * @return void
	 */
	public function deleteUnreferencedElementsAction() {
		$numRecords = $this->unreferencedElementHelper->markDeletedUnreferencedElementsRecords();
		$this->view->assign('numRecords', $numRecords);
	}

	/**
	 * Index action for migrate reference elements
	 *
	 * @return void
	 */
	public function indexConvertReferenceElementsAction() {

	}

	/**
	 * Migrates all reference elements to 'insert records' elements
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function convertReferenceElementsAction($formdata = NULL) {
		$useParentUidForTranslations = false;
		if (intval($formdata['useparentuidfortranslations']) === 1) {
			$useParentUidForTranslations = true;
		}
		$numRecords = $this->referenceElementHelper->convertReferenceElements($useParentUidForTranslations);
		$this->view->assign('numRecords', $numRecords);
	}

	/**
	 * Index action for migrateFce
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function indexMigrateFceAction($formdata = NULL) {
		if ($this->sharedHelper->getTemplavoilaStaticDsIsEnabled()) {
			$allFce = $this->migrateFceHelper->getAllFileFce();
		}
		else {
			$allFce = $this->migrateFceHelper->getAllDbFce();
		}
		$allGe = $this->migrateFceHelper->getAllGe();

		if (isset($formdata['fce'])) {
			$uidFce = intval($formdata['fce']);
		} else {
			$uidFce = current(array_keys($allFce));
		}

		if (isset($formdata['ge'])) {
			$geKey = $formdata['ge'];
		} else {
			$geKey = current(array_keys($allGe));
		}

		// Fetch content columns from FCE and GE depending on selection (first entry if empty)
		if ($uidFce > 0) {
			$fceContentCols = $this->sharedHelper->getTvContentCols($uidFce);
		} else {
			$fceContentCols = NULL;
		}

		if ($this->sharedHelper->canBeInterpretedAsInteger($geKey)) {
			$geKey = (int)$geKey;
			if ($geKey <= 0) {
				$geKey = 0;
			}
		}

		if (!empty($geKey)) {
			$geContentCols = $this->sharedHelper->getGeContentCols($geKey);
		} else {
			$geContentCols = NULL;
		}

		$this->view->assign('fceContentCols', $fceContentCols);
		$this->view->assign('geContentCols', $geContentCols);
		$this->view->assign('allFce', $allFce);
		$this->view->assign('allGe', $allGe);
		$this->view->assign('formdata', $formdata);

		// Redirect to migrateContentAction when submit button pressed
		if (isset($formdata['startAction'])) {
			$this->redirect('migrateFce',NULL,NULL,array('formdata' => $formdata));
		}
	}


	/**
	 * Migrates content from FCE to Grid Element
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function migrateFceAction($formdata) {
		$fce = $formdata['fce'];
		$ge = $formdata['ge'];
		if ($this->sharedHelper->canBeInterpretedAsInteger($ge)) {
			$ge = (int)$ge;
			if ($ge <= 0) {
				$ge = 0;
			}
		}

		$fcesConverted = 0;
		$contentElementsUpdated = 0;

		if ($fce > 0 && !empty($ge)) {
			$contentElements = $this->migrateFceHelper->getContentElementsByFce($fce);
			foreach($contentElements as $contentElement) {
				$fcesConverted++;
				$this->migrateFceHelper->migrateFceFlexformContentToGe($contentElement, $ge);

				// Migrate content to GridElement columns (if available)
				$contentElementsUpdated += $this->migrateFceHelper->migrateContentElementsForFce($contentElement, $formdata);
			}
			if ($formdata['markdeleted']) {
				$this->migrateFceHelper->markFceDeleted($fce);
			}
		}

		$this->view->assign('contentElementsUpdated', $contentElementsUpdated);
		$this->view->assign('fcesConverted', $fcesConverted);
	}

	/**
	 * Index action for migrate content
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function indexMigrateContentAction($formdata = NULL) {
		if ($this->sharedHelper->getTemplavoilaStaticDsIsEnabled()) {
			$tvtemplates = $this->migrateContentHelper->getAllFileTvTemplates();
		}
		else {
			$tvtemplates = $this->migrateContentHelper->getAllDbTvTemplates();
		}
		$beLayouts = $this->migrateContentHelper->getAllBeLayouts();

		if (isset($formdata['tvtemplate'])) {
			$uidTvTemplate = intval($formdata['tvtemplate']);
		} else {
			$uidTvTemplate = current(array_keys($tvtemplates));
		}

		if (isset($formdata['belayout'])) {
			$uidBeLayout = $formdata['belayout'];
		} else {
			$uidBeLayout = current(array_keys($beLayouts));
		}

		// Fetch content columns from TV and BE layouts depending on selection (first entry if empty)
		$tvContentCols = $this->sharedHelper->getTvContentCols($uidTvTemplate);
		$beContentCols = $this->sharedHelper->getBeLayoutContentCols($uidBeLayout);

		$this->view->assign('tvContentCols', $tvContentCols);
		$this->view->assign('beContentCols', $beContentCols);
		$this->view->assign('tvtemplates', $tvtemplates);
		$this->view->assign('belayouts', $beLayouts);
		$this->view->assign('formdata', $formdata);

		// Redirect to migrateContentAction when submit button pressed
		if (isset($formdata['startAction'])) {
			$this->redirect('migrateContent',NULL,NULL,array('formdata' => $formdata));
		}
	}

	/**
	 * Does the content migration recursive for all pages
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function migrateContentAction($formdata) {
		$uidTvTemplate = (int)$formdata['tvtemplate'];
		$uidBeLayout = (int)$formdata['belayout'];

		$contentElementsUpdated = 0;
		$pageTemplatesUpdated = 0;

		if ($uidTvTemplate > 0 && $uidBeLayout > 0) {
			$pageUids = $this->sharedHelper->getPageIds(99);

			foreach($pageUids as $pageUid) {
				if ($this->migrateContentHelper->getTvPageTemplateUid($pageUid) == $uidTvTemplate) {
					$contentElementsUpdated += $this->migrateContentHelper->migrateContentForPage($formdata, $pageUid);
				}

				// Update page template (must be called for every page, since to and next_to must be checked
				$pageTemplatesUpdated += $this->migrateContentHelper->updatePageTemplate($pageUid, $uidTvTemplate, $uidBeLayout);
			}
		}

		$this->view->assign('contentElementsUpdated', $contentElementsUpdated);
		$this->view->assign('pageTemplatesUpdated', $pageTemplatesUpdated);
	}

	/**
	 * Index action for convert multilingual content
	 *
	 * @return void
	 */
	public function indexConvertMultilangContentAction() {

	}

	/**
	 * Does the content conversion for all GridElements on all pages
	 *
	 * @return void
	 */
	public function convertMultilangContentAction() {
		$pageUids = $this->sharedHelper->getPageIds(99);

		$numGEs = 0;
		$numCEs = 0;

		foreach($pageUids as $pageUid) {
			$numGEs += $this->convertMultilangContentHelper->cloneLangAllGEs($pageUid);
			$numCEs += $this->convertMultilangContentHelper->rearrangeContentElementsForGridelementsOnPage($pageUid);
		}

		$this->view->assign('numGEs', $numGEs);
		$this->view->assign('numCEs', $numCEs);
	}

	/**
	 * Index action for fix sorting
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function indexFixSortingAction($formdata = NULL) {
		$cancel = FALSE;

		if ($formdata['fixOptions'] == 'singlePage' && $formdata['pageUid'] == '' && isset($formdata['startAction'])) {
			$cancel = TRUE;
			$this->view->assign('pageUidMissing', TRUE);
		}

		$this->view->assign('formdata', $formdata);

		// Redirect to fixSortingAction when submit button pressed
		if (isset($formdata['startAction']) && $cancel == FALSE) {
			$this->redirect('fixSorting',NULL,NULL,array('formdata' => $formdata));
		}
	}

	/**
	 * Action for fix sorting
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function fixSortingAction($formdata) {
		$numUpdated = 0;
		if ($formdata['fixOptions'] == 'singlePage') {
			$numUpdated = $this->fixSortingHelper->fixSortingForPage($formdata['pageUid']);
		} else {
			$pageUids = $this->sharedHelper->getPageIds(99);
			foreach($pageUids as $pageUid) {
				$numUpdated += $this->fixSortingHelper->fixSortingForPage($pageUid);
			}
		}
		$this->view->assign('numUpdated', $numUpdated);
	}

}
?>