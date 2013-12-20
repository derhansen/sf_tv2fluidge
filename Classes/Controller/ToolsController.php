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
 * TV Tools Backend Controller
 */
class Tx_SfTvtools_Controller_ToolsController extends Tx_Extbase_MVC_Controller_ActionController {

	/**
	 * UnreferencedElementHelper
	 *
	 * @var Tx_SfTvtools_Service_UnreferencedElementHelper
	 */
	protected $unreferencedElementHelper;

	/**
	 * DI for UnreferencedElementHelper
	 *
	 * @param Tx_SfTvtools_Service_UnreferencedElementHelper $unreferencedElementHelper
	 * @return void
	 */
	public function injectUnreferencedElementHelper(Tx_SfTvtools_Service_UnreferencedElementHelper $unreferencedElementHelper) {
		$this->unreferencedElementHelper = $unreferencedElementHelper;
	}

	/**
	 * MigrateFceHelper
	 *
	 * @var Tx_SfTvtools_Service_MigrateFceHelper
	 */
	protected $migrateFceHelper;

	/**
	 * DI for MigrateFceHelper
	 *
	 * @param Tx_SfTvtools_Service_MigrateFceHelper $migrateFceHelper
	 * @return void
	 */
	public function injectUpdateFceHelper(Tx_SfTvtools_Service_MigrateFceHelper $migrateFceHelper) {
		$this->migrateFceHelper = $migrateFceHelper;
	}

	/**
	 * MigrateContentHelper
	 *
	 * @var Tx_SfTvtools_Service_MigrateContentHelper
	 */
	protected $migrateContentHelper;

	/**
	 * DI for MigrateContentHelper
	 *
	 * @param Tx_SfTvtools_Service_MigrateContentHelper $migrateContentHelper
	 * @return void
	 */
	public function injectContentFceHelper(Tx_SfTvtools_Service_MigrateContentHelper $migrateContentHelper) {
		$this->migrateContentHelper = $migrateContentHelper;
	}

	/**
	 * @var Tx_SfTvtools_Service_SharedHelper
	 */
	protected $sharedHelper;

	/**
	 * DI for shared helper
	 *
	 * @param Tx_SfTvtools_Service_SharedHelper $sharedHelper
	 * @return void
	 */
	public function injectSharedHelper(Tx_SfTvtools_Service_SharedHelper $sharedHelper) {
		$this->sharedHelper = $sharedHelper;
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
	 * Index action for migrateFce
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function indexMigrateFceAction($formdata = NULL) {
		$allFce = $this->migrateFceHelper->getAllFce();
		$allGe = $this->migrateFceHelper->getAllGe();

		if (isset($formdata['fce'])) {
			$uidFce = $this->migrateContentHelper->getTvDsUidForTemplate($formdata['fce']);
		} else {
			$uidFce = current(array_keys($allFce));
		}

		if (isset($formdata['ge'])) {
			$uidGe = $formdata['ge'];
		} else {
			$uidGe = current(array_keys($allGe));
		}

		// Fetch content columns from FCE and GE depending on selection (first entry if empty)
		$fceContentCols = $this->sharedHelper->getTvContentCols($uidFce);
		$geContentCols = $this->sharedHelper->getGeContentCols($uidGe);

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
		if ($fce > 0 && $ge > 0) {
			$contentElements = $this->migrateFceHelper->getContentElementsByFce($fce);
			foreach($contentElements as $contentElement) {
				$this->migrateFceHelper->migrateFceFlexformContentToGe($contentElement, $ge);

				// Migrate content to columns (if available)
				$this->migrateFceHelper->migrateContentElementsForFce($contentElement, $formdata);
			}
			if ($formdata['markdeleted']) {
				$this->migrateFceHelper->markFceDeleted($fce);
			}
		}
	}

	/**
	 * Index action for migrate content
	 *
	 * @param array $formdata
	 * @return void
	 */
	public function indexMigrateContentAction($formdata = NULL) {
		$tvtemplates = $this->migrateContentHelper->getAllTvTemplates();
		$beLayouts = $this->migrateContentHelper->getAllBeLayouts();

		if (isset($formdata['tvtemplate'])) {
			$uidTvTemplate = $this->migrateContentHelper->getTvDsUidForTemplate($formdata['tvtemplate']);
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
		$uidTvTemplate = $formdata['tvtemplate'];
		$uidBeLayout = $formdata['belayout'];
		$pageUids = $this->sharedHelper->getPageIds(99);

		$contentElementsUpdated = 0;
		$pageTemplatesUpdated = 0;
		foreach($pageUids as $pageUid) {
			if ($this->migrateContentHelper->getTvPageTemplateUid($pageUid) == $uidTvTemplate) {
				$contentElementsUpdated += $this->migrateContentHelper->migrateContentForPage($formdata, $pageUid);
			}

			// Update page template (must be called for every page, since to and next_to must be checked
			$pageTemplatesUpdated += $this->migrateContentHelper->updatePageTemplate($pageUid, $uidTvTemplate, $uidBeLayout);
		}

		$this->view->assign('contentElementsUpdated', $contentElementsUpdated);
		$this->view->assign('pageTemplatesUpdated', $pageTemplatesUpdated);
	}
}
?>