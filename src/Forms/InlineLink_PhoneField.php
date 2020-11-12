<?php

namespace NSWDPC\InlineLinker;
use SilverStripe\Forms\TextField;

class InlineLink_PhoneField extends TextField {

    use InlineLink;

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'tel';

    protected $link_type = InlineLinkField::LINKTYPE_PHONE;
}
