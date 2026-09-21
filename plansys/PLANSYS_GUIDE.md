# PLANSYS FRAMEWORK — AI DEVELOPER GUIDE

> Dokumentasi ini ditujukan untuk AI developer yang akan membangun aplikasi menggunakan plansys framework.
> Default: semua pengembangan dilakukan di folder `app/`. Kode di `plansys/` hanya boleh diubah untuk patch bug/improvement framework yang memang tidak bisa dikerjakan dari `app/` — dan wajib di-commit ke repo plansys agar tidak hilang saat `git pull`.

---

## 1. ARSITEKTUR

### Struktur Direktori

```
root/
  index.php              # Entry point, load Yii framework
  app/                   # Aplikasi custom (model, controller, modul)
    controllers/         # Controller global (SiteController, dll)
    models/              # Model bisnis (ActiveRecord, table database)
    modules/
      {module}/          # Module (admin, sales, dashboard)
        controllers/     # Controller module
        forms/           # Form declarative (FIELD UTAMA plansys)
        views/           # View tradisional (jarang dipakai)
    menus/
      {module}.php       # Konfigurasi sidebar menu
    vendor/              # Composer (dompdf, dll)
  plansys/               # Framework/Platform (JANGAN DIUBAH)
    components/          # Komponen inti
      models/            # Base ActiveRecord, User, Role
      ui/                # FormBuilder + semua FormField components
    config/              # Config (main.php - dynamis via Setting)
    modules/             # Modul sistem (docs, sys, dev, install)
    vendor/              # Vendor (box/spout, phpmailer, dll)
```

### Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend Framework | Yii 1.x (PHP) |
| Frontend Framework | AngularJS 1.x |
| Database | PostgreSQL |
| ORM | CActiveRecord (extended) |
| PDF | dompdf |
| Excel | box/spout v2.7 |
| Form Builder | Custom FormBuilder (plansys) |
| Template Engine | PHP + AngularJS directives |

---

## 2. MODEL (CActiveRecord)

### Lokasi
`app/models/NamaModel.php`

### Pola Dasar

```php
<?php

class MProduk extends ActiveRecord
{
    public function tableName()
    {
        return 'm_produk'; // nama tabel di PostgreSQL
    }

    public function rules()
    {
        return array(
            // validasi: attribute wajib diisi
            array('nama, created_at, created_by, id_pabrik, harga_dasar', 'required'),
            // validasi: tipe data
            array('created_by, id_pabrik', 'numerical', 'integerOnly' => true),
            array('nama', 'length', 'max' => 256),
            array('is_active', 'length', 'max' => 5),
            array('deskripsi', 'safe'), // boleh kosong/special chars
        );
    }

    public function relations()
    {
        return array(
            // BELONGS_TO: model ini milik model lain
            'idPabrik' => array(self::BELONGS_TO, 'MPabrik', 'id_pabrik'),
            'createdBy' => array(self::BELONGS_TO, 'User', 'created_by'),
            // HAS_MANY: model ini punya banyak record lain
            'items' => array(self::HAS_MANY, 'TOrderItem', 'id_produk'),
        );
    }

    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'nama' => 'Nama',
            'is_active' => 'Is Active',
            'harga_dasar' => 'Harga Dasar',
        );
    }
}
```

### Static Method untuk API

```php
// Untuk dipanggil dari REST API (mode=function)
public static function getProduct($params)
{
    $query = "SELECT * FROM m_produk WHERE is_doc = :is_doc";
    $command = Yii::app()->db->createCommand($query);
    $command->bindValue(':is_doc', $params['is_doc']);
    return $command->queryAll();
}
```

Bila fungsi menyimpan data yang memuat token file repo (`RepoUpload`), pastikan simpan lewat AR save (`$model->save()`) supaya `doAfterSave()` menandai file **committed** otomatis; jika terpaksa query mentah, sisipkan pemanggilan `Repo::markCommittedByHashed($hashed, $userId)` (atau ekspos mode `RepoUse` dari klien) setelah save — lihat § 9 File Repository.

### Naming Convention

| Tabel | Model | Prefix |
|-------|-------|--------|
| `m_produk` | `MProduk` | `M` (master data) |
| `t_sales_order` | `TSalesOrder` | `T` (transaksi) |
| `p_user` | `User` | `P` (system) |

---

## 3. CONTROLLER

### Lokasi
`app/modules/{module}/controllers/NamaController.php`

### Pola Dasar

```php
<?php

Yii::import("app.modules.{module}.forms.{feature}.*");

class NamaController extends Controller
{
    public function filters()
    {
        return ['accessControl'];
    }

    public function accessRules()
    {
        return [
            ['allow', 'users' => ['@']], // hanya user login
            ['deny'],
        ];
    }

    // Halaman index (list/grid)
    public function actionIndex()
    {
        $this->renderForm('AdminXxxIndex');
    }

    // Form tambah/edit
    public function actionEdit($id = null)
    {
        if (is_null($id)) {
            $model = new AdminXxxForm;
        } else {
            $model = $this->loadModel($id, "AdminXxxForm");
        }

        if (isset($_POST["AdminXxxForm"])) {
            $model->attributes = $_POST["AdminXxxForm"];
            if (is_null($id)) {
                $model->created_at = date('Y-m-d H:i:s');
                $model->created_by = Yii::app()->user->id;
            }
            if ($model->save()) {
                $this->flash('Data Berhasil Disimpan');
                $this->redirect(['index']);
            }
        }
        $this->renderForm("AdminXxxForm", $model);
    }

    // Hapus
    public function actionDelete($id)
    {
        if (strpos($id, ',') > 0) {
            ActiveRecord::batchDelete("AdminXxxForm", explode(",", $id));
        } else {
            $model = $this->loadModel($id, "AdminXxxForm");
            if (!is_null($model)) {
                $model->delete();
            }
        }
        $this->flash('Data Berhasil Dihapus');
        $this->redirect(['index']);
    }

    // Custom AJAX action
    public function actionSave()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        $data = $post['data'];

        // proses data...
        echo json_encode(['st' => 1, 'msg' => 'Berhasil']);
    }
}
```

