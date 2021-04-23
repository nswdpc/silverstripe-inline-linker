<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LabelField;

/**
 * Subclass for specific composite field handling, currently not in use
 * Could be useful for future configuration
 */
class InlineLinkCompositeField extends CompositeField
{

    public function __construct($name, $title, $parent) {

        $children = FieldList::create();

        /**
         * @var Tabset
         */
        $inline_link_field = InlineLinkField::create(
            $name,
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_TYPE", "Select a link type"),
            $parent
        );

        // determine if in the context of an inline editable Elemental element
        $inline_editable = $inline_link_field->hasInlineElementalParent();

        $current_link = $inline_link_field->getRecord();
        $current_link_field = $inline_link_field->getCurrentLinkField();

        /**
         * If there is a current link,
         * render a header field and the template for the current link
         * .. and a remove checkbox
         * A link might exist without a Type, test for that
         */
        $has_current_link = false;
        if($current_link_field && $current_link && $current_link->exists() && $current_link->Type && $current_link->getLinkURL()) {
            $has_current_link = true;
            $children->push(
                LabelField::create(
                    $name . "_CurrentLinkHeader",
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.CURRENT_LINK_HEADER", "Current link")
                )
            );
            $children->push(
                // @var LiteralField
                $current_link_field
            );
            $children->push(
                // Remove the current link
                $remove_action = InlineLink_RemoveAction::create(
                    $inline_link_field->prefixedFieldName( InlineLinkField::FIELD_NAME_REMOVELINK ),
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.DELETE_LINK", 'Delete this link')
                )
            );
            $inline_link_field->setRemoveField( $remove_action );
        }

        $link_title_field = InlineLink_TitleField::create(
            $inline_link_field->prefixedFieldName( InlineLinkField::FIELD_NAME_TITLE ),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_TITLE", 'Title'),
            $inline_link_field->getRecordTitle()
        );

        $link_openinnewwindow_field = InlineLink_OpenInNewWindowField::create(
            $inline_link_field->prefixedFieldName( InlineLinkField::FIELD_NAME_OPEN_IN_NEW_WINDOW),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_OPEN_IN_NEW_WINDOW", 'Open in new window'),
            $inline_link_field->getRecordOpenInNewWindow()
        );

        // to save these fields, the InlineLinkField needs to know about them
        $inline_link_field->setTitleField( $link_title_field );
        $inline_link_field->setOpenInNewWindowField( $link_openinnewwindow_field );

        if($has_current_link) {
            $children->push(
                LabelField::create(
                    $name . "_ChangeLinkHeader",
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.CHANGE_LINK_HEADER", "Update this link")
                )
            );
        }

        $children->push(
            $link_title_field
        );

        $children->push(
            $link_openinnewwindow_field
        );

        $children->push(
            $inline_link_field
        );

        // push all child fields
        parent::__construct($children);

        // set name and title AFTER the parent composite is created
        $this->setName($name . "_InlinkLinkComposite");

        // handle inline editable element by using a fieldset/legend
        if($inline_editable) {
            $this->setLegend($title);
            $this->setTag('fieldset');
        } else {
            $this->setTitle($title);
            $this->setTag('div');
        }

    }

}
