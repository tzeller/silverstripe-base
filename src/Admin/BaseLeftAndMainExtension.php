<?php
namespace LeKoala\Base\Admin;

use SilverStripe\i18n\i18n;
use Psr\Log\LoggerInterface;
use SilverStripe\Admin\CMSMenu;
use SilverStripe\Control\Director;
use SilverStripe\View\Requirements;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Admin\LeftAndMainExtension;
use LeKoala\Base\View\CommonRequirements;

/**
 * Class \LeKoala\Base\LeftAndMainExtension
 *
 * @property \SilverStripe\Admin\ModelAdmin|\SilverStripe\Admin\SecurityAdmin|\SilverStripe\SiteConfig\SiteConfigLeftAndMain|\SilverStripe\AssetAdmin\Controller\AssetAdmin|\SilverStripe\CMS\Controllers\CMSMain|\SilverStripe\CMS\Controllers\CMSPageAddController|\SilverStripe\CMS\Controllers\CMSPagesController|\SilverStripe\Subsites\Admin\SubsiteAdmin|\LeKoala\Base\Admin\BaseLeftAndMainExtension $owner
 */
class BaseLeftAndMainExtension extends LeftAndMainExtension
{
    use Configurable;
    use BaseLeftAndMainSubsite;

    /**
     * @config
     * @var boolean
     */
    private static $dark_theme = false;

    /**
     * @config
     * @var array
     */
    private static $removed_items = [];

    /**
     * @config
     * @var array
     */
    private static $reordered_items = [];

    public function init()
    {
        $SiteConfig = SiteConfig::current_site_config();
        if ($SiteConfig->ForceSSL) {
            Director::forceSSL();
        }

        $this->removeMenuItems();
        $this->reorderMenuItems();
        $this->removeSubsiteFromMenu();

        // Check if we need font awesome (if any item use IconClass fa fa-something)
        // eg: private static $menu_icon_class = 'fa fa-calendar';
        $items = $this->owner->MainMenu();
        foreach ($items as $item) {
            if (strpos($item->IconClass, 'fa fa-') !== false) {
                CommonRequirements::fontAwesome4();
            } elseif (strpos($item->IconClass, 'fas fa-') !== false) {
                CommonRequirements::fontAwesome5();
            } elseif (strpos($item->IconClass, 'bx bx-') !== false) {
                CommonRequirements::boxIcons();
            }
        }

        // if (isset($_GET['locale'])) {
        //     i18n::set_locale($_GET['locale']);
        // }

        if (self::config()->dark_theme) {
            Requirements::css('base/css/admin-dark.css');
        }

        Requirements::javascript("base/javascript/admin.js");
        $this->requireAdminStyles();
    }

    /**
     * Hide items if necessary, example yml:
     *
     *    LeKoala\Base\Admin\BaseLeftAndMainExtension:
     *      removed_items:
     *        - SilverStripe-CampaignAdmin-CampaignAdmin
     *
     * @return void
     */
    protected function removeMenuItems()
    {
        $removedItems = self::config()->removed_items;
        if ($removedItems) {
            $css = '';
            foreach ($removedItems as $item) {
                CMSMenu::remove_menu_item($item);
                $itemParts = explode('-', $item);
                $css .= 'li.valCMS_ACCESS_' . end($itemParts) . '{display:none}' . "\n";
            }
            Requirements::customCSS($css, 'HidePermissions');
        }
    }

    /**
     * Reorder menu items based on a given array
     *
     * @return void
     */
    protected function reorderMenuItems()
    {
        $reorderedItems = self::config()->reordered_items;
        if (empty($reorderedItems)) {
            return;
        }

        $currentMenuItems = CMSMenu::get_menu_items();
        CMSMenu::clear_menu();
        // Let's add items in the given order
        $priority = 500;
        foreach ($reorderedItems as $itemName) {
            foreach ($currentMenuItems as $key => $item) {
                if ($key != $itemName) {
                    continue;
                }
                $item->priority = $priority;
                $priority -= 10;
                CMSMenu::add_menu_item($key, $item->title, $item->url, $item->controller, $item->priority);
                unset($currentMenuItems[$key]);
            }
        }
        // Add remaining items
        foreach ($currentMenuItems as $key => $item) {
            CMSMenu::add_menu_item($key, $item->title, $item->url, $item->controller, $item->priority);
        }
    }

    /**
     * This styles the top left box to match the current theme
     *
     * @return void
     */
    public function requireAdminStyles()
    {
        $SiteConfig = SiteConfig::current_site_config();
        if (!$SiteConfig->PrimaryColor) {
            return;
        }

        $PrimaryColor = $SiteConfig->dbObject('PrimaryColor');

        $bg = $PrimaryColor->Color();
        // Black is too harsh so we use a softer shadow
        $color = $PrimaryColor->ContrastColor('#333');
        $border = $PrimaryColor->HighlightColor();

        $styles = <<<CSS
.cms-menu__header {background: $bg; color: $color}
.cms-menu__header a, .cms-menu__header span {color: $color !important}
.cms-sitename {border-color: $border}
.cms-sitename:focus, .cms-sitename:hover {background-color: $border}
.cms-login-status .cms-login-status__profile-link:focus, .cms-login-status .cms-login-status__profile-link:hover {background-color: $border}
.cms-login-status .cms-login-status__logout-link:focus, .cms-login-status .cms-login-status__logout-link:hover {background-color: $border}
CSS;
        Requirements::customCSS($styles, 'AdminMenuStyles');
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return Injector::inst()->get(LoggerInterface::class)->withName('Admin');
    }
}
