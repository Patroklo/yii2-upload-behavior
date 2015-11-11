<?php

namespace cyneek\yii2\uploadBehavior;

use cyneek\yii2\uploadBehavior\models\ImageFileModel;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * UploadImageBehavior automatically uploads image, creates thumbnails and fills
 * the specified attribute with a value of the name of the uploaded image.
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
 *             'placeholder' => '@app/modules/user/assets/images/userpic.jpg',
 *             'path' => '@webroot/upload/{id}/images',
 *             'url' => '@web/upload/{id}/images',
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
     * @var string
     */
    public $placeholder;
    /**
     * @var boolean
     */
    public $createThumbsOnSave = true;
    /**
     * @var boolean
     */
    public $createThumbsOnRequest = false;
    /**
     * @var array the thumbnail profiles
     * - `action`
     * - `width`
     * - `height`
     * - `quality`
     * [
     * 'thumb' => [
     *               ['action' => 'crop', 'width' => 200, 'height' => 200, 'quality' => 90],
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
    public $imageActions;

    protected $_fileModel = 'ImageFileModel';


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->createThumbsOnSave)
        {
            $this->_normalizeThumbData();
        }
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


        if (!is_array(reset($this->imageActions)))
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
        if ($this->createThumbsOnSave)
        {
            $this->createThumbs($image);
        }
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
            // $fileModel->uploadPath = $this->path;
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

            $modelData = Yii::$app->getModule('uploadBehavior')->model($this->_fileModel, [
                'file' => $this->_file,
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

            $this->save($this->_file, $fileModel);


        }
    }





    /***
     * =============================================================================================
     * =============================================================================================
     *
     * BORRAR
     *
     * =============================================================================================
     * =============================================================================================
     */


//    /**
//     * @inheritdoc
//     */
//    protected function delete($attribute, $old = false)
//    {
//        parent::delete($attribute, $old);
//
//        $profiles = array_keys($this->thumbs);
//        foreach ($profiles as $profile)
//        {
//            $path = $this->getThumbUploadPath($attribute, $profile, $old);
//            if (is_file($path))
//            {
//                unlink($path);
//            }
//        }
//    }


//        /**
//     * @param $config
//     * @param $path
//     * @param $thumbPath
//     */
//    protected function generateImageThumb($config, $path, $thumbPath)
//    {
//        $width = ArrayHelper::getValue($config, 'width');
//        $height = ArrayHelper::getValue($config, 'height');
//        $quality = ArrayHelper::getValue($config, 'quality', 100);
//        $mode = ArrayHelper::getValue($config, 'mode', ManipulatorInterface::THUMBNAIL_INSET);
//
//        if (!$width || !$height)
//        {
//            $image = Image::getImagine()->open($path);
//            $ratio = $image->getSize()->getWidth() / $image->getSize()->getHeight();
//            if ($width)
//            {
//                $height = ceil($width / $ratio);
//            }
//            else
//            {
//                $width = ceil($height * $ratio);
//            }
//        }
//
//        // Fix error "PHP GD Allowed memory size exhausted".
//        ini_set('memory_limit', '512M');
//        Image::thumbnail($path, $width, $height, $mode)->save($thumbPath, ['quality' => $quality]);
//    }

//    /**
//     * @param $filename
//     * @param string $profile
//     * @return string
//     */
//    protected function getThumbFileName($filename, $profile = 'thumb')
//    {
//        return $profile . '-' . $filename;
//    }


//        /**
//     * @param string $attribute
//     * @param string $profile
//     * @param boolean $old
//     * @return string
//     */
//    public function getThumbUploadPath($attribute, $profile = 'thumb', $old = false)
//    {
//        /** @var BaseActiveRecord $model */
//        $model = $this->owner;
//        $path = $this->resolvePath($this->thumbPath);
//        $attribute = ($old === true) ? $model->getOldAttribute($attribute) : $model->$attribute;
//        $filename = $this->getThumbFileName($attribute, $profile);
//
//        return $filename ? Yii::getAlias($path . '/' . $filename) : null;
//    }


//
//        /**
//     * @param $profile
//     * @return string
//     */
//    protected function getPlaceholderUrl($profile)
//    {
//        list ($path, $url) = Yii::$app->assetManager->publish($this->placeholder);
//        $filename = basename($path);
//        $thumb = $this->getThumbFileName($filename, $profile);
//        $thumbPath = dirname($path) . DIRECTORY_SEPARATOR . $thumb;
//        $thumbUrl = dirname($url) . '/' . $thumb;
//
//        if (!is_file($thumbPath))
//        {
//            $this->generateImageThumb($this->thumbs[$profile], $path, $thumbPath);
//        }
//
//        return $thumbUrl;
//    }

//      /**
//     * @param string $attribute
//     * @param string $profile
//     * @return string|null
//     */
//    public function getThumbUploadUrl($attribute, $profile = 'thumb')
//    {
//        /** @var BaseActiveRecord $model */
//        $model = $this->owner;
//        $path = $this->getUploadPath($attribute, true);
//        if (is_file($path))
//        {
//            if ($this->createThumbsOnRequest)
//            {
//                $this->createThumbs();
//            }
//            $url = $this->resolvePath($this->thumbUrl);
//            $fileName = $model->getOldAttribute($attribute);
//            $thumbName = $this->getThumbFileName($fileName, $profile);
//
//            return Yii::getAlias($url . '/' . $thumbName);
//        }
//        elseif ($this->placeholder)
//        {
//            return $this->getPlaceholderUrl($profile);
//        }
//        else
//        {
//            return null;
//        }
//    }


}
