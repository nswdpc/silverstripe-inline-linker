<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;

/**
 * Subclass for specific composite field handling, currently not in use
 * Could be useful for future configuration
 */
class InlineLinkCompositeField extends CompositeField
{
    public function __construct($name, $title, $parent) {

        $children = FieldList::create();

        $inline_link_field = InlineLinkField::create($name, $title, $parent);

        $current = $inline_link_field->CurrentLink();

        $children->push(
            HeaderField::create(
                $name . "_NewLinkHeader",
                _t("NSWDPC\\InlineLinker\\InlineLinkField.NEW_LINK_HEADER", "Set the link")
            )
        );

        $link_title_field = TextField::create(
            $inline_link_field->prefixedFieldName('Title'),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_TITLE", 'Title')
        )->setSubmittedValue( $inline_link_field->getRecordTitle() );

        $children->push(
            $link_title_field
        );

        $link_openinnewwindow_field = CheckboxField::create(
            $inline_link_field->prefixedFieldName('OpenInNewWindow'),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_OPEN_IN_NEW_WINDOW", 'Open in new window')
        )->setValue( $inline_link_field->getRecordOpenInNewWindow() );
        $children->push(
            $link_openinnewwindow_field
        );

        // to save these fields, the InlineLinkField needs to know about them
        $inline_link_field->setTitleField( $link_title_field );
        $inline_link_field->setOpenInNewWindowField( $link_openinnewwindow_field );

        // Ensure the InlineLinkField (TabSet) gets added as a child
        $children->push(
            $inline_link_field
        );

        // if there is a current link, render a header field and the template for the current link
        if($current) {
            $children->push(
                HeaderField::create(
                    $name . "_CurrentLinkHeader",
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.CURRENT_LINK_HEADER", "Current link")
                )
            );
            $children->push($current);
        }

        // push all child fields
        parent::__construct($children);

        // set name and title AFTER the parent composite is created
        $this->setName($name . "_InlinkLinkComposite");
        $this->setTitle($title);

    }

}
