## Install

### Requirements
PHP, MySQL and a table to work on. No table creation required nor model generation required.

### Install with composer
```
composer require makiavelo/flex
```
Or update dependencies in composer.json
```json
"require": {
    "makiavelo/flex": "dev-master"
}
```

### Install with single file
The repository contains a phar file which can be included directly to avoid using composer.
The phar can be found here: `/phar/flex.phar`
```php
include('flex.phar');

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;
```