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
        return $title . " - " . $url;
    }

    public function TypeWithURL() {
        $type = $this->owner->Type;
        $url = $this->owner->getLinkURL();
        return $type . " - " . $url;
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

    /**
     *  Save the value from the form field, which is of the provided value
     * @param string type
     * @param FormField the child field carrying the value to be saved
     * @param DataObject $parent record that has a relation with this instance
     * @param string $title the field title
     */
    public function saveInline($type, FormField $field, DataObject $parent, string $title = "") {
        $value = $field->Value();
        switch($type) {
            case 'Link':
                // pre-existing link
                $this->owner->LinkID = $value;
                $this->owner->Type = $this->getLinkTypeLink();
                break;
            case 'SiteTree':
                // pre-existing page
                $this->owner->SiteTreeID = $value;
                $this->owner->Type = 'SiteTree';
                break;
            case 'File':
                $this->owner->FileID = $value;
                $this->owner->Type = 'File';
                break;
            case 'URL':
                $this->owner->URL = $value;
                $this->owner->Type = 'URL';
                break;
            case 'Email':
                $this->owner->Email = $value;
                $this->owner->Type = 'Email';
                break;
            case 'Phone':
                $this->owner->Phone = $value;
                $this->owner->Type = 'Phone';
                break;
            case 'URL':
                $this->owner->URL = $value;
                $this->owner->Type = 'URL';
                break;
            default:
                throw new \Exception("Unknown type {$type}");
                break;
        }

        $this->owner->Title = _t(
                                    __CLASS__ . ".LINK_FIELD_OF_TYPE_FOR_RECORD",
                                    "Link for field {title} in record {record}",
                                    [
                                        'title' => $title,
                                        'record' => $parent->getTitle()
                                    ]
                                );
        return $this->owner->write();
    }
}