### URL Pattern
```
/admin/{controller}/{action}
/admin/{controller}/edit&id={id}
/admin/{controller}/delete&id={id}
```

---

## 4. FORM (INTI PLANSYS)

### Lokasi
`app/modules/{module}/forms/{feature}/AdminXxxYyy.php`

### Naming Convention

| Nama Form | Fungsi |
|-----------|--------|
| `AdminXxxIndex.php` | Halaman list/grid |
| `AdminXxxForm.php` | Halaman tambah/edit |
| `XxxSubFeature.php` | Sub-halaman (modal, detail) |

### Struktur Form Class

```php
<?php

class AdminXxxForm extends ModelClass // extends model yang sesuai
{
    // getForm() = konfigurasi halaman
    public function getForm()
    {
        return array(
            'title' => 'Judul Halaman',
            'layout' => array(
                'name' => 'full-width', // layout: full-width, 2-column, 3-column
                'data' => array(
                    'col1' => array(
                        'type' => 'mainform',
                        'size' => '100', // persentase lebar
                    ),
                ),
            ),
            'inlineJS' => 'namafile.js', // JS file di folder yang sama
        );
    }

    // getFields() = deklarasi semua field di halaman
    public function getFields()
    {
        return array(
            // field 1
            array(
                'type' => 'TextField',
                'name' => 'nama',
                'label' => 'Nama',
            ),
            // field 2
            array(
                'type' => 'NumberField',
                'name' => 'harga',
                'label' => 'Harga',
            ),
        );
    }
}
```

### Prinsip Penting

1. **Semua field bind ke `$scope.model.xxx`** via `ng-model`
2. **Form class extends model** → field names = database column names
3. **Tidak ada PHP HTML rendering** → semua via AngularJS directives
4. **DataSource dan GridView** = pasangan utama untuk tabel data
5. **InlineJS** = akses `$scope`, `$http`, `$localStorage`, `$timeout`

---

## 5. COMPONENT REFERENCE

### 5.1 DataSource — Query Engine

**Fungsi**: Mengeksekusi SQL query, menyediakan data untuk GridView/filter.

```php
array(
    'type' => 'DataSource',
    'name' => 'dsProduk', // nama unik, diakses sebagai $scope.dsProduk
    'sql' => "SELECT p.*, pr.nama_pabrik
              FROM m_produk p
              INNER JOIN m_pabrik pr ON pr.id = p.id_pabrik
              WHERE 1=1
              {AND p.nama LIKE :nama}
              {AND p.is_active = :is_active}
              ORDER BY p.id DESC",
    'postData' => 'No', // 'No' = query via GET param, default POST
    'params' => array(
        ':nama' => 'js: model.nama', // dinamis dari field form
        ':is_active' => 'js: model.is_active', // null-safe via {}
    ),
    'execMode' => 'after', // 'after' = execute setelah model loaded
    'type' => 'DataSource',
),
```

**Param Binding Modes**:
- `'js: model.xxx'` → Angular expression, nilai dari form field
- `'(string)Yii::app()->user->id'` → PHP static, dievaluasi saat render
- `'js: 123'` → literal angka

**PENTING — Kurung Kurawal `{}` di SQL**:
- `{AND col = :param}` → clause DI-SKIP kalau param null/undefined
- `AND col = :param` (tanpa {}) → clause SELALU ada, param WAJIB terisi
- **Gunakan `{}` untuk semua param dinamis yang bisa null**
- Selalu pakai `WHERE 1=1` sebelum optional conditions

**Akses di AngularJS**:
```javascript
$scope.dsProduk.data       // array hasil query
$scope.dsProduk.totalRecords  // jumlah total
$scope.dsProduk.query()    // re-execute query
$scope.dsProduk.detail     // detail record
```

---

### 5.2 GridView — Tabel Data

**Fungsi**: Menampilkan data dari DataSource dalam tabel interaktif.

**Catatan State & Naming**:
- Sort + paging tersimpan otomatis di localStorage per-form (`pageSetting[formClassPath].dataGrids[nama]`) — pindah halaman lalu kembali, sort/paging tetap
- `name` WAJIB unik antar grid dalam satu form (satu halaman boleh banyak GridView); jika kosong → fallback otomatis `grid_<namaDatasource>`
- Tombol Reset grid hanya membersihkan state milik form tersebut

```php
array(
    'type' => 'GridView',
    'name' => 'gridView1',
    'datasource' => 'dsProduk', // nama DataSource
    'gridOptions' => array(
        'enablePaging' => 'true',
        'pageSize' => 10,
    ),
    'columns' => array(
        // Kolom teks biasa
        array(
            'columnType' => 'string',
            'name' => 'nama',
            'label' => 'Nama Produk',
        ),
        // Kolom tanggal datetime
        array(
            'columnType' => 'string',
            'name' => 'created_at',
            'label' => 'Tanggal Input',
            'options' => array('mode' => 'datetime'),
        ),
        // Kolom tanggal date
        array(
            'columnType' => 'string',
            'name' => 'expired_date',
            'label' => 'Tgl Kadaluarsa',
            'options' => array('mode' => 'date'),
        ),
        // Kolom currency (Rp.)
        array(
            'columnType' => 'string',
            'name' => 'harga',
            'label' => 'Harga',
            'cellMode' => 'custom',
            'html' => '<td ng-class="rowClass(row, \'harga\', \'string\')">
                Rp. {{row[\'harga\'] | currency : \'\' : 0}}</td>',
        ),
        // Kolom badge/label
        array(
            'columnType' => 'string',
            'name' => 'is_active',
            'label' => 'Status',
            'cellMode' => 'custom',
            'html' => '<td style="text-align:center">
                <span class="label label-success" ng-if="row.is_active == \'YES\'">YES</span>
                <span class="label label-danger" ng-if="row.is_active != \'YES\'">NO</span></td>',
        ),
        // Kolom nomor urut
        array(
            'name' => '',
            'label' => 'No.',
            'options' => array('mode' => 'sequence'),
        ),
        // Tombol edit
        array(
            'name' => '',
            'label' => '',
            'columnType' => 'string',
            'options' => array(
                'mode' => 'edit-button',
                'editUrl' => 'admin/produk/edit&id={{row.id}}',
            ),
        ),
        // Tombol hapus
        array(
            'name' => '',
            'label' => '',
            'columnType' => 'string',
            'options' => array(
                'mode' => 'del-button',
                'delUrl' => 'admin/produk/delete&id={{row.id}}',
            ),
        ),
    ),
),
```

