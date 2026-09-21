<?php

/**
 * Repo — penyimpanan metadata file upload (tabel p_repo)
 *
 * Fitur dasar Plansys untuk mencatat file yang disimpan di repository.
 * File fisik tersimpan di folder repo (Setting::getRepoPath()), sedangkan
 * baris ini menyimpan metadata-nya: nama asli, lokasi, ekstensi, nama
 * hasil hash, MIME, pemilik (user_id), dan waktu dibuat.
 */
class Repo extends ActiveRecord {

    const NAME_CIPHER = 'AES-128-CBC';

    const STATUS_TEMP       = 'temp';       // baru diupload, belum direferensikan baris apa pun
    const STATUS_COMMITTED  = 'committed';  // direferensikan oleh baris (form/API) yang tersimpan

    public function rules() {
        return array(
            array('origin_file_name, location, extension, hashed, mime, user_id', 'required'),
            array('user_id', 'numerical', 'integerOnly' => true),
            array('origin_file_name, location, extension, mime', 'length', 'max' => 256),
            array('size', 'numerical'),
            array('src', 'length', 'max' => 32),
            array('status', 'length', 'max' => 32),
        );
    }

    public function relations() {
        return array(
            'user' => array(self::BELONGS_TO, 'User', 'user_id'),
        );
    }

    public function beforeSave() {
        if ($this->isNewRecord && empty($this->created_date)) {
            $this->created_date = date('Y-m-d H:i:s');
        }
        return parent::beforeSave();
    }

