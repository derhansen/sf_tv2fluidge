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

require_once(t3lib_extMgm::extPath('templavoila').'class.tx_templavoila_api.php');

/**
 * Class with methods used in other helpers/controllers
 */
class Tx_SfTvtools_Service_SharedHelper implements t3lib_Singleton {

	/**
	 * @var tx_templavoila_api
	 */
	protected $templavoilaAPIObj;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->templavoilaAPIObj = t3lib_div::makeInstance ('tx_templavoila_api');
	}

	/**
	 * Returns the TemplaVoila Api Object
	 *
	 * @return object|tx_templavoila_api
	 */
	public function getTemplavoilaAPIObj() {
		return $this->templavoilaAPIObj;
	}

	/**
	 * Returns an array of page uids up to the given amount recursionlevel
	 *
	 * @param int $depth
	 * @return array
	 */
	public function getPageIds($depth) {
		$tree = t3lib_div::makeInstance('t3lib_queryGenerator');
		$pids = $tree->getTreeList(1, $depth, 0, 1);
		return explode(',', $pids);
	}

}

?>