**Column Modes**:

| Mode | Fungsi |
|------|--------|
| `'mode' => 'datetime'` | Format tanggal + waktu |
| `'mode' => 'date'` | Format tanggal saja |
| `'mode' => 'time'` | Format waktu saja |
| `'mode' => 'sequence'` | Nomor urut otomatis |
| `'mode' => 'editable'` | Inline editing (contenteditable) |
| `'mode' => 'editable-insert'` | Editable saat insert saja |
| `'mode' => 'editable-update'` | Editable saat update saja |
| `'mode' => 'edit-button'` | Tombol edit ke URL |
| `'mode' => 'del-button'` | Tombol hapus |
| `'mode' => 'edit-popup-button'` | Tombol edit di popup |
| `'mode' => 'checkbox'` | Checkbox selection |
| `'cellMode' => 'custom'` | Custom HTML template |

**CSS classes untuk HTML template**:
- `rowClass(row, 'field_name', 'string')` → zebra striping + alignment
- `col-{n}` → responsive columns

---

### 5.3 DataFilter — Filter Panel

**Fungsi**: Panel filter yang terhubung ke DataSource.

```php
array(
    'type' => 'DataFilter',
    'name' => 'dataFilter1',
    'datasource' => 'dsProduk', // nama DataSource
    'filters' => array(
        // Filter teks
        array(
            'filterType' => 'string',
            'name' => 'p.nama',
            'label' => 'Nama Produk',
        ),
        // Filter dropdown
        array(
            'filterType' => 'list',
            'name' => 'p.is_active',
            'label' => 'Status',
            'list' => array('YES' => 'YES', 'NO' => 'NO'),
        ),
        // Filter tanggal
        array(
            'filterType' => 'date',
            'name' => 'p.created_at',
            'label' => 'Tanggal',
        ),
        // Filter number
        array(
            'filterType' => 'number',
            'name' => 'p.harga',
            'label' => 'Harga',
        ),
    ),
),
```

**Filter Types**:
- `string` → text search (LIKE)
- `number` → exact/gte/lte
- `date` → date range dengan navigasi
- `list` → dropdown selection
- `relation` → server-side search

---

### 5.4 RelationField — Dropdown Relasional

**Fungsi**: Dropdown yang mengambil data dari tabel lain, dengan pencarian server-side.

```php
array(
    'type' => 'RelationField',
    'label' => 'Pabrik',
    'name' => 'id_pabrik', // bind ke $scope.model.id_pabrik
    'modelClass' => 'app.models.MPabrik',
    'idField' => 'id',
    'labelField' => '{nama}', // format: {nama_column}
    'relationCriteria' => array(
        'select' => 't.id, t.nama',
        'condition' => 'is_active = \'YES\'',
        'order' => 'nama ASC',
    ),
    'searchable' => 'Yes', // Enable server-side search
    'includeEmpty' => 'Yes', // Tambah baris kosong di awal
    'emptyLabel' => '-- Pilih --',
),
```

**Cara kerja**:
1. Render → query data awal → populate dropdown
2. User ketik → POST `/formfield/RelationField.search` → server-side search
3. User pilih → `$scope.model.id_pabrik` terisi dengan id

---

### 5.5 DropDownList — Dropdown Static

**Fungsi**: Dropdown dari list statis (PHP array).

```php
// Opsi 1: List statis dari PHP
array(
    'type' => 'DropDownList',
    'label' => 'Gudang',
    'name' => 'id_gudang',
    'list' => array('1' => 'Gudang A', '2' => 'Gudang B'), // PHP array
    'includeEmpty' => 'Yes',
    'searchable' => 'Yes',
),

// Opsi 2: List dari model (pakai PHP)
array(
    'type' => 'DropDownList',
    'label' => 'Gudang',
    'name' => 'id_gudang',
    'list' => CHtml::listData(MGudang::model()->findAll([
        'condition' => 'is_active = \'YES\'',
        'order' => 'nama ASC',
    ]), 'id', 'nama'),
    'includeEmpty' => 'Yes',
),
```

---

### 5.6 TextField — Input Teks

```php
array(
    'type' => 'TextField',
    'label' => 'Nama',
    'name' => 'nama',
    'fieldType' => 'text', // atau 'password'
    'fieldOptions' => array(
        'placeholder' => 'Input nama...',
    ),
    'labelWidth' => '3',
    'fieldWidth' => '9',
    'prefix' => 'Rp.',  // prepend text
    'postfix' => '.00',  // append text
),
```

---

### 5.7 NumberField — Input Angka

```php
array(
    'type' => 'NumberField',
    'label' => 'Harga Beli',
    'name' => 'harga_beli',
    'value' => 0, // default value
    'minValue' => 0,
    'maxValue' => 999999999,
    'usecommas' => 'Yes', // format: 1,000,000
    'prefix' => 'Rp.',
),
```

---

### 5.8 DateTimePicker — Input Tanggal/Waktu

```php
// Tanggal saja
array(
    'type' => 'DateTimePicker',
    'label' => 'Tanggal Lahir',
    'name' => 'birth_date',
    'fieldType' => 'date', // 'date','datetime','datepicker','monthyear','time'
    'defaultToday' => 'Yes', // auto isi tanggal hari ini
),

// Datepicker popup
array(
    'type' => 'DateTimePicker',
    'label' => 'Tanggal Input',
    'name' => 'input_date',
    'fieldType' => 'datepicker',
),
```

