<?php
namespace LeKoala\Base\Actions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\DataExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Admin\CMSProfileController;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Class \LeKoala\Base\Actions\DataObjectActionsExtension
 *
 * Maybe some day we don't have to do it ourself...
 * @link https://github.com/silverstripe/silverstripe-cms/issues/2047
 * @property \LeKoala\Base\Blocks\Block|\LeKoala\Base\News\NewsCategory|\LeKoala\Base\News\NewsItem|\LeKoala\Base\Tags\Tag|\TimelineItem|\PortfolioCategory|\PortfolioItem|\SilverStripe\Assets\File|\SilverStripe\Assets\Image|\SilverStripe\SiteConfig\SiteConfig|\SilverStripe\Versioned\ChangeSetItem|\SilverStripe\CMS\Model\SiteTree|\SilverStripe\ORM\DataObject|\SilverStripe\Security\Group|\SilverStripe\Security\Member|\LeKoala\Base\Actions\DataObjectActionsExtension $owner
 */
class DataObjectActionsExtension extends DataExtension
{
    /**
     * @link https://docs.silverstripe.org/en/3/developer_guides/customising_the_admin_interface/how_tos/extend_cms_interface/
     * @param FieldList $actions
     * @return void
     */
    public function updateCMSActions(FieldList $actions)
    {
        // Pages don't need to be improved
        if ($this->owner instanceof SiteTree) {
            return;
        }
        // SiteConfig doesn't need to be improved
        if ($this->owner instanceof SiteConfig) {
            return;
        }
        // Not implemented in CMSProfileController
        $ctrl = Controller::curr();
        if ($ctrl instanceof CMSProfileController) {
            return;
        }
        if ($this->owner->ID) {
            $label = _t('DataObjectActionsExtension.SAVEANDCLOSE', 'Save and Close');
        } else {
            $label = _t('DataObjectActionsExtension.CREATEANDCLOSE', 'Create and Close');
        }
        $saveAndClose = new FormAction('doSaveAndClose', $label);
        $saveAndClose->addExtraClass('btn-primary');
        // Full reference here: vendor\silverstripe\admin\client\src\font\icons-reference.html
        $saveAndClose->addExtraClass('font-icon-level-up');
        $saveAndClose->setUseButtonTag(true);
        $actions->push($saveAndClose);
    }

    public function getCMSUtils()
    {
        $utils = new FieldList();
        $this->owner->extend('updateCMSUtils', $utils);
        return $utils;
    }
}
