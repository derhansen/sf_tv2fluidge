<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "sf_tv2fluidge".
 *
 * Auto generated 24-06-2016 11:11
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title'            => 'TemplaVoila to Fluid/Grid Elements',
	'description'      => 'Backend module with tools that can be helpful when migration from Templavoila to Fluidtemplate and Grid Elements',
	'category'         => 'module',
	'author'           => 'Torben Hansen, Thorsten Krieger',
	'author_email'     => 'derhansen@gmail.com, t.krieger@horschler.eu',
	'author_company'   => 'Skyfillers GmbH, Horschler Kommunikation GmbH',
	'state'            => 'beta',
	'uploadfolder'     => false,
	'createDirs'       => '',
	'clearCacheOnLoad' => 0,
	'version'          => '0.7.6',
	'constraints'      =>
		array(
			'depends'   =>
				array(
					'extbase'     => '1.3',
					'fluid'       => '1.3',
					'typo3'       => '4.5.0-7.6.99',
					'templavoila' => '1.8.0',
				),
			'conflicts' =>
				array(),
			'suggests'  =>
				array(),
		),
	'clearcacheonload' => false,
);

