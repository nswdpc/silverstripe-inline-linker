<?php

namespace NSWDPC\InlineLinker;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Controllers\ElementalAreaController;
use gorriecoe\Link\Models\Link;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Tip;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\SecurityToken;
use SilverStripe\View\Requirements;

/**
 * Subclass for specific composite field handling, currently not in use
 * Could be useful for future configuration
 */
class InlineLinkField extends CompositeField
{

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
     * @var OptionsetField
     *
     */
    protected $open_in_new_window_field;

    /**
     * @var InlineLink_RemoveAction
     *
     */
    protected $remove_field;

    /**
     * @var SelectionGroup
     */
    protected $selection_group;

    /**
     * @var bool
     */
    protected $is_removing_link = false;

    const FIELD_NAME_TYPE_SEPARATOR = "___";

    const FIELD_NAME_REMOVELINK = "RemoveLink";
    const FIELD_NAME_TITLE = "Title";
    const FIELD_NAME_OPEN_IN_NEW_WINDOW = "OpenInNewWindow";
    const FIELD_NAME_TYPE = "Type";

    const LINKTYPE_EMAIL = 'Email';
    const LINKTYPE_URL = 'URL';
    const LINKTYPE_SITETREE = 'SiteTree';
    const LINKTYPE_PHONE = 'Phone';
    const LINKTYPE_FILE = 'File';
    const LINKTYPE_TYPEDEFINED = 'BasedOnType';

    public function __construct($name, $title, DataObject $parent) {
        // push all child fields
        parent::__construct($this->collectChildFields($name, $title, $parent));
        $this->setName($name);
    }

    /**
     * Collect all fields to be used in the CompositeField
     */
    protected function collectChildFields($name, $title, DataObject $parent) : FieldList {

        // set name and title early
        $this->name = $name;
        $this->title = $title;
        // initialise record and parent
        $this->parent = $this->record = null;
        $component = $parent->getComponent($name);
        if(!($component instanceof Link)) {
            throw new \InvalidArgumentException(_t(
                "NSWDPC\\InlineLinker\\InlineLinkField.INVALID_COMPONENT",
                "Error: component {name} must be an instance of Link",
                [
                    'name' => $this->name
                ]
            ));
        }

        // set a valid parent
        $this->parent = $parent;
        $this->setRecord($component);

        // determine if in the context of an inline editable Elemental element
        $inline_editable = $this->hasInlineElementalParent();

        if($inline_editable) {
            $this->setLegend($title);
            $this->setTag('fieldset');
        } else {
            $this->setTitle($title);
            $this->setTag('div');
        }

        /**
         * If there is a current link,
         * render a header field and the template for the current link
         * .. and a remove checkbox
         * A link might exist without a Type, test for that
         */
        $has = $this->hasCurrentLink();
        $remove_action = null;
        if($has) {
            $remove_action = InlineLink_RemoveAction::create(
                $this->prefixedFieldName( self::FIELD_NAME_REMOVELINK ),
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.DELETE_LINK",
                    'Delete this link'
                )
            );
            $this->setRemoveField( $remove_action );
        }

        $link_title_field = InlineLink_TitleField::create(
            $this->prefixedFieldName( self::FIELD_NAME_TITLE ),
            _t(
                "NSWDPC\\InlineLinker\\InlineLinkField.LINK_TITLE",
                'Title'
            ),
            $this->getRecordTitle()
        );

        $link_openinnewwindow_field = InlineLink_OpenInNewWindowField::create(
            $this->prefixedFieldName( self::FIELD_NAME_OPEN_IN_NEW_WINDOW),
            _t(
                "NSWDPC\\InlineLinker\\InlineLinkField.LINK_OPEN_IN_NEW_WINDOW",
                'Open in new window'
            ),
            [],
            $this->getRecordOpenInNewWindow()
        );

        $this->setTitleField( $link_title_field );
        $this->setOpenInNewWindowField( $link_openinnewwindow_field );

        $children = FieldList::create();

        $children->push(
            // common fields
            $link_title_field,
        );

        $children->push(
            // common fields
            $link_openinnewwindow_field
        );

        // selection group
        $children->push(
            $this->getLinkFields() // link type field collection
        );

        if($remove_action) {
            $children->push(
                $remove_action
            );
        }

