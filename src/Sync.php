<?php

namespace PhpGoogleDriveSync;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class Sync
{
    private $error = '';
    private $options = [];
    /** @var Client */
    private $googleClient;
    /** @var Drive */
    private $googleDrive;

    /**
     * 
     *
     * @param  array $options ['credential_account' => '']
     */
    function __construct($options = [])
    {
        $this->options = array_merge([
            'credential_account' => null
        ], $options);
    }

    public function setError($error)
    {
        if (is_object($error) && method_exists($error, 'getMessage')) {
            $this->error = $error->getMessage();
        } else {
            $this->error = $error;
        }
    }

    public function getError()
    {
        return $this->error;
    }

    public function hasError()
    {
        return strlen($this->error) > 0 ? true : false;
    }

    public function getOptions(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->options;
        }
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }

    public function setGoogleClient(Client $googleClient)
    {
        $this->googleClient = $googleClient;
    }

    public function getGoogleClient()
    {
        return $this->googleClient;
    }

    public function setGoogleDrive(Drive $googleDrive)
    {
        $this->googleDrive = $googleDrive;
    }

    public function getGoogleDrive()
    {
        return $this->googleDrive;
    }

    public function initGoogleClient()
    {
        try {
            $credentialAccount = $this->getOptions('credential_account');
            if (empty($credentialAccount)) {
                throw new \Exception('Required credential account');
            }
            if (!realpath($credentialAccount)) {
                throw new \Exception("The credential account ($credentialAccount) not found");
            }
            $client = new Client();
            $client->setAuthConfig($this->getOptions('credential_account'));
            $client->addScope([Drive::DRIVE, Drive::DRIVE_FILE]);
            $drive = new Drive($client);
            $this->setGoogleClient($client);
            $this->setGoogleDrive($drive);
        } catch (\Exception $e) {
            $this->setError($e);
        }
    }

    /**
     * Support sync files in current folder. No sub folder.
     *
     * @param  string $dirPath
     */
    public function syncFolder(string $dirPath, string $folderId)
    {
        try {
            if (!realpath($dirPath)) {
                throw new \Exception("The directory path ($dirPath) not found");
            }
            $localFiles = array_filter(glob($dirPath . '/*'), function ($localPath) {
                return is_file($localPath) && !is_dir($localPath);
            });
            $localFileUploads = [];
            $localFileNames = [];
            foreach ($localFiles as $key => $localFile) {
                $fileInfo = pathinfo($localFile);
                $fileInfo['path'] = $localFile;
                $fileInfo['mime_type'] = mime_content_type($localFile);
                array_push($localFileUploads, $fileInfo);
                array_push($localFileNames, $fileInfo['basename']);
            }
            if (empty($folderId)) {
                throw new \Exception('Required Folder ID');
            }
            $q = [
                "mimeType != 'application/vnd.google-apps.folder'",
                "trashed=false",
                "'$folderId' in parents",
            ];
            $resultListFiles = $this->getGoogleDrive()->files->listFiles([
                'q' => implode(' and ', $q),
                'fields' => "nextPageToken, files(id, name, createdTime)",
                'orderBy' => 'createdTime desc'
            ]);
            $driveFiles = $resultListFiles->getFiles();
            foreach ($driveFiles as $driveFileKey => $driveFile) {
                $indexSearch = array_search($driveFile->getName(), $localFileNames);
                // Remove if not in local list
                if ($indexSearch === false) {
                    $this->getGoogleDrive()->files->delete($driveFile->getId());
                } else {
                    // No upload with file exists in remote
                    unset($localFileNames[$indexSearch]);
                    unset($localFileUploads[$indexSearch]);
                }
            }
            if (count($localFileUploads) > 0) {
                // Upload file to remote
                foreach ($localFileUploads as $localFileUpload) {
                    $uploadResult = $this->uploadFile($localFileUpload, [
                        'parent_ids' => [$folderId]
                    ]);
                    if ($uploadResult === false) {
                        throw new \Exception($this->getError());
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            $this->setError($e);
        }
        return false;
    }

    /**
     * uploadFile
     *
     * @param  string|array $filePath
     * @param  array $opts ['parent_ids' => []]
     */
    public function uploadFile($filePath, $opts = [])
    {
        try {
            if (is_string($filePath)) {
                if (!realpath($filePath)) {
                    throw new \Exception("The file path ($filePath) not found");
                }
                $tempFile = pathinfo($filePath);
                $tempFile['mime_type'] = mime_content_type($filePath);
                $tempFile['path'] = $filePath;
            } else {
                $tempFile = $filePath;
            }
            $opts = array_merge([
                'parent_ids' => [] // string[]
            ], $opts);
            $file = new DriveFile();
            $file->setName($tempFile['basename']);
            if (is_array($opts['parent_ids']) && count($opts['parent_ids']) > 0) {
                $file->setParents($opts['parent_ids']);
            }
            $result = $this->getGoogleDrive()->files->create($file, [
                'data' => file_get_contents($tempFile['path']),
                'mimeType' => $tempFile['mime_type'],
                'uploadType' => 'multipart',
            ]);
            return $result;
        } catch (\Exception $e) {
            $this->setError($e);
        }
        return false;
    }
}
