<?php

namespace NSWDPC\InlineLinker;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Controllers\ElementalAreaController;
use gorriecoe\Link\Models\Link;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\LabelField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\SelectionGroup;
use SilverStripe\Forms\SelectionGroup_Item;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\SecurityToken;

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
        $value = null;

        // store record and parent
        $this->parent = $this->record = null;
        if($component = $parent->getComponent($name)) {
            $this->parent = $parent;
            $value = $component->Type;
        }
        if(!($component instanceof Link)) {
            throw new \InvalidArgumentException("Component {$name} must be an instance of Link::class");
        }

        $this->setRecord($component);

        // The selection group of fields for each type of link
        $this->createSelectionGroup($value);

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
                _t("NSWDPC\\InlineLinker\\InlineLinkField.DELETE_LINK", 'Delete this link')
            );
            $this->setRemoveField( $remove_action );
        }

        $link_title_field = InlineLink_TitleField::create(
            $this->prefixedFieldName( self::FIELD_NAME_TITLE ),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_TITLE", 'Title'),
            $this->getRecordTitle()
        );

        $link_openinnewwindow_field = InlineLink_OpenInNewWindowField::create(
            $this->prefixedFieldName( self::FIELD_NAME_OPEN_IN_NEW_WINDOW),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_OPEN_IN_NEW_WINDOW", 'Open in new window'),
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

        if($remove_action) {
            $children->push(
                $remove_action
            );
        }

        // selection group
        $children->push(
            $this->getLinkTypeFields()
        );

        return $children;

    }

    /**
     * Create the fields used to select the link type and provide the link value
     * @param null|string $type the current link type (null if not yet set)
     */
    protected function createSelectionGroup($type) : InlineLink_SelectionGroup {
        $this->selection_group = InlineLink_SelectionGroup::create(
            $this->prefixedFieldName( self::FIELD_NAME_TYPE ),// name
            _t("NSWDPC\\InlineLinker\\InlineLinkField.CHOOSE_A_LINK_TYPE", "Choose the type of the link"),// title
            $this->getAvailableItems() // tabs
        )->setCurrentType($type);
        return $this->selection_group;
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
            throw new ValidationException( _t("NSWDPC\\InlineLinker\\InlineLinkField.NO_VALUES_SUPPLIED_SAVE", "No values were supplied to save") );
        }

        $fields = $this->children->dataFields();
        foreach($fields as $field) {
            $field->setSubmittedValue( null );
        }

        foreach($values as $index => $value) {

            if($index == self::FIELD_NAME_TITLE) {
                // handle title field
                $this->getTitleField()->setSubmittedValue( $value );
            } else if($index == self::FIELD_NAME_OPEN_IN_NEW_WINDOW) {
                // handle open in new window field
                $this->getOpenInNewWindowField()->setSubmittedValue( $value );
            } else if($field = $this->children->dataFieldByName( $this->prefixedFieldName( $index ) )) {
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
                $this->getTitleField()->setSubmittedValue('');
                $this->getOpenInNewWindowField()->setSubmittedValue(false);
                $link->delete();
                // do not proceed
                return;
            }
        }

        // @var FormField
        $type_field = $this->children->dataFieldByName( $this->prefixedFieldName( self::FIELD_NAME_TYPE ) );
        if(!$type_field) {
            // type field was not a data field
            // no type field present
            // get from submitted value
            foreach($this->children->dataFields() as $field) {
                $index = $this->getIndexFromName( $field->getName() );
                switch($index) {
                    case self::LINKTYPE_EMAIL:
                    case self::LINKTYPE_URL:
                    case self::LINKTYPE_SITETREE:
                    case self::LINKTYPE_PHONE:
                    case self::LINKTYPE_FILE:
                        $value = $field->dataValue();
                        if($value) {
                            $type_field = $field;
                            $type = $index;
                            break 2;
                        }
                        break;
                    default:
                        // unhandled field
                        break;
                }
            }
        } else {
            $type = $type_field->dataValue();
        }

        // no type field
        if(!$type_field) {
            throw new ValidationException("The link type could not be determined or is unknown");
        }

        // @var string eg Email
        if(!$type) {
            throw new ValidationException("The link type was empty");
        }

        $value_field = $this->children->dataFieldByName( $this->prefixedFieldName( $type ) );
        if(!$value_field) {
            throw new ValidationException("The link value could not be found");
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

        if($type) {
            // apply the value found
            $link = $this->createOrAssociateLink($type, $value_field);
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
     * @param string $type eg. 'Email'
     * @param mixed $value eg. 'bob@example.com'
     * @param FormField $field the Form field holding the data related to the type
     * @return Link
     */
    protected function createOrAssociateLink(string $type, FormField $field) : Link {
        $value = $field->dataValue();

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
                throw new ValidationException("The link of the type '{$type}' cannot be saved'");
                break;
        }

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
     * Returns whether the type passed in as the current type
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
     * Returns available fields in order of precedence
     * @return array
     */
    protected function getAvailableItems() {

        $record = $this->getRecord();

        $fields = [

            'URL' => InlineLink_SelectionGroup_Item::create(
                'URL', // name
                _t("NSWDPC\\InlineLinker\\InlineLinkField.EXTERNAL", "External"),// title
                // field(s) in the item
                InlineLink_URLField::create(
                    $this->prefixedFieldName('URL'),
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.EXTERNAL_URL", 'Provide an external URL'),
                    (isset($record->URL) ? $record->URL : '')
                )->setConfig([
                    'html5validation' => true,
                    'defaultparts' => [
                        'scheme' => 'https'
                    ],
                ])->setDescription(
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.EXTERNAL_URL_NOTE", 'The URL should start with an https:// or http://')
                ),
                $this->prefixedFieldName('Type'),
                $this->isTypeCurrent('URL')
            ),

            'Email' => InlineLink_SelectionGroup_Item::create(
                'Email',
                _t("NSWDPC\\InlineLinker\\InlineLinkField.Email", "Email"),
                InlineLink_EmailField::create(
                    $this->prefixedFieldName('Email'),
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.ENTER_EMAIL_ADDRESS", 'Enter a valid email address'),
                    (isset($record->Email) ? $record->Email : '')
                )->setDescription(
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.EMAIL_NOTE", 'e.g. \'someone@example.com\'')
                ),
                $this->prefixedFieldName('Type'),
                $this->isTypeCurrent('Email')
            ),

            'SiteTree' => InlineLink_SelectionGroup_Item::create(
                'SiteTree',
                _t("NSWDPC\\InlineLinker\\InlineLinkField.Page", "Page"),
                InlineLink_SiteTreeField::create(
                    $this->prefixedFieldName('SiteTree'),
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.CHOOSE_PAGE_ON_THIS_WEBSITE", 'Choose a page on this website or type to start searching'),
                    SiteTree::class
                )->setValue( $record->SiteTreeID ),
                $this->prefixedFieldName('Type'),
                $this->isTypeCurrent('SiteTree')
            ),

            'File' => InlineLink_SelectionGroup_Item::create(
                'File',
                _t("NSWDPC\\InlineLinker\\InlineLinkField.File", "File"),
                InlineLink_FileField::create(
                    $this->prefixedFieldName('File'),
                    _t(__CLASS__ . '.CHOOSE_A_FILE', 'Choose a file on this website'),
                ),
                $this->prefixedFieldName('Type'),
                $this->isTypeCurrent('File')
            ),

            'Phone' => InlineLink_SelectionGroup_Item::create(
                'Phone',
                _t("NSWDPC\\InlineLinker\\InlineLinkField.Phone", "Phone"),
                InlineLink_PhoneField::create(
                    $this->prefixedFieldName('Phone'),
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.ENTER_A_PHONE_NUMBER", 'Enter a telephone number'),
                    (isset($record->Phone) ? $record->Phone : '')
                )->setDescription(
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.PHONE_NOTE", 'Supply the country dialling code to remove ambiguity')
                )->addExtraClass('text'),
                $this->prefixedFieldName('Type'),
                $this->isTypeCurrent('Phone')
            )

        ];

        return $fields;
    }

}
