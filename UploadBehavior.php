<?php

namespace cyneek\yii2\uploadBehavior;

use cyneek\yii2\upload\models\AbstractFileManager;
use cyneek\yii2\upload\models\FileModel;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;

/**
 * UploadBehavior automatically uploads file and fills the specified attribute
 * with a value of the name of the uploaded file.
 *
 * To use UploadBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use cyneek\yii2\upload\UploadBehavior;
 *
 * function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UploadBehavior::className(),
 *             'attribute' => 'file',
 *             'scenarios' => ['insert', 'update']
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Joseba Juániz <joseba.juaniz@gmail.com>
 * @author Alexander Mohorev <dev.mohorev@gmail.com>
 */
class UploadBehavior extends Behavior
{

    /**
     * @event Event an event that is triggered after a file is uploaded.
     */
    const EVENT_AFTER_UPLOAD = 'afterUpload';
    /**
     * @var string action executed when uploading a file
     */
    public $fileActionOnSave = 'insert';
    /**
     * @var string the attribute which holds the attachment.
     */
    public $attribute;
    /**
     * @var array the scenarios in which the behavior will be triggered
     */
    public $scenarios = ['default', 'insert', 'update', 'delete'];
    /**
     * @var string The base path or path alias to the directory in which to save files.
     */
    public $path;
    /**
     * @var bool Getting file instance by name
     */
    public $instanceByName = FALSE;
    protected $validSaveActions = ['insert', 'update', 'delete'];
    /** @var array with data to create a FileManager object
     *
     *  [
     *      'class' => 'cyneek\yii2\upload\LocalFileManager'
     *      'parameters' => 'url'
     * ]
     *
     */
    protected $fileManager;
    /**
     * @var UploadedFile the uploaded file instance.
     */
    protected $_file;


