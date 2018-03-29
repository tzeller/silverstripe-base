<?php
namespace LeKoala\Base\Theme;
use SilverStripe\Forms\Tab;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Control\Director;
use LeKoala\Base\Forms\ColorField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\DataExtension;
use LeKoala\Base\Helpers\ZipHelper;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\DropdownField;
/**
 * Class \LeKoala\Base\Theme\ThemeSiteConfigExtension
 *
 * @property \SilverStripe\SiteConfig\SiteConfig|\LeKoala\Base\Theme\ThemeSiteConfigExtension $owner
 * @property string $PrimaryColor
 * @property string $SecondaryColor
 * @property string $ThemeColor
 * @property string $HeaderFontFamily
 * @property int $HeaderFontWeight
 * @property string $BodyFontFamily
 * @property int $BodyFontWeight
 * @property string $GoogleFonts
 * @property int $LogoID
 * @property int $IconID
 * @property int $FaviconID
 * @method \SilverStripe\Assets\Image Logo()
 * @method \SilverStripe\Assets\Image Icon()
 * @method \SilverStripe\Assets\File Favicon()
 */
class ThemeSiteConfigExtension extends DataExtension
{
    private static $db = [
        "PrimaryColor" => "Varchar(9)",
        "SecondaryColor" => "Varchar(9)",
        "ThemeColor" => "Varchar(9)",
        "HeaderFontFamily" => "Varchar(59)",
        "HeaderFontWeight" => "Int",
        "BodyFontFamily" => "Varchar(59)",
        "BodyFontWeight" => "Int",
        "GoogleFonts" => "Varchar(99)",
    ];
    private static $has_one = [
        "Logo" => Image::class,
        "Icon" => Image::class,
        "Favicon" => File::class,
    ];
    private static $owns = [
        "Logo",
        "Icon",
    ];
    public function onAfterWrite()
    {
        if ($this->owner->LogoID) {
            $Logo = $this->owner->Logo();
            if (!$Logo->isPublished()) {
                $Logo->doPublish();
            }
        }
        if ($this->owner->IconID) {
            $Icon = $this->owner->Icon();
            if (!$Icon->isPublished()) {
                $Icon->doPublish();
            }
        }
    }
    public static function listFontWeights()
    {
        return [
            100 => 'thin',
            200 => 'extra-light',
            300 => 'light',
            400 => 'regular',
            500 => 'medium',
            600 => 'semi-bold',
            700 => 'bold',
            800 => 'extra-bold',
            900 => 'black',
        ];
    }
    public function updateCMSFields(FieldList $fields)
    {
        $themeTab = new Tab("Theme");
        $fields->addFieldToTab('Root', $themeTab);
        //
        $ColorsHeader = new HeaderField("ColorsHeader", "Colors");
        $themeTab->push($ColorsHeader);
        $ColorsGroup = new FieldGroup();
        $themeTab->push($ColorsGroup);
        $PrimaryColor = new ColorField('PrimaryColor');
        $ColorsGroup->push($PrimaryColor);
        $SecondaryColor = new ColorField('SecondaryColor');
        $ColorsGroup->push($SecondaryColor);
        $ThemeColor = new ColorField('ThemeColor');
        $ThemeColor->setTooltip("Select a color that gives a good contrast with your Icon");
        $ColorsGroup->push($ThemeColor);
        //
        $FontsHeader = new HeaderField("FontsHeader", "Fonts");
        $themeTab->push($FontsHeader);
        $FontsGroup = new FieldGroup();
        $themeTab->push($FontsGroup);
        $HeaderFont = new TextField("HeaderFontFamily");
        $FontsGroup->push($HeaderFont);
        $HeaderFontWeight = new DropdownField("HeaderFontWeight", $this->owner->fieldLabel('HeaderFontWeight'), self::listFontWeights());
        $FontsGroup->push($HeaderFontWeight);
        $BodyFont = new TextField("BodyFontFamily");
        $FontsGroup->push($BodyFont);
        $BodyFontWeight = new DropdownField("BodyFontWeight", $this->owner->fieldLabel('BodyFontWeight'), self::listFontWeights());
        $FontsGroup->push($BodyFontWeight);
        $GoogleFonts = new TextField("GoogleFonts");
        $GoogleFonts->setAttribute('placeholder', "Open+Sans|Roboto");
        $themeTab->push($GoogleFonts);
        //
        $ImagesHeader = new HeaderField("ImagesHeader", "Images");
        $themeTab->push($ImagesHeader);
        /* @var $Logo UploadField */
        $Logo = UploadField::create("Logo");
        $Logo->setFolderName("Theme");
        $Logo->setAllowedFileCategories("image/supported");
        $themeTab->push($Logo);
        $Icon = UploadField::create("Icon");
        $Icon->setFolderName("Theme");
        $Icon->setAllowedFileCategories("image/supported");
        $themeTab->push($Icon);
        /* @var $Favicon UploadField */
        $Favicon = UploadField::create("Favicon");
        $Favicon->setFolderName("Theme");
        $Favicon->setAllowedExtensions('zip');
        $Favicon->setDescription("Upload the zip file generated with <a href=\"https://realfavicongenerator.net/\" target=\"_blank\">Real Favicon Generator</a>. Theme Color will be used as background for your icon.");
        $themeTab->push($Favicon);
    }
    public function Favicons($path = '')
    {
        $path = preg_replace('/\/+/', '/', Director::baseURL() . $path . '/');
        // A mask color, used by macOS safari and touch bar (should look good with a white icon)
        $mask = $this->owner->PrimaryColor ? '#' . trim($this->owner->PrimaryColor, '#') : '#000000';
        // A contrast color for the icon, used by Windows Metro and Android
        $theme = $this->owner->ThemeColor ? '#' . trim($this->owner->ThemeColor, '#') : '#ffffff';
        return $this->owner->customise(
            array(
                'Path' => $path,
                'ThemeColor' => $theme,
                'MaskColor' => $mask,
            )
        )->renderWith('Favicons');
    }
    public function getThemeAssetURL()
    {
        return '/assets/_theme/' . $this->owner->ID;
    }
    public function getThemeAssetsFolder()
    {
        $dir = Director::publicFolder() . $this->getThemeAssetURL();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
    public function onBeforeWrite()
    {
        $changedFields = $this->owner->getChangedFields(true, 2);
        if (isset($changedFields['FaviconID'])) {
            $this->unpackFaviconArchive();
        }
    }
    protected function unpackFaviconArchive()
    {
        /* @var $Favicon File */
        $Favicon = $this->owner->Favicon();
        $FaviconData = $Favicon->getString();
        $tmpName = \tempnam(TEMP_PATH, 'ss');
        \file_put_contents($tmpName, $FaviconData);
        $dir = $this->getThemeAssetsFolder();
        $ZipArchive = new \ZipArchive;
        $res = $ZipArchive->open($tmpName);
        if ($res === true) {
            $ZipArchive->extractTo($dir);
            $ZipArchive->close();
        } else {
            die('failed : ' . ZipHelper::getErrorMessage($res));
        }
    }
}
