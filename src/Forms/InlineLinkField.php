<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\HeaderField;
use BurnBright\ExternalURLField\ExternalURLField;
use gorriecoe\Link\Models\Link;
use gorriecoe\LinkField\LinkField;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\View\Requirements;

/**
 * Inline link field
 */
class InlineLinkField extends LinkField {

    protected $schemaDataType = FormField::SCHEMA_DATA_TYPE_STRUCTURAL;

    /** @skipUpgrade */
    protected $schemaComponent = 'CompositeField';

    private static $field_index_name = "InlineLink";

    public function __construct($name, $title, $parent)
    {
        parent::__construct($name, $title, $parent);
    }

    /**
     * Return a prefixed field name
     * @return string
     */
    protected function prefixedFieldName($name) {
        $index = $this->config()->get('field_index_name');
        return $this->getName() . "[{$index}][{$name}]";
    }

    public function getHasOneField() {
        $field = InlineLinkCompositeField::create(
            FieldList::create( $this->getAvailableFields() )
        )->setDescription(
            _t(
                __CLASS__ . ".SELECT_A_TYPE_OF_LINK",
                "Select the type of link you would like to create. The first link provided is saved."
            )
        );
        $field->setName( $this->getName() . "_Composite" );
        // this field's form on all child fields of the Composite
        $field->setForm($this->getForm());
        return $field;
    }

    /**
     * Returns available fields in order of precedence
     * @return array
     */
    protected function getAvailableFields() {

        $fields = [];
        $links = Link::get()->sort('Title ASC');
        if($this->record->exists()) {

            $links = $links->exclude("ID", $this->record->ID);

            $fields[] = HeaderField::create(
                $this->prefixedFieldName('CurrentLinkDetails'),
                _t(__CLASS__ . ".CURRENT_LINK_DETAILS", "Current link"),
                3
            );

            $fields[] = LiteralField::create(
                $this->prefixedFieldName('Existing'),
                '<p class="message info">'
                    . "<strong>" . _t(__CLASS__ . ".CURRENT_VALUE", "Title") . "</strong>: " . $this->record->Title
                    . "<br>"
                    . "<strong>" . _t(__CLASS__ . ".CURRENT_VALUE", "Link") . "</strong>: " . $this->record->getLinkURL()
                    . "<br>"
                    . "<strong>" . _t(__CLASS__ . ".CURRENT_TYPE", "Type") . "</strong>: " . $this->record->Type
                 . '</p>'
            );

            $toggle_title = _t(__CLASS__ . ".REPLACE_THIS_LINK_BELOW", "Replace this link");

        } else {

            $toggle_title = "Choose and save a link";

        }

        $fields[] = ToggleCompositeField::create(
            $this->prefixedFieldName('LinkToggler'),
            $toggle_title,
            [
                // existing link
                DropdownField::create(
                    $this->prefixedFieldName('Link'),
                    'Existing link',
                    $links->map('ID','TypeWithURL')->toArray()
                )->setEmptyString(''),

                // external URL
                ExternalURLField::create(
                    $this->prefixedFieldName('URL'),
                    'External URL'
                )->setConfig([
                    'html5validation' => true,
                    'defaultparts' => [
                        'scheme' => 'https'
                    ],
                ]),

                // email EMAILADDRESS
                EmailField::create(
                    $this->prefixedFieldName('Email'),
                    'Email address'
                ),

                // internal page
                TreeDropdownField::create(
                    $this->prefixedFieldName('SiteTree'),
                    'Internal page',
                    SiteTree::class
                )->setForm( $this->getForm() ),

                // current file record
                TreeDropdownField::create(
                    $this->prefixedFieldName('File'),
                    _t(__CLASS__ . '.FILE', 'File'),
                    File::class,
                    "ID",
                    "Title"
                )->setForm( $this->getForm() ),

                // phone
                TextField::create(
                    $this->prefixedFieldName('Phone'),
                    'Telephone'
                )->setInputType('tel')

            ]
        );
        return $fields;
    }

    /**
     * @param array $properties
     * @return CompositeField|GridField
     */
    public function Field($properties = [])
    {
        switch ($this->isOneOrMany()) {
            case 'one':
                return $this->getHasOneField();
                break;
            default:
                return parent::Field($properties);
                break;
        }
    }

    /**
     *
     * First validate then attempt to create  Link record from the value provided
     */
    protected function createLinkFromValue($validator) {
        $index = $this->config()->get('field_index_name');
        if( !is_array($this->value) || empty($this->value[ $index ]) || !is_array( $this->value[ $index ]) ) {
            $validator->validationError(
                $this->name,
                _t(
                    __CLASS__ . ".GENERAL_VALIDATION_ERROR",
                    "The {title} field requires at least one type of link",
                    [
                        'title' => $this->Title()
                    ]

                ),
                'validation'
            );
            return false;
        }

        // value the composite field
        $field = $this->getHasOneField();
        if(!($valid = $field->validate($validator))) {
            return false;
        }

        $fields = $field->FieldList();

        $values = $this->value[ $index ];
        $fields_with_values = [];
        foreach($values as $key => $value) {
            $field = $fields->dataFieldByName( $this->prefixedFieldName( $key ));
            $field->setValue( $value );
            if($value) {
                // this will get any textfield values and dropdown fields with an empty value
                $fields_with_values[ $key ] = $field;
            }
        }

        if(empty($fields_with_values)) {
            // no values submitted
            $validator->validationError(
                $this->getName(),
                _t(
                    __CLASS__ . ".NO_DATA_PROVIDED",
                    "Please provide at least one link"
                ),
                'validation'
            );
            return false;
        } else if(count($fields_with_values) > 1) {
            // more than one value provided
            $validator->validationError(
                $this->getName(),
                _t(
                    __CLASS__ . ".ONLY_ONE_TYPE_OF_LINK_PLEASE",
                    "Please ensure only one type of link is provided",

                ),
                'validation'
            );
            return false;
        } else {

            // just one value provided

            /**
             * @var string one of Link, URL, SiteTree, Email, Phone, File
             */
            $type  = key($fields_with_values);

            /**
             * @var string value to save
             */
            $field = current($fields_with_values);

            try {
                $link = Link::create();
                if($id = $link->saveInline($type, $field, $this->parent, $this->Title())) {
                    // save the link record ID to the parent (which will save itself)
                    $this->parent->setField($this->getName() . "ID", $id);
                    return true;
                }
                throw new \Exception("Could not save value at this time");
            } catch (\Exception $e) {
                $validator->validationError(
                    $this->getName(),
                    _t(
                        __CLASS__ . ".SAVE_INLINE_FIELD_ERROR",
                        "Error: {error}",
                        [
                            'error' => $e->getMessage()
                        ]
                    ),
                    'validation'
                );
                return false;
            }
        }
    }

    /**
     * Validate this field, in the CMS context this is called from CompositeField::validate
     *
     * @param Validator $validator
     * @return bool
     */
    public function validate($validator)
    {
        switch ($this->isOneOrMany()) {
            case 'one':
                return $this->createLinkFromValue($validator);
                break;
            default:
                return parent::validate($validator);
                break;
        }
    }

}
