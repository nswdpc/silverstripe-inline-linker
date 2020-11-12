<?php

namespace NSWDPC\InlineLinker;
use SilverStripe\Forms\DropdownField;

class InlineLink_LinkField extends DropdownField {

    use InlineLink;

    protected $link_type = InlineLinkField::LINKTYPE_LINK;

}
