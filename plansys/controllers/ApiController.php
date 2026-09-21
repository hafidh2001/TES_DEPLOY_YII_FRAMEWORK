<?php
Yii::import("application.components.Authorization");

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
// Yii::import('app.controllers.*');

/**
 * PATCH 2026-08:
 * - Mode API hanya tersisa 'login', 'function', 'RepoUpload',
 *   'RepoDownload', dan 'RepoUse'. Akses data sepenuhnya dikendalikan
 *   developer lewat static method di model (otorisasi per-fungsi ditulis
 *   di model, bukan di controller).
 * - Login di-rate-limit: maksimal 5 percobaan per 15 menit per kombinasi
 *   username + IP (disimpan di application cache), termasuk percobaan
 *   dengan username yang tidak terdaftar.
 * - user_token berlaku 7 hari: payload terenkripsi {user_id, exp, time}
 *   (AES-128-CBC + HMAC-SHA256, key dari setting app.restApiSecretKey).
 * - User dengan is_deleted ditolak, baik saat login maupun saat authorize.
 * - Pada mode function, identitas pemilik token selalu disisipkan ke
 *   $params['id_user'] dan tidak bisa dipalsukan oleh klien.
 * - Error handler mode function menghormati @ / error_reporting dan
 *   melewatkan E_DEPRECATED (noise PHP 8 pada framework lawas).
 */
class ApiController extends CController {

    const ENCRYPTION_METHOD = 'AES-128-CBC';
    const TOKEN_TTL_DAYS    = 7;
    const LOGIN_MAX_ATTEMPT = 5;
    const LOGIN_WINDOW_SEC  = 900; // 15 menit

    protected $token_list = [];

    public function queryDB($query, $param){
        $sql = Yii::app()->db->createCommand($query);
        if($param=='all'):
            return json_encode($sql->queryAll());
        elseif($param=='row'):
            return json_encode($sql->queryRow());
        endif;
    }

    public function jsonDecoder($rest){
        return json_decode($rest, true);
    }

    public function handleRestJson($id = null){
        $rest_json = file_get_contents("php://input");
        $post = $this->jsonDecoder($rest_json);//json_decode($rest_json, true);

        echo $post['col'];
    }

    public function actionIndex(){
        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            die();
        }

        // Bedakan request JSON (mode function/login) vs multipart/form-data (mode RepoUpload).
        // Pada multipart, parameter dikirim sebagai field form dan file lewat $_FILES.
        $isMultipart = (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false)
                    || !empty($_FILES);

        if ($isMultipart) {
            $api_params = array_merge($_POST, $_FILES);
        } else {
            $api_params = json_decode(file_get_contents("php://input"), true);
        }
        $mode       = @$api_params['mode'];

        if(Setting::get('app.restApi') != 'ON'){
            echo 'Rest API is not Enabled';
            die();
        }

        // layer 1: app token harus cocok dengan setting app.restApiToken
        if(@$api_params['token'] !== Setting::get('app.restApiToken')){
            $this->response(401, 'Unauthorized');
        }

        if($mode == 'login'){
            if ($isMultipart) {
                $this->response(400, 'Login harus menggunakan JSON, bukan multipart');
            }
            echo json_encode($this->login(@$api_params['params']));
            die();
        }

        // layer 2: user_token harus token terenkripsi valid yang memuat
        // user_id terdaftar, belum expired, dan akunnya tidak dihapus
        $authUser = $this->authorizeUserToken(@$api_params['user_token']);
        if(!$authUser instanceof User){
            $this->response(401, 'Unauthorized');
        }

        if($mode == 'RepoUpload'){
            echo json_encode($this->repoUpload($authUser));
            die();
        }

        if($mode == 'RepoDownload'){
            $this->repoDownload($authUser, @$api_params['params']);
            die();
        }

        if($mode == 'RepoUse'){
            echo json_encode($this->repoUse($authUser, @$api_params['params']));
            die();
        }

