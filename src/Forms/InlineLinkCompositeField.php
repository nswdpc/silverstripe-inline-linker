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

        // if there is a current link, render a header field and the template for the current link
        if($current) {
            $children->push(
                CompositeField::create(
                    HeaderField::create(
                        $name . "_CurrentLinkHeader",
                        _t("NSWDPC\\InlineLinker\\InlineLinkField.CURRENT_LINK_HEADER", "Current link")
                    ),
                    $current
                )
            );
        }

        $link_title_field = TextField::create(
            $inline_link_field->prefixedFieldName( InlineLinkField::FIELD_NAME_TITLE ),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_TITLE", 'Title'),
            $inline_link_field->getRecordTitle()
        );

        $link_openinnewwindow_field = CheckboxField::create(
            $inline_link_field->prefixedFieldName( InlineLinkField::FIELD_NAME_OPEN_IN_NEW_WINDOW),
            _t("NSWDPC\\InlineLinker\\InlineLinkField.LINK_OPEN_IN_NEW_WINDOW", 'Open in new window'),
            $inline_link_field->getRecordOpenInNewWindow()
        );

        // to save these fields, the InlineLinkField needs to know about them
        $inline_link_field->setTitleField( $link_title_field );
        $inline_link_field->setOpenInNewWindowField( $link_openinnewwindow_field );

        $children->push(
            CompositeField::create(
                HeaderField::create(
                    $name . "_NewLinkHeader",
                    _t("NSWDPC\\InlineLinker\\InlineLinkField.NEW_LINK_HEADER", "Update the link")
                ),
                $link_title_field,
                $link_openinnewwindow_field,
                $inline_link_field
            )
        );

        // push all child fields
        parent::__construct($children);

        // set name and title AFTER the parent composite is created
        $this->setName($name . "_InlinkLinkComposite");
        $this->setTitle($title);

    }

}
