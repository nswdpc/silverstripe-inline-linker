<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\TabSet;

class InlineLink_SelectionGroup extends TabSet {

    /**
     *  @var null|string
     */
    protected $current_type = null;

    /**
     * @inheritdoc
     */
    public function __construct($name, $titleOrTab = null, $tabs = null)
    {
        parent::__construct($name, $titleOrTab, $tabs);
    }

    /**
     * Merge child field data into this form
     */
    public function getSchemaDataDefaults()
    {
        $defaults = parent::getSchemaDataDefaults();
        $defaults['activeTab'] = $this->current_type;
        return $defaults;
    }

    /**
     * @param $type null|string
     */
    public function setCurrentType($type) {
        $this->current_type = $type;
        return $this;
    }

    /**
     * @return null|string
     */
    public function CurrentType() {
        return $this->current_type;
    }

}
