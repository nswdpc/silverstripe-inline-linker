<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObjectInterface;


/**
 * This field handles any type of text entry link e.g URL, Email, Phone
 */
class InlineLink_TypeDefinedTextField extends TextField {

    use InlineLink;

    /**
     * This is INPUT's type attribute value.
     *
     * @var string
     */
    protected $inputType = 'text';

    /**
     * @var string
     */
    protected $link_type = '';

    public function Type()
    {
        return 'text';
    }

    /**
     * @inheritdoc
     */
    public function validate(): \SilverStripe\Core\Validation\ValidationResult
    {
        switch($this->getLinkType()) {
            case InlineLinkField::LINKTYPE_EMAIL:
                $field = InlineLink_EmailField::create(
                    $this->getName() . "_" . InlineLinkField::LINKTYPE_EMAIL,
                    InlineLinkField::LINKTYPE_EMAIL,
                    $this->dataValue()
                );
                $validationResult = $field->validate();
                break;
            case InlineLinkField::LINKTYPE_PHONE:
                $field = InlineLink_PhoneField::create(
                    $this->getName() . "_" . InlineLinkField::LINKTYPE_PHONE,
                    InlineLinkField::LINKTYPE_PHONE,
                    $this->dataValue()
                );
                $validationResult = $field->validate();
                break;
            case InlineLinkField::LINKTYPE_URL:
                $field = InlineLink_URLField::create(
                    $this->getName() . "_" . InlineLinkField::LINKTYPE_URL,
                    InlineLinkField::LINKTYPE_URL,
                    $this->dataValue()
                );
                $validationResult = $field->validate();
                break;
            default:
                $validationResult = \SilverStripe\Core\Validation\ValidationResult::create();
                break;
        }
        return $validationResult;
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
