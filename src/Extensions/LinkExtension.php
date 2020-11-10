<?php

namespace NSWDPC\InlineLinker;
use gorriecoe\Link\Models\Link;
use Silverstripe\Forms\DropdownField;
use Silverstripe\Forms\FormField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\ViewableData;


class LinkExtension extends DataExtension {

    private static $has_one = [
        'Link' => Link::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'Link',
                __CLASS__ . ".LINK", "Existing link",
                Link::get()->sort('Title ASC')->map("ID","Title")->toArray()
            )
        );
    }

    public function TitleWithURL() {
        $title = $this->owner->Title;
        $url = $this->owner->getLinkURL();
        return "#" . $this->owner->ID . " " . $title . " - " . $url;
    }

    public function TypeWithURL() {
        $type = $this->owner->Type;
        $url = $this->owner->getLinkURL();
        return "#" . $this->owner->ID . " " . $type . " - " . $url;
    }

    /**
     * Link is a valid allowed type
     */
    public function updateTypes(&$types) {
        $types[] = $this->getLinkTypeLink();
    }

    public function updateLinkURL(&$link_url) {
        if($this->owner->Type == $this->getLinkTypeLink()) {
            $link = $this->owner->Link();
            if($link && $link->exists()) {
                $link_url = $link->getLinkURL();
            }
        }
        if($link_url instanceof ViewableData) {
            $link_url = $link_url->forTemplate();
        } else {
            $link_url = strval($link_url);
        }
    }

    public function getLinkTypeLink() {
        return "Link";
    }

}
