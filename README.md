# Inline linking field for Silverstripe

This module provides a basic **inline** linking field, saving into the Link model provided by gorriecoe/silverstripe-link

Rather than take the content editor to a new data entry screen, the link can be added and saved to the current record in one of the provided fields:

- 🧪 Choose a current link record
- Enter an external URL
- Email address
- Internal page
- Existing file asset
- Phone

The object of this module is to:

- allow editing and creation of links within the context of the parent record
- have no Javascript dependencies, beyond those provided by core framework fields
- act as a drop-in replacement for the LinkField provided by gorriecoe/silverstripe-linkfield (for has-one relations only)

## Usage

Use the InlineLinkCompositeField to load the fields:

```php
<?php
namespace Some\Thing;

use gorriecoe\Link\Models\Link;
use NSWDPC\InlineLinker\InlineLinkCompositeField;
use SilverStripe\ORM\DataObject;

//....

class MyThing extends DataObject {

    private static $has_one = [
        'MyLink' => Link::class
    ];

    public function getCmsFields()
    {
        $fields = parent::getCmsFields();
        $fields->addFieldsToTab(
            'Root.Main', [
                //-- some fields
                $this->getLinkField()
                //-- some other fields
            ]
        );
        return $fields;
    }

    public function getLinkField() {
        return InlineLinkCompositeField::create(
                'MyLink',
                _t(__CLASS__ . '.LINK', 'My link title'),
                $this
        );
    }

}
```

## Requirements

See [composer.json](./composer.json)

## Installation

The recommended way of installing this module is via [composer](https://getcomposer.org/download/)

```
composer require nswdpc/silverstripe-inline-linker
```

> Remember to add a repository entry in composer.json to load this module, currently.

## License

[BSD-3-Clause](./LICENSE.md)

## Documentation

* [Documentation](./docs/en/001_index.md)

## Configuration

None, yet

## Maintainers

+ [dpcdigital@NSWDPC:~$](https://dpc.nsw.gov.au)

## Bugtracker

We welcome bug reports, pull requests and feature requests on the Github Issue tracker for this project.

Please review the [code of conduct](./code-of-conduct.md) prior to opening a new issue.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.

Please review the [code of conduct](./code-of-conduct.md) prior to completing a pull request.
