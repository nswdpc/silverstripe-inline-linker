<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\HeaderField;
use BurnBright\ExternalURLField\ExternalURLField;
use gorriecoe\Link\Models\Link;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\DropdownField;
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
     * @var gorriecoe\Link\Models\Link|null
     */
    protected $record;

    /**
     * @var SilverStripe\ORM\DataObject|null
     */
    protected $parent;

    public function __construct($name, $title, $parent)
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
     * @param mixed $value
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
            if($field = $this->children->dataFieldByName( $this->prefixedFieldName( $key ) )) {
                $field->setSubmittedValue( $value );
            }
        }
    }

    public function hasData() {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function saveInto(DataObjectInterface $record)
    {
        $children = $this->getChildren()->dataFields();
        foreach($children as $field) {
            $name = $field->getName();
            $value = $field->dataValue();
            if(!$value) {
                continue;
            }
            if($link = $this->createOrAssociateLink($field)) {
                if(!$link->exists()) {
                    $link->Title = "Link for record " . $this->parent->getTitle();
                    $link->write();
                }
                $this->record = $link;
                // save the link id
                $this->parent->setField($this->getName() . "ID", $link->ID);
            }
            // only save the first value
            break;
        }
    }

    /**
     * Create or save a link using the value from the form field
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
                $record = [
                    'FileID' => $value,
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
    protected function prefixedFieldName($type) {
        return $this->getName() . "[{$type}]";
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
     * Returns available fields in order of precedence
     * @return array
     */
    protected function getAvailableFields() {

        $fields = FieldList::create();
        $links = Link::get()->sort('Title ASC');

        if($this->record && $this->record->exists()) {
            $links = $links->exclude("ID", $this->record->ID);
            $fields->push(
                Tab::create(
                    'Current',
                    _t(__CLASS__ . '.THE_CURRENT_LINK', 'Current')
                )
            );

            $fields->addFieldsToTab(
                'Current',
                [
                    HeaderField::create(
                        $this->prefixedFieldName('CurrentLinkDetails'),
                        _t(__CLASS__ . ".CURRENT_LINK_DETAILS", "Current link"),
                        3
                    ),
                    // literal field template for the current link
                    LiteralField::create(
                        $this->prefixedFieldName('ExistingLinkRecord'),
                        '<p class="message notice">'
                            . "<strong>" . _t(__CLASS__ . ".CURRENT_VALUE", "Title") . "</strong>: " . $this->record->Title
                            . "<br>"
                            . "<strong>" . _t(__CLASS__ . ".CURRENT_VALUE", "Link") . "</strong>: " . $this->record->getLinkURL()
                            . "<br>"
                            . "<strong>" . _t(__CLASS__ . ".CURRENT_TYPE", "Type") . "</strong>: " . $this->record->Type
                         . '</p>'
                    )
                ]
            );

        }

        // a field that the editor can toggle to open
        $fields->push(
            Tab::create(
                'Link',
                'Link',
            )
        );

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
                'External',
                'External'
            )
        );
        $fields->addFieldToTab(
            'External',
            ExternalURLField::create(
                $this->prefixedFieldName('URL'),
                _t( __CLASS__ . '.EXTERNAL_URL', 'Enter an external URL')
            )->setConfig([
                'html5validation' => true,
                'defaultparts' => [
                    'scheme' => 'https'
                ],
            ])->setInputType('url')
        );

        $fields->push(
            Tab::create(
                'Email',
                'Email'
            )
        );

        $email_field_name = $this->prefixedFieldName('Email');
        $fields->addFieldToTab(
            'Email',
            EmailField::create(
                $email_field_name,
                _t( __CLASS__ . '.ENTER_EMAIL_ADDRESS', 'Enter a valid email address')
            )
        );

        $fields->push(
            Tab::create(
                'Page',
                'Page'
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
                'File'
            )
        );
        $fields->addFieldToTab(
            'File',
            TreeDropdownField::create(
                $this->prefixedFieldName('File'),
                _t(__CLASS__ . '.CHOOSE_A_FILE', 'Choose a file on this website'),
                File::class,
                "ID",
                "Title"
            )->setForm( $this->getForm() )
        );

        $fields->push(
            Tab::create(
                'Phone',
                'Phone'
            )
        );

        $fields->addFieldToTab(
            'Phone',
            TextField::create(
                $this->prefixedFieldName('Phone'),
                _t( __CLASS__ . '.ENTER_A_PHONE_NUMBER', 'Enter a telephone number')
            )->setInputType('tel')
        );

        return $fields;
    }

}
