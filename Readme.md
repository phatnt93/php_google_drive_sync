# PHP Google Drive Sync

Sync files in folder.

## Requirement

1. Google Client API and service_account.json file
2. PHP >= 8.0

## Example

See example:

```php
<?php

use PhpGoogleDriveSync\Sync;

define('BASE_PATH', __DIR__);

require BASE_PATH . '/../vendor/autoload.php';

$folderId = '1UlcKImBa8-yGQQNalHeQlx5N9DJ523nM';
$sync = new Sync([
    'credential_account' => BASE_PATH . '/service_account.json'
]);
$sync->initGoogleClient();
if ($sync->hasError()) {
    throw new \Exception($sync->getError());
}
$sync->syncFolder(BASE_PATH . '/folder1', $folderId);
```
