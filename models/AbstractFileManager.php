<?php

namespace cyneek\yii2\uploadBehavior\models;

use League\Flysystem\Filesystem;
use yii\base\Object;

abstract class AbstractFileManager extends Object
{

    /** @var  Filesystem */
    public $fileSystem;

    /**
     * Returns the main HTTP path for the adapter that will be used
     * to access the file in the application
     *
     * For example, in Local may be an http://localserver.com/
     *
     * @return String
     */
    abstract public function HTTPPath();

    /**
     * Returns the main base path for the adapter that will be used
     * to access the file.
     *
     * For example, in Local may be an /home/etc/application...
     *
     * @return String
     */
    abstract public function basePath();


    /**
     * Writes the local uploaded file into the specific file system
     *
     * @param String $originFile // File path, name and extension
     * @param String $destinyFile // File name and extension
     *
     * @return bool
     */
    public function writeFile($originFile, $destinyFile)
    {
        $stream = fopen($originFile, 'r+');

        $return = $this->fileSystem->writeStream($destinyFile, $stream);

        fclose($stream);

        return $return;
    }

    /**
     * Check if a file exists
     *
     * @param String $fileLocation // location with relative path from FlySystem basepath
     *
     * @return bool
     */
    public function existFile($fileLocation)
    {
        return $this->fileSystem->has($fileLocation);
    }

    /**
     * Deletes a file
     *
     * @param String $fileLocation // location with relative path from FlySystem basepath
     *
     * @return bool
     */
    public function deleteFile($fileLocation)
    {
        return $this->fileSystem->delete($fileLocation);
    }

}