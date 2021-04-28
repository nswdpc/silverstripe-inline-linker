<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObjectInterface;

/**
 * Subclassed field to provide a remote link checkbox
 * NSWDPC\InlineLinker\InlineLinkField detects the remove action and handles that
 */
class InlineLink_RemoveAction extends CheckboxField {

    use InlineLink;

}
