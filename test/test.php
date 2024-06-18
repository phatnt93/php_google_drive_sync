<?php

use PhpGoogleDriveSync\Sync;

define('BASE_PATH', __DIR__);
date_default_timezone_set('Asia/Ho_Chi_Minh');

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
