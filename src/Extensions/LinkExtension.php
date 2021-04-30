<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\ORM\DataExtension;

/**
 * Extension for {@link gorriecoe\Link\Models\Link} providing additional methods
 * and behaviour
 * @author James
 */
class LinkExtension extends DataExtension {

    /**
     * @var array
     * When the Link record is 'published', publish these relationships
     * via the ownership API
     */
    private static $owns = [
        'File',
        'SiteTree'
    ];

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