        if($mode == 'function'){ //Panggil Function static di MODEL
            $model = @$api_params['model'];
            if(!$model || !class_exists($model)){
                $this->response(400, 'Model is required');
            }
            $func = @$api_params['function'];
            if(!$func || !method_exists($model, $func)){
                $this->response(400, 'Function not found');
            }
            // sisipkan identitas user dari token ke $params function;
            // nilai id_user dari klien selalu ditimpa agar tidak bisa dipalsukan
            $fnParams = @$api_params['params'];
            if(!is_array($fnParams)){
                $fnParams = [];
            }
            $fnParams['id_user'] = $authUser->id;
            // warning/notice/error di dalam function model diubah menjadi
            // exception lokal supaya bisa dikirim sebagai JSON rapi,
            // bukan halaman error HTML milik framework.
            // Handler tetap menghormati @ (suppression) dan E_DEPRECATED:
            // framework lawas sengaja memakai @ di banyak titik dan masih
            // memancarkan deprecation PHP 8 — keduanya bukan error nyata,
            // tidak boleh mengubah response menjadi 500.
            set_error_handler(function($severity, $message, $file, $line){
                if(!(error_reporting() & $severity) || $severity == E_DEPRECATED){
                    return true;
                }
                throw new ErrorException($message, 0, $severity, $file, $line);
            });
            try {
                $res = $model::$func($fnParams);
                restore_error_handler();
                echo json_encode($res);
            } catch(Throwable $e){
                $this->response(500, 'Model error: ' . $e->getMessage());
            }
        } else {
            $this->response(400, 'Unknown mode');
        }
    }

    /**
     * Mode RepoUpload: terima file multipart, simpan ke repo dengan
     * folder per tanggal YYYY-MM-DD, rename ke nama unik berisi
     * user_id + timestamp + random hex, catat metadata ke p_repo.
     * @param User $authUser user terautentikasi dari user_token
     * @return array response sukses/gagal
     */
    private function repoUpload($authUser){
        if(!isset($_FILES['file'])){
            return ['status' => false, 'message' => 'Field file tidak ditemukan'];
        }

        $file = $_FILES['file'];
        if(is_array($file['name'])){
            return ['status' => false, 'message' => 'Hanya satu file per request'];
        }
        if($file['error'] !== UPLOAD_ERR_OK){
            return ['status' => false, 'message' => 'Upload gagal (error code ' . $file['error'] . ')'];
        }
        if(!is_uploaded_file($file['tmp_name'])){
            return ['status' => false, 'message' => 'File tidak valid'];
        }

        $origin = basename($file['name']);
        $userId = $authUser->id;
        $timestamp = time();
        $hex = bin2hex(random_bytes(8));
        $uniqueName = $userId . '_' . $timestamp . '_' . $hex;

        // pengaman: tolak file yang memuat kode / ekstensi berbahaya
        $unsafe = Repo::unsafeUploadReason($origin, $file['tmp_name']);
        if ($unsafe !== '') {
            return ['status' => false, 'message' => $unsafe];
        }

        $subDir = date('Y-m-d', $timestamp);

        $model = Repo::storeFile($file['tmp_name'], $origin, $subDir, $userId, $uniqueName, 'API');
        if(!$model){
            return ['status' => false, 'message' => 'Gagal menyimpan file'];
        }

        return [
            'status'  => true,
            'message' => 'File berhasil diupload',
            'data'    => $model->hashed,
        ];
    }

    /**
     * Mode RepoDownload: kirim file yang sebelumnya di-upload (RepoUpload).
     * Params wajib: hashed (token yang dikembalikan saat upload).
     * File dikirim mentah (raw) dengan header MIME, bukan JSON.
     * @param User $authUser user terautentikasi
     * @param array $params parameter dari request
     */
    private function repoDownload($authUser, $params){
        $hashed = @$params['hashed'];
        if(!is_string($hashed) || $hashed == ''){
            $this->response(400, 'Field hashed wajib diisi');
        }

        $repo = Repo::findByHashed($hashed);
        if(!$repo){
            $this->response(404, 'File tidak ditemukan');
        }

        $path = $repo->getAbsolutePath();
        if(!is_file($path)){
            $this->response(404, 'File tidak ditemukan');
        }

        header('Content-Type: ' . ($repo->mime ? $repo->mime : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $repo->origin_file_name . '"');
        readfile($path);
    }

    /**
     * Mode RepoUse: tandai file yang sudah diupload sebagai committed,
     * sehingga tidak ikut dibersihkan cleanRepo (file temp lewat umur).
     * Dipakai bila referensi ke file disimpan tanpa lewat AR save (mis.
     * query mentah), atau sebagai pengaman ganda setelah save selesai.
     * Params wajib: hashed (token dari RepoUpload). File harus milik user
     * yang sedang login (id_user diambil dari user_token, tak bisa dipalsukan).
     * @param User $authUser user terautentikasi
     * @param array $params parameter dari request
     */
    private function repoUse($authUser, $params){
        $hashed = @$params['hashed'];
        if(!is_string($hashed) || $hashed == ''){
            return ['status' => false, 'message' => 'Field hashed wajib diisi'];
        }

        $repo = Repo::markCommittedByHashed($hashed, $authUser->id);
        if(!$repo){
            return ['status' => false, 'message' => 'File tidak ditemukan atau bukan milik Anda'];
        }

        return [
            'status'  => true,
            'message' => 'File telah ditandai terpakai',
            'data'    => ['hashed' => $repo->hashed],
        ];
    }

    private function getCache(){
        try {
            return Yii::app()->cache;
        } catch(Exception $e){
            return null;
        }
    }

    private function loginRateKey($username){
        return 'apiLoginRL_' . md5(strtolower(trim((string)$username)) . '|' . @$_SERVER['REMOTE_ADDR']);
    }

    /**
     * Hitung setiap percobaan login; tolak (429) bila sudah melewati batas.
     */
    private function throttleLogin($username){
        $cache = $this->getCache();
        if(!$cache){
            return;
        }
        $key     = $this->loginRateKey($username);
        $attempt = $cache->get($key);
        if($attempt === false){
            $cache->set($key, 1, self::LOGIN_WINDOW_SEC);
            return;
        }
        if($attempt >= self::LOGIN_MAX_ATTEMPT){
            $this->response(429, 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.');
        }
        $cache->set($key, $attempt + 1, self::LOGIN_WINDOW_SEC);
    }

    private function clearLoginThrottle($username){
        $cache = $this->getCache();
        if($cache){
            $cache->delete($this->loginRateKey($username));
        }
    }

    private function login($post){
        // catat percobaan lebih dulu, sebelum verifikasi apa pun
        $this->throttleLogin(@$post['username']);

        $Muser = User::model()->findByAttributes([
            "username" => @$post["username"]
        ]);

        if (!isset($Muser)) {
            $result = ["status" => false, "message" => "Pengguna belum terdaftar."];
        } else if (!empty($Muser->is_deleted)) {
            $result = ["status" => false, "message" => "Pengguna tidak aktif."];
        } else if (!password_verify(@$post["password"], $Muser->password)) {
            $result = ["status" => false, "message" => "Kata sandi salah."];
        } else {
            // sukses: hapus penghitur percobaan untuk username ini
            $this->clearLoginThrottle(@$post["username"]);

            $user = (array)$Muser->attributes;

            unset($user["password"]);
            unset($user["username"]);

            $exp  = strtotime(date("Y-m-d", strtotime(date("Y-m-d"))) . " + " . self::TOKEN_TTL_DAYS . " days");
            $time = date('Y-m-d H:i:s');

            // user_token terenkripsi berisi user_id + exp, bukan nilai kolom DB
            $data = [
                'user'       => $user,
                'user_token' => $this->encryptPayload([
                    'user_id' => $Muser->id,
                    'exp'     => $exp,
                    'time'    => $time,
                ]),
                'exp'        => $exp,
                'time'       => $time,
            ];

            // jwt opsional untuk kompatibilitas klien lama
            $a           = new Authorization();
            $data['jwt'] = $a->generateToken($data);
            $result = ["status" => true, "message" => "Berhasil masuk.", "data" => $data];
        }
        return $result;
    }

    /**
     * Verifikasi user_token terenkripsi: dekripsi dengan key dari setting
     * app.restApiSecretKey, pastikan belum expired, user_id benar-benar
     * terdaftar (lookup by primary key) dan akunnya tidak dihapus.
     * @return User|null record user jika valid, null selain itu
     */
    private function authorizeUserToken($token){
        if(!is_string($token) || $token == ''){
            return null;
        }

        $payload = $this->decryptPayload($token);
        if($payload === false || !isset($payload['user_id'], $payload['exp'])){
            return null;
        }

        if(time() > $payload['exp']){
            return null;
        }

        $user = User::model()->findByPk($payload['user_id']);
        if(!$user || !empty($user->is_deleted)){
            return null;
        }
        return $user;
    }

    private function getKey(){
        $key = Setting::get('app.restApiSecretKey');
        if(!$key){
            throw new Exception("Setting app.restApiSecretKey belum diset");
        }
        return $key;
    }

    private function encryptPayload($data){
        $key  = $this->getKey();
        $iv   = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
        $enc  = openssl_encrypt(json_encode($data), self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $enc, $key, true);
        return base64_encode($iv . $hmac . $enc);
    }

    /**
     * @return array|false payload jika token valid & integritas terjaga, false selain itu
     */
    private function decryptPayload($token){
        $key = $this->getKey();
        $c   = base64_decode($token, true);
        if($c === false){
            return false;
        }

        $ivlen = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        if(strlen($c) < $ivlen + 32 + 1){
            return false;
        }

        $iv      = substr($c, 0, $ivlen);
        $hmac    = substr($c, $ivlen, 32);
        $ct      = substr($c, $ivlen + 32);
        $calcmac = hash_hmac('sha256', $ct, $key, true);
        if(!hash_equals($calcmac, $hmac)){
            return false;
        }

        $json = openssl_decrypt($ct, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        return $json === false ? false : json_decode($json, true);
    }

    private function response($status, $data){
        http_response_code($status);
        echo json_encode(['status' => $status, 'data' => $data]);
        die();
    }
}
