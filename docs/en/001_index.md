# Documentation

## Example Usage

Use the `InlineLinkField` to load the fields:

```php
<?php
namespace Some\Thing;

use gorriecoe\Link\Models\Link;
use NSWDPC\InlineLinker\InlineLinkField;
use SilverStripe\ORM\DataObject;

//....

class MyThing extends DataObject {

    /**
     * Declare the relation MyLink
     * @var array
     */
    private static $has_one = [
        'MyLink' => Link::class
    ];

    /**
     * Add the field
     * @return FieldList
     */
    public function getCmsFields()
    {
        $fields = parent::getCmsFields();
        
        // remove scaffolded Dropdownfield
        $fields->removeByName('MyLinkID');
        
        // replace it with the InlineLinkField
        $fields->addFieldsToTab(
            'Root.Main', [
                //-- some fields
                $this->getLinkField()
                //-- some other fields
            ]
        );
        
        return $fields;
    }

    /**
     * @return InlineLinkField
     */
    public function getLinkField() {
        return InlineLinkField::create(
                'MyLink',
                _t(__CLASS__ . '.LINK', 'My link title'),
                $this
        );
    }

}
```
