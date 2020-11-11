<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\HeaderField;
use BurnBright\ExternalURLField\ExternalURLField;
use gorriecoe\Link\Models\Link;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\Tabset;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\View\Requirements;

/**
 * The Inline link field extends TabSet, provides child fields that
 * save to the gorriecode/link model
 */
class InlineLinkField extends TabSet {

    /**
     * Use custom react component
     *
     * @var string
     */
    protected $schemaComponent = 'Tabs';

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

    public function __construct(string $name, string $title, DataObject $parent)
    {
        // set name and title early
        $this->name = $name;
        $this->title = $title;

        // store record and parent
        if($parent instanceof DataObject && $parent->hasMethod($name)) {
            $this->parent = $parent;
            $link = $this->parent->{$name}();
            if($link instanceof Link) {
                $this->record = $link;
            }
        }
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
        if(!is_array($values)) {
            // unexpected
            return false;
        }
        foreach($values as $key => $value) {
            if($key == "Title") {
                $this->getTitleField()->setSubmittedValue( $value );
            } else if($key == "OpenInNewWindow") {
                $this->getOpenInNewWindowField()->setSubmittedValue( $value );
            } else if($field = $this->children->dataFieldByName( $this->prefixedFieldName( $key ) )) {
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
        $children = $this->getChildren()->dataFields();
        $field_with_value = null;
        // find a child field with a value
        foreach($children as $field) {
            $name = $field->getName();
            $value = $field->dataValue();
            if($value) {
                $field_with_value = $field;
                break;
            }
        }

        $open_in_new_window = "";
        $title = "Auto-created title for a link in " . $this->parent->getTitle();
        if($open_in_new_window_field = $this->getOpenInNewWindowField()) {
            $open_in_new_window = $open_in_new_window_field->dataValue();
        }
        if($title_field = $this->getTitleField()) {
            $title = $title_field->dataValue();
        }

        $link = null;
        if($field_with_value) {
            if($link = $this->createOrAssociateLink($field_with_value)) {
                // TODO if the link exists, this could change the status
                $link->OpenInNewWindow = $open_in_new_window;
                // TODO if the link exists, this will change the title of it
                $link->Title = $title;
                $link->write();
            }
        } else {
            if($this->record) {
                // if the record exists, update the value (do not change any link type/URL etc)
                $link = $this->record;
            } else {
                // create a new link record, without any data
                $link = Link::create();
            }

            $link->Title = $title;
            $link->OpenInNewWindow = $open_in_new_window;
            $link->write();
        }

        if($link) {
            // the link becomes the record
            $this->record = $link;
            // save the link id to the parent element that has the relation to the link
            $this->parent->setField($this->getName() . "ID", $link->ID);
        }

    }

    /**
     * Create or save a link using the value from the form field
     * @todo final validation on the value / field ?
     * @param FormField the child field carrying the value to be saved
     */
    protected function createOrAssociateLink(FormField $field) {
        $type = $this->getTypeFromName($field->getName());
        $value = $field->dataValue();
        $record = [];
        switch($type) {
            case 'Link':
                // pre-existing Link record
                $record = [
                    'LinkID' => $value,
                    'Type' => $this->getLinkTypeLink()
                ];
                break;
            case 'SiteTree':
                $record = [
                    'SiteTreeID' => $value,
                    'Type' => 'SiteTree',
                ];
                break;
            case 'File':
                // for files, getItemIDs
                $id_list = $field->getItemIDs();
                $file_id = 0;//TODO error?
                if(is_array($id_list)) {
                    $file_id = reset($id_list);
                }
                $record = [
                    'FileID' => $file_id,
                    'Type' => 'File'
                ];
                break;
            case 'URL':
                $record = [
                    'URL' => $value,
                    'Type' => 'URL'
                ];
                break;
            case 'Email':
                $record = [
                    'Email' => $value,
                    'Type' => 'Email'
                ];
                break;
            case 'Phone':
                $record = [
                    'Phone' => $value,
                    'Type' => 'Phone'
                ];
                break;
            case 'URL':
                $record = [
                    'URL' => $value,
                    'Type' => 'URL'
                ];
                break;
            default:
                throw new \Exception("Unknown type {$type}");
                break;
        }

        $link = Link::get()->filter(
            $record
        )->first();
        if($link && $link->exists()) {
            // matches existing Link record
            return $link;
        }

        $link = Link::create($record);
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
     * @return mixed null|Link
     */
    public function getRecord() {
        return $this->record;
    }

    /**
     * Return a prefixed field name, e./g LinkTarget[Email]
     * @param string $type the type of the link
     * @return string
     */
    public function prefixedFieldName($type) {
        return $this->getName() . "[{$type}]";
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
     * Work out the type based on the field name, the type is the last index
     * @return string
     */
    protected function getTypeFromName($complete_field_name) {
        $result = [];
        $name = $this->getName();
        parse_str($complete_field_name, $results);
        if(isset($results[ $name ])) {
            $target = $results[ $name ];
            $type = key($target);
        }
        return $type;
    }

    /**
     * @return LiteralField
     */
    public function CurrentLink() {
        $field = $this->CurrentLinkTemplate();
        return $field;
    }

    protected function CurrentLinkTemplate($name = "ExistingLinkRecord") {
        // literal field template for the current link
        $field = null;
        if($this->record && $this->record->exists()) {
            $field = LiteralField::create(
                $this->prefixedFieldName('ExistingLinkRecord'),
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

        $fields = FieldList::create();

        $fields->push(
            Tab::create(
                'External',
                _t(__CLASS__ . ".EXTERNAL", "External")
            )
        );

        $fields->addFieldToTab(
            'External',
            ExternalURLField::create(
                $this->prefixedFieldName('URL'),
                _t( __CLASS__ . '.EXTERNAL_URL', 'Provide an external URL')
            )->setConfig([
                'html5validation' => true,
                'defaultparts' => [
                    'scheme' => 'https'
                ],
            ])->setDescription(
                _t( __CLASS__ . '.EXTERNAL_URL_NOTE', 'The URL should start with an https:// or http://')
            )->setInputType('url')
        );

        // a field that the editor can toggle to open
        $fields->push(
            Tab::create(
                'Link',
                _t(__CLASS__ . ".LINK", "Link")
            )
        );

        // get links
        $links = Link::get()->sort('Title ASC');
        if($this->record && $this->record->exists()) {
            // except the current one
            $links = $links->exclude("ID", $this->record->ID);
        }

        $fields->addFieldToTab(
            'Link',
            DropdownField::create(
                $this->prefixedFieldName('Link'),
                _t( __CLASS__ . '.EXISTING_LINK', 'Choose an existing link record'),
                $links->map('ID','TypeWithURL')->toArray()
            )->setEmptyString(''),
        );

        $fields->push(
            Tab::create(
                'Email',
                _t(__CLASS__ . ".Email", "Email")
            )
        );

        $email_field_name = $this->prefixedFieldName('Email');
        $fields->addFieldToTab(
            'Email',
            EmailField::create(
                $email_field_name,
                _t( __CLASS__ . '.ENTER_EMAIL_ADDRESS', 'Enter a valid email address')
            )->setDescription(
                _t( __CLASS__ . '.EMAIL_NOTE', 'e.g. \'someone@example.com\'')
            )
        );

        $fields->push(
            Tab::create(
                'Page',
                _t(__CLASS__ . ".Page", "Page")
            )
        );

        $fields->addFieldToTab(
            'Page',
            TreeDropdownField::create(
                $this->prefixedFieldName('SiteTree'),
                _t( __CLASS__ . '.CHOOSE_PAGE_ON_THIS_WEBSITE', 'Choose a page on this website'),
                SiteTree::class
            )->setForm( $this->getForm() )
        );

        $fields->push(
            Tab::create(
                'File',
                _t(__CLASS__ . ".File", "File")
            )
        );

        $fields->addFieldToTab(
            'File',
            UploadField::create(
                $this->prefixedFieldName('File'),
                _t(__CLASS__ . '.CHOOSE_A_FILE', 'Choose a file on this website'),
            )->setUploadEnabled(true)
            ->setAttachEnabled(true)
            ->setAllowedMaxFileNumber(1)
            ->setIsMultiUpload(false)
        );

        $fields->push(
            Tab::create(
                'Phone',
                _t(__CLASS__ . ".Phone", "Phone")
            )
        );

        $fields->addFieldToTab(
            'Phone',
            TextField::create(
                $this->prefixedFieldName('Phone'),
                _t( __CLASS__ . '.ENTER_A_PHONE_NUMBER', 'Enter a telephone number')
            )->setDescription(
                _t( __CLASS__ . '.PHONE_NOTE', 'Supply the country dialling code to remove ambiguity')
            )->setInputType('tel')
        );

        return $fields;
    }

}
