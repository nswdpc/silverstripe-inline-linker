<?php

namespace NSWDPC\InlineLinker;
use SilverStripe\Forms\LiteralField;

/**
 * For fields that cannot provide data-* attributes to components
 * This field acts as a pair to hold the signal
 * It's a simple hidden field with the id  and data-signal attributes, and nothing else
 */
class SignallerField extends LiteralField {

    use InlineLink;

    protected $link_type = '';

    /**
     * @inheritdoc
     * Content provided is ignored
     */
    public function __construct($name, $content = '')
    {
        $content = '';
        parent::__construct($name, $content);
    }

    /**
     * @param array $properties
     *
     * @return string
     */
    public function FieldHolder($properties = [])
    {
        return $this->getContent();
    }

    public function getContent() {
        $signals = $this->getSignals();
        if($signals) {
            $signals = htmlspecialchars(json_encode($signals));
        } else {
            $signals = "";
        }
        $name = htmlspecialchars($this->getName());
        return "<input id=\"{$name}\" type=\"hidden\" data-signals=\"{$signals}\">";
    }

}
