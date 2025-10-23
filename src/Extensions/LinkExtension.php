<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\Core\Extension;

/**
 * Extension for {@link gorriecoe\Link\Models\Link} providing additional methods
 * and behaviour
 * @author James
 * @extends \SilverStripe\Core\Extension<(\gorriecoe\Link\Models\Link & static)>
 */
class LinkExtension extends Extension
{
    public function TitleWithURL(): string
    {
        $title = $this->getOwner()->Title;
        $url = $this->getOwner()->getLinkURL();
        return "#" . $this->getOwner()->ID . " " . $title . " - " . $url;
    }

    public function TypeWithURL(): string
    {
        $type = $this->getOwner()->Type;
        $url = $this->getOwner()->getLinkURL();
        return "#" . $this->getOwner()->ID . " " . $type . " - " . $url;
    }

}