        return $children;

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
                // grab any {$this->name}__{index} values into an array
                foreach($values_all as $name => $value) {
                    $index = $this->getIndexFromName($name);
                    if($index) {
                        $values[ $index ] = $value;
                    }
                }
            }
        }

        if(!is_array($values)) {
            // cannot proceed unless we have some values
            throw new ValidationException(_t(
                "NSWDPC\\InlineLinker\\InlineLinkField.NO_VALUES_SUPPLIED_SAVE",
                "No values were supplied to save"
            ));
        }

        // clear data field values
        $fields = $this->children->dataFields();
        foreach($fields as $field) {
            $field->setSubmittedValue( null );
        }

        // set submitted values
        foreach($values as $index => $value) {
            if($index == self::FIELD_NAME_TITLE) {
                // handle title field
                $this->getTitleField()->setSubmittedValue( $value );
            } else if($index == self::FIELD_NAME_OPEN_IN_NEW_WINDOW) {
                // handle open in new window field
                $this->getOpenInNewWindowField()->setSubmittedValue( $value );
            } else if($field = $this->children->dataFieldByName( $this->prefixedFieldName( $index ) )) {
                // set the submitted value on the relevant field
                $field->setSubmittedValue( $value );
            }
        }
    }

    /**
     * @param InlineLink_TitleField
     */
    public function setTitleField(InlineLink_TitleField $field) {
        $this->title_field = $field;
        return $this;
    }

    /**
     * @return InlineLink_TitleField
     */
    public function getTitleField() {
        return $this->title_field;
    }

    /**
     * @param InlineLink_OpenInNewWindowField
     */
    public function setOpenInNewWindowField(InlineLink_OpenInNewWindowField $field) {
        $this->open_in_new_window_field = $field;
        return $this;
    }

    /**
     * @return InlineLink_OpenInNewWindowField
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
     * @return SelectionGroup
     */
    public function getLinkTypeFields() {
        return $this->selection_group;
    }

    /**
     * This field handles data
     */
    public function hasData() {
        return true;
    }

    public function canSubmitValue() : bool {
        return true;
    }

    /**
     * This field handles all the saving
     */
    public function collateDataFields(&$list, $saveableOnly = false)
    {
        return;
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
                // clear all field submitted
                // avoids re-display with data
                foreach($this->children->dataFields() as $field) {
                    $field->setSubmittedValue(null);
                }
                $link->delete();
                // do not proceed
                return;
            }
        }

        // @var FormField
        $type_field = $this->children->dataFieldByName( $this->prefixedFieldName( self::FIELD_NAME_TYPE ) );
        // no type field
        if(!$type_field) {
            throw new ValidationException(_t(
                "NSWDPC\\InlineLinker\\InlineLinkField.NO_LINK_TYPE_FIELD_ERROR",
                "The link type could not be determined or is unknown"
            ));
        }

        $type = $type_field->dataValue();
        // @var string eg Email
        if(!$type) {
            // if there is no type value provided, no link can be created
            return;
        }

        // grab the value field based on the Type selected
        $value_field = $this->children->dataFieldByName( $this->prefixedFieldName( $type ) );
        if(!$value_field) {
            // maybe 'BasedOnType' multi link field value
            $value_field = $this->children->dataFieldByName( $this->prefixedFieldName( self::LINKTYPE_TYPEDEFINED) );
        }

        if(!$value_field) {
            throw new ValidationException(_t(
                "NSWDPC\\InlineLinker\\InlineLinkField.NO_LINK_VALUE_ERROR",
                "A value for the link could not be found"
            ));
        }

        //set model options
        $open_in_new_window = 0;
        $title =_t(
            "NSWDPC\\InlineLinker\\InlineLinkField.AUTO_TITLE",
            "Auto-created title for a link in " . $this->parent->getTitle()
        );
        if($open_in_new_window_field = $this->getOpenInNewWindowField()) {
            $open_in_new_window = $open_in_new_window_field->dataValue();
        }
        if($title_field = $this->getTitleField()) {
            $title = $title_field->dataValue();
        }

        if($type) {
            // apply the value found
            $link = $this->createOrAssociateLink($type, $value_field);

            // save Title and OpenInNewWindow
            $link->Title = $title;
            $link->OpenInNewWindow = $open_in_new_window;
            $link->write();

            // the link becomes the record
            $this->setRecord($link);
            // save the link id to the parent element that has the relation to the link
            $this->parent->setField($this->getName() . "ID", $link->ID);
        }

    }

    /**
     * Create or save a link using the value from the form field
     * @param string $type eg. 'Email'
     * @param mixed $value eg. 'bob@example.com'
     * @param FormField $field the Form field holding the data related to the type
     * @return Link
     */
    protected function createOrAssociateLink(string $type, FormField $field) : Link {
        $value = $field->dataValue();

        // defaults
        $base = [
            'URL' => null,
            'FileID' => null,
            'Email' => null,
            'Phone' => null,
            'SiteTreeID' => null,
        ];

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
                throw new ValidationException(_t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.UNHANDLED_LINK_TYPE_ERROR",
                    "The link of the type '{type}' cannot be saved'",
                    [
                        'type' => $type
                    ]
                ));
                break;
        }

        // apply data over defaults
        $data = array_merge($base, $data);

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
     * Returns whether the type passed in as the current Link.Type
     * @return bool
     */
    protected function isTypeCurrent($type) : bool {
        return !empty($this->record->Type) && $this->record->Type == $type;
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
     * Return a prefixed field name, eg. LinkTarget[Email]
     * @param string $index eg. Email, Type, OpenInNewWindow...
     * @return string
     */
    public function prefixedFieldName($index) {
        if($this->hasInlineElementalParent()) {
            /*
             * Cannot use index notation due to
             * https://github.com/dnadesign/silverstripe-elemental/issues/381
             * https://github.com/silverstripe/silverstripe-admin/issues/639
             */
            return $this->getName() . self::FIELD_NAME_TYPE_SEPARATOR . $index;
        } else {
            /**
             * Can use indexed notation - either a non inline editable element
             * or a normal dataobject edit form
             */
            return $this->getName() . "[{$index}]";
        }
    }

    /**
     * Work out the index based on the field name
     * If the parent is an inline editable element, take that into account
     * @return string
     */
    protected function getIndexFromName($complete_field_name) {
        $type = "";
        if($this->hasInlineElementalParent()) {
            // the field name should start with the prefix...
            if(strpos($complete_field_name, $this->getName() . self::FIELD_NAME_TYPE_SEPARATOR) !== 0) {
                // invalid field name
                return "";
            }
            // Get type using separator
            $parts = explode(self::FIELD_NAME_TYPE_SEPARATOR, $complete_field_name);
            // 0=parentname 1=type eg. LinkTarget__Index
            $index = isset($parts[1]) ? $parts[1] : '';
            return $index;
        } else {
            // Non inline_editable elements or standard modeladmin, using field[index] naming
            $result = [];
            $name = $this->getName();
            parse_str($complete_field_name, $results);
            if(isset($results[ $name ])) {
                $target = $results[ $name ];
                $index = key($target);
            }
            return $index;
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
            $html = $this->record->renderWith('NSWDPC/InlineLinker/CurrentLinkTemplate');
            $field = LiteralField::create(
                $this->prefixedFieldName($name),
                $html
            );
        }
        return $field;
    }

    /**
     * Return all available Link Fields
     * Modify fields via the updateLinkFields extension method
     * @return CompositeField
     */
    public function getLinkFields() : CompositeField {

        $record = $this->getRecord();
        $type = '';
        $file_list = null;
        $value = '';
        if($record && $record->exists()) {
            $type = $record->Type;
            // file storage
            $file_list = ArrayList::create();
            $file_list->push( $record->File() );
            // Retrieve the link value, based on the record type
            switch($record->Type) {
                case self::LINKTYPE_URL:
                    $value = $record->URL;
                    break;
                case self::LINKTYPE_EMAIL:
                    $value = $record->Email;
                    break;
                case self::LINKTYPE_PHONE:
                    $value = $record->Phone;
                    break;
                default:
                    $value = '';
                    break;
            }
        }

        $fields = CompositeField::create(

            DropdownField::create(
                $this->prefixedFieldName(self::FIELD_NAME_TYPE),
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.THE_LINK_TYPE",
                    "Choose a link type"
                ),
                [
                    self::LINKTYPE_SITETREE => _t("NSWDPC\\InlineLinker\\InlineLinkField.PAGE_TYPE", 'A page on this website'),
                    self::LINKTYPE_URL => _t("NSWDPC\\InlineLinker\\InlineLinkField.URL_TYPE", 'An external URL (including optional #anchor)'),
                    self::LINKTYPE_EMAIL => _t("NSWDPC\\InlineLinker\\InlineLinkField.EMAIL_TYPE", 'An email address'),
                    self::LINKTYPE_PHONE => _t("NSWDPC\\InlineLinker\\InlineLinkField.PHONE_TYPE", 'A phone number'),
                    self::LINKTYPE_FILE => _t("NSWDPC\\InlineLinker\\InlineLinkField.FILE_TYPE", 'A file on this website')
                ],
                $type
            )->setDescription(
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.THE_LINK_TYPE_DESCRIPTION",
                    "Leave empty for no link"
                ),
            )->setValue($type)
            ->setEmptyString(''),// default to no link

            InlineLink_URLField::create(
                $this->prefixedFieldName(self::LINKTYPE_URL),
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.ENTER_WEBSITE_URL",
                    'Enter a website URL'
                ),
                $record->URL ?: ''
            )->setTip(
                new Tip(
                    _t(
                        "NSWDPC\\InlineLinker\\InlineLinkField.ENTER_WEBSITE_URL_RIGHTNOTE",
                        'Website links should begin with https:// or http://'
                    )
                )
            )->setDescription(
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.ENTER_WEBSITE_URL_DESCRIPTION",
                    'Website links should begin with https:// or http://'
                )
            )->setAttribute(
                'data-signals',
                json_encode([
                    [
                        'containerSelector' => '.form-group',
                        'triggerElement' => $this->prefixedFieldName(self::FIELD_NAME_TYPE),
                        'value' => [ self::LINKTYPE_URL ]
                    ]
                ])
            ),

            InlineLink_EmailField::create(
                $this->prefixedFieldName(self::LINKTYPE_EMAIL),
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.ENTER_AN_EMAIL_ADDRESS",
                    'Enter an e-mail address'
                ),
                $record->Email ?: ''
            )->setAttribute(
                'data-signals',
                json_encode([
                    [
                        'containerSelector' => '.form-group',
                        'triggerElement' => $this->prefixedFieldName(self::FIELD_NAME_TYPE),
                        'value' => [ self::LINKTYPE_EMAIL ]
                    ]
                ])
            ),

            InlineLink_PhoneField::create(
                $this->prefixedFieldName(self::LINKTYPE_PHONE),
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.ENTER_AN_PHONE_NUMBER",
                    'Enter a phone number'
                ),
                $record->Phone ?: ''
            )->setTip(
                new Tip(
                    _t(
                        "NSWDPC\\InlineLinker\\InlineLinkField.ENTER_AN_PHONE_NUMBER_RIGHTNOTE",
                        'Phone numbers should start with the country dialling code'
                    )
                )
            )->setDescription(
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.ENTER_AN_PHONE_NUMBER_DESCRIPTION",
                    'Phone numbers should start with the country dialling code, example +61 499 999 999'
                )
            )->setAttribute(
                'data-signals',
                json_encode([
                    [
                        'containerSelector' => '.form-group',
                        'triggerElement' => $this->prefixedFieldName(self::FIELD_NAME_TYPE),
                        'value' => [ self::LINKTYPE_PHONE ]
                    ]
                ])
            ),

            InlineLink_SiteTreeField::create(
                $this->prefixedFieldName(self::LINKTYPE_SITETREE),
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.CHOOSE_PAGE_ON_THIS_WEBSITE",
                    'Choose a page on this website or type to start searching'
                ),
                SiteTree::class
            )->setValue(
                $record->SiteTreeID ?: null
            )->setAttribute(
                'data-signals',
                json_encode([
                    [
                        'containerSelector' => '.form-group',
                        'triggerElement' => $this->prefixedFieldName(self::FIELD_NAME_TYPE),
                        'value' => [ self::LINKTYPE_SITETREE  ]
                    ]
                ])
            ),

            InlineLink_FileField::create(
                $this->prefixedFieldName(self::LINKTYPE_FILE),
                _t(
                    "NSWDPC\\InlineLinker\\InlineLinkField.CHOOSE_A_FILE",
                    'Upload to or choose a file on this website'
                ),
                $file_list
            )->setAttribute(
                'data-signals',
                json_encode([
                    [
                        'containerSelector' => '.form-group',
                        'triggerElement' => $this->prefixedFieldName(self::FIELD_NAME_TYPE),
                        'value' => [ self::LINKTYPE_FILE  ]
                    ]
                ])
            )

        );

        $this->extend('updateLinkFields', $fields);

        Requirements::javascript(
            'nswdpc/silverstripe-inline-linker:/client/dist/js/app.js'
        );

        return $fields;
    }

}
