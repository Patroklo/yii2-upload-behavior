<?php


namespace cyneek\yii2\upload\models;


use Imagine\Image\ManipulatorInterface;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\imagine\Image;


class ImageFileModel extends FileModel
{

    /**
     * Model that will be the parent image in case
     * the object it's a thumbnail
     *
     * @var FileModel
     */
    public $parentModel;

    /**
     * Describes the actions that will be made to the original image
     * [
     *  ['action' => 'crop', 'width' => 200, 'height' => 200, 'quality' => 90]
     * ]
     *
     *
     * @var array
     */
    public $imageActions;


    protected $temporaryFile;

    /**
     * @inheritdoc
     */
    function init()
    {
        parent::init();

        $this->on($this::EVENT_BEFORE_INSERT, [$this, '_imageActions']);
        $this->on($this::EVENT_BEFORE_UPDATE, [$this, '_imageActions']);

        $this->on($this::EVENT_BEFORE_DELETE, [$this, '_deleteThumbnails']);
    }

    protected function _prepareFileData(Event $event)
    {
        parent::_prepareFileData($event);

        if (!is_null($this->parentModel))
        {
            $this->parentId = $this->parentModel->id;
        }
    }


    protected function _deleteThumbnails()
    {
        $thumbnails = self::find()->where(['parentId' => $this->id])->all();

        foreach ($thumbnails as $thumbnail)
        {
            $thumbnail->fileManager = $this->fileManager;
            $thumbnail->delete();
        }
    }


    /**
     * @param $config
     * @param $path
     * @param $thumbPath
     */
    protected function _imageActions()
    {

        $tempDir = \Yii::getAlias(\Yii::$app->getModule('uploadBehavior')->baseDir);

        // Check the directory with localhost if it exists.
        $localFileManager = new LocalFileManager(['basePath' => $tempDir]);
        $localFileManager->fileSystem->createDir('temp');

        $tempDir.= 'temp/';

        foreach ($this->imageActions as $config)
        {

            $action = ArrayHelper::getValue($config, 'action', 'thumbnail');
            $width = ArrayHelper::getValue($config, 'width', 0);
            $height = ArrayHelper::getValue($config, 'height', 0);
            $quality = ArrayHelper::getValue($config, 'quality', 100);


            if (!$width || !$height)
            {
                $image = Image::getImagine()->open($this->file->tempName);
                $ratio = $image->getSize()->getWidth() / $image->getSize()->getHeight();
                if ($width)
                {
                    $height = ceil($width / $ratio);
                }
                else
                {
                    $width = ceil($height * $ratio);
                }
            }

            //  Make an intermediary file to manipulate it

            $this->temporaryFile = $tempDir .  $this->fileName . '.' . $this->extension;


            if ($action == 'thumbnail')
            {
                $mode = ArrayHelper::getValue($config, 'mode', ManipulatorInterface::THUMBNAIL_INSET);
                // Fix error "PHP GD Allowed memory size exhausted".
                ini_set('memory_limit', '512M');
                Image::thumbnail($this->file->tempName, $width, $height, $mode)->save($this->temporaryFile, ['quality' => $quality]);
            }
            else
            {
                $start = ArrayHelper::getValue($config, 'start', [0, 0]);
                // Fix error "PHP GD Allowed memory size exhausted".
                ini_set('memory_limit', '512M');
                Image::crop($this->file->tempName, $width, $height, $start)->save($this->temporaryFile, ['quality' => $quality]);
            }
        }

        if (is_null($this->temporaryFile))
        {
            $localFileManager = new LocalFileManager(['basePath' => $tempDir]);
            $localFileManager->writeFile($this->file->tempName, $this->fileName . '.' . $this->extension);
            $this->temporaryFile = \Yii::getAlias(\Yii::$app->getModule('uploadBehavior')->baseDir) . 'temp/' . $this->fileName . '.' . $this->extension;
        }

    }


    /**
     * Changes the uploaded file from it's initial destination to its final directory
     *
     * @return bool
     */
    protected function _saveUploadedFile()
    {
        $returnData = $this->fileManager->writeFile($this->temporaryFile, $this->relativePath . $this->fileName . '.' . $this->extension);
        unlink($this->temporaryFile);
        return $returnData;
    }


}