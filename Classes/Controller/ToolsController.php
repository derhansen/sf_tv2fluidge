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
	 * @var Tx_SfTvtools_Service_UnreferencedElementService
	 */
	protected $UnreferencedElementService;

	/**
	 * DI for UnreferencedElementService
	 *
	 * @param Tx_SfTvtools_Service_UnreferencedElementService $unreferencedElementService
	 * @return void
	 */
	public function injectFsrFacilityRepository(Tx_SfTvtools_Service_UnreferencedElementService $unreferencedElementService) {
		$this->UnreferencedElementService = $unreferencedElementService;
	}


	public function indexAction() {

	}

	public function deleteUnreferencedElementsAction() {
		$numRecords = $this->UnreferencedElementService->markDeletedUnreferencedElementsRecords();
		$this->view->assign('numRecords', $numRecords);
	}
}
?>