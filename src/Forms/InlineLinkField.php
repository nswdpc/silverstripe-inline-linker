<?php

namespace NSWDPC\InlineLinker;

use BurnBright\ExternalURLField\ExternalURLField;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Controllers\ElementalAreaController;
use gorriecoe\Link\Models\Link;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\Tabset;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\SecurityToken;
use SilverStripe\View\Requirements;

/**
 * The Inline link field extends TabSet, provides child fields that
 * save to the gorriecode/link model
 */
class InlineLinkField extends TabSet {

    /**
     * @var gorriecoe\Link\Models\Link|null
     */
    protected $record;

    /**
     * @var SilverStripe\ORM\DataObject|null
     */
    protected $parent;

    /**
     * @var TextField
     *
     */
    protected $title_field;

    /**
     * @var CheckboxField
     *
     */
    protected $open_in_new_window_field;

    /**
     * @var InlineLink_RemoveAction
     *
     */
    protected $remove_field;

    /**
     * @var bool
     */
    protected $is_removing_link = false;

    const FIELD_NAME_TYPE_SEPARATOR = "___";

    const FIELD_NAME_REMOVELINK = "RemoveLink";
    const FIELD_NAME_TITLE = "Title";
    const FIELD_NAME_OPEN_IN_NEW_WINDOW = "OpenInNewWindow";

    const LINKTYPE_EMAIL = 'Email';
    const LINKTYPE_URL = 'URL';
    const LINKTYPE_SITETREE = 'SiteTree';
    const LINKTYPE_PHONE = 'Phone';
    const LINKTYPE_FILE = 'File';

    public function __construct(string $name, string $title, DataObject $parent)
    {
        // set name and title early
        $this->name = $name;
        $this->title = $title;

        // store record and parent
        $this->parent = $this->record = null;
        if($parent instanceof DataObject && $component = $parent->getComponent($name)) {
            $this->parent = $parent;
        }
        if(!($component instanceof Link)) {
            throw new \Exception("Component {$name} must be an instance of Link::class");
        }

        $this->setRecord($component);

        // get all available fields
        $tabs = $this->getAvailableFields();
        parent::__construct($name, $title, $tabs);
    }

    /**
     * Once a record is created, the ID value of the Link is the data value for saving
     * @return mixed
     */
    public function dataValue() {
        if($this->record instanceof Link) {
            return $this->record->ID;
        } else {
            return null;
        }
    }

    /**
     * This is called just prior to saveInto, set submitted values on child fields
     * to allow saveInto to update/create a link
     *
     * @param mixed $values - these will be all values in $this->getName() index
     * @param array|DataObject $data
     * @return $this
     */
    public function setSubmittedValue($values, $data = null)
    {

        /**
         * Due to https://github.com/dnadesign/silverstripe-elemental/issues/381
         * and https://github.com/silverstripe/silverstripe-admin/issues/639
         * Must rescue data for child fields using namespaced removal method
         * If the parent is not an inline_editable element, the fields are named e.g "field[name]"
         * and this becomes easier
         */
        if($inline = $this->hasInlineElementalParent()) {
            $controller = Controller::curr();
            $request = $controller->getRequest();
            $post = $request->requestVars();
            // Check security token
            if (!SecurityToken::inst()->checkRequest($request)) {
                throw new ValidationException( "Failed security token check" );
            }
            // De-namespace the posted values
            $values = [];
            $values_all = ElementalAreaController::removeNamespacesFromFields($post, $this->parent->ID);
            // mogrify them into something we expect
            if(is_array($values_all)) {
                // grab any {$this->name}__{type} values into an array
                foreach($values_all as $name => $value) {
                    $type = $this->getTypeFromName($name);
                    if($type) {
                        $values[ $type ] = $value;
                    }
                }
            }
        }

        if(!is_array($values)) {
            // cannot proceed unless we have some values
            throw new ValidationException( _t(__CLASS__. ".NO_VALUES_SUPPLIED_SAVE", "No values were supplied to save") );
        }

        foreach($values as $type => $value) {
            if($type == self::FIELD_NAME_TITLE) {
                // handle title field
                $this->getTitleField()->setSubmittedValue( $value );
            } else if($type == self::FIELD_NAME_OPEN_IN_NEW_WINDOW) {
                // handle open in new window field
                $this->getOpenInNewWindowField()->setSubmittedValue( $value );
            } else if($field = $this->children->dataFieldByName( $this->prefixedFieldName( $type ) )) {
                $field->setSubmittedValue( $value );
            }
        }
    }

    /**
     * @param TextField
     */
    public function setTitleField(TextField $field) {
        $this->title_field = $field;
        return $this;
    }

    /**
     * @return TextField
     */
    public function getTitleField() {
        return $this->title_field;
    }

    /**
     * @param CheckboxField
     */
    public function setOpenInNewWindowField(CheckboxField $field) {
        $this->open_in_new_window_field = $field;
        return $this;
    }

    /**
     * @return CheckboxField
     */
    public function getOpenInNewWindowField() {
        return $this->open_in_new_window_field;
    }

