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
     * @param bool $markAsNegativeColPos
     */
    public function deleteUnreferencedElementsCommand($markAsNegativeColPos = false) {
        $this->sharedHelper->setUnlimitedTimeout();
        $numRecords = $this->unreferencedElementHelper->markDeletedUnreferencedElementsRecords((bool)$markAsNegativeColPos);
        $this->outputLine($numRecords . ' records deleted');
    }
    /**
     * @param bool $useParentUidForTranslations
     * @param bool $useAllLangIfDefaultLangIsReferenced
     */
    public function convertReferenceElementsCommand($useParentUidForTranslations = false, $useAllLangIfDefaultLangIsReferenced = false) {
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
            $formdata['tv_col_'.$i] = $colsArr[0];
            $formdata['be_col_'.$i] = $colsArr[1];
            $i++;
        }
        $this->sharedHelper->setUnlimitedTimeout();
        $contentElementsUpdated = 0;
        $pageTemplatesUpdated   = 0;
        if ($uidTvTemplate > 0 && $uidBeLayout > 0)
        {
            $pageUids = $this->sharedHelper->getPageIds();
            foreach ($pageUids as $pageUid)
            {
                if ($this->sharedHelper->getTvPageTemplateUid($pageUid) == $uidTvTemplate)
                {
                    $contentElementsUpdated += $this->migrateContentHelper->migrateContentForPage($formdata, $pageUid);
                    $this->migrateContentHelper->migrateTvFlexformForPage($formdata, $pageUid);
                }
                // Update page template (must be called for every page, since to and next_to must be checked
                $pageTemplatesUpdated += $this->migrateContentHelper->updatePageTemplate($pageUid, $uidTvTemplate, $uidBeLayout);
            }
            if ($markDeleted)
            {
                $this->migrateContentHelper->markTvTemplateDeleted($uidTvTemplate);
            }
        }
    }
    /**
     * Action for fix sorting
     *
     * @param int $pageUid
     * @param string $fixOptions
     * @return void
     * @internal param array $formdata
     */
    public function fixSortingCommand($fixOptions, $pageUid)
    {
        $this->sharedHelper->setUnlimitedTimeout();
        $numUpdated = 0;
        if ($fixOptions == 'singlePage')
        {
            $numUpdated = $this->fixSortingHelper->fixSortingForPage($pageUid);
        }
        else
        {
            $pageUids = $this->sharedHelper->getPageIds();
            foreach ($pageUids as $pageUid)
            {
                $numUpdated += $this->fixSortingHelper->fixSortingForPage($pageUid);
            }
        }
        $this->outputLine($numUpdated . ' sortings fixed');
    }
}