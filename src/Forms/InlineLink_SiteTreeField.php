<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\TreeDropdownField;

class InlineLink_SiteTreeField extends TreeDropdownField {

    use InlineLink;

    protected $sourceObject = SiteTree::class;

    protected $link_type = InlineLinkField::LINKTYPE_SITETREE;

}