    /**
     * @param InlineLink_RemoveAction
     */
    public function setRemoveField(InlineLink_RemoveAction $field) {
        $this->remove_field = $field;
        return $this;
    }

    /**
     * @return InlineLink_RemoveAction
     */
    public function getRemoveField() {
        return $this->remove_field;
    }

    /**
     * This field handles data
     */
    public function hasData() {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function saveInto(DataObjectInterface $record)
    {

        // handle removal
        $remove_field = $this->getRemoveField();
        if($remove_field
            && ($remove_field->dataValue() == 1)
            && ($link = $this->getRecord())
        ) {
            if($link && $link->exists()) {
                $this->getTitleField()->setSubmittedValue('');
                $this->getOpenInNewWindowField()->setSubmittedValue(false);
                $link->delete();
                // do not proceed
                return;
            }
        }

        $children = $this->getChildren()->dataFields();
        $field_with_value = null;

        // find a child field with a value, use that as the value for the link
        foreach($children as $field) {
            $name = $field->getName();
            $value = $field->dataValue();
            if($value) {
                $field_with_value = $field;
                break;
            }
        }

        //set model options
        $open_in_new_window = "";
        $title = "Auto-created title for a link in " . $this->parent->getTitle();
        if($open_in_new_window_field = $this->getOpenInNewWindowField()) {
            $open_in_new_window = $open_in_new_window_field->dataValue();
        }
        if($title_field = $this->getTitleField()) {
            $title = $title_field->dataValue();
        }

        // create or update the current link record
        if($field_with_value) {
            // apply the value found
            $link = $this->createOrAssociateLink($field_with_value);
        } else {
            // might be updating or creating a link with no value
            $link = $this->getRecord();
            if(!$link || !$link->exists()) {
                // create a new link record, without any data
                $link = Link::create();
            }
        }

        // save Title and OpenInNewWindow
        $link->Title = $title;
        $link->OpenInNewWindow = $open_in_new_window;
        $link->write();

        // the link becomes the record
        $this->setRecord($link);
        // save the link id to the parent element that has the relation to the link
        $this->parent->setField($this->getName() . "ID", $link->ID);

    }

    /**
     * Create or save a link using the value from the form field
     * @todo final validation on the value / field ?
     * @param FormField the child field carrying the value to be saved
     */
    protected function createOrAssociateLink(FormField $field) {
        $type = $this->getTypeFromName($field->getName());
        $value = $field->dataValue();
        $data = [];
        switch($type) {
            case self::LINKTYPE_SITETREE:
                $data = [
                    'SiteTreeID' => $value,
                    'Type' => $type
                ];
                break;
            case self::LINKTYPE_FILE:
                // for files, getItemIDs
                $id_list = $field->getItemIDs();
                $file_id = 0;//TODO error?
                if(is_array($id_list)) {
                    $file_id = reset($id_list);
                }
                $data = [
                    'FileID' => $file_id,
                    'Type' => $type
                ];
                break;
            case self::LINKTYPE_URL:
                $data = [
                    'URL' => $value,
                    'Type' => $type
                ];
                break;
            case self::LINKTYPE_EMAIL:
                $data = [
                    'Email' => $value,
                    'Type' => $type
                ];
                break;
            case self::LINKTYPE_PHONE:
                $data = [
                    'Phone' => $value,
                    'Type' => $type
                ];
                break;
            default:
                throw new ValidationException("The link of the type '{$type}' cannot be saved'");
                break;
        }

        $link = $this->getRecord();
        if($link instanceof Link) {
            // update the existing link
            foreach($data as $field => $value) {
                $link->setField($field, $value);
            }
        } else {
            // new, create a new link
            $link = Link::create($data);
        }
        return $link;
    }

    /**
     * Set the current link record
     */
    public function setRecord(Link $record) {
        $this->record = $record;
    }

    /**
     * Get the current link record, if any
     * @return mixed null|\gorriecoe\Link\Models\Link
     */
    public function getRecord() {
        return $this->record;
    }

    /**
     * Determine whether the parent of this field is an elemental element
     * @return boolean
     */
    public function hasInlineElementalParent() {
        // If there is no silverstripe-elemental module installed, then no need to check...
        if(!class_exists("\\DNADesign\\Elemental\\Models\\BaseElement")) {
            return false;
        }
        return $this->parent
                && ($this->parent instanceof BaseElement)
                && $this->parent->config()->get('inline_editable');
    }

    /**
     * Return a prefixed field name, e./g LinkTarget[Email]
     * @param string $type the type of the link
     * @return string
     */
    public function prefixedFieldName($type) {
        if($this->hasInlineElementalParent()) {
            /*
             * Cannot use index notation due to
             * https://github.com/dnadesign/silverstripe-elemental/issues/381
             * https://github.com/silverstripe/silverstripe-admin/issues/639
             */
            return $this->getName() . self::FIELD_NAME_TYPE_SEPARATOR . $type;
        } else {
            /**
             * Can use indexed notation - either a non inline editable element
             * or a normal dataobject edit form
             */
            return $this->getName() . "[{$type}]";
        }
    }

    /**
     * Work out the type based on the field name
     * If the parent is an inline editable element, take that into account
     * @return string
     */
    protected function getTypeFromName($complete_field_name) {
        $type = "";
        if($this->hasInlineElementalParent()) {
            // the field name should start with the prefix...
            if(strpos($complete_field_name, $this->getName() . self::FIELD_NAME_TYPE_SEPARATOR) !== 0) {
                // invalid field name
                return "";
            }
            // Get type using separator
            $parts = explode(self::FIELD_NAME_TYPE_SEPARATOR, $complete_field_name);
            // 0=parentname 1=type
            $type = isset($parts[1]) ? $parts[1] : '';
            return $type;
        } else {
            // Non inline_editable elements or standard modeladmin, using field[type] naming
            $result = [];
            $name = $this->getName();
            parse_str($complete_field_name, $results);
            if(isset($results[ $name ])) {
                $target = $results[ $name ];
                $type = key($target);
            }
            return $type;
        }
    }

    /**
     * Return the title of the current record
     * @return string
     */
    public function getRecordTitle() {
        $record = $this->getRecord();
        $title = trim(isset($record->Title) ? $record->Title : '');
        return $title;
    }


    /**
     * Return the OpenInNewWindow value of the current record
     * @return string
     */
    public function getRecordOpenInNewWindow() {
        $record = $this->getRecord();
        if(isset($record->OpenInNewWindow)) {
            return $record->OpenInNewWindow == 1 ? 1: 0;
        }
        return 0;
    }

    /**
     * @return LiteralField
     * @deprecated
     */
    public function CurrentLink() {
        return $this->getCurrentLinkField();
    }

    /**
     * @return mixed null|LiteralField
     */
    public function getCurrentLinkField() {
        $field = $this->getCurrentLinkTemplate();
        return $field;
    }

    /**
     * Returns whether a current link exists and it is valid
     * A valid Link record has a Type value and a URL
     * @return bool
     */
    public function hasCurrentLink() : bool {
        return $this->record
            && $this->record->exists()
            && $this->record->Type
            && $this->record->getLinkURL();
    }

    /**
     * Return a literal field template for the current link
     * @return mixed null|LiteralField
     */
    protected function getCurrentLinkTemplate($name = "ExistingLinkRecord") {
        $field = null;
        if($this->hasCurrentLink()) {
            $field = LiteralField::create(
                $this->prefixedFieldName($name),
                $this->record->renderWith('NSWDPC/InlineLinker/CurrentLinkTemplate')
            );
        }
        return $field;
    }

    /**
     * Returns available fields in order of precedence
     * @return array
     */
    protected function getAvailableFields() {

        // get links
        $links = Link::get()->sort('Title ASC');
        if($this->record && $this->record->exists()) {
            // except the current one
            $links = $links->exclude("ID", $this->record->ID);
        }

        $fields[] = Tab::create(
            _t(__CLASS__ . ".EXTERNAL", "External"),
            InlineLink_URLField::create(
                $this->prefixedFieldName('URL'),
                _t( __CLASS__ . '.EXTERNAL_URL', 'Provide an external URL')
            )->setConfig([
                'html5validation' => true,
                'defaultparts' => [
                    'scheme' => 'https'
                ],
            ])->setDescription(
                _t( __CLASS__ . '.EXTERNAL_URL_NOTE', 'The URL should start with an https:// or http://')
            )
        );

        $fields[] = Tab::create(
            _t(__CLASS__ . ".Email", "Email"),
            InlineLink_EmailField::create(
                $this->prefixedFieldName('Email'),
                _t( __CLASS__ . '.ENTER_EMAIL_ADDRESS', 'Enter a valid email address')
            )->setDescription(
                _t( __CLASS__ . '.EMAIL_NOTE', 'e.g. \'someone@example.com\'')
            )
        );

        $fields[] = Tab::create(
            _t(__CLASS__ . ".Page", "Page"),
            InlineLink_SiteTreeField::create(
                $this->prefixedFieldName('SiteTree'),
                _t( __CLASS__ . '.CHOOSE_PAGE_ON_THIS_WEBSITE', 'Choose a page on this website or type to start searching'),
                SiteTree::class
            )
        );

        $fields[] = Tab::create(
            _t(__CLASS__ . ".File", "File"),
            InlineLink_FileField::create(
                $this->prefixedFieldName('File'),
                _t(__CLASS__ . '.CHOOSE_A_FILE', 'Choose a file on this website'),
            )
        );


        $fields[] = Tab::create(
            _t(__CLASS__ . ".Phone", "Phone"),
            InlineLink_PhoneField::create(
                $this->prefixedFieldName('Phone'),
                _t( __CLASS__ . '.ENTER_A_PHONE_NUMBER', 'Enter a telephone number')
            )->setDescription(
                _t( __CLASS__ . '.PHONE_NOTE', 'Supply the country dialling code to remove ambiguity')
            )->addExtraClass('text')
        );

        return $fields;
    }

}