    /**
     * Enkripsi nama file fisik menjadi token hex (tanpa ekstensi).
     * Deterministik terhadap key setting app.restApiSecretKey, sehingga
     * nilai yang sama selalu menghasilkan token yang sama (bisa dipakai
     * sebagai kunci lookup di kolom hashed).
     * @param string $name nama file fisik (tanpa ekstensi)
     * @return string token hex terenkripsi
     */
    public static function encryptName($name) {
        $key = Setting::get('app.restApiSecretKey');
        if (!$key) {
            return $name;
        }
        $iv = substr(hash('sha256', $key), 0, openssl_cipher_iv_length(self::NAME_CIPHER));
        $enc = openssl_encrypt($name, self::NAME_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return bin2hex($enc);
    }

    /**
     * Dekripsi token hex (kolom hashed) kembali menjadi nama file fisik.
     * @param string $hashed token hasil encryptName()
     * @return string|false nama file asli bila valid, false bila gagal
     */
    public static function decryptName($hashed) {
        $key = Setting::get('app.restApiSecretKey');
        if (!$key || !is_string($hashed) || $hashed == '') {
            return false;
        }
        $iv = substr(hash('sha256', $key), 0, openssl_cipher_iv_length(self::NAME_CIPHER));
        $raw = @hex2bin($hashed);
        if ($raw === false) {
            return false;
        }
        $dec = openssl_decrypt($raw, self::NAME_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $dec === false ? false : $dec;
    }

    /**
     * Nama file fisik (tanpa ekstensi) hasil dekripsi dari kolom hashed.
     * @return string|false
     */
    public function getName() {
        return self::decryptName($this->hashed);
    }

    /**
     * Simpan record file ke p_repo sekaligus pindahkan file fisik
     * ke folder repo. Kolom hashed berisi nama file fisik yang di-enkripsi
     * (tanpa ekstensi), sehingga nama fisik tidak pernah terekspos mentah
     * dan bisa didapat kembali via Repo::decryptName().
     *
     * @param string $tmpPath  path file sementara hasil upload
     * @param string $originFileName nama file asli
     * @param string $subDir   sub-folder di dalam repo (relatif), contoh "pasien/5"
     * @param int    $userId   id user pemilik upload
     * @param string $uniqueName custom nama file fisik tanpa ekstensi (misal dari API
     *                            upload "4_1787890539_a1b2c3d4"), bila kosong dibuat
     *                            otomatis md5+uniqid
     * @return Repo|false     instance model yang tersimpan, atau false bila gagal
     */
    public static function storeFile($tmpPath, $originFileName, $subDir = '', $userId = null, $uniqueName = '', $src = '') {
        if (empty($userId)) {
            $userId = @Yii::app()->user->id;
        }
        if (empty($tmpPath) || !is_file($tmpPath) || empty($originFileName) || empty($userId)) {
            return false;
        }

        $repoRoot = Setting::getRepoPath();
        if (!is_dir($repoRoot)) {
            @mkdir($repoRoot, 0755, true);
        }

        $subDir = trim(str_replace('\\', '/', $subDir), '/');
        if ($subDir != '') {
            $subDir = preg_replace('#[^\w/\-\.]#', '', $subDir);
            $targetDir = $repoRoot . DIRECTORY_SEPARATOR . $subDir;
        } else {
            $targetDir = $repoRoot;
        }
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $originFileName = basename($originFileName);
        $extension = strtolower(pathinfo($originFileName, PATHINFO_EXTENSION));
        $fInfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $fInfo ? finfo_file($fInfo, $tmpPath) : 'application/octet-stream';
        if ($fInfo) {
            finfo_close($fInfo);
        }

        // nama file fisik (tanpa ekstensi), tersedia sebagai ekspos publik
        $name = $uniqueName != '' ? $uniqueName : md5(uniqid(mt_rand(), true));
        $stored = $extension != '' ? $name . '.' . $extension : $name;
        $location = ($subDir != '' ? $subDir . '/' : '') . $stored;

        if (!move_uploaded_file($tmpPath, $targetDir . DIRECTORY_SEPARATOR . $stored)) {
            if (!copy($tmpPath, $targetDir . DIRECTORY_SEPARATOR . $stored)) {
                return false;
            }
        }
        @chmod($targetDir . DIRECTORY_SEPARATOR . $stored, 0644);

        $model = new Repo;
        $model->origin_file_name = $originFileName;
        $model->location = $location;
        $model->extension = $extension;
        $model->hashed = self::encryptName($name);
        $model->mime = $mime;
        $model->size = round(filesize($targetDir . DIRECTORY_SEPARATOR . $stored) / 1024, 2);
        $model->src = $src != '' ? $src : 'WEB';
        $model->user_id = $userId;
        $model->status = self::STATUS_TEMP;

        if (!$model->save()) {
            @unlink($targetDir . DIRECTORY_SEPARATOR . $stored);
            return false;
        }

        return $model;
    }

    /**
     * Cari record Repo dari token terenkripsi (kolom hashed).
     * @param string $hashed hasil encryptName()
     * @return Repo|null
     */
    public static function findByHashed($hashed) {
        if (!is_string($hashed) || $hashed == '') {
            return null;
        }
        return Repo::model()->findByAttributes(['hashed' => $hashed]);
    }

    /**
     * Tandai record sebagai committed (direferensikan oleh baris tersimpan).
     * File berstatus temp yang sudah lewat umurnya akan dibersihkan otomatis,
     * sehingga file upload yang form-nya tidak pernah di-submit tidak menumpuk.
     * @return bool
     */
    public function markCommitted() {
        if ($this->status == self::STATUS_COMMITTED) {
            return true;
        }
        $this->status = self::STATUS_COMMITTED;
        return $this->save();
    }

    /**
     * Tandai file repo sebagai committed lewat token hashed.
     * Dipakai saat referensi disimpan cadangan tanpa lewat AR save (mis.
     * query mentah CDbCommand), supaya file tidak ikut tersweep cleanRepo.
     * @param string   $hashed token hasil encryptName()
     * @param int|null $userId wajib isi untuk validasi kepemilikan file
     * @return Repo|null record yang berhasil ditandai, null jika tidak ada
     */
    public static function markCommittedByHashed($hashed, $userId = null) {
        $repo = self::findByHashed($hashed);
        if (!$repo) {
            return null;
        }
        if ($userId !== null && (int)$repo->user_id != (int)$userId) {
            return null;
        }
        if (!$repo->markCommitted()) {
            return null;
        }
        return $repo;
    }

    /**
     * Bersihkan file repo sementara (status temp) yang belum direferensikan
     * oleh baris apa pun selama lebih dari $maxAgeSeconds.
     * Dipanggil dari command console (cleanRepo) secara terjadwal.
     *
     * @param int $maxAgeSeconds umur maksimal file temp sebelum dihapus
     * @return int jumlah record yang dihapus
     */
    public static function cleanupTemporary($maxAgeSeconds = 86400) {
        $cut = date('Y-m-d H:i:s', time() - $maxAgeSeconds);
        $rows = Repo::model()->findAllByAttributes(
            ['status' => self::STATUS_TEMP],
            'created_date < :cut',
            [':cut' => $cut]
        );
        $n = 0;
        foreach ($rows as $r) {
            if ($r->deleteFile()) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Daftar ekstensi yang memuat kode yang dilarang diupload.
     * Dipakai untuk mencegah web shell / executable menumpang di repo.
     * @return array
     */
    public static function forbiddenExtensions() {
        return [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phar',
            'asp', 'aspx', 'ascx', 'jsp', 'jspx', 'cfm', 'cgi', 'pl', 'py', 'rb',
            'sh', 'bash', 'bashrc', 'zsh', 'ksh', 'csh', 'fish',
            'exe', 'com', 'bat', 'cmd', 'scr', 'ps1', 'jar', 'war', 'apk',
            'so', 'dll', 'dylib', 'bin', 'msi', 'msc', 'js', 'vbs', 'vbe',
        ];
    }

    /**
     * Cek nama file & isi file sebelum diupload, untuk menolak file yang
     * memuat "kodingan" (web shell, script executable, dll).
     *
     * @param string $originFileName nama file asli dari klien
     * @param string $tmpPath        path file sementara hasil upload
     * @return string pesan alasan penolakan; string kosong = file aman
     */
    public static function unsafeUploadReason($originFileName, $tmpPath) {
        $extension = strtolower(pathinfo($originFileName, PATHINFO_EXTENSION));

        // 1) ekstensi berbahaya (script/executable) ditolak mentah.
        if (in_array($extension, self::forbiddenExtensions())) {
            return 'Tipe file .' . $extension . ' tidak diizinkan';
        }

        // 2) pemeriksaan isi: baca blok awal file untuk deteksi penanda kode.
        if (is_file($tmpPath)) {
            $head = file_get_contents($tmpPath, false, null, 0, 65536);
            if ($head !== false) {
                // pola untuk file teks (source code / script). File biner
                // (gambar/arsip) penuh byte acak yang tak sengaja bisa
                // mengandung "<%", "<?", dsb — itu bukan kodingan.
                $patterns = [
                    '~<\?php~i',                      // tag PHP
                    '~<\?xml[A-Z\s]~i',               // XML scripting
                    '~<% *~',                         // ASP/JSP tag
                    '~<!DOCTYPE\s+SCRIPT~i',
                    '~^(\x23!)/(bin|usr/bin/env)~m',
                    '~\b(base64_decode|eval|system|passthru|shell_exec|exec|assert)\s*\(~i',
                ];
                if (strpos($head, "\x00") !== false) {
                    // file biner: cek hanya tag PHP (mencegah polyglot web
                    // shell yang disisipkan di dalam gambar/arsip).
                    $patterns = ['~<\?php~i'];
                }
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $head)) {
                        return 'File terdeteksi memuat kode yang tidak diizinkan';
                    }
                }
            }
        }

        return '';
    }

    /**
     * Hapus record metadata beserta file fisiknya.
     * @return bool true bila berhasil
     */
    public function deleteFile() {
        $repoRoot = Setting::getRepoPath();
        $path = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->location);
        if (is_file($path)) {
            @unlink($path);
        }
        return $this->delete();
    }

    /**
     * @return string path absolut file fisik di repository
     */
    public function getAbsolutePath() {
        return Setting::getRepoPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->location);
    }

    public function tableName() {
        return 'p_repo';
    }

}