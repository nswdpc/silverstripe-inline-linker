<?php

namespace NSWDPC\InlineLinker;

/**
 * Common trait for all link types
 */
trait InlineLink {

    // protected $link_type = '';

    public function getLinkType() : string {
        return $this->link_type;
    }

}
