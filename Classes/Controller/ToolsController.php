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
	 * Default index action for module
	 *
	 * @return void
	 */
	public function indexAction() {

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
	 * @return void
	 */
	public function indexMigrateFceAction() {
		$allFce = $this->migrateFceHelper->getAllFce();
		$allGe = $this->migrateFceHelper->getAllGe();

		$this->view->assign('allFce', $allFce);
		$this->view->assign('allGe', $allGe);
	}


	/**
	 * Migrates content from FCE to Grid Element
	 *
	 * @param int $fce
	 * @param int $ge
	 * @param bool $markdeleted
	 * @return void
	 */
	public function migrateFceAction($fce, $ge, $markdeleted = FALSE) {
		if ($fce > 0 && $ge > 0) {
			$contentElements = $this->migrateFceHelper->getContentElementsByFce($fce);
			foreach($contentElements as $contentElement) {
				$this->migrateFceHelper->migrateFceContentToGe($contentElement, $ge);
			}
			if ($markdeleted) {
				$this->migrateFceHelper->markFceDeleted($fce);
			}
		}
	}

	/**
	 * Index action for migrateContent
	 *
	 * @return void
	 */
	public function indexMigrateContentAction() {
		$templates = $this->migrateContentHelper->getAllTvTemplates();
		$beLayouts = $this->migrateContentHelper->getAllBeLayouts();
		t3lib_utility_Debug::debug($templates);
		t3lib_utility_Debug::debug($beLayouts);
	}

	public function migrateContentAction() {

	}
}
?>