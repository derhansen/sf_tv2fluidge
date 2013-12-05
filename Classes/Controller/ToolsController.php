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
	 * @var Tx_SfTvtools_Service_UpdateFceHelper
	 */
	protected $UpdateFceHelper;

	/**
	 * DI for UnreferencedElementHelper
	 *
	 * @param Tx_SfTvtools_Service_UpdateFceHelper $unreferencedElementHelper
	 * @return void
	 */
	public function injectUpdateFceHelper(Tx_SfTvtools_Service_UpdateFceHelper $unreferencedElementHelper) {
		$this->UpdateFceHelper = $unreferencedElementHelper;
	}

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

	public function indexConvertFCEAction() {
		$allFce = $this->UpdateFceHelper->getAllFce();
		$allGe = $this->UpdateFceHelper->getAllGe();

		$this->view->assign('allFce', $allFce);
		$this->view->assign('allGe', $allGe);
		//t3lib_utility_Debug::debug($this->UpdateFceHelper->getAllGe());
		//$contentElements = $this->UpdateFceHelper->getContentElementsByFce(10);
		//t3lib_utility_Debug::debug('Count:' . count($contentElements));
		//t3lib_utility_Debug::debug($contentElements[0]);
		//$contentElement = $this->UpdateFceHelper->getContentElementByUid(300);
		//t3lib_utility_Debug::debug($contentElement);
		//$this->UpdateFceHelper->convertFceToGe($contentElement, 0, 1);
	}

	/**
	 * @param int $fce
	 * @param int $ge
	 * @return void
	 */
	public function convertFceAction($fce, $ge) {
		if ($fce > 0 && $ge > 0) {
			$contentElements = $this->UpdateFceHelper->getContentElementsByFce($fce);
			foreach($contentElements as $contentElement) {
				$this->UpdateFceHelper->convertFceToGe($contentElement, $ge);
			}
		}
	}
}
?>