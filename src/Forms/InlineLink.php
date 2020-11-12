<?php

namespace NSWDPC\InlineLinker;

trait InlineLink {

    // protected $link_type = '';

    public function getLinkType() : string {
        return $this->link_type;
    }

}
