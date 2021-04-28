<?php

namespace NSWDPC\InlineLinker;

use Codem\Utilities\HTML5\TelField;

/**
 * Provide a phone number for association with a Link
 */
class InlineLink_PhoneField extends TelField {

    use InlineLink;

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'tel';

    protected $link_type = InlineLinkCompositeField::LINKTYPE_PHONE;

}
