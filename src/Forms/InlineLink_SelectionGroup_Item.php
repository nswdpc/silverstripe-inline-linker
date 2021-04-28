<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\Tab;

/**
 * Represents a type selection tab in the selection group tabset
 */
class InlineLink_SelectionGroup_Item extends Tab {

    /**
     * @var string
     */
    protected $type_name = '';

    /**
     * @var bool
     */
    protected $is_current = false;

    /**
     * @inheritdoc
     * @param string $type_name the field type for the radio button selector
     */
     public function __construct($name, $title, $fields, string $type_name, bool $is_current = false)
     {
         parent::__construct($name, $title, $fields);
         $this->type_name = $type_name;
         $this->is_current = $is_current;
     }

     public function IsCurrent() : bool {
         return $this->is_current;
     }

     public function LinkTypeName() : string {
         return $this->type_name;
     }

}
