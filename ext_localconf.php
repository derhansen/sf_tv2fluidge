<?php
if (TYPO3_MODE === 'BE') {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][$_EXTKEY] =
        Tx_SfTv2fluidge_Command_Tv2fluidgeCommandController::class;
}