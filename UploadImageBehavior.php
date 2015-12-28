<?php

namespace cyneek\yii2\uploadBehavior;

use cyneek\yii2\uploadBehavior\models\ImageFileModel;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * UploadImageBehavior automatically uploads image, creates thumbnails and adds a new row in the uploaded files table.
 *
 * To use UploadImageBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use cyneek\yii2\upload\UploadBehavior;
 *
 * function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UploadImageBehavior::className(),
 *             'attribute' => 'file',
 *             'scenarios' => ['insert', 'update'],
 *             'thumbPath' => '@webroot/upload/{id}/images/thumb',
 *             'thumbUrl' => '@web/upload/{id}/images/thumb',
 *             'thumbs' => [
 *                   'thumb' => ['width' => 400, 'quality' => 90],
 *                   'preview' => ['width' => 200, 'height' => 200],
 *              ],
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Joseba Juániz <joseba.juaniz@gmail.com>
 */
class UploadImageBehavior extends UploadBehavior
{
    /**
     * @var array the thumbnail profiles
     * - `action`
     * - `width`
     * - `height`
     * - `quality`
     * [
     * 'thumb' => [
     *             ['action' => 'crop', 'width' => 200, 'height' => 200, 'quality' => 90],
     *          ]
     * ]
     */
    public $thumbs = [];
    /**
     * @var string|null
     */
    public $thumbPath;

    /**
     * Describes the actions that will be made to the original image
     * [
     *  ['action' => 'crop', 'width' => 200, 'height' => 200, 'quality' => 90]
     * ]
     *
     * @var String[]
     */
    public $imageActions = [];

    protected $_fileModel = 'ImageFileModel';


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->_normalizeThumbData();

    }



    //hay que poner lo de imageActions en saveFile y todo eso

    /**
     * Checks if the thumb data it's properly configured
     *
     * @throws InvalidConfigException
     */
    protected function _normalizeThumbData()
    {
        if ($this->thumbPath === null)
        {
            $this->thumbPath = $this->path;
        }


        if (!empty($this->imageActions) && !is_array(reset($this->imageActions)))
        {
            $this->imageActions = [$this->imageActions];
        }


        foreach ($this->thumbs as $key => $actions)
        {

            if (!is_array($actions))
            {
                $this->thumbs[$key] = [$this->thumbs[$key]];
            }
        }

        foreach ($this->thumbs as $key => $actions)
        {
            foreach ($actions as $config)
            {
                $width = ArrayHelper::getValue($config, 'width', 0);
                $height = ArrayHelper::getValue($config, 'height', 0);
                if ($height < 1 && $width < 1)
                {
                    throw new InvalidConfigException(sprintf(
                        'Length of either side of thumb cannot be 0 or negative, current size ' .
                        'is %sx%s', $width, $height
                    ));
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function afterUpload($image)
    {
        parent::afterUpload($image);

        $this->createThumbs($image);
    }


    /**
     * Saves the uploaded file.
     * @param UploadedFile $file the uploaded file instance
     * @param ImageFileModel $fileModel
     * @return boolean true whether the file is saved successfully
     */
    protected function save($file, $fileModel = NULL)
    {
        if (!is_null($fileModel))
        {
            $fileModel->file = $file;

            $fileModel->fileManager = $this->_getFileManager();

            if (is_null($fileModel->imageActions))
            {
                $fileModel->imageActions = $this->imageActions;
            }

        }
        else
        {
            $modelData = Yii::$app->getModule('uploadBehavior')->model($this->_fileModel, [
                'file' => $file,
                'model' => $this->owner,
                'uploadPath' => $this->path,
                'entityAttribute' => $this->attribute,
                'imageActions' => $this->imageActions,
                'fileManager' => $this->_getFileManager()
            ]);

            /** @var ImageFileModel $fileModel */
            $fileModel = Yii::createObject($modelData);
        }

        $fileModel->save();

        return $fileModel;
    }


    /**
     *
     * @param ImageFileModel $image
     * @throws \yii\base\Exception
     */
    protected function createThumbs($image)
    {

        foreach ($this->thumbs as $profile => $actions)
        {
            foreach ($this->_files as $_file)
            {
                $modelData = Yii::$app->getModule('uploadBehavior')->model($this->_fileModel, [
                    'file' => $_file,
                    'model' => $this->owner,
                    'uploadPath' => $this->thumbPath,
                    'entityAttribute' => $this->attribute,
                    'fileManager' => $this->_getFileManager(),
                    'parentModel' => $image,
                    'imageActions' => $actions,
                    'childName' => $profile,
                ]);

                /** @var ImageFileModel $fileModel */
                $fileModel = Yii::createObject($modelData);

                $this->save($_file, $fileModel);
            }
        }
    }

}