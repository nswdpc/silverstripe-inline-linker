<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObjectInterface;

/**
 * A checkbox to handle the saving and display of the 'Open in new window' setting
 * Note: OptionsetField saving issue : https://github.com/silverstripe/silverstripe-admin/issues/787
 */
class InlineLink_OpenInNewWindowField extends CheckboxField {

    use InlineLink;

    /**
     * @var string
     */
    protected $link_type = '';

    /**
     * @inheritdoc
     */
    public function __construct($name, $title = null, $value = null) {
        parent::__construct($name, $title, $value);
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
