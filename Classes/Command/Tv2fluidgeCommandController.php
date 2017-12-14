<?php

class Tx_SfTv2fluidge_Command_Tv2fluidgeCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
{
    /**
     * UnreferencedElementHelper
     *
     * @var Tx_SfTv2fluidge_Service_UnreferencedElementHelper
     * @inject
     */
    protected $unreferencedElementHelper;
    /**
     * ReferenceElementHelper
     *
     * @var Tx_SfTv2fluidge_Service_ReferenceElementHelper
     * @inject
     */
    protected $referenceElementHelper;
    /**
     * MigrateContentHelper
     *
     * @var Tx_SfTv2fluidge_Service_MigrateContentHelper
     * @inject
     */
    protected $migrateContentHelper;
    /**
     * @var Tx_SfTv2fluidge_Service_SharedHelper
     * @inject
     */
    protected $sharedHelper;
    /**
     * @var Tx_SfTv2fluidge_Service_FixSortingHelper
     * @inject
     */
    protected $fixSortingHelper;

    /**
     * Delete unreferenced elements
     *
     * @param bool $markAsNegativeColPos
     */
    public function deleteUnreferencedElementsCommand($markAsNegativeColPos = false)
    {
        $this->sharedHelper->setUnlimitedTimeout();
        $numRecords = $this->unreferencedElementHelper->markDeletedUnreferencedElementsRecords((bool)$markAsNegativeColPos);
        $this->outputLine($numRecords . ' records deleted');
    }

    /**
     * Convert reference elements to 'insert records' elements
     *
     * @param bool $useParentUidForTranslations
     * @param bool $useAllLangIfDefaultLangIsReferenced
     */
    public function convertReferenceElementsCommand($useParentUidForTranslations = false, $useAllLangIfDefaultLangIsReferenced = false)
    {
        $formdata = array(
            'useparentuidfortranslations' => (int)$useParentUidForTranslations,
            'usealllangifdefaultlangisreferenced' => (int)$useAllLangIfDefaultLangIsReferenced
        );
        $this->sharedHelper->setUnlimitedTimeout();
        $this->referenceElementHelper->initFormData($formdata);
        $numRecords = $this->referenceElementHelper->convertReferenceElements();
        $this->outputLine($numRecords . ' records converted');
    }

    /**
     * Migrate content from TemplaVoila to Fluidtemplate
     *
     * @param int $uidTvTemplate
     * @param int $uidBeLayout
     * @param string $data
     * @param bool $markDeleted
     * @param string $convertFlexformOption
     * @param string $flexformFieldPrefix
     */
    public function migrateContentCommand($uidTvTemplate, $uidBeLayout, $data, $markDeleted = false, $convertFlexformOption = 'merge', $flexformFieldPrefix = 'tx_')
    {
        $formdata = array(
            'tvtemplate' => $uidTvTemplate,
            'belayout' => $uidBeLayout,
            'convertflexformoption' => $convertFlexformOption,
            'flexformfieldprefix' => $flexformFieldPrefix,
            'markdeleted' => $markDeleted
        );
        $data = explode(',', $data);
        $i = 1;
        foreach ($data as $columns) {
            $colsArr = explode('=', $columns);
            $formdata['tv_col_' . $i] = $colsArr[0];
            $formdata['be_col_' . $i] = $colsArr[1];
            $i++;
        }
        $this->sharedHelper->setUnlimitedTimeout();
        $contentElementsUpdated = 0;
        $pageTemplatesUpdated = 0;
        if ($uidTvTemplate > 0 && $uidBeLayout > 0) {
            $pageUids = $this->sharedHelper->getPageIds();
            foreach ($pageUids as $pageUid) {
                if ($this->sharedHelper->getTvPageTemplateUid($pageUid) == $uidTvTemplate) {
                    $contentElementsUpdated += $this->migrateContentHelper->migrateContentForPage($formdata, $pageUid);
                    $this->migrateContentHelper->migrateTvFlexformForPage($formdata, $pageUid);
                }
                // Update page template (must be called for every page, since to and next_to must be checked
                $pageTemplatesUpdated += $this->migrateContentHelper->updatePageTemplate($pageUid, $uidTvTemplate, $uidBeLayout);
            }
            if ($markDeleted) {
                $this->migrateContentHelper->markTvTemplateDeleted($uidTvTemplate);
            }
        }
    }

    /**
     * Fix sorting for a given pageUid
     *
     * @param int $pageUid
     * @return void
     */
    public function fixSortingCommand($pageUid = null)
    {
        $this->sharedHelper->setUnlimitedTimeout();
        $numUpdated = $this->fixSortingHelper->fixSortingForPage($pageUid);
        $this->outputLine($numUpdated . ' sortings fixed');
    }

    /**
     * Automatically fix sorting for all pages
     *
     * @return void
     */
    public function fixSortingAutoCommand()
    {
        $numUpdated = 0;
        $this->sharedHelper->setUnlimitedTimeout();
        $pageUids = $this->sharedHelper->getPageIds();
        foreach ($pageUids as $pageUidToFix) {
            $numUpdated += $this->fixSortingHelper->fixSortingForPage($pageUidToFix);
        }
        $this->outputLine($numUpdated . ' sortings fixed');
    }
}