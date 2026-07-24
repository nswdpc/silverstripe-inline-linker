<?php

declare(strict_types=1);

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObjectInterface;

/**
 * A checkbox to handle the saving and display of the 'Open in new window' setting
 * Note: OptionsetField saving issue : https://github.com/silverstripe/silverstripe-admin/issues/787
 */
class InlineLink_OpenInNewWindowField extends CheckboxField
{
    use InlineLink;

    /**
     * @var string
     */
    protected $link_type = '';

    /**
     * Saving of this value happens in the {@link InlineLinkField}
     */
    #[\Override]
    public function saveInto(DataObjectInterface $record)
    {
    }

    /**
     * Saving of this value happens in the {@link InlineLinkField}
     */
    #[\Override]
    public function canSubmitValue(): bool
    {
        return false;
    }

}
