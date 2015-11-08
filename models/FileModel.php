<?php

namespace cyneek\yii2\upload\models;

use League\Flysystem\Filesystem;
use Yii;
use yii\base\Event;
use yii\base\InvalidCallException;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Class FileModel
 * @package cyneek\yii2\fileupload\models
 */

/**
 * Class FileModel
 * @package cyneek\yii2\fileupload\models
 *
 * @property integer $id
 *
 * class name of the parent object that will hold the file
 * @property string $entity
 *
 * Id of the object that holds the file
 * @property integer $entityId
 *
 *
 * Manually defined in each instantiation of the class
 * will define an additional filter to the parent object
 * because objects can hold more than one type of image
 * @property string $entityAttribute
 *
 * Only has values if a file has more than one copy of itself
 * like images of different sizes, all will reference the main
 * file additionally to its object id
 * @property integer $parentId
 *
 * Only used when the file it's a copy from the original, like a
 * thumbnail, for example, this field will help to tell apart the
 * different files
 * @property string $childName
 * @property string $uploadDate
 * @property integer $fileOrder
 *
 * Path relative with the fileManager assigned to the model
 * @property string $relativePath
 *
 * Complete path with the /home/application/uploads... way to get the file
 * @property string $completePath
 *
 * Complete path with the http:// way to get the file in the application
 * @property string $webPath
 * @property string $originalFileName
 * @property string $fileName
 * @property string $mimeType
 * @property string $extension
 * @property integer $fileSize
 * @property string $exif
 * @property integer $userId
 * @property boolean $updated
 */
class FileModel extends ActiveRecord
{


    /**
     * The file to be saved
     *
     * @var UploadedFile
     */
    public $file;

    /**
     * The model linked with the file
     *
     * @var ActiveRecord
     */
    public $model;

    /**
     * Base upload path for the file
     *
     * @var String
     */
    public $uploadPath;

    /** @var AbstractFileManager $fileManager */
    public $fileManager;

    /**
     * TableName
     *
     * If for any reason you don't want to use the same table for all files, here you can change it.
     *
     * @return string
     */
    public static function tableName()
    {
        return '{{%file_uploads}}';
    }

    /**
     * Initialization of all basic values, throws exception if any of the
     * basic values that needs to run the model it's not defined
     *
     * @throws \Exception
     */
    function init()
    {
        parent::init();


        // Events used to prevent CRUD without a declared fileManager to
        // work with the archives linked to the object.

        $this->on($this::EVENT_BEFORE_INSERT, [$this, '_checkFileData']);
        $this->on($this::EVENT_BEFORE_UPDATE, [$this, '_checkFileData']);
        $this->on($this::EVENT_BEFORE_DELETE, [$this, '_checkFileData']);

        // Event to get the data for validation purposes
        $this->on($this::EVENT_BEFORE_VALIDATE, [$this, '_prepareFileData']);

        // Events that save the uploaded file into its final location
        $this->on($this::EVENT_AFTER_INSERT, [$this, '_saveUploadedFile']);

        $this->on($this::EVENT_AFTER_UPDATE, [$this, '_saveUploadedFile']);

        // Events that delete old uploaded files when updating / deleting
        $this->on($this::EVENT_BEFORE_UPDATE, [$this, '_preUpdateActions']);

        $this->on($this::EVENT_BEFORE_DELETE, [$this, '_preDeleteActions']);


        // $this->on($this::EVENT_BEFORE_DELETE, [$this, '_preUpdateActions']);


        //  $this->on($this::EVENT_AFTER_INSERT, [$this, '_makeCopies']);

        //  $this->on($this::EVENT_AFTER_UPDATE, [$this, '_makeCopies']);

        //  $this->on($this::EVENT_AFTER_INSERT, [$this, '_deployFile']);

        // $this->on($this::EVENT_AFTER_UPDATE, [$this, '_deployFile']);

    }


    protected function _checkFileData()
    {
        if (is_null($this->entityAttribute))
        {
            throw new \Exception('[File object init()] Not defined main attribute to link the file into the model.');
        }

        if (!$this->fileManager instanceof AbstractFileManager)
        {
            throw new \Exception('[File object init()] FileManager must be an instance of AbstractFileManager.');
        }
    }


