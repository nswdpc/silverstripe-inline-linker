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
        if(!$this->owner->config()->get('enable_linkrecord_linking')) {
            return;
        }
        $links = Link::get()->sort('Title ASC');
        $links = $links->map("ID","TitleWithURL")->toArray();
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'LinkID',
                _t(__CLASS__ . ".EXISTING_LINK", "Choose an existing link"),
                $links
            )->setEmptyString('')
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
        if($this->owner->config()->get('enable_linkrecord_linking')) {
            $types[] = InlineLinkField::LINKTYPE_LINK;
        }
    }

    public function updateLinkURL(&$link_url) {
        if($this->owner->config()->get('enable_linkrecord_linking')) {
            if($this->owner->Type == InlineLinkField::LINKTYPE_LINK) {
                $link = $this->owner->Link();
                if($link && $link->exists()) {
                    $link_url = $link->getLinkURL();
                }
            }
        }
        if($link_url instanceof ViewableData) {
            $link_url = $link_url->forTemplate();
        } else {
            $link_url = strval($link_url);
        }
    }

    public function getLinkTypeLink() {
        return InlineLinkField::LINKTYPE_LINK;
    }

}
