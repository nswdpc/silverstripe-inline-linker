<?php

namespace NSWDPC\InlineLinker;
use SilverStripe\Forms\EmailField;

class InlineLink_EmailField extends EmailField {

    use InlineLink;

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'email';

    protected $link_type = InlineLinkField::LINKTYPE_EMAIL;

}
