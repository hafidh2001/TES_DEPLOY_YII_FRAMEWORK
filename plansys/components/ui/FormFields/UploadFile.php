<?php

class UploadFile extends FormField {

    public function getFieldProperties() {
        return array (
            array (
                'label' => 'Field Name',
                'name' => 'name',
                'options' => array (
                    'ng-model' => 'active.name',
                    'ng-change' => 'changeActiveName()',
                    'ps-list' => 'modelFieldList',
                ),
                'listExpr' => 'FormsController::$modelFieldList',
                'searchable' => 'Yes',
                'showOther' => 'Yes',
                'type' => 'DropDownList',
            ),
            array (
                'label' => 'Label',
                'name' => 'label',
                'options' => array (
                    'ng-model' => 'active.label',
                    'ng-change' => 'save()',
                    'ng-delay' => '500',
                ),
                'type' => 'TextField',
            ),
            array (
                'label' => 'File Type',
                'name' => 'fileType',
                'options' => array (
                    'ng-model' => 'active.fileType',
                    'ng-change' => 'save()',
                    'ng-delay' => '500',
                ),
                'fieldOptions' => array (
                    'placeholder' => 'ex: jpg, doc, xls',
                ),
                'type' => 'TextField',
            ),
            array (
                'label' => 'Upload Path (PHP)',
                'name' => 'uploadPath',
                'options' => array (
                    'ng-model' => 'active.uploadPath',
                    'ng-change' => 'save()',
                    'ng-delay' => '500',
                ),
                'fieldOptions' => array (
                    'placeholder' => 'ex: geo/{$model->id}',
                    'style' => 'min-height:50px;white-space:pre;word-break:break-all;',
                    'auto-grow' => '',
                ),
                'type' => 'TextArea',
            ),
            array (
                'label' => 'Layout',
                'name' => 'layout',
                'options' => array (
                    'ng-model' => 'active.layout',
                    'ng-change' => 'save();',
                ),
                'listExpr' => 'array(\'Horizontal\',\'Vertical\')',
                'type' => 'DropDownList',
            ),
            array (
                'label' => 'Mode',
                'name' => 'mode',
                'options' => array (
                    'ng-model' => 'active.mode',
                    'ng-change' => 'save();',
                ),
                'list' => array (
                    'Upload + Browse + Download' => 'Upload + Browse + Download',
                    'Browse + Download' => 'Browse + Download',
                    'Upload + Download' => 'Upload + Download',
                    'Download Only' => 'Download Only',
                ),
                'type' => 'DropDownList',
            ),
            array (
                'label' => 'Allow Delete',
                'name' => 'allowDelete',
                'options' => array (
                    'ng-model' => 'active.allowDelete',
                    'ng-change' => 'save()',
                ),
                'listExpr' => '[\'Yes\',\'No\']',
                'type' => 'DropDownList',
            ),
            array (
                'label' => 'Allow Overwrite',
                'name' => 'allowOverwrite',
                'options' => array (
                    'ng-model' => 'active.allowOverwrite',
                    'ng-change' => 'save()',
                ),
                'listExpr' => '[\'Yes\',\'No\']',
                'type' => 'DropDownList',
            ),
            array (
                'label' => 'Show File Name',
                'name' => 'showFileName',
                'options' => array (
                    'ng-model' => 'active.showFileName',
                    'ng-change' => 'save()',
                ),
                'listExpr' => '[\'Yes\',\'No\']',
                'type' => 'DropDownList',
            ),
            array (
                'type' => 'Text',
                'value' => '<hr/>',
            ),
            array (
                'label' => 'Max (in KB)',
                'name' => 'restrict',
                'options' => array (
                    'ng-model' => 'active.restrict',
                    'ng-change' => 'save()'
                ),
                'type' => 'TextField',
            ),
            array (
                'type' => 'Text',
                'value' => '<hr/>',
            ),
            array (
                'column1' => array (
                    array (
                        'type' => 'Text',
                        'value' => '<column-placeholder></column-placeholder>',
                    ),
                    array (
                        'label' => 'Label Width',
                        'name' => 'labelWidth',
                        'layout' => 'Vertical',
                        'labelWidth' => '12',
                        'fieldWidth' => '11',
                        'options' => array (
                            'ng-model' => 'active.labelWidth',
                            'ng-change' => 'save()',
                            'ng-delay' => '500',
                            'ng-disabled' => 'active.layout == \'Vertical\'',
                        ),
                        'type' => 'TextField',
                    ),
                ),
                'column2' => array (
                    array (
                        'type' => 'Text',
                        'value' => '<column-placeholder></column-placeholder>',
                    ),
                    array (
                        'label' => 'Field Width',
                        'name' => 'fieldWidth',
                        'layout' => 'Vertical',
                        'labelWidth' => '12',
                        'fieldWidth' => '11',
                        'options' => array (
                            'ng-model' => 'active.fieldWidth',
                            'ng-change' => 'save()',
                            'ng-delay' => '500',
                        ),
                        'type' => 'TextField',
                    ),
                ),
                'w1' => '50%',
                'w2' => '50%',
                'type' => 'ColumnField',
            ),
            array (
                'label' => 'Options',
                'name' => 'options',
                'type' => 'KeyValueGrid',
            ),
            array (
                'label' => 'Label Options',
                'name' => 'labelOptions',
                'type' => 'KeyValueGrid',
            ),
            array (
                'label' => 'Field Options',
                'name' => 'fieldOptions',
                'type' => 'KeyValueGrid',
            ),
        );
    }

