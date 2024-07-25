<?php

namespace NSWDPC\InlineLinker;
use Codem\Utilities\HTML5\UrlField;

/**
 * Allow a user to provide a URL for association with the link
 */
class InlineLink_URLField extends UrlField {

    use InlineLink;

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'url';

    protected $link_type = InlineLinkField::LINKTYPE_URL;

}
