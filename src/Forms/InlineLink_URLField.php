<?php

namespace NSWDPC\InlineLinker;
use BurnBright\ExternalURLField\ExternalURLField;

/**
 * Allow a user to provide a URL for association with the link
 */
class InlineLink_URLField extends ExternalURLField {

    use InlineLink;

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'url';

    protected $link_type = InlineLinkField::LINKTYPE_URL;

}
