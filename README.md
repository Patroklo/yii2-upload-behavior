# Yii2 File Upload Behavior

File or Image upload behavior for Yii2 applications

## What's File upload behavior?

This module changes the route system definition of Yii2 in order to, instead of having to define the routes in the config file of the application now will be possible to make a series of files that hold the routes that the user will define for his web. This module lets the calling to a series of methods that will define the system routes in a more intuitive way that the basic Yii2 system getting it's inspiration from the routing system defined by Laravel.

Developed by Joseba Juániz ([@Patroklo](http://twitter.com/Patroklo))

## Minimum requirements

* Yii2
* Php 5.4 or above

## License

This is free software. It is released under the terms of the following BSD License.

Copyright (c) 2015, by Cyneek
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. Neither the name of Cyneek nor the names of its contributors
   may be used to endorse or promote products derived from this software
   without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER "AS IS" AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

## Instalation

* Install [Yii 2](http://www.yiiframework.com/download)
* Install package via [composer](http://getcomposer.org/download/) `"cyneek/yii2-upload-behavior": "*"`
* Update config file _'config/web.php'_

```
...
'modules' => [
	'uploadBehavior' => [
		'class' => 'cyneek\yii2\uploadBehavior\Module',
	],
]
...
```

* Execute the migration file
    * ```php yii migrate --migrationPath=@vendor/cyneek/yii2-upload-behavior/migrations```

* Profit!


## Definition

### UploadBehavior

This behavior will make the upload of files 

* **attribute** (required) The attribute that will link the model with the file we want to upload.

* **scenarios** (default: "default", "insert", "update", "delete") Scenarios in which the upload will take place. 

* **fileActionOnSave** (default: insert)(required) Action that will take place when uploading a file.
        Valid actions: 
        * **insert** Will always insert new files when there's a new upload.
        * **update** Will overwrite the old file with the new file using the same name.
        * **delete** Will delete the old file and insert a new file in the system.

* **path** The base path or path alias to the directory in which to save files.

* **instanceByName** Getting file instance by name.


### UploadImageBehavior

It has the UploadBehavior attributes and:

* **thumbs** Array list with the thumbnail properties and actions
        ```php
        [
            'thumbName' => [
                  ['action' => 'crop', 'width' => 200, 'height' => 200, 'quality' => 90],
                  ...
             ]
         ]
         ```

* **thumbPath** Path where the thumbs will be moved.

* **imageActions** Describes the actions that will be made to the original image
        ```php
            [
             ['action' => 'crop', 'width' => 200, 'height' => 200, 'quality' => 90]
            ]
        ```
        
## Usage

As an example:

```php
    function behaviors()
    {
        return [
            [
                'class' => UploadBehavior::className(),
                'attribute' => 'file',
                'scenarios' => ['default'],
                'fileActionOnSave' => 'delete'
            ],
            [
                'class' => UploadImageBehavior::className(),
                'attribute' => 'avatar',
            ],
        ];
    }
```