---

### 5.9 ToggleSwitch — ON/OFF

```php
array(
    'type' => 'ToggleSwitch',
    'label' => 'Is Active',
    'name' => 'is_active',
    'onLabel' => 'YES',  // value saat ON
    'offLabel' => 'NO',   // value saat OFF
    'size' => 'small',    // 'normal' atau 'small'
),
```

---

### 5.10 ActionBar — Header Bar

```php
array(
    'type' => 'ActionBar',
    'linkBar' => array(
        array(
            'type' => 'LinkButton',
            'label' => 'Kembali',
            'icon' => 'chevron-left',
            'options' => array('href' => 'url:/admin/xxx/index'),
        ),
        array(
            'type' => 'LinkButton',
            'label' => 'Simpan',
            'buttonType' => 'success',
            'icon' => 'check',
            'options' => array('ng-click' => 'form.submit(this)'),
        ),
    ),
    'title' => 'Judul Halaman',
),
```

**Button Types**: `success` (hijau), `danger` (merah), `info` (biru), `warning` (kuning), `default` (abu-abu)

---

### 5.11 LinkButton — Tombol Aksi

```php
array(
    'type' => 'LinkButton',
    'label' => 'Tambah',
    'buttonType' => 'success',
    'icon' => 'plus',
    'options' => array(
        'href' => 'url:/admin/xxx/edit', // navigate ke URL
        // ATAU
        'ng-click' => 'myFunction()', // panggil JS function
        // ATAU
        'confirm' => 'Apakah yakin?', // konfirmasi dialog
    ),
),
```

---

### 5.12 ColumnField — Layout Kolom

```php
array(
    'type' => 'ColumnField',
    'w1' => '50%', // lebar kolom 1
    'w2' => '50%', // lebar kolom 2
    'column1' => array(
        array('type' => 'TextField', 'label' => 'Field 1', 'name' => 'field1'),
    ),
    'column2' => array(
        array('type' => 'TextField', 'label' => 'Field 2', 'name' => 'field2'),
    ),
),

// 3 kolom
array(
    'type' => 'ColumnField',
    'w1' => '33%',
    'w2' => '34%',
    'w3' => '33%',
    'column1' => array(...),
    'column2' => array(...),
    'column3' => array(...),
),
```

---

### 5.13 ListView — Repeatable Sub-Form

**Fungsi**: Daftar item yang bisa ditambah/dihapus/di-reorder (drag-drop).

```php
array(
    'type' => 'ListView',
    'name' => 'items',
    'fieldTemplate' => 'datasource', // 'datasource' | 'form' | 'default'
    'datasource' => 'dsOrderItems',
    'templateForm' => 'OrderItemForm', // class form untuk setiap row
    'sortable' => 'Yes',
    'deletable' => 'Yes',
    'insertable' => 'Yes',
    'minItem' => 1,
),
```

---

### 5.14 Text (HTML Custom)

```php
array(
    'type' => 'Text',
    'display' => 'all-line',
    'value' => '<div class="alert alert-info">
        <strong>Info:</strong> Ini custom HTML content
    </div>',
),
```

---

### 5.15 HiddenField

```php
array(
    'type' => 'HiddenField',
    'name' => 'id',
),
```

---

### 5.16 UploadFile (Server-managed upload)

Upload file ke **repo** terpusat di server (tabel `p_repo` + folder `plansys/repo/`), menggantikan upload browser-native (lihat 5.17 untuk pengecualian). File menempel pada baris lewat token `hashed` 64-hex yang tersimpan di kolom.

```php
array(
    'type' => 'UploadFile',
    'name' => 'foto',
    'label' => 'Foto',
    'uploadPath' => 'repo/produk/{$model->id}',
    'fileType' => 'jpg,png,gif',
),
```

Alur & status file (sama seperti API `RepoUpload`):
- Saat upload → file tersimpan status **`temp`** (belum dirujuk).
- Saat baris disimpan (form submit) → `ActiveRecord::doAfterSave()` memindai atribut, menemukan kolom berisi token 64-hex, lalu menandai file **`committed`** (otomatis, tanpa kode tambahan).
- Menghapus/ganti file di form → file lama ikut dihapus dari repo (soft-delete `__repoDeleted`).
- File `temp` yang barisnya tidak pernah tersimpan di-sweep oleh command `cleanRepo` (lihat § 9 File Repository).

#### Fallback path lama (proyek yang belum pakai hash)

Pada proyek yang di-*upgrade* — `value` UploadFile di database masih berupa **path** (misal `2023-09-05/foto.jpg` atau path absolut repo), bukan token 64-hex. `UploadFile::resolveFile()` mendeteksi otomatis:

- Nilai berupa **64 karakter hex** → dianggap token hash → dicari di `p_repo` (`Repo::findByHashed`).
- Nilai **selain itu** → dianggap path lama → di-*resolve* langsung lewat `RepoManager::resolve()` dan ditampilkan/diunduh tanpa menyentuh `p_repo`.

Deteksi ini dipakai `actionDownload`, `actionThumb`, dan `actionCheckFile`, jadi nilai path lama tetap tampil (nama file = `basename`) dan bisa diunduh. Pengecekan seragam memakai `UploadFile::isHashed($value)`.

Catatan: `ActiveRecord::doAfterSave()` hanya memindai atribut 64-hex untuk `markCommitted()`, sehingga path lama tidak ikut di-flag — tetapi file path lama yang dihapus lewat tombol delete belum dibersihkan fisiknya saat save (perlu penanganan `__repoDeleted` untuk jalur path).

---

### 5.17 UploadFileNative (Browser-native upload)

```php
array(
    'type' => 'UploadFileNative',
    'name' => 'import_file',
    'label' => 'File Excel',
),
// Baca file di JS:
// document.querySelector('input[ps-name="import_file"]').files[0]
```

