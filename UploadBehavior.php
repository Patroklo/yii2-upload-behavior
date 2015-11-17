<?php

namespace cyneek\yii2\uploadBehavior;

use cyneek\yii2\uploadBehavior\models\AbstractFileManager;
use cyneek\yii2\uploadBehavior\models\FileModel;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;

/**
 * UploadBehavior automatically uploads a file and adds a new row in the uploaded files table.
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
    protected $_files;

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
                'class' => 'cyneek\yii2\uploadBehavior\models\LocalFileManager',
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
                $this->_files[] = $file;
            }
            else
            {
                if ($this->instanceByName === TRUE)
                {
                    $this->_files = UploadedFile::getInstancesByName($this->attribute);
                }
                else
                {
                    $this->_files = UploadedFile::getInstances($model, $this->attribute);
                }

                $model->{$this->attribute} = $this->_files;
            }

           /* if ($this->_files instanceof UploadedFile)
            {
                $model->{$this->attribute};
            }*/
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

            foreach ($this->_files as $_file)
            {
                if ($_file instanceof UploadedFile && !$model->getIsNewRecord() && $this->fileActionOnSave === 'delete')
                {
                    $this->deleteFiles($this->attribute);
                }
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


        foreach ($this->_files as $_file)
        {
            if ($_file instanceof UploadedFile)
            {

                $previousFile = NULL;

                if (!$model->getIsNewRecord() && $this->fileActionOnSave === 'update')
                {
                    $previousFile = $this->linkedFile($this->attribute);
                }

                $savedFile = $this->save($_file, $previousFile);

                $this->afterUpload($savedFile);
            }
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

}
