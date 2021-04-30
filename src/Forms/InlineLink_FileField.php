<?php

namespace NSWDPC\InlineLinker;

use SilverStripe\AssetAdmin\Forms\UploadField;

/**
 * Allow a file to be associated with a {@link gorriecoe\Link\Models\Link}
 */
class InlineLink_FileField extends UploadField {

    use InlineLink;

    protected $link_type = InlineLinkField::LINKTYPE_FILE;

    /**
     * Set if uploading new files is enabled.
     * If false, only existing files can be selected
     *
     * @var bool
     */
    protected $uploadEnabled = true;

    /**
     * Set if selecting existing files is enabled.
     * If false, only new files can be selected.
     *
     * @var bool
     */
    protected $attachEnabled = true;

    /**
     * The number of files allowed for this field
     *
     * @var null|int
     */
    protected $allowedMaxFileNumber = 1;

    /**
     * @var bool|null
     */
    protected $multiUpload = false;

}
