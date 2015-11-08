<?php

namespace cyneek\yii2\upload\models;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use yii\helpers\Url;

/**
 * Class LocalFileManager
 * @package cyneek\yii2\upload\models
 *
 * Uses the Local adapter from FlySystem
 *
 */
class LocalFileManager extends AbstractFileManager
{

    public $basePath;

    function init()
    {
        $this->fileSystem = new Filesystem(new Local($this->basePath));
    }

    /**
     * @inheritdoc
     */
    public function basePath()
    {
         return \Yii::getAlias($this->basePath);
    }

    /**
     * @inheritdoc
     */
    public function HTTPPath()
    {
        $path = str_replace(\Yii::getAlias('@webroot').'/', '', $this->basePath());

        return Url::home(true).$path;
    }

}