<?php

namespace NSWDPC\InlineLinker;

use Codem\Utilities\HTML5\EmailField;

/**
 * An email field
 */
class InlineLink_EmailField extends EmailField {

    use InlineLink;

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'email';

    protected $link_type = InlineLinkCompositeField::LINKTYPE_EMAIL;

}
