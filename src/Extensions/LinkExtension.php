<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Core\Extension;

/**
 * Extension for {@link gorriecoe\Link\Models\Link} providing additional methods
 * and behaviour
 * @author James
 */
class LinkExtension extends Extension {

    public function TitleWithURL() {
        $title = $this->owner->Title;
        $url = $this->owner->getLinkURL();
        return "#" . $this->owner->ID . " " . $title . " - " . $url;
    }

    public function TypeWithURL() {
        $type = $this->owner->Type;
        $url = $this->owner->getLinkURL();
        return "#" . $this->owner->ID . " " . $type . " - " . $url;
    }

}
