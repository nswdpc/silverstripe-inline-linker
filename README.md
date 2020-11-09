# Inline linking field for Silverstripe

This module provides a basic **inline** linking field, saving into the Link model provided by gorriecoe/silverstripe-link

Rather than take the content editor to a new data entry screen, the link can be added and saved to the current record in one of the provided fields:

- Choose a current link record
- Enter an external URL
- Email address
- Internal page
- Existing file asset
- Phone

The object of this module is to:

- have no Javascript dependencies, beyond those provided by core framework fields.
- act as a drop-in replacement for the LinkField provided by gorriecoe/silverstripe-linkfield (for has-one relations only), using Injector.


## Requirements

See [composer.json](./composer.json)

```
"gorriecoe/silverstripe-linkfield": "^1.0.0",
"burnbright/silverstripe-externalurlfield" : "^0.3"
```

## Installation

The recommended way of installing this module is via [composer](https://getcomposer.org/download/)

```
composer require nswdpc/silverstripe-inline-linker
```

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
