<?php

use cyneek\yii2\uploadBehavior\models\FileModel;
use yii\db\Schema;

class m141203_185519_fileModel extends \yii\db\Migration
{


    public function up()
    {
        $tableOptions = NULL;
        if ($this->db->driverName === 'mysql')
        {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable(FileModel::tableName(), [
            'id' => Schema::TYPE_PK,
            'entity' => Schema::TYPE_STRING . ' NOT NULL',
            'entityId' => Schema::TYPE_INTEGER . ' NOT NULL',
            'entityAttribute' => Schema::TYPE_STRING . ' NOT NULL',
            'parentId' => Schema::TYPE_INTEGER . ' NOT NULL',
            'childName' => Schema::TYPE_STRING . ' NULL DEFAULT NULL',
            'uploadDate' => Schema::TYPE_DATETIME . ' NOT NULL',
            'fileOrder' => Schema::TYPE_INTEGER . ' NOT NULL',
            'relativePath' => 'varchar(512) NOT NULL',
            'completePath' => 'varchar(512) NOT NULL',
            'webPath' => 'varchar(512) NOT NULL',
            'originalFileName' => Schema::TYPE_STRING . ' NOT NULL',
            'fileName' => Schema::TYPE_STRING . ' NOT NULL',
            'mimeType' => Schema::TYPE_STRING . ' NOT NULL',
            'extension' => Schema::TYPE_STRING . ' NOT NULL',
            'fileSize' => Schema::TYPE_INTEGER . ' NOT NULL',
            'exif' => Schema::TYPE_TEXT . ' NULL DEFAULT NULL',
            'userId' => Schema::TYPE_INTEGER . ' NULL DEFAULT NULL',
            'updated' => Schema::TYPE_SMALLINT . ' NULL DEFAULT 0'
        ], $tableOptions);

        $this->createIndex("file_upload_entity", FileModel::tableName(), "entity", FALSE);
        $this->createIndex("file_upload_entityAttribute", FileModel::tableName(), "entityAttribute", FALSE);
        $this->createIndex("file_upload_entityAttributeComplex", FileModel::tableName(), ["entity", "entityAttribute"], FALSE);
        $this->createIndex("file_upload_childName", FileModel::tableName(), "childName", FALSE);
        $this->createIndex("file_upload_userId", FileModel::tableName(), "userId", FALSE);
        $this->createIndex("file_upload_allEntities", FileModel::tableName(), ["entity", "entityId", "entityAttribute"], FALSE);
    }

    public function down()
    {
        $this->dropTable('file_uploads');
    }
}
