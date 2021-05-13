<?php

namespace NSWDPC\InlineLinker;

/**
 * Trait for all link type fields
 */
trait InlineLink {

    /**
     * @var array
     */
    protected $signals = [];

    /**
     * @return string
     */
    public function getLinkType() : string {
        return $this->link_type ?: '';
    }

    /**
     * Set signals, return self
     */
    public function setSignals(array $signals) : self {
        $this->signals = $signals;
        $this->setAttribute('data-signals', json_encode($signals));
        return $this;
    }

    public function getSignals() : array {
        return $this->signals;
    }

    /**
     * Apply signals, if set, to schema data for Component field handling
     */
    public function getSchemaDataDefaults() {
        $data = parent::getSchemaDataDefaults();
        $signals = $this->getSignals();
        if(!empty($signals)) {
            $data['attributes'][ 'data-signals' ] = json_encode($signals);
        }
        return $data;
    }

}