---

## 6. INLINE JAVASCRIPT

### File Location
`app/modules/{module}/forms/{feature}/namafile.js`

### Referensi di Form

```php
public function getForm()
{
    return array(
        // ...
        'inlineJS' => 'namafile.js',
    );
}
```

### Aksesible Variables di JS

```javascript
$scope.model          // object semua field form
$scope.dsNama         // DataSource instance
$scope.model.id       // value field 'id'

// AngularJS services
$http.post(url, data) // AJAX POST
$localStorage         // Local storage
$timeout(fn, ms)      // Delayed execution
$rootScope            // Global scope

// Yii URL helper
Yii.app.createUrl('module/controller/action')
```

### Contoh InlineJS

```javascript
// Simpan data via AJAX
$scope.saveItem = function() {
    var url = 'admin/stock/save';
    var item = {
        'id_produk': $scope.model.id_produk,
        'id_gudang': $scope.model.id_gudang,
        'qty': $scope.model.qty,
    };

    $http.post(Yii.app.createUrl(url), { data: item }).then(function(r) {
        if (r.data.st === 1) {
            alert(r.data.msg);
            $scope.dsStockItem.query(); // refresh grid
        }
    });
};

// Modal overlay
$scope.showModal = function() {
    window.location.hash = '#my-modal-id';
};

// Import Excel via AJAX
$scope.doImport = function() {
    var fd = new FormData();
    fd.append('file', document.querySelector('input[ps-name="import_file"]').files[0]);
    fd.append('id_gudang', $scope.model.id_gudang);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', Yii.app.createUrl('admin/stock/doImport'), true);
    xhr.onload = function() {
        $scope.importResult = JSON.parse(xhr.responseText);
        $scope.$apply(); // trigger digest
    };
    xhr.send(fd);
};
```

---

## 7. POPUP / MODAL OVERLAY

**Pola**: HTML overlay + CSS class `overlay` + `#hash` navigation.

```php
// 1. Tambah overlay container (di akhir getFields)
array(
    'type' => 'Text',
    'display' => 'all-line',
    'value' => '<div id="my-modal" class="overlay">
        <div class="popup">
            <div class="header-red">
                <h2>Judul Modal</h2>
            </div>
            <a class="close" href="#">&times;</a>
            <div class="content">
                <div class="row"><div class="col-md-12">',
),

// 2. Field-field di dalam modal
array(
    'type' => 'RelationField',
    'label' => 'Produk',
    'name' => 'id_produk',
    // ... config
),
array(
    'type' => 'LinkButton',
    'label' => 'Simpan',
    'buttonType' => 'success',
    'options' => array('ng-click' => 'saveItem()'),
),

// 3. Tutup overlay container
array(
    'type' => 'Text',
    'display' => 'all-line',
    'value' => '</div></div></div></div></div>',
),
```

```javascript
// Buka modal
$scope.showModal = function() {
    window.location.hash = '#my-modal';
};
// Tutup modal otomatis via <a class="close" href="#"></a>
```

---

## 8. MENU KONFIGURASI

### Lokasi
`app/menus/{module}.php`

### Format

```php
<?php
$options = array('mode' => 'normal');

return array(
    // Menu group
    array(
        'label' => 'Master',
        'icon' => 'fa-database', // FontAwesome icon
        'url' => '#',
        'items' => array(
            array(
                'label' => 'Produk',
                'icon' => 'fa-cubes',
                'url' => '/admin/produk/index',
            ),
            array(
                'label' => 'Outlet',
                'icon' => 'fa-share-alt',
                'url' => '/admin/outlet/index',
            ),
        ),
        'state' => 'collapsed',
    ),
    // Menu standalone
    array(
        'label' => 'Dashboard',
        'icon' => 'fa-tachometer',
        'url' => '/dashboard/home/index',
    ),
);
```

---

## 9. REST API

### Endpoint
`POST {base_url}/index.php?r=Api` dengan JSON body (`?r=api` maupun `?r=Api` sama-sama valid).
Implementasi tunggal: `plansys/controllers/ApiController.php` (sudah di-patch & hardened; override lama di app sudah dihapus).

### Autentikasi 2 Lapis
1. **App token** (`token`) — shared secret, wajib sama dengan setting `restApiToken` (`app/config/settings.json`). Salah → HTTP 401.
2. **User token** (`user_token`) — diterbitkan mode `login`; payload `{user_id, exp, time}` terenkripsi AES-128-CBC + tanda tangan HMAC-SHA256 (key = setting `restApiSecretKey`). Masa berlaku **7 hari**. User `is_deleted` ditolak. Invalid/expired/di-tamper → HTTP 401.

### Modes (5)

| Mode | Fungsi | Parameter |
|------|--------|-----------|
| `login` | Verifikasi kredensial → terbitkan `user_token` (7 hari) | `params: {username, password}` |
| `function` | Panggil static method model; `params.id_user` **di-injeksi otomatis** dari user_token (nilai dari klien selalu ditimpa) | `model, function, params` |
| `RepoUpload` | Upload file ke repo (multipart) → balas token `hashed` | field form `file` + field `mode/token/user_token` (multipart) |
| `RepoDownload` | Unduh file raw ber-`hashed` | `params: {hashed}` |
| `RepoUse` | Tandai file `temp` → `committed` (cadangan bila referensi disimpan tanpa AR save) | `params: {hashed}` |

Mode lama (`find`, `edit`, `delete`, `custom`) sudah **dihapus** demi keamanan → HTTP 400. Fitur sub-query `"return"` di mode login juga sudah dihapus (breaking change).

### Rate Limit Login
Maksimal 5 percobaan gagal / 15 menit per kombinasi username+IP (disimpan via Yii cache/CFileCache — folder runtime harus writable di produksi). Melebihi → HTTP 429. Login sukses me-reset counter.

### Kontrak Respons

