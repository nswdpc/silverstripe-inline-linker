<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObjectInterface;

class InlineLink_TitleField extends TextField {

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'text';

    use InlineLink;

    public function Type()
    {
        return 'text';
    }

    /**
     * Saving of this value happens in the {@link InlineLinkField}
     */
    public function saveInto(DataObjectInterface $record)
    {
        return;
    }

    /**
     * Saving of this value happens in the {@link InlineLinkField}
     */
    public function canSubmitValue() : bool {
        return false;
    }

}
