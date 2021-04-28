<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\DataObjectInterface;

class InlineLink_OpenInNewWindowField extends OptionsetField {

    use InlineLink;

    public function __construct($name, $title = null, $source = [], $value = null) {
        $source = [
            0 => _t("NSWDPC\\InlineLinker\\InlineLink.NO", 'No'),
            1 => _t("NSWDPC\\InlineLinker\\InlineLink.YES", 'Yes'),
        ];
        parent::__construct($name, $title, $source, $value);
    }

    /**
     * Saving of this value happens in the {@link InlineLinkField}
     */
    public function saveInto(DataObjectInterface $record)
    {
        return;
    }

}