| HTTP | Arti |
|------|------|
| 200 | Sukses — body `{"status": ..., "data": ...}` |
| 400 | Unknown mode / Model is required / Function not found / model class tidak ada / param repo tidak valid |
| 401 | App token salah, atau user_token invalid/expired/is_deleted |
| 404 | `RepoDownload`: file tidak ditemukan |
| 429 | Rate limit login tercapai |
| 500 | Error di model function — body JSON `{"status": 500, "data": "Model error: <pesan>"}` (bukan halaman HTML) |

### File Repository (Repo API)

Semua upload file (form WEB & API mobile) disimpan terpusat: metadata di tabel **`p_repo`**, fisik di `plansys/repo/YYYY-MM-DD/{user_id}_{timestamp}_{hex}.{ext}`. Token akses = kolom **`hashed`** (64 hex, AES-128-CBC pakai key dari setting `restApiSecretKey`).

| Status `p_repo.status` | Arti |
|------------------------|------|
| `temp` | baru di-upload, belum dirujuk baris tersimpan manapun |
| `committed` | sudah dirujuk baris tersimpan → aman dari pembersihan otomatis |

**Lifecycle standar**:
1. Upload → `RepoUpload` → file `temp`, klien menyimpan `hashed`.
2. Simpan baris via AR save (`$model->save()`) yang memuat `hashed` di salah satu kolom → `ActiveRecord::doAfterSave()` men-scan atribut 64-hex → otomatis `markCommitted()`. Berlaku untuk WEB dan mode `function`.
3. Jika referensi baris disimpan **tanpa** AR save (query mentah `createCommand()`), panggil `RepoUse` setelah save agar file tidak tersweep.
4. File `temp` yang lewat umur dibersihkan command **`cleanRepo`**: `php yiic.php cleanRepo --ageHours=24` (file di atas 24 jam). File `committed` tidak pernah dihapus otomatis. Jadwalkan via cron.

**Keamanan**:
- Upload ditolak bila ekstensi/isi berbahaya (`Repo::unsafeUploadReason()` — web shell/executable).
- `RepoDownload` cukup butuh `hashed` (bersifat bearer token).
- `RepoUse` mewajibkan file = milik `user_token` yang dipakai (id_user dari klien tidak bisa dipalsukan).

**Contoh upload** (multipart/form-data, bukan JSON):

```
POST {base_url}/index.php?r=Api
token        = APP_TOKEN
user_token   = USER_TOKEN
mode         = RepoUpload
file         = @foto.jpg      (field form "file", satu file)
```

Respon: `{"status": true, "message": "File berhasil diupload", "data": "<hashed>"}`

**Contoh unduh**:

```json
{
    "token": "APP_TOKEN",
    "user_token": "USER_TOKEN",
    "mode": "RepoDownload",
    "params": { "hashed": "<hashed>" }
}
```
Balasan = file raw dengan header `Content-Type`/`Content-Disposition` (bukan JSON).

**Contoh RepoUse** (tandai terpakai setelah data disimpan via query mentah):

```json
{
    "token": "APP_TOKEN",
    "user_token": "USER_TOKEN",
    "mode": "RepoUse",
    "params": { "hashed": "<hashed>" }
}
```
Respon: `{"status": true, "message": "File telah ditandai terpakai", "data": {"hashed": "<hashed>"}}`

### Contoh Request

```json
{
    "token": "APP_TOKEN",
    "user_token": "USER_TOKEN",
    "mode": "function",
    "model": "MPuskesmas",
    "function": "getPuskesmasInfo",
    "params": {
        "id": 1
    }
}
```

Login: `{"token": "APP_TOKEN", "mode": "login", "params": {"username": "...", "password": "..."}}` → ambil `data.user_token`.

**Catatan keamanan**:
- Semua static method model bisa dipanggil via mode `function` — hanya ekspos method yang memang dimaksudkan untuk API (beri nama jelas / whitelist internal bila perlu).
- Parameter wajib yang lupa dikirim tidak meledak jadi HTML; tetap JSON 500 dengan pesan `Model error: Undefined array key "..."`.

---

## 10. WORKFLOW MEMBUAT FITUR BARU

### Contoh: Membuat CRUD "Gudang"

#### Step 1: Buat Model
`app/models/MGudang.php`

#### Step 2: Buat Migration (jalankan ke DB)
`app/migrations/m260730_000001_create_m_gudang.php`
Lalu jalankan: `php yiic migrate`

#### Step 3: Buat Form Index
`app/modules/admin/forms/mGudang/AdminMGudangIndex.php`
- extends MGudang
- DataSource: SELECT dari m_gudang
- GridView: kolom tabel + edit/del buttons

#### Step 4: Buat Form Edit
`app/modules/admin/forms/mGudang/AdminMGudangForm.php`
- extends MGudang
- Fields: Nama (TextField), Is Active (ToggleSwitch)

#### Step 5: Buat Controller
`app/modules/admin/controllers/MGudangController.php`
- actionIndex() → renderForm('AdminMGudangIndex')
- actionEdit($id) → renderForm('AdminMGudangForm', $model)
- actionDelete($id) → delete + redirect

#### Step 6: Tambah Menu
`app/menus/admin.php`
- Tambah array di group 'Master'

---

## 11. COMMON PATTERNS

### Pattern: Index + Filter + Pagination

```php
// Index form
public function getFields()
{
    return array(
        array('type' => 'ActionBar', 'linkBar' => array(
            array('type' => 'LinkButton', 'label' => 'Tambah',
                'buttonType' => 'success', 'icon' => 'plus',
                'options' => array('href' => 'url:/admin/xxx/edit')),
        ), 'title' => 'Daftar Xxx'),

        // Filter
        array('type' => 'DataFilter', 'name' => 'df1', 'datasource' => 'ds1',
            'filters' => array(
                array('filterType' => 'string', 'name' => 'nama', 'label' => 'Nama'),
            )),

        // Data source
        array('type' => 'DataSource', 'name' => 'ds1',
            'sql' => "SELECT * FROM table WHERE 1=1 {AND nama LIKE :nama}",
            'params' => array(':nama' => 'js: model.nama'),
            'execMode' => 'after'),

        // Grid
        array('type' => 'GridView', 'name' => 'grid1', 'datasource' => 'ds1',
            'columns' => array(
                // ... columns
            )),
    );
}
```