    /**
     * Define the upload_date (as allways it's the same, we will surely can use the sweet yii 2 behaviors) and userid
     * @return array
     */
    public function behaviors()
    {
        return [
            'upload_date' => [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'uploadDate',
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'uploadDate',
                ],
                'value' => function ()
                {
                    return date('Y-m-d H:i:s');
                },
            ],
            'upload_userid' => [
                'class' => BlameableBehavior::className(),
                'createdByAttribute' => 'userId',
                'updatedByAttribute' => 'userId',
            ],
            'update_attribute' => [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'updated',
                ],
                'value' => function ($event)
                {
                    return '1';
                },
            ]
        ];
    }

    /**
     * Rules
     *
     * @return array
     */
    function rules()
    {
        $rules = [
            [['entity', 'childName', 'entityAttribute', 'relativePath', 'completePath', 'fileName', 'mimeType', 'extension', 'exif'], 'string'],
            [['entity', 'file'], 'required'],
            [['entityId', 'parentId', 'fileOrder', 'userId', 'fileSize'], 'integer'],
            [['file'], 'file', 'maxFiles' => 1],
            [['entity', 'childName', 'entityAttribute', 'entityId', 'parentId', 'uploadDate', 'fileOrder', 'relativePath', 'completePath', 'fileName', 'mimeType', 'extension', 'fileSize', 'exif', 'userId', 'updated'], 'safe']
        ];

        return $rules;
    }


    /**
     * Launched every EVENT_BEFORE_INSERT event.
     *
     * Updates the fields previously to inserting them into the database
     *
     * If there is no uploaded file returns a not valid event and the save() will stop
     *
     * Also, if $record_id it's not defined, tries again to give it a value.
     *
     * @param Event $event
     * @return bool
     * @throws \Exception
     */
    protected function _prepareFileData(Event $event)
    {
        if (is_null($this->entityId) && !is_null($this->model) && $this->model->getIsNewRecord() == FALSE)
        {
            $this->entityId = $this->model->getPrimaryKey();
        }
        elseif (is_null($this->entityId))
        {
            throw new \Exception('[File object _prepareFileData()] File Upload objects must have a defined record_id.');
        }

        // Define the file_order, if it's null, will set it to the max amount of files plus one
        if (is_null($this->fileOrder))
        {
            $max_file_order = $this::find()->where(['entity' => $this->entity,
                'entityAttribute' => $this->entityAttribute,
                'entityId' => $this->entityId,
                'parentId' => 0
            ])->select('max(fileOrder)')->scalar();

            $this->fileOrder = $max_file_order + 1;
        }

        // This values will only change if there is a new record
        // the updates should mantain the url of the original file
        // we will make an exception with the mimeType and extension
        // in this case
        if ($this->getIsNewRecord())
        {
            $this->entity = $this->model->className();
            $this->relativePath = $this->_getPath();
            $this->completePath = $this->fileManager->basePath() . $this->_getPath();
            $this->webPath = $this->fileManager->HTTPPath() . $this->_getPath();
            $this->originalFileName = $this->file->getBaseName();
            $this->fileName = $this->generateFileName();
        }

        $this->mimeType = FileHelper::getMimeType($this->file->tempName);
        $this->extension = $this->file->getExtension();
        $this->fileSize = $this->file->size;
        $this->exif = $this->getExifData();

    }


    /**
     * Launched every EVENT_BEFORE_UPDATE event.
     *
     * @param Event $event
     * @return bool|NULL
     */
    protected function _preUpdateActions(Event $event)
    {
        $relativePath = $this->getOldAttribute('relativePath');
        $fileName = $this->getOldAttribute('fileName');
        $extension = $this->getOldAttribute('extension');

        $file = $relativePath . $fileName . '.' . $extension;

        $this->deleteUploadedFile($file);

    }

    /**
     * Launched every EVENT_AFTER_DELETE event
     *
     * @param Event $event
     * @return bool|NULL
     */
    protected function _preDeleteActions(Event $event)
    {
        $file = $this->relativePath . $this->fileName . '.' . $this->extension;

        $this->deleteUploadedFile($file);
    }

    /**
     * Deletes the uploaded file from its defined destination
     *
     * @param String $fileLocation // location with relative path from FlySystem basepath
     * @throws \yii\base\InvalidConfigException
     */
    protected function deleteUploadedFile($file)
    {
        if ($this->fileManager->existFile($file))
        {
            $this->fileManager->deleteFile($file);
        }
    }

    /**
     * Changes the uploaded file from it's initial destination to its final directory
     *
     * @return bool
     */
    protected function _saveUploadedFile()
    {
        return $this->fileManager->writeFile($this->file->tempName, $this->relativePath . $this->fileName . '.' . $this->extension);
    }


    /**
     * Generates random filename and ensures that it's unique
     * for the active path
     *
     * @return string
     */
    protected function generateFileName()
    {
        $fileName = uniqid();

        return $fileName;
    }


    /**
     * Get the exif data from the file, if available
     *
     * @return string
     */
    protected function getExifData()
    {
        if (is_null($this->exif))
        {
            $exif_data = (function_exists('exif_read_data') ? @exif_read_data($this->file->tempName) : FALSE);

            if ($exif_data != FALSE)
            {
                $this->exif = json_encode($exif_data);
            }
            else
            {
                $this->exif = '';
            }
        }

        return $this->exif;
    }


    /**
     * Returns the path where the file will be stored.
     * If not defined, by default will move it into a modelClass/year/month/day/ path
     *
     * @return string
     */
    protected function _getPath()
    {
        if (is_null($this->uploadPath))
        {
            $reflect = new \ReflectionClass($this->model);
            $this->uploadPath = $this->uploadPath . $reflect->getShortName() . '/' . date('Y') . '/' . date('m') . '/' . date('d') . '/';
        }
        elseif (substr($this->uploadPath, -1) !== '/')
        {
            $this->uploadPath .= '/';
        }

        return $this->uploadPath;
    }


    /**
     * Returns the complete path of the loaded file
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->webPath . $this->fileName . '.' . $this->extension;
    }

    /**
     * Returns all the childs this file has
     *
     * @param String $childName
     * @return mixed
     */
    public function getChildren($childName = NULL)
    {
        return $this->_getChild($childName)->all();
    }


    /**
     * Returns the first child this file has
     *
     * @param String $childName
     * @return mixed
     */
    public function getChild($childName = NULL)
    {

        return $this->_getChild($childName)->one();
    }

    /**
     * @param String $childName
     * @return $this
     */
    protected function _getChild($childName)
    {

        if (is_null($this->primaryKey))
        {
            throw new InvalidCallException("Can't load child files while main FileModel object it's not loaded.");
        }

        $search_array = [
            'parentId' => $this->primaryKey
        ];


        if (!is_null($childName))
        {
            $search_array['childName'] = $childName;
        }

        /** @var ActiveRecord $model */
        return $this::find()->where($search_array)
            ->orderBy(['fileOrder' => SORT_ASC]);
    }

}