<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\TreeDropdownField;

class InlineLink_SiteTreeField extends TreeDropdownField {

    use InlineLink;

    protected $link_type = InlineLinkField::LINKTYPE_SITETREE;

    /**
     * This subclass only allows SiteTree::class as the source object
     */
    #[\Override]
    public function setSourceObject($class)
    {
        if(class_exists(SiteTree::class)) {
            $this->sourceObject = SiteTree::class;
        } else {
            $this->sourceObject = null;
        }

        return $this;
    }

    /**
     * This subclass only allows SiteTree::class as the source object
     */
    #[\Override]
    public function getSourceObject()
    {
        if(class_exists(SiteTree::class)) {
            $this->sourceObject = SiteTree::class;
        } else {
            $this->sourceObject = null;
        }

        return parent::getSourceObject();
    }

}