### Pattern: Form Edit dengan Relation

```php
public function getFields()
{
    return array(
        array('type' => 'ActionBar', 'linkBar' => array(
            array('type' => 'LinkButton', 'label' => 'Kembali',
                'icon' => 'chevron-left',
                'options' => array('href' => 'url:/admin/xxx/index')),
            array('type' => 'LinkButton', 'label' => 'Simpan',
                'buttonType' => 'success', 'icon' => 'check',
                'options' => array('ng-click' => 'form.submit(this)')),
        ), 'title' => 'Detail Xxx'),

        array('type' => 'HiddenField', 'name' => 'id'),

        array('type' => 'ColumnField', 'w1' => '50%', 'w2' => '50%',
            'column1' => array(
                array('type' => 'TextField', 'label' => 'Nama', 'name' => 'nama'),
            ),
            'column2' => array(
                array('type' => 'ToggleSwitch', 'label' => 'Is Active', 'name' => 'is_active',
                    'onLabel' => 'YES', 'offLabel' => 'NO'),
            )),
    );
}
```

### Pattern: Sub-Grid (Modal Overlay)

```php
// ... di form yang sama
// 1. Tambah button "Kelola"
// 2. DataSource untuk sub-grid
// 3. GridView untuk sub-grid
// 4. Overlay modal dengan field-field
// 5. InlineJS untuk saveItem()
```

### Pattern: Import Excel

```php
// 1. Form: DropDownList (gudang) + UploadFileNative + LinkButton
// 2. InlineJS: FormData + XHR → POST
// 3. Controller actionDoImport: box/spout parser → insert ke DB
// 4. Return JSON {st:1, msg:'Berhasil: X data'}
```

### Pattern: Delete dengan Konfirmasi

```php
array(
    'type' => 'LinkButton',
    'label' => 'Hapus',
    'buttonType' => 'danger',
    'icon' => 'trash',
    'options' => array(
        'ng-if' => '!isNewRecord',
        'href' => 'url:/admin/xxx/delete?id={model.id}',
        'confirm' => 'Apakah Anda Yakin?',
    ),
),
```

### Pattern: Currency Display di GridView

```php
array(
    'columnType' => 'string',
    'name' => 'harga',
    'label' => 'Harga',
    'cellMode' => 'custom',
    'html' => '<td ng-class="rowClass(row, \'harga\', \'string\')">
        Rp. {{row[\'harga\'] | currency : \'\' : 0}}</td>',
),
```

### Pattern: Badge/Status di GridView

```php
array(
    'columnType' => 'string',
    'name' => 'status',
    'label' => 'Status',
    'cellMode' => 'custom',
    'html' => '<td style="text-align:center">
        <span class="label label-success" ng-if="row.status == \'PAID\'">PAID</span>
        <span class="label label-warning" ng-if="row.status == \'PENDING\'">PENDING</span>
    </td>',
),
```

---

## 12. COMMON MISTAKES & FIXES

### 1. Error: `p.indexOf is not a function`
**Penyebab**: Param value di DataSource bukan string.
**Fix**: Cast ke string: `':param' => (string)Yii::app()->user->id`

### 2. Error: `Invalid parameter number`
**Penyebab**: Params di SQL tidak dibungkus `{}` padahal bisa null.
**Fix**: Gunakan `{AND col = :param}` + `WHERE 1=1`

### 3. Error: `Undefined array key "name"`
**Penyebab**: GridView column edit/del button tanpa `'name' => ''`
**Fix**: Tambah `'name' => '', 'label' => ''` di kolom button

### 4. Error: `Properti X tidak didefinisikan`
**Penyebab**: Field di form tidak ada di tabel database.
**Fix**: Hapus field yang tidak ada di model/tabel.

### 5. Tidak bisa upload file via form
**Penyebab**: Nested form tag (FormBuilder sudah render `<form>`)
**Fix**: Gunakan `UploadFileNative` + AJAX (FormData + XHR), jangan pakai `<form>` manual

### 6. Error: `plansys is not defined`
**Penyebab**: Bug lama — filter `dateFormat` di `static/js/index.app.js` membaca global yang tak pernah didefinisikan.
**Status**: SUDAH DIPATCH (global di-guard di baris 3 file tersebut). Jika muncul setelah deploy → pasti cache JS browser/Cloudflare (`max-age=14400`): hard refresh / purge cache.

### 7. API function balik 500 `Model error: Undefined array key "..."`
**Penyebab**: Parameter wajib tidak dikirim klien.
**Fix**: Kirim params lengkap. Error model sudah dikonversi jadi JSON rapi oleh ApiController (set_error_handler lokal), bukan halaman HTML.

---

## 13. DATABASE CONVENTIONS

### PostgreSQL Specific
- `SERIAL PRIMARY KEY` untuk auto increment
- `VARCHAR(256)` untuk text pendek
- `TEXT` untuk text panjang
- `NUMERIC(18,2)` untuk decimal
- `TIMESTAMP DEFAULT NOW()` untuk created_at
- `DATE` untuk tanggal tanpa waktu
- `CHECK constraint` untuk validasi di DB level

### Migration Pattern
```php
class m260730_000001_create_table_xxx extends Migration
{
    public function up()
    {
        $this->createTable('xxx', array(
            'id' => 'pk',
            'nama' => 'string NOT NULL',
            'is_active' => 'string DEFAULT \'YES\'',
            'created_at' => 'datetime NOT NULL',
            'created_by' => 'integer NOT NULL',
        ));
    }

    public function down()
    {
        $this->dropTable('xxx');
    }
}
```

---

## 14. REFERENCE: SEMUA COMPONENT TYPES