    public $name;
    public $label = "File Upload";
    public $layout = 'Horizontal';
    public $value;
    public $mode = 'Upload + Download';
    public $filePattern = '';
    public $labelWidth = 4;
    public $fieldWidth = 8;
    public $uploadPath = '';
    public $fileType = '';
    public $options = [];
    public $allowDelete = 'Yes';
    public $allowOverwrite = 'Yes';
    public $showFileName = 'No';
    public $labelOptions = [];
    public $fieldOptions = [];
	public $restrict = '';

    /** @var string $toolbarName */
    public static $toolbarName = "Upload File";

    /** @var string $category */
    public static $category = "User Interface";

    /** @var string $toolbarIcon */
    public static $toolbarIcon = "fa fa-upload";

    public function getUploadPath() {
        $dir = Yii::getPathOfAlias('repo' . '.' . $this->uploadPath);

        if ($dir != "" && !file_exists($dir)) {
            mkdir($dir, 0755, true);
            chmod($dir, 0755);
        }
        return $dir;
    }

    public function getFileType() {
        return $this->fileType;
    }

    public function includeJS() {
        return ['upload-file.v1.js'];
    }

    public function getLayoutClass() {
        return ($this->layout == 'Vertical' ? 'form-vertical' : '');
    }

    public function getErrorClass() {
        return (count($this->errors) > 0 ? 'has-error has-feedback' : '');
    }

    public function getlabelClass() {
        if ($this->layout == 'Vertical') {
            $class = "control-label col-sm-12";
        } else {
            $class = "control-label col-sm-{$this->labelWidth}";
        }

        $class .= @$this->labelOptions['class'];
        return $class;
    }

    public function actionUpload($path = null) {
        if (!isset($_FILES['file'])) {
            echo json_encode(["success" => "No", "files" => json_encode($_FILES)]);
            die();
        }
        $file = $_FILES["file"];
        $name = $file['name'];

        $fb = FormBuilder::load($_GET['class']);
        if (!$fb) {
            echo json_encode(["success" => "No", "message" => "Konfigurasi form tidak ditemukan"]);
            die();
        }
        $ff = $fb->findField(['name' => $_GET['name']]);

        // cek ekstensi sesuai konfigurasi field (fileType)
        $fileType = trim(@$ff['fileType']);
        if ($fileType != '') {
            $allowed = array_map('trim', explode(',', $fileType));
            $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                echo json_encode([
                    'success' => 'No',
                    'message' => 'Tipe file tidak diijinkan, File yang diijinkan adalah ' . $fileType,
                ]);
                die();
            }
        }

        // pengaman: tolak file yang memuat kode / ekstensi berbahaya
        $unsafe = Repo::unsafeUploadReason($name, $file['tmp_name']);
        if ($unsafe != '') {
            echo json_encode(['success' => 'No', 'message' => $unsafe]);
            die();
        }

        // batas ukuran (KB), sesuai konfigurasi field (restrict)
        if (isset($ff['restrict']) && $ff['restrict'] != '') {
            if (filesize($file['tmp_name']) > (int) $ff['restrict'] * 1024) {
                echo json_encode(['success' => 'too-large']);
                die();
            }
        }

        // simpan lewat Repo (p_repo): nama unik + folder tanggal + hashed terenkripsi.
        // nilai yang disimpan di field ini adalah token hashed, setara API RepoUpload.
        $userId     = Yii::app()->user->id;
        $timestamp  = time();
        $uniqueName = $userId . '_' . $timestamp . '_' . bin2hex(random_bytes(8));
        $subDir     = date('Y-m-d', $timestamp);

