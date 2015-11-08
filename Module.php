<?php
namespace cyneek\yii2\upload;

use cyneek\yii2\upload\models\FileModel;
use cyneek\yii2\upload\models\ImageFileModel;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * fileupload module
 *
 * @author joseba <joseba.juaniz@gmail.com>
 */
class Module extends \yii\base\Module
{
    /**
     * @var string Alias for module
     */
    public $alias = "@uploadBehavior";

    /**
     * Array that will store the models used in the package
     * e.g. :
     * [
     *     'FileModel' => 'cyneek\yii2\upload\models\FileModel'
     * ]
     *
     * The classes defined here will be merged with getDefaultModels()
     * having he manually defined by the user preference.
     *
     * @var array
     */
    public $modelMap = [];

    /**
     * Upload directory
     *
     * @var string
     */
    public $baseDir = '@webroot/uploads/';


    public function init()
    {
        parent::init();

        $this->defineModelClasses();

    }

    /**
     * Merges the default and user defined model classes
     * Also let's the developer to set new ones with the
     * parameter being those the ones with most preference.
     *
     * @param array $modelClasses
     */
    public function defineModelClasses($modelClasses = [])
    {
        $this->modelMap = ArrayHelper::merge(
            $this->getDefaultModels(),
            $this->modelMap,
            $modelClasses
        );
    }

    /**
     * Get default model classes
     */
    public function getDefaultModels()
    {
        return [
            'FileModel' => FileModel::className(),
            'ImageFileModel' => ImageFileModel::className()
        ];
    }

    /**
     * Get defined className of model
     *
     * Returns an string or array compatible
     * with the Yii::createObject method.
     *
     * @param string $name
     * @param array $config // You should never send an array with a key defined as "class" since this will
     *                      // overwrite the main className defined by the system.
     * @return string|array
     */
    public function model($name, $config = [])
    {
        $modelData = $this->modelMap[ucfirst($name)];

        if (!empty($config))
        {
            if (is_string($modelData))
            {
                $modelData = ['class' => $modelData];
            }

            $modelData = ArrayHelper::merge(
                $modelData,
                $config
            );
        }

        return $modelData;
    }

}