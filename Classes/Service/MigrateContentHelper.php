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
 * Helper class for handling TV content column migration to Fluid backend layouts
 */
class Tx_SfTvtools_Service_MigrateContentHelper implements t3lib_Singleton {

	/**
	 * Returns an array of all TemplaVoila page templates
	 *
	 * @return array
	 */
	public function getAllTvTemplates() {
		$fields = 'tx_templavoila_tmplobj.uid, tx_templavoila_tmplobj.title';
		$table = 'tx_templavoila_datastructure, tx_templavoila_tmplobj';
		$where = 'tx_templavoila_datastructure.scope=1 AND tx_templavoila_datastructure.uid = tx_templavoila_tmplobj.datastructure
			AND tx_templavoila_datastructure.deleted=0 AND tx_templavoila_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$templates = array();
		foreach($res as $fce) {
			$templates[$fce['uid']] = $fce['title'];
		}

		return $templates;
	}

	/**
	 * Returns an array of all Grid Elements
	 *
	 * @return array
	 */
	public function getAllBeLayouts() {
		$fields = 'uid, title';
		$table = 'backend_layout';
		$where = 'deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$beLayouts = array();
		foreach($res as $ge) {
			$beLayouts[$ge['uid']] = $ge['title'];
		}

		return $beLayouts;
	}

}

?>