        $model = Repo::storeFile($file['tmp_name'], $name, $subDir, $userId, $uniqueName, 'WEB');
        if (!$model) {
            echo json_encode(['success' => 'No', 'message' => 'Gagal menyimpan file']);
            die();
        }

        echo json_encode([
            'success'      => 'Yes',
            'path'         => $model->hashed,
            'downloadPath' => $model->hashed,
            'name'         => $model->origin_file_name,
        ]);
    }

//    public function actionDescription() {
//        $postdata = file_get_contents("php://input");
//        $post = CJSON::decode($postdata);
//        $name = base64_decode($post['name']);
//        $path = base64_decode($post['path']);
//        $content = base64_decode($post['desc']);
//        $desc = JsonModel::load($path . DIRECTORY_SEPARATOR . $name . '.json');
//        $desc->set('desc', $content);
//    }

    public function actionThumb($t) {
        $resolved = $this->resolveFile($t);
        if (!$resolved) {
            return;
        }
        $file = $resolved['file'];

        $supported_image = array(
            'gif',
            'jpg',
            'jpeg',
            'png',
            'bmp',
            'tga'
        );
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $supported_image)) {
            return;
        }

        $img = Yii::app()->img->load($file);
        $img->resizeToWidth(250);

        $dir = Yii::getPathOfAlias('webroot.assets.thumb.' . date('Y-m-d'));
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            chmod($dir, 0755);
        }
        $thumb = $dir . DIRECTORY_SEPARATOR . basename(time() . '_' . rand(1, 10000) . "." . pathinfo($file, PATHINFO_EXTENSION));
        $img->save($thumb);
        $url = str_replace(Yii::getPathOfAlias('webroot'), '', $thumb);
        $url = str_replace('', '/', $url);

        echo Yii::app()->baseUrl . $url;
    }

    public function actionCheckFile() {
        $postdata = file_get_contents("php://input");
        $post = json_decode($postdata, true);
        $resolved = $this->resolveFile(isset($post['file']) ? $post['file'] : null);
        if ($resolved) {
            echo json_encode([
                'status' => 'exist',
                'desc' => '',
                'downloadPath' => $post['file'],
                'name' => $resolved['name'],
            ]);
        } else {
            echo json_encode([
                'status' => 'not exist',
            ]);
        }
    }

    public function actionDownload($f, $n) {
        $resolved = $this->resolveFile($f);
        if (!$resolved) {
            throw new CHttpException(404);
            return false;
        }
        $file = $resolved['file'];
        $name = $resolved['name'] != '' ? $resolved['name'] : $n;

        $mem_limit = ini_get('memory_limit');
        ini_set('memory_limit', -1);
        if (isset($_GET['d'])) {
            echo file_get_contents($file);
        } else {
            Yii::app()->request->sendFile($name, file_get_contents($file));
        }
        ini_set('memory_limit', $mem_limit);
    }

    public function actionRemove() {
        $postdata = file_get_contents("php://input");
        $post = CJSON::decode($postdata);
        if (isset($post['file'])) {
            $repo = Repo::findByHashed($post['file']);
            if ($repo) {
                $repo->deleteFile();
            } else {
                $paths = [$post['file'], base64_decode($post['file'])];
                foreach ($paths as $p) {
                    if ($p === false || $p === '') {
                        continue;
                    }
                    $resolved = RepoManager::resolve($p);
                    if (is_file($resolved)) {
                        @unlink($resolved);
                    }
                }
            }
        }
    }

    private function resolveFile($tokenOrPath) {
        if ($tokenOrPath === null || $tokenOrPath === '') {
            return null;
        }
        $repo = Repo::findByHashed($tokenOrPath);
        if ($repo) {
            return [
                'file' => $repo->getAbsolutePath(),
                'name' => $repo->origin_file_name,
            ];
        }
        $paths = [$tokenOrPath, base64_decode($tokenOrPath)];
        foreach ($paths as $p) {
            if ($p === false || $p === '') {
                continue;
            }
            $resolved = RepoManager::resolve($p);
            if (is_file($resolved)) {
                return ['file' => $resolved, 'name' => basename($resolved)];
            }
        }
        return null;
    }

    public function getFieldColClass() {
        return "col-sm-" . $this->fieldWidth;
    }

    public function render() {
        $this->addClass('form-group form-group-sm', 'options');
        $this->addClass($this->layoutClass, 'options');
        $this->addClass($this->errorClass, 'options');

        $this->addClass('form-control', 'fieldOptions');

        $this->setDefaultOption('ng-model', "model['{$this->originalName}']", $this->options);
        $this->setDefaultOption('style', "min-width:275px;", $this->options);
        return $this->renderInternal('template_render.php');
    }

}

?>