<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObjectInterface;

class InlineLink_OpenInNewWindowField extends CheckboxField {

    use InlineLink;

    /**
     * Saving of this value happens in the {@link InlineLinkField}
     */
    public function saveInto(DataObjectInterface $record)
    {
        return;
    }

}
