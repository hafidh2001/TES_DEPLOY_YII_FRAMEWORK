<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");


class ApiMobileServiceController extends Controller {

    public $enableCsrfValidation = false;

    // public function filters() {
    //     return ['accessControl'];
    // }

    // public function accessRules() {
    //     return [
    //         ['allow', 'actions' => ['getUser', 'login', 'editProfile', 'ChangePassword', 'GetMasterPpds', 'getMasterStase', 
    //         'CreateLogbookMilestone', 'GetMasterStaff', 'GetMasterAction', 'GetLogbook', 'GetMilestoneNotTaken', 
    //         'GetMilestoneTaken', 'GetMasterSemester', 'GetMilestoneStaff', 'GetNotification', 'GetTodo', 'GetListExplorePpds'], 'users' => ['*']], // tambah 'login'
    //         ['deny']
    //     ];
    // }
    
    // ==========================================================================================================================================================
    // ====================================================================== User Section ======================================================================
    // ==========================================================================================================================================================
    public function actionLogin() {
        
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['username']) || !isset($post['password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Username dan password wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        $username = $post['username'];
        $password = $post['password'];

    try {
        // Step 1: Cek username
        $cek = Yii::app()->dbPrasi->createCommand()
            ->select('id, username, password')
            ->from('m_user')
            ->where('username = :username', [':username' => $username])
            ->queryRow();

        if (!$cek) {
            echo json_encode([
                'success' => false,
                'message' => 'Username tidak ditemukan'
            ]);
            Yii::app()->end();
        }

        // Step 2: Verifikasi bcrypt
        if (!password_verify($password, $cek['password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Password salah'
            ]);
            Yii::app()->end();
        }
        
        $user = Yii::app()->dbPrasi->createCommand()
            ->select('
                u.id,
                u.display_name,
                u.code,
                u.username,
                u.id_role,
                u.email,
                u.address,
                u.date_of_birth,
                u.phone,
                r.name AS role_name,
                u.id_client,
                c.name AS client_name,
                u.id_semester,
                msem.name AS semester_name,
                u.id_stase,
                ms.name AS stase_name,
                u.status
            ')
            ->from('m_user u')
            ->leftJoin('m_role r', 'r.id = u.id_role')
            ->leftJoin('m_client c', 'c.id = u.id_client')
            ->leftJoin('m_stase ms', 'ms.id = u.id_stase')
            ->leftJoin('m_semester msem', 'msem.id = u.id_semester')
            ->where('u.username = :username', [':username' => $username])
            ->queryRow();
        
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => $user
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    public function actionEditProfile() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi input
        if (!isset($post['id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'ID user wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            // Cek user ada atau tidak
            $cek = Yii::app()->dbPrasi->createCommand()
                ->select('id')
                ->from('m_user')
                ->where('id = :id', [':id' => $post['id']])
                ->queryRow();
    
            if (!$cek) {
                echo json_encode([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ]);
                Yii::app()->end();
            }
    
            // Whitelist field yang boleh diupdate
            $allowedFields = ['display_name', 'email', 'phone', 'address', 'date_of_birth'];
    
            $data = [];
            foreach ($allowedFields as $field) {
                if (isset($post[$field])) {
                    $data[$field] = $post[$field];
                }
            }
    
            if (empty($data)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tidak ada data yang diupdate'
                ]);
                Yii::app()->end();
            }
    
            // Raw SQL - hanya update field yang ada
            $setParts = [];
            $params   = [':id' => $post['id']];
    
            foreach ($data as $field => $value) {
                $setParts[]        = "$field = :$field";
                $params[":$field"] = $value;
            }
    
            $sql = "UPDATE m_user SET " . implode(', ', $setParts) . " WHERE id = :id";
            Yii::app()->dbPrasi->createCommand($sql)->execute($params);
    
            // Ambil data terbaru
            $user = Yii::app()->dbPrasi->createCommand()
                ->select('
                    u.id, u.display_name, u.code, u.username, u.id_role, u.email,
                    u.address, u.date_of_birth, u.phone, r.name as role_name,
                    u.id_client, c.name as client_name, u.id_semester, u.id_stase,
                    u.status
                ')
                ->from('m_user u')
                ->leftJoin('m_role r', 'r.id = u.id_role')
                ->leftJoin('m_client c', 'c.id = u.id_client')
                ->where('u.id = :id', [':id' => $post['id']])
                ->queryRow();
    
            echo json_encode([
                'success' => true,
                'message' => 'Profile berhasil diupdate',
                'data'    => $user
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    public function actionChangePassword() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi input
        if (!isset($post['id']) || !isset($post['old_password']) || !isset($post['new_password']) || !isset($post['confirm_password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'ID, password lama, password baru, dan konfirmasi password wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        // Cek new_password dan confirm_password sama
        if ($post['new_password'] !== $post['confirm_password']) {
            echo json_encode([
                'success' => false,
                'message' => 'Password baru dan konfirmasi password tidak sama'
            ]);
            Yii::app()->end();
        }
    
        // Minimal panjang password
        if (strlen($post['new_password']) < 6) {
            echo json_encode([
                'success' => false,
                'message' => 'Password baru minimal 6 karakter'
            ]);
            Yii::app()->end();
        }
    
        try {
            // Cek user ada atau tidak
            $cek = Yii::app()->dbPrasi->createCommand()
                ->select('id, password')
                ->from('m_user')
                ->where('id = :id', [':id' => $post['id']])
                ->queryRow();
    
            if (!$cek) {
                echo json_encode([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ]);
                Yii::app()->end();
            }
    
            // Verifikasi password lama
            if (!password_verify($post['old_password'], $cek['password'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Password lama salah'
                ]);
                Yii::app()->end();
            }
    
            // Hash password baru dengan bcrypt
            $newPasswordHash = password_hash($post['new_password'], PASSWORD_BCRYPT);
    
            // Update password
            Yii::app()->dbPrasi->createCommand()->update(
                'm_user',
                ['password' => $newPasswordHash],
                'id = :id',
                [':id' => $post['id']]
            );
    
            echo json_encode([
                'success' => true,
                'message' => 'Password berhasil diubah'
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    // ==========================================================================================================================================================
    // ====================================================================== Master Section ======================================================================
    // ==========================================================================================================================================================
    
    public function actionGetMasterPpds() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // id_client wajib diisi agar data sesuai client user yang login
        if (!isset($post['id_client'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_client wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            $data = Yii::app()->db->createCommand()
                ->select('u.id, u.display_name, u.id_client, u.status, u.is_show, r.name as role_name')
                ->from('m_user u')
                ->leftJoin('m_role r', 'r.id = u.id_role')
                ->where('u.deleted_at IS NULL AND u.is_show = true AND u.status = :status AND r.name = :role AND u.id_client = :id_client', [
                    ':status'    => 'Active',
                    ':role'      => 'ppds',
                    ':id_client' => $post['id_client']
                ])
                ->order('u.display_name ASC')
                ->queryAll();
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }

    public function actionGetMasterStaff() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // id_client wajib diisi agar data sesuai client user yang login
        if (!isset($post['id_client'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_client wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            $data = Yii::app()->db->createCommand()
                ->select('mu.id, mu.display_name')
                ->from('m_user mu')
                ->join('m_role mr', 'mr.id = mu.id_role')
                ->where('mr.name = :role AND mu.id_client = :id_client AND mu.status = :status AND mu.is_show = true AND mu.deleted_at IS NULL', [
                    ':role'      => 'staff',
                    ':id_client' => $post['id_client'],
                    ':status'    => 'Active',
                ])
                ->order('mu.display_name ASC')
                ->queryAll();
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    public function actionGetMasterSemester() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_client wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            $sql = "
                SELECT
                    smt.id,
                    smt.name,
                    smt.id_stage,
                    smt.id_client,
                    stage.id AS _stage_id,
                    stage.name AS _stage_name,
                    stage.label_color AS _stage_label_color,
                    stage.code AS _stage_code,
                    stage.id_client AS _stage_id_client
                FROM m_semester smt
                LEFT JOIN m_stage stage ON stage.id = smt.id_stage
                WHERE smt.id_client = :id_client
                ORDER BY smt.id ASC
            ";
    
            $rows = Yii::app()->db->createCommand($sql)
                ->bindValue(':id_client', $post['id_client'])
                ->queryAll();
    
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'id'        => $row['id'],
                    'name'      => $row['name'],
                    'id_stage'  => $row['id_stage'],
                    'id_client' => $row['id_client'],
                    'm_stage'   => [
                        'id'          => $row['_stage_id'],
                        'name'        => $row['_stage_name'],
                        'label_color' => $row['_stage_label_color'],
                        'code'        => $row['_stage_code'],
                        'id_client'   => $row['_stage_id_client'],
                    ],
                ];
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    
    // ==========================================================================================================================================================
    // ====================================================================== Home Section ======================================================================
    // ==========================================================================================================================================================
    
    // api show menu
    public function actionGetMasterAction() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_client wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            if (is_null($post['id_semester'])) {
                $sql = "
                    SELECT
                        ma.id AS id_action,
                        ma.name AS action_name,
                        ma.id_type,
                        mat.name AS action_type_name
                    FROM m_action ma
                    LEFT JOIN m_action_type mat ON ma.id_type = mat.id
                    WHERE mat.id_client = :id_client
                    AND ma.name NOT IN ('Action Test', 'Stase')
                    ORDER BY
                        CASE mat.name
                            WHEN 'Activity' THEN 1
                            WHEN 'Academic' THEN 2
                            WHEN 'Others'   THEN 3
                            ELSE 4
                        END,
                        ma.name ASC
                ";
    
                $data = Yii::app()->dbPrasi->createCommand($sql)
                    ->bindParam(':id_client', $post['id_client'])
                    ->queryAll();
    
            } else {
                $sql = "
                    SELECT
                        mas.id_action,
                        ma.name AS action_name,
                        ma.id_type,
                        mat.name AS action_type_name
                    FROM m_action_semester mas
                    LEFT JOIN m_action ma ON mas.id_action = ma.id
                    LEFT JOIN m_action_type mat ON ma.id_type = mat.id
                    WHERE mas.id_client = :id_client
                      AND mas.id_semester = :id_semester
                      AND ma.name NOT IN ('Action Test', 'Stase')
                    ORDER BY
                        CASE mat.name
                            WHEN 'Activity' THEN 1
                            WHEN 'Academic' THEN 2
                            WHEN 'Others'   THEN 3
                            ELSE 4
                        END,
                        ma.name ASC
                ";
    
                $data = Yii::app()->dbPrasi->createCommand($sql)
                    ->bindParam(':id_client', $post['id_client'])
                    ->bindParam(':id_semester', $post['id_semester'])
                    ->queryAll();
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }


    public function actionGetLogbook() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id_client']) || !isset($post['id_action']) || !isset($post['role']) || !isset($post['user_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_client, id_action, role, user_id wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            $role   = $post['role'];
            $userId = $post['user_id'];
            $params = [
                ':id_action' => $post['id_action'],
                ':id_client' => $post['id_client'],
            ];
    
            // Filter berdasarkan role
            if ($role === 'ppds') {
                $roleFilter = "t.id_user = :user_id";
                $params[':user_id'] = $userId;
            } elseif ($role === 'staff') {
                $roleFilter = "
                    m_user.is_show = true 
                    AND m_user.status = 'Active'
                ";
                // hapus $params[':user_id'] di sini
            } else {
                $roleFilter = "1=1";
            }
    
            $sql = "
                SELECT
                    t.*,
                    m_user.display_name AS _peserta_display_name,
                    m_hosp.id AS _hospital_id,
                    m_hosp.name AS _hospital_name,
                    mac.id AS _category_id,
                    mac.name AS _category_name,
                    m_stase.id AS _stase_id,
                    m_stase.name AS _stase_name,
                    m_stase.id_stage AS _stase_id_stage,
                    m_stase.id_client AS _stase_id_client,
                    m_stase.sequence AS _stase_sequence,
                    CASE
                        WHEN t.verified = true THEN 'Verified'
                        WHEN t.verified = false AND t.verified_status = 'rejected' THEN 'Rejected'
                        ELSE '-'
                    END AS _status,
                    COALESCE(
                        (SELECT ROUND(AVG(score::numeric), 2)
                         FROM t_logbook_asm WHERE id_logbook = t.id AND score > 0)::text,
                        '-'
                    ) AS _score
                FROM t_logbook t
                LEFT JOIN m_user m_user ON t.id_user = m_user.id AND m_user.deleted_at IS NULL
                LEFT JOIN m_hospital m_hosp ON t.id_hospital = m_hosp.id
                LEFT JOIN m_action_category mac ON t.id_category = mac.id
                LEFT JOIN m_stase ON t.id_stase = m_stase.id
                WHERE
                    t.deleted_at IS NULL
                    AND t.id_action = :id_action
                    AND t.id_client = :id_client
                    AND ($roleFilter)
                ORDER BY t.created_date DESC
            ";
    
            $command = Yii::app()->dbPrasi->createCommand($sql);
            foreach ($params as $key => $value) {
                $command->bindValue($key, $value);
            }
    
            $rows = $command->queryAll();
    
            // Susun nested structure
            $data = [];
            foreach ($rows as $row) {
                $logbook = [];
    
                // Field utama t_logbook
                foreach ($row as $key => $value) {
                    if (strpos($key, '_') !== 0) {
                        $logbook[$key] = $value;
                    }
                }
    
                // Nested m_user
                $logbook['m_user'] = [
                    'display_name' => $row['_peserta_display_name'],
                ];
    
                // Nested m_hospital
                $logbook['m_hospital'] = $row['_hospital_id'] ? [
                    'id'   => $row['_hospital_id'],
                    'name' => $row['_hospital_name'],
                ] : null;
    
                // Nested m_action_category
                $logbook['m_action_category'] = [
                    'id'   => $row['_category_id'],
                    'name' => $row['_category_name'],
                ];
    
                // Nested m_stase
                $logbook['m_stase'] = $row['_stase_id'] ? [
                    'id'        => $row['_stase_id'],
                    'name'      => $row['_stase_name'],
                    'id_stage'  => $row['_stase_id_stage'],
                    'id_client' => $row['_stase_id_client'],
                    'sequence'  => $row['_stase_sequence'],
                ] : null;
    
                // Nested t_logbook_status (ambil terpisah)
                $statusSql = "
                    SELECT tls.*, mar.role, mu.display_name AS staff_name
                    FROM t_logbook_status tls
                    LEFT JOIN m_action_role mar ON tls.id_action_role = mar.id
                    LEFT JOIN m_user mu ON tls.id_user = mu.id
                    WHERE tls.id_logbook = :id_logbook
                ";
                $logbook['t_logbook_status'] = Yii::app()->dbPrasi->createCommand($statusSql)
                    ->bindValue(':id_logbook', $row['id'])
                    ->queryAll();
    
                // Nested t_logbook_asm (ambil terpisah)
                $asmSql = "SELECT * FROM t_logbook_asm WHERE id_logbook = :id_logbook";
                $logbook['t_logbook_asm'] = Yii::app()->dbPrasi->createCommand($asmSql)
                    ->bindValue(':id_logbook', $row['id'])
                    ->queryAll();
    
                $logbook['status'] = $row['_status'];
                $logbook['score']  = $row['_score'];
    
                $data[] = $logbook;
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    public function actionGetNotification() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode(['success' => false, 'message' => 'id_client wajib diisi']);
            Yii::app()->end();
        }
    
        if (!isset($post['id_user'])) {
            echo json_encode(['success' => false, 'message' => 'id_user wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            $sql = "
                SELECT
                    tn.id,
                    tn.id_user,
                    tn.id_client,
                    tn.type,
                    tn.message,
                    tn.url,
                    tn.read,
                    tn.date,
                    tn.deleted_at,
                    mu.id AS _mu_id,
                    mu.display_name AS _mu_display_name,
                    mu.username AS _mu_username
                FROM t_notif tn
                LEFT JOIN m_user mu ON tn.id_user = mu.id
                WHERE tn.id_user = :id_user
                  AND tn.id_client = :id_client
                  AND tn.deleted_at IS NULL
                ORDER BY tn.date DESC
            ";
    
            $rows = Yii::app()->db->createCommand($sql)
                ->bindValue(':id_user', $post['id_user'])
                ->bindValue(':id_client', $post['id_client'])
                ->queryAll();
    
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'id'         => $row['id'],
                    'id_user'    => $row['id_user'],
                    'id_client'  => $row['id_client'],
                    'type'       => $row['type'],
                    'message'    => $row['message'],
                    'url'        => $row['url'],
                    'read'       => $row['read'],
                    'date'       => $row['date'],
                    'deleted_at' => $row['deleted_at'],
                    'm_user'     => [
                        'id'           => $row['_mu_id'],
                        'display_name' => $row['_mu_display_name'],
                        'username'     => $row['_mu_username'],
                    ],
                ];
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    public function actionReadNotification() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id'])) {
            echo json_encode(['success' => false, 'message' => 'id wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            // Cek notif ada atau tidak
            $cek = Yii::app()->db->createCommand()
                ->select('id, read')
                ->from('t_notif')
                ->where('id = :id AND deleted_at IS NULL', [':id' => $post['id']])
                ->queryRow();
    
            if (!$cek) {
                echo json_encode(['success' => false, 'message' => 'Notifikasi tidak ditemukan']);
                Yii::app()->end();
            }
    
            // Update read menjadi true
            $sql = "UPDATE t_notif SET read = true WHERE id = :id";
            Yii::app()->db->createCommand($sql)->execute([':id' => $post['id']]);
    
            echo json_encode([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai sudah dibaca',
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    // todo list 
    
    public function actionGetTodo() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode(['success' => false, 'message' => 'id_client wajib diisi']);
            Yii::app()->end();
        }
    
        if (!isset($post['id_user'])) {
            echo json_encode(['success' => false, 'message' => 'id_user wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            $sql = "
                WITH status_logbooks AS (
                    SELECT DISTINCT id_logbook
                    FROM t_logbook_status
                    WHERE id_user = :id_user
                      AND status IN ('pending', 'revised')
                      AND deleted_at IS NULL
                ),
    
                filtered_logbooks AS (
                    SELECT
                        lb.id,
                        lb.id_action,
                        lb.id_user,
                        lb.id_hospital,
                        lb.date,
                        lb.id_category,
                        lb.id_client,
                        lb.verified as verified,
                        ma.name AS m_action_name,
                        mu.display_name AS m_user_display_name,
                        ms.id AS semester_id,
                        ms.name AS semester_name,
                        ms.id_stage,
                        stg.id AS stage_id,
                        stg.name AS stage_name,
                        stg.label_color AS stage_label_color
                    FROM t_logbook lb
                    INNER JOIN status_logbooks sl ON lb.id = sl.id_logbook
                    INNER JOIN m_action ma ON lb.id_action = ma.id
                    INNER JOIN m_user mu ON lb.id_user = mu.id
                    LEFT JOIN m_semester ms ON lb.id_semester = ms.id
                    LEFT JOIN m_stage stg ON ms.id_stage = stg.id
                    WHERE lb.deleted_at IS NULL
                      AND lb.verified = false
                      AND lb.id_client = :id_client
                      AND ma.identifier NOT IN ('exam', 'stase')
                      AND mu.is_deleted = false
                      AND mu.is_show = true
                      AND mu.status = 'Active'
                )
    
                SELECT * FROM filtered_logbooks
                ORDER BY date DESC
            ";
    
            $rows = Yii::app()->db->createCommand($sql)
                ->bindValue(':id_user', $post['id_user'])
                ->bindValue(':id_client', $post['id_client'])
                ->queryAll();
    
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'id'          => $row['id'],
                    'id_action'   => $row['id_action'],
                    'id_user'     => $row['id_user'],
                    'id_hospital' => $row['id_hospital'],
                    'date'        => $row['date'],
                    'id_category' => $row['id_category'],
                    'id_client'   => $row['id_client'],
                    'verified'   => $row['verified'],
                    'm_action'    => [
                        'name' => $row['m_action_name'],
                    ],
                    'm_user'      => [
                        'display_name' => $row['m_user_display_name'],
                    ],
                    'm_semester'  => $row['semester_id'] ? [
                        'id'      => $row['semester_id'],
                        'name'    => $row['semester_name'],
                        'id_stage' => $row['id_stage'],
                        'm_stage' => $row['stage_id'] ? [
                            'id'          => $row['stage_id'],
                            'name'        => $row['stage_name'],
                            'label_color' => $row['stage_label_color'],
                        ] : null,
                    ] : null,
                ];
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    public function actionGetLogbookById() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id'])) {
            echo json_encode(['success' => false, 'message' => 'id wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            $sql = "
                SELECT
                    lb.*,
                    u.id AS _u_id,
                    u.display_name AS _u_display_name,
                    u.username AS _u_username,
                    s.name AS _s_name,
                    st.label_color AS _st_label_color,
                    ma.id AS _ma_id,
                    ma.id_type AS _ma_id_type,
                    ma.name AS _ma_name,
                    mat.name AS _mat_name,
                    ma.has_notes, ma.has_attachment, ma.has_category,
                    ma.is_milestone, ma.show_on_milestone, ma.multiple_verification,
                    ma.has_score, ma.has_presentation, ma.has_location,
                    ma.has_emr, ma.has_another_role, ma.has_title,
                    ma.id_client AS _ma_id_client, ma.has_status, ma.show_on_menu,
                    ma.has_hospital, ma.attachment_name, ma.has_score_option,
                    ma.is_schedule, ma.max_entry_per_day, ma.identifier,
                    ma.is_grouped_by_category, ma.has_operation_code, ma.is_exam,
                    mac.id AS _mac_id,
                    mac.name AS _mac_name,
                    mh.id AS _mh_id,
                    mh.name AS _mh_name
                FROM t_logbook lb
                LEFT JOIN m_user u ON lb.id_user = u.id
                LEFT JOIN m_semester s ON u.id_semester = s.id
                LEFT JOIN m_stage st ON s.id_stage = st.id
                LEFT JOIN m_action ma ON lb.id_action = ma.id
                LEFT JOIN m_action_type mat ON ma.id_type = mat.id
                LEFT JOIN m_action_category mac ON lb.id_category = mac.id
                LEFT JOIN m_hospital mh ON lb.id_hospital = mh.id
                WHERE lb.id = :id
            ";
    
            $row = Yii::app()->dbPrasi->createCommand($sql)
                ->bindValue(':id', $post['id'])
                ->queryRow();
    
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Logbook tidak ditemukan']);
                Yii::app()->end();
            }
    
            // Field utama lb.*
            $excludeKeys = [
                '_u_id', '_u_display_name', '_u_username', '_s_name', '_st_label_color',
                '_ma_id', '_ma_id_type', '_ma_name', '_ma_id_client', '_mac_id', '_mac_name',
                '_mh_id', '_mh_name', '_mat_name',
                'has_notes', 'has_attachment', 'has_category', 'is_milestone', 'show_on_milestone',
                'multiple_verification', 'has_score', 'has_presentation', 'has_location',
                'has_emr', 'has_another_role', 'has_title', 'has_status', 'show_on_menu',
                'has_hospital', 'attachment_name', 'has_score_option', 'is_schedule',
                'max_entry_per_day', 'identifier', 'is_grouped_by_category', 'has_operation_code', 'is_exam'
            ];
    
            $data = [];
            foreach ($row as $key => $value) {
                if (!in_array($key, $excludeKeys)) {
                    $data[$key] = $value;
                }
            }
    
            // Nested m_user
            $data['m_user'] = [
                'id'           => $row['_u_id'],
                'display_name' => $row['_u_display_name'],
                'username'     => $row['_u_username'],
                'm_semester'   => $row['_s_name'] ? [
                    'name'    => $row['_s_name'],
                    'm_stage' => $row['_st_label_color'] ? [
                        'label_color' => $row['_st_label_color'],
                    ] : null,
                ] : null,
            ];
    
            // Nested m_action
            $data['m_action'] = [
                'id'                     => $row['_ma_id'],
                'id_type'                => $row['_ma_id_type'],
                'name'                   => $row['_ma_name'],
                'action_type_name'       => $row['_mat_name'],
                'has_notes'              => $row['has_notes'],
                'has_attachment'         => $row['has_attachment'],
                'has_category'           => $row['has_category'],
                'is_milestone'           => $row['is_milestone'],
                'show_on_milestone'      => $row['show_on_milestone'],
                'multiple_verification'  => $row['multiple_verification'],
                'has_score'              => $row['has_score'],
                'has_presentation'       => $row['has_presentation'],
                'has_location'           => $row['has_location'],
                'has_emr'                => $row['has_emr'],
                'has_another_role'       => $row['has_another_role'],
                'has_title'              => $row['has_title'],
                'id_client'              => $row['_ma_id_client'],
                'has_status'             => $row['has_status'],
                'show_on_menu'           => $row['show_on_menu'],
                'has_hospital'           => $row['has_hospital'],
                'attachment_name'        => json_decode($row['attachment_name'], true) ?? [],
                'has_score_option'       => $row['has_score_option'],
                'is_schedule'            => $row['is_schedule'],
                'max_entry_per_day'      => $row['max_entry_per_day'],
                'identifier'             => $row['identifier'],
                'is_grouped_by_category' => $row['is_grouped_by_category'],
                'has_operation_code'     => $row['has_operation_code'],
                'is_exam'                => $row['is_exam'],
            ];
    
            // t_logbook_status
            $statusSql = "
                SELECT
                    lbs.*,
                    ar.id AS _ar_id, ar.role AS _ar_role,
                    lbsu.id AS _lbsu_id, lbsu.id_role AS _lbsu_id_role,
                    lbsu.display_name AS _lbsu_display_name
                FROM t_logbook_status lbs
                LEFT JOIN m_action_role ar ON lbs.id_action_role = ar.id
                LEFT JOIN m_user lbsu ON lbs.id_user = lbsu.id
                WHERE lbs.id_logbook = :id_logbook
            ";
            $statusRows = Yii::app()->dbPrasi->createCommand($statusSql)
                ->bindValue(':id_logbook', $post['id'])
                ->queryAll();
    
            $data['t_logbook_status'] = [];
            foreach ($statusRows as $s) {
                $status = [];
                foreach ($s as $key => $value) {
                    if (strpos($key, '_') !== 0) {
                        $status[$key] = $value;
                    }
                }
                $status['m_action_role'] = $s['_ar_id'] ? [
                    'id'   => $s['_ar_id'],
                    'role' => $s['_ar_role'],
                ] : null;
                $status['m_user'] = $s['_lbsu_id'] ? [
                    'id'           => $s['_lbsu_id'],
                    'id_role'      => $s['_lbsu_id_role'],
                    'display_name' => $s['_lbsu_display_name'],
                ] : null;
                $data['t_logbook_status'][] = $status;
            }
    
            // t_logbook_emr
            $emrSql = "SELECT * FROM t_logbook_emr WHERE id_logbook = :id_logbook";
            $data['t_logbook_emr'] = Yii::app()->dbPrasi->createCommand($emrSql)
                ->bindValue(':id_logbook', $post['id'])
                ->queryAll();
    
            // t_logbook_asm
            $asmSql = "SELECT * FROM t_logbook_asm WHERE id_logbook = :id_logbook";
            $data['t_logbook_asm'] = Yii::app()->dbPrasi->createCommand($asmSql)
                ->bindValue(':id_logbook', $post['id'])
                ->queryAll();
    
            // t_logbook_attachment
            $attachSql = "SELECT * FROM t_logbook_attachment WHERE id_logbook = :id_logbook";
            $data['t_logbook_attachment'] = Yii::app()->dbPrasi->createCommand($attachSql)
                ->bindValue(':id_logbook', $post['id'])
                ->queryAll();
    
            // m_action_category
            $data['m_action_category'] = $row['_mac_id'] ? [
                'id'   => $row['_mac_id'],
                'name' => $row['_mac_name'],
            ] : null;
    
            // m_hospital
            $data['m_hospital'] = $row['_mh_id'] ? [
                'id'   => $row['_mh_id'],
                'name' => $row['_mh_name'],
            ] : null;
    
            // m_another_role - filter by id_another_role dari logbook
            $anotherRoleSql = "
                SELECT
                    maar.*,
                    mar.id AS _mar_id,
                    mar.role_name AS _mar_role_name,
                    mar.id_client AS _mar_id_client,
                    mar.id_action AS _mar_id_action
                FROM m_action_another_role maar
                LEFT JOIN m_another_role mar ON maar.id_another_role = mar.id
                WHERE maar.id_action = :id_action
                  AND maar.id_another_role = :id_another_role
            ";
            $anotherRoles = Yii::app()->dbPrasi->createCommand($anotherRoleSql)
                ->bindValue(':id_action', $row['_ma_id'])
                ->bindValue(':id_another_role', $row['id_another_role'])
                ->queryAll();
    
            $anotherRoleData = [];
            foreach ($anotherRoles as $ar) {
                $item = [];
                foreach ($ar as $key => $value) {
                    if (strpos($key, '_') !== 0) {
                        $item[$key] = $value;
                    }
                }
                $item['m_another_role'] = $ar['_mar_id'] ? [
                    'id'        => $ar['_mar_id'],
                    'role_name' => $ar['_mar_role_name'],
                    'id_client' => $ar['_mar_id_client'],
                    'id_action' => $ar['_mar_id_action'],
                ] : null;
                $anotherRoleData[] = $item;
            }
    
            $data['m_another_role'] = [
                'm_action_another_role' => $anotherRoleData,
            ];
    
            echo json_encode([
                'success' => true,
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    public function actionUpdateLogbookStatus() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_logbook'])) {
            echo json_encode(['success' => false, 'message' => 'id_logbook wajib diisi']);
            Yii::app()->end();
        }
    
        if (!isset($post['id_user'])) {
            echo json_encode(['success' => false, 'message' => 'id_user wajib diisi']);
            Yii::app()->end();
        }
    
        if (!isset($post['status'])) {
            echo json_encode(['success' => false, 'message' => 'status wajib diisi']);
            Yii::app()->end();
        }
    
        $allowedStatus = ['verified', 'revised', 'rejected'];
        if (!in_array($post['status'], $allowedStatus)) {
            echo json_encode(['success' => false, 'message' => 'Status tidak valid. Pilih: verified, revised, rejected']);
            Yii::app()->end();
        }
    
        try {
            // Cek logbook ada atau tidak
            $logbook = Yii::app()->db->createCommand()
                ->select('id, verified, verified_status')
                ->from('t_logbook')
                ->where('id = :id AND deleted_at IS NULL', [':id' => $post['id_logbook']])
                ->queryRow();
    
            if (!$logbook) {
                echo json_encode(['success' => false, 'message' => 'Logbook tidak ditemukan']);
                Yii::app()->end();
            }
    
            $status   = $post['status'];
            $notes    = isset($post['notes']) ? $post['notes'] : null;
            $dateTime = date('Y-m-d H:i:s');
    
            // Tentukan nilai verified berdasarkan status
            if ($status === 'verified') {
                $verified        = true;
                $verified_status = 'verified';
            } elseif ($status === 'revised') {
                $verified        = false;
                $verified_status = 'revised';
            } else {
                // rejected
                $verified        = false;
                $verified_status = 'rejected';
            }
    
            // Update t_logbook
            $updateLogbookSql = "
                UPDATE t_logbook 
                SET verified = :verified, verified_status = :verified_status
                WHERE id = :id
            ";
            Yii::app()->db->createCommand($updateLogbookSql)->execute([
                ':verified'        => $verified ? 'true' : 'false',
                ':verified_status' => $verified_status,
                ':id'              => $post['id_logbook'],
            ]);
    
            // Cek apakah t_logbook_status sudah ada untuk user ini
            $existingStatus = Yii::app()->db->createCommand()
                ->select('id')
                ->from('t_logbook_status')
                ->where('id_logbook = :id_logbook AND id_user = :id_user AND deleted_at IS NULL', [
                    ':id_logbook' => $post['id_logbook'],
                    ':id_user'    => $post['id_user'],
                ])
                ->queryRow();
    
            if ($existingStatus) {
                // notes hanya diupdate jika status rejected
                if ($status === 'rejected') {
                    $updateStatusSql = "
                        UPDATE t_logbook_status
                        SET status = :status, date_time = :date_time, notes = :notes
                        WHERE id = :id
                    ";
                    Yii::app()->db->createCommand($updateStatusSql)->execute([
                        ':status'    => $status,
                        ':date_time' => $dateTime,
                        ':notes'     => $notes,
                        ':id'        => $existingStatus['id'],
                    ]);
                } else {
                    // verified / revised - notes tidak diupdate
                    $updateStatusSql = "
                        UPDATE t_logbook_status
                        SET status = :status, date_time = :date_time
                        WHERE id = :id
                    ";
                    Yii::app()->db->createCommand($updateStatusSql)->execute([
                        ':status'    => $status,
                        ':date_time' => $dateTime,
                        ':id'        => $existingStatus['id'],
                    ]);
                }
            } else {
                // Insert t_logbook_status baru
                $insertStatusSql = "
                    INSERT INTO t_logbook_status (id_logbook, id_user, id_action_role, status, date_time, notes, id_client)
                    VALUES (:id_logbook, :id_user, :id_action_role, :status, :date_time, :notes, :id_client)
                ";
                Yii::app()->db->createCommand($insertStatusSql)->execute([
                    ':id_logbook'     => $post['id_logbook'],
                    ':id_user'        => $post['id_user'],
                    ':id_action_role' => isset($post['id_action_role']) ? $post['id_action_role'] : null,
                    ':status'         => $status,
                    ':date_time'      => $dateTime,
                    ':notes'          => $status === 'rejected' ? $notes : null,
                    ':id_client'      => isset($post['id_client']) ? $post['id_client'] : null,
                ]);
            }
    
            echo json_encode([
                'success' => true,
                'message' => 'Status logbook berhasil diupdate',
                'data'    => [
                    'id_logbook'      => $post['id_logbook'],
                    'verified'        => $verified,
                    'verified_status' => $verified_status,
                    'status'          => $status,
                    'date_time'       => $dateTime,
                    'notes'           => $notes,
                ]
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    // ===========================================================================================================================================================
    // ====================================================================== Stase Section ======================================================================
    // ===========================================================================================================================================================
    
    // /////////////////////////////
    // get master data stase select
    // /////////////////////////////
    public function actionGetMasterStase() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id_client'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_client wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            $data = Yii::app()->db->createCommand()
                ->select('id, name, id_client')
                ->from('m_stase')
                ->where('id_client = :id_client', [
                    ':id_client' => $post['id_client']
                ])
                ->order('name ASC')
                ->queryAll();
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    // /////////////////////////////
    // create stase logbook
    // /////////////////////////////
    public function actionCreateLogbookMilestone() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi input wajib
        if (!isset($post['id_action']) || !isset($post['id_client']) || !isset($post['created_by']) || !isset($post['date'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_action, id_client, created_by, dan date wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            // Ambil data m_action
            $m_action = Yii::app()->db->createCommand()
                ->select('*')
                ->from('m_action')
                ->where('id = :id', [':id' => $post['id_action']])
                ->queryRow();
    
            if (!$m_action) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Action tidak ditemukan'
                ]);
                Yii::app()->end();
            }
    
            // Ambil data user yang login (created_by)
            $loginUser = Yii::app()->db->createCommand()
                ->select('u.id, u.id_semester, u.id_stase, u.id_client, r.name as role_name')
                ->from('m_user u')
                ->leftJoin('m_role r', 'r.id = u.id_role')
                ->where('u.id = :id', [':id' => $post['created_by']])
                ->queryRow();
    
            if (!$loginUser) {
                echo json_encode([
                    'success' => false,
                    'message' => 'User login tidak ditemukan'
                ]);
                Yii::app()->end();
            }
    
            // Validasi berdasarkan role
            $role = $loginUser['role_name'];
            if ($role === 'ppds') {
                // ppds wajib ada t_logbook_status
            } else if ($role === 'staff' || $role === 'institution') {
                // staff/institution wajib ada id_user (PPDS)
                if (!isset($post['id_user'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'PPDS wajib diisi'
                    ]);
                    Yii::app()->end();
                }
            }
    
            // Inisialisasi variable
            $id_user         = $post['created_by'];
            $id_semester     = null;
            $id_stase        = null;
            $verified        = isset($post['verified']) ? (bool)$post['verified'] : false;
            $verified_status = isset($post['verified_status']) ? $post['verified_status'] : 'pending';
            $op_code         = null;
    
            // Generate operation code jika diperlukan
            if ($m_action['has_operation_code'] && !isset($post['operation_code'])) {
                $op_code = 'LB' . date('YmdHi');
            }
    
            // Cek is_exam atau is_milestone && role staff
            if ($m_action['is_exam'] || ($m_action['is_milestone'] && $role === 'staff')) {
    
                // Wajib ada id_user & id_stase
                if (!isset($post['id_user'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'id_user PPDS wajib diisi'
                    ]);
                    Yii::app()->end();
                }
    
                // Step 1: Update id_stase di m_user PPDS dulu
                if (isset($post['id_stase'])) {
                    Yii::app()->db->createCommand()->update(
                        'm_user',
                        ['id_stase' => $post['id_stase']],
                        'id = :id AND id_client = :id_client',
                        [
                            ':id'        => $post['id_user'],
                            ':id_client' => $post['id_client']
                        ]
                    );
                }
    
                // Step 2: Ambil id_semester & id_stase terbaru dari m_user PPDS
                $ppds = Yii::app()->db->createCommand()
                    ->select('id, id_semester, id_stase')
                    ->from('m_user')
                    ->where('id = :id AND id_client = :id_client', [
                        ':id'        => $post['id_user'],
                        ':id_client' => $post['id_client']
                    ])
                    ->queryRow();
    
                if (!$ppds) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'User PPDS tidak ditemukan'
                    ]);
                    Yii::app()->end();
                }
    
                $id_user         = $ppds['id'];
                $id_semester     = $ppds['id_semester'];
                $id_stase        = $ppds['id_stase'];
                $verified        = true;
                $verified_status = 'verified';
    
            } else {
                // Ambil id_semester & id_stase dari user yang LOGIN
                $res = Yii::app()->db->createCommand()
                    ->select('id, id_semester, id_stase')
                    ->from('m_user')
                    ->where('id = :id AND id_client = :id_client', [
                        ':id'        => $post['created_by'],
                        ':id_client' => $post['id_client']
                    ])
                    ->queryRow();
    
                $id_user     = $res['id'];
                $id_semester = $res['id_semester'];
                $id_stase    = $res['id_stase'];
            }
    
            // Siapkan nilai SQL
            $notes        = isset($post['notes']) && $post['notes'] !== '' ? "'" . addslashes($post['notes']) . "'" : 'NULL';
            $exam_result  = isset($post['exam_result']) && $post['exam_result'] !== '' ? "'" . addslashes($post['exam_result']) . "'" : 'NULL';
            $op_code_sql  = $op_code ? "'" . $op_code . "'" : (isset($post['operation_code']) && $post['operation_code'] !== '' ? "'" . addslashes($post['operation_code']) . "'" : 'NULL');
            $id_sem_sql   = $id_semester ? $id_semester : 'NULL';
            $id_sta_sql   = $id_stase ? $id_stase : 'NULL';
            $is_retake    = isset($post['is_retake']) && $post['is_retake'] ? 'true' : 'false';
            $verified_sql = $verified ? 'true' : 'false';
            $schedule     = isset($post['schedule_status']) && $post['schedule_status'] !== '' ? $post['schedule_status'] : 'pending';
            $date_now     = date('Y-m-d H:i:s');
    
            // Cek apakah create atau update
            if (!isset($post['id'])) {
                // CREATE
                $sql = "INSERT INTO t_logbook 
                    (notes, date, id_action, id_user, id_semester, id_stase, id_client, verified, verified_status, created_by, created_date, exam_result, is_retake, schedule_status, operation_code)
                    VALUES 
                    ({$notes}, '{$post['date']}', {$post['id_action']}, {$id_user}, {$id_sem_sql}, {$id_sta_sql}, {$post['id_client']}, {$verified_sql}, '{$verified_status}', {$post['created_by']}, '{$date_now}',                     {$exam_result}, {$is_retake}, '{$schedule}', {$op_code_sql})
                    RETURNING id";
    
                $result     = Yii::app()->db->createCommand($sql)->queryRow();
                $id_logbook = $result['id'];
    
            } else {
                // UPDATE
                $verified_status_update = $verified_status === 'rejected' ? 'revised' : $verified_status;
    
                $sql = "UPDATE t_logbook SET
                    notes         = {$notes},
                    date          = '{$post['date']}',
                    id_action     = {$post['id_action']},
                    id_user       = {$id_user},
                    id_client     = {$post['id_client']},
                    verified      = {$verified_sql},
                    verified_status = '{$verified_status_update}',
                    created_by    = {$post['created_by']},
                    created_date  = '{$post['date']}',
                    updated_date  = '{$date_now}',
                    exam_result   = {$exam_result},
                    is_retake     = {$is_retake},
                    schedule_status = '{$schedule}',
                    operation_code = {$op_code_sql}
                    WHERE id = {$post['id']}";
    
                Yii::app()->db->createCommand($sql)->execute();
                $id_logbook = $post['id'];
            }
    
            // Insert t_logbook_status jika ada
            if (isset($post['t_logbook_status']) && is_array($post['t_logbook_status'])) {
                $logbook_status = array_filter($post['t_logbook_status'], function($e) {
                    return isset($e['id_user']);
                });
    
                foreach ($logbook_status as $item) {
                    // Tentukan status
                    if ($item['status'] === 'rejected') {
                        $status = 'revised';
                    } else if ($item['status'] === 'verified') {
                        $status = 'verified';
                    } else if ($item['status'] === 'approved') {
                        $status = 'approved';
                    } else if ($item['status'] === 'revised') {
                        $status = 'revised';
                    } else {
                        $status = 'pending';
                    }
    
                    $id_action_role = isset($item['id_action_role']) ? $item['id_action_role'] : 'NULL';
                    $date_time      = date('Y-m-d H:i:s');
    
                    // Cek existing
                    $existing = Yii::app()->db->createCommand()
                        ->select('id')
                        ->from('t_logbook_status')
                        ->where('id_logbook = :id_logbook AND id_user = :id_user', [
                            ':id_logbook' => $id_logbook,
                            ':id_user'    => $item['id_user']
                        ])
                        ->queryRow();
    
                    if ($existing) {
                        // Update
                        Yii::app()->db->createCommand()->update(
                            't_logbook_status',
                            [
                                'status'         => $status,
                                'id_action_role' => $id_action_role !== 'NULL' ? $id_action_role : null,
                                'date_time'      => $date_time
                            ],
                            'id_logbook = :id_logbook AND id_user = :id_user',
                            [
                                ':id_logbook' => $id_logbook,
                                ':id_user'    => $item['id_user']
                            ]
                        );
                    } else {
                        // Insert
                        Yii::app()->db->createCommand()->insert('t_logbook_status', [
                            'id_logbook'     => $id_logbook,
                            'id_user'        => $item['id_user'],
                            'id_action_role' => $id_action_role !== 'NULL' ? $id_action_role : null,
                            'status'         => $status,
                            'date_time'      => $date_time
                        ]);
                    }
    
                    // Insert notifikasi
                    $m_action_name = $m_action['name'];
                    $date_formatted = date('d/m/Y H:i', strtotime($post['date']));
                    $message = addslashes("{$loginUser['role_name']} menambahkan data {$m_action_name} pada {$date_formatted}, Mohon berikan verifikasi anda. Klik detail untuk melihat logbook");
    
                    // Ambil id_role penerima notif
                    $notif_user = Yii::app()->db->createCommand()
                        ->select('id, id_role')
                        ->from('m_user')
                        ->where('id = :id', [':id' => $item['id_user']])
                        ->queryRow();
    
                    if ($notif_user) {
                        Yii::app()->db->createCommand()->insert('t_notif', [
                            'message'    => $message,
                            'date'       => $date_now,
                            'type'       => 'verify',
                            'id_user'    => $item['id_user'],
                            'url'        => '/staff/action/' . $post['id_action'] . '/' . $id_logbook,
                            'id_role'    => $notif_user['id_role'],
                            'id_client'  => $post['id_client'],
                            'id_logbook' => $id_logbook
                        ]);
                    }
                }
            }
    
            // Ambil data logbook yang baru dibuat/diupdate
            $logbook = Yii::app()->db->createCommand()
                ->select('*')
                ->from('t_logbook')
                ->where('id = :id', [':id' => $id_logbook])
                ->queryRow();
    
            echo json_encode([
                'success' => true,
                'message' => isset($post['id']) ? 'Logbook berhasil diupdate' : 'Logbook berhasil dibuat',
                'data'    => $logbook
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }    
    
    
    public function actionGetMilestoneNotTaken() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode([
                'success' => false,
                'message' => 'id_client wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        if (!isset($post['user_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'user_id wajib diisi'
            ]);
            Yii::app()->end();
        }
    
        try {
            $sql = "
                SELECT
                    s.id,
                    s.name,
                    s.id_stage,
                    s.id_client,
                    s.sequence,
                    stage.id AS _stage_id,
                    stage.name AS _stage_name,
                    stage.label_color AS _stage_label_color,
                    stage.id_institution AS _stage_id_institution,
                    stage.created_date AS _stage_created_date,
                    stage.created_by AS _stage_created_by,
                    stage.updated_date AS _stage_updated_date,
                    stage.updated_by AS _stage_updated_by,
                    stage.code AS _stage_code,
                    stage.id_client AS _stage_id_client
                FROM m_stase s
                LEFT JOIN m_stage stage ON stage.id = s.id_stage
                WHERE s.id_client = :id_client
                    AND NOT EXISTS (
                        SELECT 1 FROM t_logbook lb
                        JOIN m_action a ON a.id = lb.id_action
                        WHERE lb.id_stase = s.id
                            AND lb.id_user = :user_id
                            AND a.is_milestone = true
                            AND a.show_on_milestone = true
                            AND lb.deleted_at IS NULL
                    )
                ORDER BY s.sequence DESC, s.id_stage DESC
            ";
    
            $rows = Yii::app()->db->createCommand($sql)
                ->bindValue(':id_client', $post['id_client'])
                ->bindValue(':user_id', $post['user_id'])
                ->queryAll();
    
            // Susun nested structure
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'id'        => $row['id'],
                    'name'      => $row['name'],
                    'id_stage'  => $row['id_stage'],
                    'id_client' => $row['id_client'],
                    'sequence'  => $row['sequence'],
                    'm_stage'   => [
                        'id'             => $row['_stage_id'],
                        'name'           => $row['_stage_name'],
                        'label_color'    => $row['_stage_label_color'],
                        'id_institution' => $row['_stage_id_institution'],
                        'created_date'   => $row['_stage_created_date'],
                        'created_by'     => $row['_stage_created_by'],
                        'updated_date'   => $row['_stage_updated_date'],
                        'updated_by'     => $row['_stage_updated_by'],
                        'code'           => $row['_stage_code'],
                        'id_client'      => $row['_stage_id_client'],
                    ],
                ];
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    public function actionGetMilestoneTaken() {
       header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id_client'])) {
            echo json_encode(['success' => false, 'message' => 'id_client wajib diisi']);
            Yii::app()->end();
        }
    
        if (!isset($post['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'user_id wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            // Query 1: Semua action yang show_on_milestone
            $actionSql = "
                SELECT *
                FROM m_action
                WHERE show_on_milestone = true
                  AND id_client = :id_client
                ORDER BY name ASC
            ";
            $actions = Yii::app()->db->createCommand($actionSql)
                ->bindValue(':id_client', $post['id_client'])
                ->queryAll();
    
            $result = [];
            foreach ($actions as $action) {
                // Query 2: m_action_semester per action
                $actionSemesterSql = "
                    SELECT
                        acts.id,
                        smt.id AS _semester_id,
                        smt.name AS _semester_name,
                        stage.id AS _stage_id,
                        stage.name AS _stage_name,
                        stage.label_color AS _stage_label_color
                    FROM m_action_semester acts
                    LEFT JOIN m_semester smt ON smt.id = acts.id_semester
                    LEFT JOIN m_stage stage ON stage.id = smt.id_stage
                    WHERE acts.id_action = :id_action
                    ORDER BY smt.name ASC
                ";
                $actionSemesters = Yii::app()->db->createCommand($actionSemesterSql)
                    ->bindValue(':id_action', $action['id'])
                    ->queryAll();
    
                $actionSemesterData = [];
                foreach ($actionSemesters as $row) {
                    $actionSemesterData[] = [
                        'id'         => $row['id'],
                        'm_semester' => [
                            'id'      => $row['_semester_id'],
                            'name'    => $row['_semester_name'],
                            'm_stage' => [
                                'id'          => $row['_stage_id'],
                                'name'        => $row['_stage_name'],
                                'label_color' => $row['_stage_label_color'],
                            ],
                        ],
                    ];
                }
    
                // Query 3: t_logbook per action per user
                $logbookSql = "
                    SELECT
                        lb.id,
                        lb.is_retake,
                        smt.id AS _semester_id,
                        smt.name AS _semester_name,
                        stase.id AS _stase_id,
                        stase.name AS _stase_name,
                        stage.label_color AS _stage_label_color
                    FROM t_logbook lb
                    LEFT JOIN m_semester smt ON smt.id = lb.id_semester
                    LEFT JOIN m_stase stase ON stase.id = lb.id_stase
                    LEFT JOIN m_stage stage ON stage.id = stase.id_stage
                    WHERE lb.id_action = :id_action
                      AND lb.id_user = :user_id
                      AND lb.verified_status = 'verified'
                      AND lb.deleted_at IS NULL
                    ORDER BY lb.created_date DESC
                ";
                $logbooks = Yii::app()->db->createCommand($logbookSql)
                    ->bindValue(':id_action', $action['id'])
                    ->bindValue(':user_id', $post['user_id'])
                    ->queryAll();
    
                $logbookData = [];
                foreach ($logbooks as $row) {
                    $logbookData[] = [
                        'id'         => $row['id'],
                        'is_retake'  => $row['is_retake'],
                        'm_semester' => [
                            'id'   => $row['_semester_id'],
                            'name' => $row['_semester_name'],
                        ],
                        'm_stase'    => [
                            'id'      => $row['_stase_id'],
                            'name'    => $row['_stase_name'],
                            'm_stage' => [
                                'label_color' => $row['_stage_label_color'],
                            ],
                        ],
                    ];
                }
    
                $result[] = array_merge($action, [
                    'm_action_semester' => $actionSemesterData,
                    't_logbook'         => $logbookData,
                ]);
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($result),
                'data'    => $result
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    public function actionGetMilestoneStaff() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode(['success' => false, 'message' => 'id_client wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            // Query utama
            $sql = "
                SELECT
                    lb.*,
    
                    -- m_action
                    ma.id AS _ma_id, ma.id_type AS _ma_id_type, ma.name AS _ma_name,
                    ma.has_notes, ma.has_attachment, ma.has_category, ma.is_milestone,
                    ma.show_on_milestone, ma.multiple_verification, ma.has_score,
                    ma.has_presentation, ma.has_location, ma.has_emr, ma.has_another_role,
                    ma.has_title, ma.has_status, ma.show_on_menu, ma.has_hospital,
                    ma.attachment_name, ma.has_score_option, ma.is_schedule,
                    ma.max_entry_per_day, ma.identifier, ma.is_grouped_by_category,
                    ma.has_operation_code, ma.is_exam, ma.id_client AS _ma_id_client,
    
                    -- m_user
                    mu.id AS _mu_id, mu.display_name AS _mu_display_name,
                    mu.username AS _mu_username, mu.email AS _mu_email,
                    mu.password AS _mu_password, mu.id_role AS _mu_id_role,
                    mu.is_deleted AS _mu_is_deleted, mu.created_date AS _mu_created_date,
                    mu.created_by AS _mu_created_by, mu.updated_date AS _mu_updated_date,
                    mu.updated_by AS _mu_updated_by, mu.phone AS _mu_phone,
                    mu.address AS _mu_address, mu.date_of_birth AS _mu_date_of_birth,
                    mu.code AS _mu_code, mu.picture AS _mu_picture,
                    mu.id_institution AS _mu_id_institution,
                    mu.id_sub_category AS _mu_id_sub_category,
                    mu.id_semester AS _mu_id_semester, mu.id_stase AS _mu_id_stase,
                    mu.id_client AS _mu_id_client, mu.gender AS _mu_gender,
                    mu.id_year AS _mu_id_year, mu.status AS _mu_status,
                    mu.inisial_code AS _mu_inisial_code, mu.is_show AS _mu_is_show,
                    mu.deleted_at AS _mu_deleted_at, mu.inactive_at AS _mu_inactive_at,
                    mu.inactive_notes AS _mu_inactive_notes,
                    mu.reactivate_date AS _mu_reactivate_date,
    
                    -- m_stase
                    mstase.id AS _stase_id, mstase.name AS _stase_name,
                    mstase.id_stage AS _stase_id_stage, mstase.id_client AS _stase_id_client,
                    mstase.sequence AS _stase_sequence,
    
                    -- m_semester
                    smt.id AS _smt_id, smt.name AS _smt_name,
                    smt.id_stage AS _smt_id_stage, smt.id_client AS _smt_id_client
    
                FROM t_logbook lb
                JOIN m_user mu ON mu.id = lb.id_user
                JOIN m_action ma ON ma.id = lb.id_action
                LEFT JOIN m_stase mstase ON mstase.id = lb.id_stase
                LEFT JOIN m_semester smt ON smt.id = lb.id_semester
                WHERE mu.deleted_at IS NULL
                  AND mu.is_show = true
                  AND mu.status = 'Active'
                  AND lb.deleted_at IS NULL
                  AND ma.show_on_milestone = true
                  AND ma.is_milestone = true
                  AND lb.id_client = :id_client
                ORDER BY lb.date DESC
            ";
    
            $rows = Yii::app()->db->createCommand($sql)
                ->bindValue(':id_client', $post['id_client'])
                ->queryAll();
    
            $data = [];
            foreach ($rows as $row) {
                // Field utama lb.*
                $logbook = [];
                $excludeKeys = ['has_notes', 'has_attachment', 'has_category', 'is_milestone',
                    'show_on_milestone', 'multiple_verification', 'has_score', 'has_presentation',
                    'has_location', 'has_emr', 'has_another_role', 'has_title', 'has_status',
                    'show_on_menu', 'has_hospital', 'attachment_name', 'has_score_option',
                    'is_schedule', 'max_entry_per_day', 'identifier', 'is_grouped_by_category',
                    'has_operation_code', 'is_exam'];
    
                foreach ($row as $key => $value) {
                    if (strpos($key, '_') !== 0 && !in_array($key, $excludeKeys)) {
                        $logbook[$key] = $value;
                    }
                }
    
                // Nested m_action
                $logbook['m_action'] = [
                    'id'                     => $row['_ma_id'],
                    'id_type'                => $row['_ma_id_type'],
                    'name'                   => $row['_ma_name'],
                    'has_notes'              => $row['has_notes'],
                    'has_attachment'         => $row['has_attachment'],
                    'has_category'           => $row['has_category'],
                    'is_milestone'           => $row['is_milestone'],
                    'show_on_milestone'      => $row['show_on_milestone'],
                    'multiple_verification'  => $row['multiple_verification'],
                    'has_score'              => $row['has_score'],
                    'has_presentation'       => $row['has_presentation'],
                    'has_location'           => $row['has_location'],
                    'has_emr'                => $row['has_emr'],
                    'has_another_role'       => $row['has_another_role'],
                    'has_title'              => $row['has_title'],
                    'id_client'              => $row['_ma_id_client'],
                    'has_status'             => $row['has_status'],
                    'show_on_menu'           => $row['show_on_menu'],
                    'has_hospital'           => $row['has_hospital'],
                    'attachment_name'        => $row['attachment_name'],
                    'has_score_option'       => $row['has_score_option'],
                    'is_schedule'            => $row['is_schedule'],
                    'max_entry_per_day'      => $row['max_entry_per_day'],
                    'identifier'             => $row['identifier'],
                    'is_grouped_by_category' => $row['is_grouped_by_category'],
                    'has_operation_code'     => $row['has_operation_code'],
                    'is_exam'                => $row['is_exam'],
                ];
    
                // Nested m_user
                $logbook['m_user'] = [
                    'id'               => $row['_mu_id'],
                    'display_name'     => $row['_mu_display_name'],
                    'username'         => $row['_mu_username'],
                    'email'            => $row['_mu_email'],
                    'password'         => $row['_mu_password'],
                    'id_role'          => $row['_mu_id_role'],
                    'is_deleted'       => $row['_mu_is_deleted'],
                    'created_date'     => $row['_mu_created_date'],
                    'created_by'       => $row['_mu_created_by'],
                    'updated_date'     => $row['_mu_updated_date'],
                    'updated_by'       => $row['_mu_updated_by'],
                    'phone'            => $row['_mu_phone'],
                    'address'          => $row['_mu_address'],
                    'date_of_birth'    => $row['_mu_date_of_birth'],
                    'code'             => $row['_mu_code'],
                    'picture'          => $row['_mu_picture'],
                    'id_institution'   => $row['_mu_id_institution'],
                    'id_sub_category'  => $row['_mu_id_sub_category'],
                    'id_semester'      => $row['_mu_id_semester'],
                    'id_stase'         => $row['_mu_id_stase'],
                    'id_client'        => $row['_mu_id_client'],
                    'gender'           => $row['_mu_gender'],
                    'id_year'          => $row['_mu_id_year'],
                    'status'           => $row['_mu_status'],
                    'inisial_code'     => $row['_mu_inisial_code'],
                    'is_show'          => $row['_mu_is_show'],
                    'deleted_at'       => $row['_mu_deleted_at'],
                    'inactive_at'      => $row['_mu_inactive_at'],
                    'inactive_notes'   => $row['_mu_inactive_notes'],
                    'reactivate_date'  => $row['_mu_reactivate_date'],
                ];
    
                // Nested m_stase
                $logbook['m_stase'] = $row['_stase_id'] ? [
                    'id'        => $row['_stase_id'],
                    'name'      => $row['_stase_name'],
                    'id_stage'  => $row['_stase_id_stage'],
                    'id_client' => $row['_stase_id_client'],
                    'sequence'  => $row['_stase_sequence'],
                ] : null;
    
                // Nested m_semester
                $logbook['m_semester'] = $row['_smt_id'] ? [
                    'id'        => $row['_smt_id'],
                    'name'      => $row['_smt_name'],
                    'id_stage'  => $row['_smt_id_stage'],
                    'id_client' => $row['_smt_id_client'],
                ] : null;
    
                $data[] = $logbook;
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    
    // ===========================================================================================================================================================
    // ====================================================================== Explore Section ======================================================================
    // ===========================================================================================================================================================
    
    
    
    public function actionGetListExplorePpds() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode(['success' => false, 'message' => 'id_client wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            $sql = "
                SELECT
                    ms.id AS semester_id,
                    ms.name AS semester_name,
                    stg.label_color AS stage_color,
                    mu.id AS user_id,
                    mu.display_name,
                    mu.code,
                    st.name AS stase_name
                FROM m_user mu
                LEFT JOIN m_semester ms ON mu.id_semester = ms.id
                LEFT JOIN m_stage stg ON ms.id_stage = stg.id
                LEFT JOIN m_stase st ON mu.id_stase = st.id
                WHERE mu.id_role = (SELECT id FROM m_role WHERE name = 'ppds' AND id_client = :id_client)
                  AND mu.deleted_at IS NULL
                  AND mu.is_show = true
                  AND mu.status = 'Active'
                  AND mu.id_client = :id_client
                ORDER BY ms.name ASC
            ";
    
            $rows = Yii::app()->db->createCommand($sql)
                ->bindValue(':id_client', $post['id_client'])
                ->queryAll();
    
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'user_id'       => $row['user_id'],
                    'display_name'  => $row['display_name'],
                    'code'          => $row['code'],
                    'stase_name'    => $row['stase_name'],
                    'm_semester'    => $row['semester_id'] ? [
                        'id'          => $row['semester_id'],
                        'name'        => $row['semester_name'],
                        'stage_color' => $row['stage_color'],
                    ] : null,
                ];
            }
    
            echo json_encode([
                'success' => true,
                'total'   => count($data),
                'data'    => $data
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    public function actionExploreDetailPpds() {
        header('Content-Type: application/json');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        // Validasi wajib
        if (!isset($post['id_client'])) {
            echo json_encode(['success' => false, 'message' => 'id_client wajib diisi']);
            Yii::app()->end();
        }
    
        if (!isset($post['id_user'])) {
            echo json_encode(['success' => false, 'message' => 'id_user wajib diisi']);
            Yii::app()->end();
        }
    
        try {
            $sql = "
                SELECT
                    mu.id,
                    mu.display_name,
                    mu.code,
                    mu.address,
                    mu.gender,
                    mu.date_of_birth,
                    ms.name AS semester_name,
                    stg.label_color,
                    tl.id AS logbook_id,
                    tl.id_user AS logbook_id_user,
                    ma.id AS action_id,
                    ma.name AS action_name,
                    ma.identifier AS action_identifier
                FROM m_user mu
                LEFT JOIN m_semester ms ON mu.id_semester = ms.id
                LEFT JOIN m_stage stg ON ms.id_stage = stg.id
                LEFT JOIN t_logbook tl ON tl.id_user = mu.id
                    AND tl.deleted_at IS NULL
                    AND tl.id_client = :id_client
                LEFT JOIN m_action ma ON tl.id_action = ma.id
                WHERE mu.id = :id_user
                  AND mu.id_client = :id_client
                  AND mu.deleted_at IS NULL
            ";
    
            $rows = Yii::app()->db->createCommand($sql)
                ->bindValue(':id_client', $post['id_client'])
                ->bindValue(':id_user', $post['id_user'])
                ->queryAll();
    
            if (empty($rows)) {
                echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
                Yii::app()->end();
            }
    
            // Data user diambil dari row pertama
            $firstRow = $rows[0];
            $user = [
                'id'            => $firstRow['id'],
                'display_name'  => $firstRow['display_name'],
                'code'          => $firstRow['code'],
                'address'       => $firstRow['address'],
                'gender'        => $firstRow['gender'],
                'date_of_birth' => $firstRow['date_of_birth'],
                'm_semester'    => [
                    'name'        => $firstRow['semester_name'],
                    'label_color' => $firstRow['label_color'],
                ],
                't_logbook'     => [],
            ];
    
            // Susun logbook array
            foreach ($rows as $row) {
                if ($row['logbook_id']) {
                    $user['t_logbook'][] = [
                        'id'      => $row['logbook_id'],
                        'id_user' => $row['logbook_id_user'],
                        'm_action' => $row['action_id'] ? [
                            'id'         => $row['action_id'],
                            'name'       => $row['action_name'],
                            'identifier' => $row['action_identifier'],
                        ] : null,
                    ];
                }
            }
    
            echo json_encode([
                'success' => true,
                'data'    => $user
            ]);
    
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        Yii::app()->end();
    }
    
    public function actionGetLogbookIdCustomer() {
    header('Content-Type: application/json');
    $rest_json = file_get_contents("php://input");
    $post = json_decode($rest_json, true);

    if (!isset($post['id_client']) || !isset($post['id_action']) || !isset($post['role']) || !isset($post['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'id_client, id_action, role, user_id wajib diisi'
        ]);
        Yii::app()->end();
    }

    try {
        $role   = $post['role'];
        $userId = $post['user_id'];
        $customerId = isset($post['customer_id']) ? $post['customer_id'] : null;
        
        $params = [
            ':id_action' => $post['id_action'],
            ':id_client' => $post['id_client'],
        ];

        // Filter berdasarkan role
        if ($role === 'ppds') {
            $roleFilter = "t.id_user = :user_id";
            $params[':user_id'] = $userId;
        } elseif ($role === 'staff') {
            $roleFilter = "
                m_user.is_show = true 
                AND m_user.status = 'Active'
            ";
        } else {
            $roleFilter = "1=1";
        }

        // Filter customer_id jika ada
        $customerFilter = "";
        if ($customerId) {
            $customerFilter = "AND t.id_user = :customer_id";
            $params[':customer_id'] = $customerId;
        }

        $sql = "
            SELECT
                t.id,
                t.id_action,
                t.created_by,
                t.verified,
                t.exam_result,
                t.location,
                t.date,
                m_user.display_name AS _user_display_name
            FROM t_logbook t
            LEFT JOIN m_user m_user ON t.id_user = m_user.id AND m_user.deleted_at IS NULL
            WHERE
                t.deleted_at IS NULL
                AND t.id_action = :id_action
                AND t.id_client = :id_client
                AND ($roleFilter)
                $customerFilter
            ORDER BY t.created_date DESC
        ";

        $command = Yii::app()->db->createCommand($sql);
        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
        }

        $rows = $command->queryAll();

        // Susun nested structure
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => $row['id'],
                'id_action' => $row['id_action'],
                'created_by' => $row['created_by'],
                'verified' => $row['verified'],
                'exam_result' => $row['exam_result'],
                'location' => $row['location'],
                'date' => $row['date'],
                'm_user' => [
                    'display_name' => $row['_user_display_name']
                ]
            ];
        }

        echo json_encode([
            'success' => true,
            'total'   => count($data),
            'data'    => $data
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    Yii::app()->end();
}
    
    
    
    
    
    
    
}