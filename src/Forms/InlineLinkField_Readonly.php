<?php

namespace NSWDPC\InlineLinker;

use gorriecoe\Link\Models\Link;
use SilverStripe\Forms\ReadonlyField;

/**
 * Readonly variation for inlinelinkfield
 */
class InlineLinkField_Readonly extends ReadonlyField
{

    /**
     * @var bool
     */
    protected $readonly = true;

    /**
     * @var bool
     */
    protected $disabled = true;

    /**
     * @var null|Link
     */
    protected $linkRecord = null;

    /**
     * Set the link record
     */
    public function setRecord(Link $link) {
        $this->linkRecord = $link;
    }

    /**
     * Get the link record
     * @return null|Link
     */
    public function getRecord() {
        return $this->linkRecord;
    }

    /**
     * Return the value of this field, which is the link URL and the link type
     * @return string
     */
    public function Value()
    {
        $record = $this->getRecord();
        if($record && ($record instanceof Link) && ($linkUrl = $record->getLinkURL())) {

            // Get translated value of the link type
            $suffix = preg_replace("/[^a-zA-Z]/", "_", $record->Type);
            $linkType = _t(
                "NSWDPC\\InlineLinker\\InlineLinkField.LINK_TYPE_{$suffix}",
                $record->TypeLabel
            );

            return _t(
                "NSWDPC\\InlineLinker\\InlineLinkField.LINK_URL_AND_TYPE",
                '{url} ({type})',
                [
                    'url' => $linkUrl,
                    'type' => $linkType
                ]
            );

        } else {

            return _t(
                "NSWDPC\\InlineLinker\\InlineLinkField.NO_LINK_URL",
                'No link'
            );

        }
    }

    /**
     * @return string
     */
    public function getValueCast()
    {
        return 'Text';
    }

}