| Type | Fungsi | Category |
|------|--------|----------|
| `ActionBar` | Header bar + buttons | Layout |
| `AceEditor` | Code editor | User Interface |
| `ChartBar/Line/Pie/Area` | Grafik | Charts |
| `CheckboxList` | Multiple checkbox | User Interface |
| `ColorPicker` | Color input | User Interface |
| `ColumnField` | Multi-column layout | Layout |
| `DataFilter` | Filter panel | Data & Tables |
| `DataGrid` | (deprecated) | Data & Tables |
| `DataTable` | Simple table | Data & Tables |
| `DataSource` | Query engine | Data & Tables |
| `DateTimePicker` | Date/time input | User Interface |
| `DropDownList` | Static dropdown | User Interface |
| `GridView` | Full data grid | Data & Tables |
| `HiddenField` | Hidden input | User Interface |
| `IconPicker` | Icon picker | User Interface |
| `KeyValueGrid` | Key-value editor | Data & Tables |
| `LabelField` | Read-only label | User Interface |
| `LinkButton` | Button/link | User Interface |
| `ListView` | Repeatable sub-form | Data & Tables |
| `ModalDialog` | Bootstrap modal | Layout |
| `NumberField` | Number input | User Interface |
| `PopupWindow` | Popup | Layout |
| `RadioButtonList` | Radio buttons | User Interface |
| `RelationField` | Relational dropdown | Data & Tables |
| `RepoBrowser` | File browser | User Interface |
| `SectionHeader` | Section divider | Layout |
| `SqlCriteria` | SQL criteria builder | Data & Tables |
| `SubForm` | Nested form | Layout |
| `SubmitButton` | Form submit button | User Interface |
| `TagField` | Tag input | User Interface |
| `Text` | Raw HTML content | User Interface |
| `TextArea` | Multi-line text | User Interface |
| `TextField` | Text input | User Interface |
| `ToggleSwitch` | ON/OFF toggle | User Interface |
| `TreeView` | Hierarchical tree | Data & Tables |
| `UploadFile` | Server-managed upload | User Interface |
| `UploadFileNative` | Browser-native upload | User Interface |

---

## 15. RIWAYAT PATCH FRAMEWORK (AGUSTUS 2026)

Patch lokal pada repo plansys (semuanya sudah di-commit; aman dari `git pull` berikutnya):

| Patch | Lokasi | Isi |
|-------|--------|-----|
| Hardening REST API | `controllers/ApiController.php` | Hanya mode `login`+`function`, token enkripsi AES+HMAC 7 hari, rate limit login, tolak `is_deleted`, injeksi `id_user`, error JSON via set_error_handler lokal |
| Redaksi kredensial DB saat dump | `framework/db/CDbConnection.php` | `username`/`password` tidak disimpan lagi sebagai properti publik; dipindah ke WeakMap statis + magic getter/setter. `var_dump`/`print_r`/`var_export`/`json_encode` pada objek db/command tidak lagi membocorkan kredensial |
| Repo API upload/download/commit | `controllers/ApiController.php`, `models/Repo.php` | Mode baru `RepoUpload` (multipart→`hashed`), `RepoDownload` (kirim file raw), `RepoUse` (commit eksplisit + cek kepemilikan file milik user_token) |
| Status `temp`/`committed` di repo | `models/Repo.php` | `STATUS_TEMP`/`STATUS_COMMITTED`, `markCommitted()`, `markCommittedByHashed()`, `cleanupTemporary()`; kolom `p_repo.status` + default `committed` |
| Auto-commit referensi file | `components/models/ActiveRecord.php` | `doAfterSave()` men-scan atribut 64-hex → `markCommitted()` (guard: file repo tidak menandai dirinya sendiri); blok `__repoDeleted` menghapus file lama saat ganti/hapus di form |
| Command `cleanRepo` | `commands/CleanRepoCommand.php` | Bersihkan file `temp` lewat `--ageHours` (default 24 jam) via `php yiic.php cleanRepo`; file `committed` tidak disentuh |
| Migration `p_repo` | `migrations/m260828_073722_p_repo.php` | Tabel `p_repo` dibuat otomatis saat install baru (skema = prod); idempoten — dilewati jika tabel sudah ada |
| Fix cache pageSetting | `components/ui/FormBuilder.js.php` | `$scope.pageInfo.pathinfo` kini diisi `formClassPath` → state filter/grid per-form, tidak lagi numpuk di key `"undefined"` global |
| Fix persistensi DataFilter | `components/ui/FormFields/DataFilter/data-filter.js` | Key storage pakai `storageKey` (= formClassPath), save merge tanpa hapus sibling, `resetPageSetting` lokal agar tombol Reset bekerja |
| Fallback nama kosong | `data-filter.js`, `GridView/grid.v2.js`, `DataGrid/js/data-grid.js` | Komponen tanpa `name` dapat nama deterministik `filter_/grid_<datasource>` |
| Guard global plansys | `static/js/index.app.js` | `var plansys = typeof plansys == "undefined" ? {} : plansys;` — menutup error `plansys is not defined` pada filter dateFormat |
| Bersih-bersih | `FormBuilder.php`, `ErrorHandler.php.bckp.hpd` | Dead code `formatGlob_test()` (berisi print_r+die) dan file backup dihapus |
| OAuth mati dihapus | `config/main.php`, `DevSettingApp.php`, `Setting.php`, `extensions/{eauth,eoauth,lightopenid}` | Fitur Google OAuth bawaan (pakai Google+ API yang sudah dimatikan Google sejak 2019 & tak pernah terhubung ke alur login) dibuang. Kebutuhan Google login ke depan: bangun modern (Google Identity Services) di `app/` |

Patch yang datang dari upstream pull (bukan buatan kita, tapi penting diketahui):
- `WebRequest::getTokenFromInput` — guard non-array + `?? null` (menutup TypeError CSRF)
- `ErrorHandler` — log error dipindah ke `app/config/errorlogs` (pastikan folder writable)

---

*Terakhir diperbarui: 2026-08-31*