    protected $_fileModel = 'FileModel';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->attribute === NULL)
        {
            throw new InvalidConfigException('The "attribute" property must be set.');
        }

        if (!in_array($this->fileActionOnSave, $this->validSaveActions))
        {
            throw new InvalidConfigException('The "fileActionOnSave" property it\'ts not properly set.');
        }

        if (is_null($this->fileManager))
        {
            $basePath = Yii::$app->getModule('uploadBehavior')->baseDir;

            $this->fileManager = [
                'class' => 'cyneek\yii2\upload\models\LocalFileManager',
                'basePath' => Yii::getAlias($basePath),
            ];
        }
    }


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * This method is invoked before validation starts.
     */
    public function beforeValidate()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;

        if (in_array($model->scenario, $this->scenarios))
        {
            if (($file = $model->{$this->attribute}) instanceof UploadedFile)
            {
                $this->_file = $file;
            }
            else
            {
                if ($this->instanceByName === TRUE)
                {
                    $this->_file = UploadedFile::getInstanceByName($this->attribute);
                }
                else
                {
                    $this->_file = UploadedFile::getInstance($model, $this->attribute);
                }

                $model->{$this->attribute} = $this->_file;
            }

            if ($this->_file instanceof UploadedFile)
            {
                $model->{$this->attribute};
            }
        }
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     */
    public function beforeSave()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (in_array($model->scenario, $this->scenarios))
        {
            if ($this->_file instanceof UploadedFile && !$model->getIsNewRecord() && $this->fileActionOnSave === 'delete')
            {
                $this->deleteFiles($this->attribute);
            }
        }
    }

    /**
     * Delete the specified files or all linked to the attribute
     *
     * @param String $attribute
     * @param FileModel[] $fileModels
     * @throws \Exception
     */
    public function deleteFiles($attribute, $fileModels = [])
    {
        if (empty($fileModels))
        {
            $fileModels = $this->linkedFiles($attribute);
        }
        elseif ($fileModels instanceof FileModel)
        {
            $fileModels = [$fileModels];
        }

        foreach ($fileModels as $file)
        {
            $file->fileManager = $this->_getFileManager();
            $file->delete();
        }
    }

    /**
     * @return array|AbstractFileManager|object
     * @throws InvalidConfigException
     */
    protected function _getFileManager()
    {
        if (!$this->fileManager instanceof AbstractFileManager)
        {
            $this->fileManager = Yii::createObject($this->fileManager);

        }
        return $this->fileManager;
    }

    /**
     * This method is called at the end of inserting or updating a record.
     * @throws \yii\base\InvalidParamException
     */
    public function afterSave()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;

        if ($this->_file instanceof UploadedFile)
        {

            $previousFile = NULL;

            if (!$model->getIsNewRecord() && $this->fileActionOnSave === 'update')
            {
                $previousFile = $this->linkedFile($this->attribute);
            }

            $savedFile = $this->save($this->_file, $previousFile);

            $this->afterUpload($savedFile);
        }
    }

    /**
     * Returns all the files linked to the model
     * in the attribute set in the behavior
     *
     * @param $attribute
     * @return FileModel[]
     */
    public function linkedFiles($attribute)
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        $modelData = Yii::$app->getModule('uploadBehavior')->model($this->_fileModel);

        /** @var FileModel $fileModel */
        $fileModel = Yii::createObject($modelData);

        return $fileModel::find()->where([
            'entity' => $model->className(),
            'entityId' => $model->getPrimaryKey(),
            'entityAttribute' => $attribute,
            'parentId' => 0
        ])
            ->orderBy(['fileOrder' => SORT_ASC])
            ->all();
    }

    /**
     * Returns the first or only file linked to the model
     * in the attribute set in the behavior
     *
     * @param $attribute
     * @return FileModel
     */
    public function linkedFile($attribute)
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        $modelData = Yii::$app->getModule('uploadBehavior')->model($this->_fileModel);

        /** @var FileModel $fileModel */
        $fileModel = Yii::createObject($modelData);

        return $fileModel::find()->where([
            'entity' => $model->className(),
            'entityId' => $model->getPrimaryKey(),
            'entityAttribute' => $attribute,
            'parentId' => 0
        ])
            ->orderBy(['fileOrder' => SORT_ASC])
            ->one();
    }

    /**
     * Saves the uploaded file.
     * @param UploadedFile $file the uploaded file instance
     * @param FileModel $fileModel
     * @return boolean true whether the file is saved successfully
     */
    protected function save($file, $fileModel = NULL)
    {
        if (!is_null($fileModel))
        {
            $fileModel->file = $file;
            // $fileModel->uploadPath = $this->path;
            $fileModel->fileManager = $this->_getFileManager();
        }
        else
        {
            $modelData = Yii::$app->getModule('uploadBehavior')->model($this->_fileModel, [
                'file' => $file,
                'model' => $this->owner,
                'uploadPath' => $this->path,
                'entityAttribute' => $this->attribute,
                'fileManager' => $this->_getFileManager()
            ]);

            /** @var FileModel $fileModel */
            $fileModel = Yii::createObject($modelData);
        }
        $fileModel->save();

        return $fileModel;
    }

    /**
     * This method is invoked after uploading a file.
     * The default implementation raises the [[EVENT_AFTER_UPLOAD]] event.
     * You may override this method to do postprocessing after the file is uploaded.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterUpload($image)
    {
        $this->owner->trigger(self::EVENT_AFTER_UPLOAD);
    }

    /**
     * This method is invoked before deleting a record.
     */
    public function beforeDelete()
    {
        if ($this->attribute)
        {
            $this->deleteFiles($this->attribute);
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
//     * Returns file path for the attribute.
//     * @param string $attribute
//     * @param boolean $old
//     * @return string|null the file path.
//     */
//    public function getUploadPath($attribute, $old = FALSE)
//    {
//        /** @var BaseActiveRecord $model */
//        $model = $this->owner;
//        $path = $this->resolvePath($this->path);
//        $fileName = ($old === TRUE) ? $model->getOldAttribute($attribute) : $model->$attribute;
//
//        return $fileName ? Yii::getAlias($path . '/' . $fileName) : NULL;
//    }
//
//    /**
//     * Returns file url for the attribute.
//     * @param string $attribute
//     * @return string|null
//     */
//    public function getUploadUrl($attribute)
//    {
//        /** @var BaseActiveRecord $model */
//        $model = $this->owner;
//        $url = $this->resolvePath($this->url);
//        $fileName = $model->getOldAttribute($attribute);
//
//        return $fileName ? Yii::getAlias($url . '/' . $fileName) : NULL;
//    }
//
//    /**
//     * Replaces all placeholders in path variable with corresponding values.
//     */
//    protected function resolvePath($path)
//    {
//        /** @var BaseActiveRecord $model */
//        $model = $this->owner;
//
//        return preg_replace_callback('/{([^}]+)}/', function ($matches) use ($model)
//        {
//            $name = $matches[1];
//            $attribute = ArrayHelper::getValue($model, $name);
//            if (is_string($attribute) || is_numeric($attribute))
//            {
//                return $attribute;
//            }
//            else
//            {
//                return $matches[0];
//            }
//        }, $path);
//    }
//
// 
//
//    /**
//     * @param UploadedFile $file
//     * @return string
//     */
//    protected function getFileName($file)
//    {
//        if ($this->generateNewName)
//        {
//            return $this->generateNewName instanceof Closure
//                ? call_user_func($this->generateNewName, $file)
//                : $this->generateFileName($file);
//        }
//        else
//        {
//            return $this->sanitize($file->name);
//        }
//    }
//
//    /**
//     * Replaces characters in strings that are illegal/unsafe for filename.
//     *
//     * #my*  unsaf<e>&file:name?".png
//     *
//     * @param string $filename the source filename to be "sanitized"
//     * @return boolean string the sanitized filename
//     */
//    public static function sanitize($filename)
//    {
//        return str_replace([' ', '"', '\'', '&', '/', '\\', '?', '#'], '-', $filename);
//    }
//
//    /**
//     * Generates random filename.
//     * @param UploadedFile $file
//     * @return string
//     */
//    protected function generateFileName($file)
//    {
//        return uniqid() . '.' . $file->extension;
//    }


}
