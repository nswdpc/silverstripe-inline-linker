<?php

namespace NSWDPC\InlineLinker;
use SilverStripe\Forms\TreeDropdownField;

class InlineLink_SiteTreeField extends TreeDropdownField {

    use InlineLink;

    protected $sourceObject = SiteTree::class;

    protected $link_type = InlineLinkCompositeField::LINKTYPE_SITETREE;

}
