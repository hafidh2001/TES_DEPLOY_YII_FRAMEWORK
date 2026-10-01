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
 /* Replace the existing actionLogin() method in ApiMobileServiceController.
 * Do not append a second actionLogin().
 * Dashboard points are bound to the currently open Morbiditas session. A
 * deliberate return to a previous semester therefore remains 0 until new
 * verified Morbiditas records are created in that new session.
 */
public function actionLogin()
{
    header('Content-Type: application/json');
    $post = json_decode(file_get_contents('php://input'), true);

    if (!is_array($post) || !isset($post['username']) || !isset($post['password'])) {
        echo json_encode(array('success'=>false,'message'=>'Username dan password wajib diisi'));
        Yii::app()->end();
    }

    try {
        $db = Yii::app()->dbPrasi;
        $username = $post['username'];
        $password = $post['password'];
        $credential = $db->createCommand()
            ->select('id, username, password')
            ->from('m_user')
            ->where('username = :username', array(':username'=>$username))
            ->queryRow();
        if (!$credential) {
            echo json_encode(array('success'=>false,'message'=>'Username tidak ditemukan'));
            Yii::app()->end();
        }
        if (!password_verify($password, $credential['password'])) {
            echo json_encode(array('success'=>false,'message'=>'Password salah'));
            Yii::app()->end();
        }

        $user = $db->createCommand()
            ->select('u.id,u.display_name,u.code,u.username,u.id_role,u.email,u.address,u.date_of_birth,u.phone,
                r.name AS role_name,u.id_client,c.name AS client_name,u.id_semester,msem.name AS semester_name,
                u.id_stase,ms.name AS stase_name,u.status')
            ->from('m_user u')
            ->leftJoin('m_role r', 'r.id = u.id_role')
            ->leftJoin('m_client c', 'c.id = u.id_client')
            ->leftJoin('m_stase ms', 'ms.id = u.id_stase')
            ->leftJoin('m_semester msem', 'msem.id = u.id_semester')
            ->where('u.username = :username', array(':username'=>$username))
            ->queryRow();

        if ($user && strtolower((string)$user['role_name']) === 'ppds') {
            $this->ensureMorbiditasPointsSessionTable($db);
            $session = $db->createCommand('SELECT id,started_at FROM t_morbiditas_points_history
                WHERE id_user=:user AND id_client=:client AND id_semester=:semester AND ended_at IS NULL
                ORDER BY id DESC LIMIT 1')
                ->bindValues(array(':user'=>(int)$user['id'], ':client'=>(int)$user['id_client'],
                    ':semester'=>(int)$user['id_semester']))->queryRow();

            /* No session means this is pre-migration data. Existing points remain visible
             * until the first deliberate semester change seeds and closes that baseline. */
            $user['total_points'] = $this->calculateMorbiditasSessionPoints(
                $db, (int)$user['id'], (int)$user['id_client'], (int)$user['id_semester'],
                $session ? $session['started_at'] : '1970-01-01 00:00:00+00'
            );
        }

        echo json_encode(array('success'=>true,'message'=>'Login berhasil','data'=>$user));
    } catch (Exception $e) {
        echo json_encode(array('success'=>false,'message'=>$e->getMessage()));
    }
    Yii::app()->end();
}



/* Add this method once inside ApiMobileServiceController.
 * Route: apiMobileService/getDashboardMorbiditasState
 * The app calls this whenever the PPDS Home dashboard becomes visible, so the
 * displayed Stase, Semester, and Morbiditas points do not remain a stale login snapshot.
 */
public function actionGetDashboardMorbiditasState()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id_user', 'id_client') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success'=>false, 'message'=>$field.' wajib berupa ID positif'));
            Yii::app()->end();
        }
    }

    try {
        $db = Yii::app()->dbPrasi;
        $idUser = (int)$post['id_user'];
        $idClient = (int)$post['id_client'];
        $user = $db->createCommand("SELECT u.id,u.id_client,u.id_semester,u.id_stase,
                sem.name AS semester_name,st.name AS stase_name
            FROM m_user u
            INNER JOIN m_role r ON r.id=u.id_role
            LEFT JOIN m_semester sem ON sem.id=u.id_semester AND sem.id_client=u.id_client
            LEFT JOIN m_stase st ON st.id=u.id_stase AND st.id_client=u.id_client
            WHERE u.id=:user AND u.id_client=:client AND u.deleted_at IS NULL
              AND u.status='Active' AND lower(r.name)='ppds'")
            ->bindValues(array(':user'=>$idUser, ':client'=>$idClient))->queryRow();
        if (!$user) throw new RuntimeException('PPDS aktif tidak ditemukan untuk client ini');

        $this->ensureMorbiditasPointsSessionTable($db);
        $session = $db->createCommand('SELECT started_at FROM t_morbiditas_points_history
            WHERE id_user=:user AND id_client=:client AND id_semester=:semester AND ended_at IS NULL
            ORDER BY id DESC LIMIT 1')
            ->bindValues(array(':user'=>$idUser, ':client'=>$idClient, ':semester'=>(int)$user['id_semester']))
            ->queryRow();
        $points = $this->calculateMorbiditasSessionPoints(
            $db, $idUser, $idClient, (int)$user['id_semester'],
            $session ? $session['started_at'] : '1970-01-01 00:00:00+00'
        );

        echo json_encode(array('success'=>true, 'data'=>array(
            'id_user'=>$idUser,
            'id_client'=>$idClient,
            'id_semester'=>$user['id_semester'] === null ? null : (int)$user['id_semester'],
            'semester_name'=>$user['semester_name'],
            'id_stase'=>$user['id_stase'] === null ? null : (int)$user['id_stase'],
            'stase_name'=>$user['stase_name'],
            'total_points'=>$points,
        )));
    } catch (Exception $e) {
        echo json_encode(array('success'=>false, 'message'=>$e->getMessage()));
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
    
    /* Replace the existing actionChangePassword() method. Do not append it. */
public function actionChangePassword() {
    header('Content-Type: application/json');
    $rest_json = file_get_contents("php://input");
    $post = json_decode($rest_json, true);

    if (!isset($post['id']) || !isset($post['old_password']) || !isset($post['new_password']) || !isset($post['confirm_password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID, password lama, password baru, dan konfirmasi password wajib diisi'
        ]);
        Yii::app()->end();
    }

    if ($post['new_password'] !== $post['confirm_password']) {
        echo json_encode([
            'success' => false,
            'message' => 'Password baru dan konfirmasi password tidak sama'
        ]);
        Yii::app()->end();
    }

    // Mobile policy: five characters minimum. Keep this aligned with passwordPolicy.ts.
    if (strlen($post['new_password']) < 5) {
        echo json_encode([
            'success' => false,
            'message' => 'Password baru minimal 5 karakter'
        ]);
        Yii::app()->end();
    }

    try {
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

        if (!password_verify($post['old_password'], $cek['password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Password lama salah'
            ]);
            Yii::app()->end();
        }

        $newPasswordHash = password_hash($post['new_password'], PASSWORD_BCRYPT);
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
            $data = Yii::app()->dbPrasi->createCommand()
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
    
    
    /* Replace the existing actionGetLogbook() method with this entire method. */
public function actionGetLogbook()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id_client', 'id_action', 'role', 'user_id') as $field) {
        if (!is_array($post) || !array_key_exists($field, $post)) {
            echo json_encode(array('success'=>false, 'message'=>$field.' wajib diisi')); Yii::app()->end();
        }
    }
    foreach (array('id_client', 'id_action', 'user_id') as $field) {
        if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success'=>false, 'message'=>$field.' wajib berupa angka positif')); Yii::app()->end();
        }
    }
    $page = isset($post['page']) && preg_match('/^[1-9][0-9]*$/', (string)$post['page']) ? (int)$post['page'] : 1;
    $limit = isset($post['limit']) && preg_match('/^[1-9][0-9]*$/', (string)$post['limit']) ? (int)$post['limit'] : 30;
    $limit = min(100, $limit); $offset = ($page - 1) * $limit;
    $role = strtolower(trim((string)$post['role']));
    $db = Yii::app()->dbPrasi;

    try {
        $actionForList = $db->createCommand('SELECT is_exam FROM m_action WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id'=>(int)$post['id_action'], ':client'=>(int)$post['id_client']))->queryRow();
        if (!$actionForList) throw new RuntimeException('action tidak ditemukan untuk client ini');
        $isExamAction = $this->createLogbookFlag($actionForList, 'is_exam');
        $params = array(':id_action'=>(string)$post['id_action'], ':id_client'=>(string)$post['id_client']);

        if ($role === 'ppds') {
            $roleFilter = 't.id_user = :user_id';
            $params[':user_id'] = (string)$post['user_id'];
        } elseif ($role === 'staff') {
            // Prasi-Bun: Staff sees Exam records created by that Staff user.
            if ($isExamAction) {
                $roleFilter = "mu.is_show = true AND mu.status = 'Active' AND t.created_by = :user_id";
                $params[':user_id'] = (string)$post['user_id'];
            } else {
                $roleFilter = "mu.is_show = true AND mu.status = 'Active' AND EXISTS ("
                    . "SELECT 1 FROM t_logbook_status tls WHERE tls.id_logbook = t.id "
                    . "AND tls.id_user = :user_id AND tls.id_client = :status_client "
                    . "AND tls.deleted_at IS NULL)";
                $params[':user_id'] = (string)$post['user_id'];
                $params[':status_client'] = (string)$post['id_client'];
            }
        } else $roleFilter = '1=1';

        $where = array('t.deleted_at IS NULL', 't.id_action = :id_action', 't.id_client = :id_client', '('.$roleFilter.')');
        if (isset($post['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$post['start_date'])) {
            $where[] = 't.date >= CAST(:start_date AS date)'; $params[':start_date'] = $post['start_date'];
        }
        if (isset($post['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$post['end_date'])) {
            $where[] = "t.date < (CAST(:end_date AS date) + INTERVAL '1 day')"; $params[':end_date'] = $post['end_date'];
        }
        if (isset($post['title']) && is_scalar($post['title']) && trim((string)$post['title']) !== '') {
            $where[] = 't.title ILIKE :title'; $params[':title'] = '%'.trim((string)$post['title']).'%';
        }
        foreach (array('id_hospital'=>'hospital', 'id_category'=>'category', 'id_another_role'=>'another role', 'id_stase'=>'stase') as $field=>$label) {
            if (isset($post[$field]) && $post[$field] !== '' && $post[$field] !== null) {
                if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) throw new RuntimeException($label.' tidak valid');
                $where[] = 't.'.$field.' = :'.$field; $params[':'.$field] = (string)$post[$field];
            }
        }
        if (isset($post['exam_result']) && is_scalar($post['exam_result']) && trim((string)$post['exam_result']) !== '') {
            $where[] = 't.exam_result = :exam_result'; $params[':exam_result'] = trim((string)$post['exam_result']);
        }
        if (isset($post['id_ppds']) && $post['id_ppds'] !== '' && $post['id_ppds'] !== null) {
            if (!preg_match('/^[1-9][0-9]*$/', (string)$post['id_ppds'])) throw new RuntimeException('PPDS tidak valid');
            $ppds = $db->createCommand("SELECT u.id FROM m_user u INNER JOIN m_role r ON r.id=u.id_role WHERE u.id=:id AND u.id_client=:client AND u.is_show=true AND u.status='Active' AND LOWER(TRIM(r.name))='ppds'")
                ->bindValues(array(':id'=>(int)$post['id_ppds'], ':client'=>(int)$post['id_client']))->queryRow();
            if (!$ppds) throw new RuntimeException('PPDS tidak tersedia untuk client ini');
            $where[] = 't.id_user = :id_ppds';
            $params[':id_ppds'] = (string)$post['id_ppds'];
        }
        if (isset($post['id_staff']) && $post['id_staff'] !== '' && $post['id_staff'] !== null) {
            if (!preg_match('/^[1-9][0-9]*$/', (string)$post['id_staff'])) throw new RuntimeException('staff tidak valid');
            $staff = $db->createCommand("SELECT u.id FROM m_user u INNER JOIN m_role r ON r.id=u.id_role WHERE u.id=:id AND u.id_client=:client AND u.is_show=true AND u.status='Active' AND LOWER(TRIM(r.name)) IN ('staff','staff jejaring')")
                ->bindValues(array(':id'=>(int)$post['id_staff'], ':client'=>(int)$post['id_client']))->queryRow();
            if (!$staff) throw new RuntimeException('staff tidak tersedia untuk client ini');
            $where[] = 'EXISTS (SELECT 1 FROM t_logbook_status tls_filter WHERE tls_filter.id_logbook=t.id AND tls_filter.id_user=:id_staff AND tls_filter.id_client=:staff_client AND tls_filter.deleted_at IS NULL)';
            $params[':id_staff'] = (string)$post['id_staff']; $params[':staff_client'] = (string)$post['id_client'];
        }
        if (array_key_exists('is_presentation', $post)) {
            $rawPresentation = $post['is_presentation'];
            if (in_array($rawPresentation, array(true, 1, '1', 'true', 't'), true)) $presentation = 'true';
            elseif (in_array($rawPresentation, array(false, 0, '0', 'false', 'f'), true)) $presentation = 'false';
            else throw new RuntimeException('presentasi tidak valid');
            $where[] = 't.is_presentation = CAST(:is_presentation AS boolean)'; $params[':is_presentation'] = $presentation;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int)$db->createCommand('SELECT COUNT(*) FROM t_logbook t LEFT JOIN m_user mu ON mu.id=t.id_user AND mu.deleted_at IS NULL WHERE '.$whereSql)->bindValues($params)->queryScalar();
        $sql = 'SELECT t.*, mu.display_name AS _peserta_display_name, mh.id AS _hospital_id, mh.name AS _hospital_name, '
            . 'mac.id AS _category_id, mac.name AS _category_name, ms.id AS _stase_id, ms.name AS _stase_name '
            . 'FROM t_logbook t LEFT JOIN m_user mu ON mu.id=t.id_user AND mu.deleted_at IS NULL '
            . 'LEFT JOIN m_hospital mh ON mh.id=t.id_hospital LEFT JOIN m_action_category mac ON mac.id=t.id_category '
            . 'LEFT JOIN m_stase ms ON ms.id=t.id_stase WHERE '.$whereSql.' ORDER BY t.date DESC, t.created_date DESC LIMIT :limit OFFSET :offset';
        $command = $db->createCommand($sql)->bindValues($params);
        $command->bindValue(':limit', $limit, PDO::PARAM_INT); $command->bindValue(':offset', $offset, PDO::PARAM_INT);
        $rows = $command->queryAll();
        $ids = array_map(function($row) { return (int)$row['id']; }, $rows);
        $statusByLogbook = array(); $asmByLogbook = array();
        if ($ids) {
            $in = array(); $inParams = array();
            foreach ($ids as $i=>$id) { $key=':logbook_'.$i; $in[]=$key; $inParams[$key]=$id; }
            $statuses = $db->createCommand('SELECT tls.*, mar.role, mu.display_name AS staff_name FROM t_logbook_status tls LEFT JOIN m_action_role mar ON mar.id=tls.id_action_role LEFT JOIN m_user mu ON mu.id=tls.id_user WHERE tls.id_logbook IN ('.implode(',', $in).')')->bindValues($inParams)->queryAll();
            foreach ($statuses as $status) $statusByLogbook[$status['id_logbook']][] = $status;
            $asms = $db->createCommand('SELECT * FROM t_logbook_asm WHERE id_logbook IN ('.implode(',', $in).')')->bindValues($inParams)->queryAll();
            foreach ($asms as $asm) $asmByLogbook[$asm['id_logbook']][] = $asm;
        }
        $data = array();
        foreach ($rows as $row) {
            $logbook = array(); foreach ($row as $key=>$value) if (strpos($key, '_') !== 0) $logbook[$key]=$value;
            $logbook['m_user'] = array('id'=>$row['id_user'], 'display_name'=>$row['_peserta_display_name']);
            $logbook['m_hospital'] = $row['_hospital_id'] ? array('id'=>$row['_hospital_id'], 'name'=>$row['_hospital_name']) : null;
            $logbook['m_action_category'] = $row['_category_id'] ? array('id'=>$row['_category_id'], 'name'=>$row['_category_name']) : null;
            $logbook['m_stase'] = $row['_stase_id'] ? array('id'=>$row['_stase_id'], 'name'=>$row['_stase_name']) : null;
            $logbook['t_logbook_status'] = isset($statusByLogbook[$row['id']]) ? $statusByLogbook[$row['id']] : array();
            $logbook['t_logbook_asm'] = isset($asmByLogbook[$row['id']]) ? $asmByLogbook[$row['id']] : array();
            $data[] = $logbook;
        }
        echo json_encode(array('success'=>true, 'data'=>$data, 'total'=>$total, 'page'=>$page, 'limit'=>$limit, 'has_more'=>($offset + count($data)) < $total));
    } catch (Throwable $e) {
        Yii::log('GetLogbook failed: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
        echo json_encode(array('success'=>false, 'message'=>'Gagal memuat data logbook'));
    }
    Yii::app()->end();
}


    
    
//     public function actionGetLogbook()
// {
//     header('Content-Type: application/json; charset=utf-8');
//     $post = json_decode(file_get_contents('php://input'), true);
//     foreach (array('id_client', 'id_action', 'role', 'user_id') as $field) {
//         if (!is_array($post) || !array_key_exists($field, $post)) {
//             echo json_encode(array('success'=>false, 'message'=>$field.' wajib diisi')); Yii::app()->end();
//         }
//     }
//     foreach (array('id_client', 'id_action', 'user_id') as $field) {
//         if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
//             echo json_encode(array('success'=>false, 'message'=>$field.' wajib berupa angka positif')); Yii::app()->end();
//         }
//     }

//     $page = isset($post['page']) && preg_match('/^[1-9][0-9]*$/', (string)$post['page'])
//         ? (int)$post['page'] : 1;
//     $limit = isset($post['limit']) && preg_match('/^[1-9][0-9]*$/', (string)$post['limit'])
//         ? (int)$post['limit'] : 30;
//     $limit = min(100, $limit);
//     $offset = ($page - 1) * $limit;
//     $role = strtolower(trim((string)$post['role']));

//     $db = Yii::app()->dbPrasi;
//     try {
//         $params = array(':id_action'=>(string)$post['id_action'], ':id_client'=>(string)$post['id_client']);
//         if ($role === 'ppds') {
//             $roleFilter = 't.id_user = :user_id';
//             $params[':user_id'] = (string)$post['user_id'];
//         } elseif ($role === 'staff') {
//             /* A Staff/Jejaring list contains only logbooks assigned to this
//              * verifier, never every active PPDS logbook for the action. */
//             $roleFilter = "mu.is_show = true AND mu.status = 'Active' AND EXISTS ("
//                 . "SELECT 1 FROM t_logbook_status tls WHERE tls.id_logbook = t.id "
//                 . "AND tls.id_user = :user_id AND tls.id_client = :status_client "
//                 . "AND tls.deleted_at IS NULL)";
//             $params[':user_id'] = (string)$post['user_id'];
//             $params[':status_client'] = (string)$post['id_client'];
//         } else {
//             $roleFilter = '1=1';
//         }

//         $where = array('t.deleted_at IS NULL', 't.id_action = :id_action', 't.id_client = :id_client', '('.$roleFilter.')');
//         if (isset($post['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$post['start_date'])) {
//             $where[] = 't.date >= CAST(:start_date AS date)'; $params[':start_date'] = $post['start_date'];
//         }
//         if (isset($post['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$post['end_date'])) {
//             $where[] = "t.date < (CAST(:end_date AS date) + INTERVAL '1 day')"; $params[':end_date'] = $post['end_date'];
//         }
//         if (isset($post['title']) && is_scalar($post['title']) && trim((string)$post['title']) !== '') {
//             $where[] = 't.title ILIKE :title'; $params[':title'] = '%'.trim((string)$post['title']).'%';
//         }
//         foreach (array('id_hospital'=>'hospital', 'id_category'=>'category', 'id_another_role'=>'another role', 'id_stase'=>'stase') as $field=>$label) {
//             if (isset($post[$field]) && $post[$field] !== '' && $post[$field] !== null) {
//                 if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) throw new RuntimeException($label.' tidak valid');
//                 $where[] = 't.'.$field.' = :'.$field; $params[':'.$field] = (string)$post[$field];
//             }
//         }
//         if (isset($post['exam_result']) && is_scalar($post['exam_result']) && trim((string)$post['exam_result']) !== '') {
//             $where[] = 't.exam_result = :exam_result'; $params[':exam_result'] = trim((string)$post['exam_result']);
//         }
//         $whereSql = implode(' AND ', $where);

//         $total = (int)$db->createCommand('SELECT COUNT(*) FROM t_logbook t LEFT JOIN m_user mu ON mu.id=t.id_user AND mu.deleted_at IS NULL WHERE '.$whereSql)
//             ->bindValues($params)->queryScalar();
//         $sql = 'SELECT t.*, mu.display_name AS _peserta_display_name, mh.id AS _hospital_id, mh.name AS _hospital_name, '
//             . 'mac.id AS _category_id, mac.name AS _category_name, ms.id AS _stase_id, ms.name AS _stase_name '
//             . 'FROM t_logbook t LEFT JOIN m_user mu ON mu.id=t.id_user AND mu.deleted_at IS NULL '
//             . 'LEFT JOIN m_hospital mh ON mh.id=t.id_hospital LEFT JOIN m_action_category mac ON mac.id=t.id_category '
//             . 'LEFT JOIN m_stase ms ON ms.id=t.id_stase WHERE '.$whereSql.' ORDER BY t.date DESC, t.created_date DESC LIMIT :limit OFFSET :offset';
//         $command = $db->createCommand($sql)->bindValues($params);
//         $command->bindValue(':limit', $limit, PDO::PARAM_INT);
//         $command->bindValue(':offset', $offset, PDO::PARAM_INT);
//         $rows = $command->queryAll();

//         $ids = array_map(function($row) { return (int)$row['id']; }, $rows);
//         $statusByLogbook = array(); $asmByLogbook = array();
//         if ($ids) {
//             $in = array(); $inParams = array();
//             foreach ($ids as $i=>$id) { $key=':logbook_'.$i; $in[]=$key; $inParams[$key]=$id; }
//             $statuses = $db->createCommand('SELECT tls.*, mar.role, mu.display_name AS staff_name FROM t_logbook_status tls LEFT JOIN m_action_role mar ON mar.id=tls.id_action_role LEFT JOIN m_user mu ON mu.id=tls.id_user WHERE tls.id_logbook IN ('.implode(',', $in).')')
//                 ->bindValues($inParams)->queryAll();
//             foreach ($statuses as $status) $statusByLogbook[$status['id_logbook']][] = $status;
//             $asms = $db->createCommand('SELECT * FROM t_logbook_asm WHERE id_logbook IN ('.implode(',', $in).')')->bindValues($inParams)->queryAll();
//             foreach ($asms as $asm) $asmByLogbook[$asm['id_logbook']][] = $asm;
//         }
//         $data = array();
//         foreach ($rows as $row) {
//             $logbook = array();
//             foreach ($row as $key=>$value) if (strpos($key, '_') !== 0) $logbook[$key]=$value;
//             $logbook['m_user'] = array('id'=>$row['id_user'], 'display_name'=>$row['_peserta_display_name']);
//             $logbook['m_hospital'] = $row['_hospital_id'] ? array('id'=>$row['_hospital_id'], 'name'=>$row['_hospital_name']) : null;
//             $logbook['m_action_category'] = $row['_category_id'] ? array('id'=>$row['_category_id'], 'name'=>$row['_category_name']) : null;
//             $logbook['m_stase'] = $row['_stase_id'] ? array('id'=>$row['_stase_id'], 'name'=>$row['_stase_name']) : null;
//             $logbook['t_logbook_status'] = isset($statusByLogbook[$row['id']]) ? $statusByLogbook[$row['id']] : array();
//             $logbook['t_logbook_asm'] = isset($asmByLogbook[$row['id']]) ? $asmByLogbook[$row['id']] : array();
//             $data[] = $logbook;
//         }
//         echo json_encode(array('success'=>true, 'data'=>$data, 'total'=>$total, 'page'=>$page, 'limit'=>$limit, 'has_more'=>($offset + count($data)) < $total));
//     } catch (Throwable $e) {
//         Yii::log('GetLogbook failed: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
//         echo json_encode(array('success'=>false, 'message'=>'Gagal memuat data logbook'));
//     }
//     Yii::app()->end();
// }



    // public function actionGetLogbook() {
    //     header('Content-Type: application/json');
    //     $rest_json = file_get_contents("php://input");
    //     $post = json_decode($rest_json, true);
    
    //     if (!isset($post['id_client']) || !isset($post['id_action']) || !isset($post['role']) || !isset($post['user_id'])) {
    //         echo json_encode([
    //             'success' => false,
    //             'message' => 'id_client, id_action, role, user_id wajib diisi'
    //         ]);
    //         Yii::app()->end();
    //     }
    
    //     try {
    //         $role   = $post['role'];
    //         $userId = $post['user_id'];
    //         $params = [
    //             ':id_action' => $post['id_action'],
    //             ':id_client' => $post['id_client'],
    //         ];
    
    //         // Filter berdasarkan role
    //         if ($role === 'ppds') {
    //             $roleFilter = "t.id_user = :user_id";
    //             $params[':user_id'] = $userId;
    //         } elseif ($role === 'staff') {
    //             $roleFilter = "
    //                 m_user.is_show = true 
    //                 AND m_user.status = 'Active'
    //             ";
    //             // hapus $params[':user_id'] di sini
    //         } else {
    //             $roleFilter = "1=1";
    //         }
    
    //         $sql = "
    //             SELECT
    //                 t.*,
    //                 m_user.display_name AS _peserta_display_name,
    //                 m_hosp.id AS _hospital_id,
    //                 m_hosp.name AS _hospital_name,
    //                 mac.id AS _category_id,
    //                 mac.name AS _category_name,
    //                 m_stase.id AS _stase_id,
    //                 m_stase.name AS _stase_name,
    //                 m_stase.id_stage AS _stase_id_stage,
    //                 m_stase.id_client AS _stase_id_client,
    //                 m_stase.sequence AS _stase_sequence,
    //                 CASE
    //                     WHEN t.verified = true THEN 'Verified'
    //                     WHEN t.verified = false AND t.verified_status = 'rejected' THEN 'Rejected'
    //                     ELSE '-'
    //                 END AS _status,
    //                 COALESCE(
    //                     (SELECT ROUND(AVG(score::numeric), 2)
    //                      FROM t_logbook_asm WHERE id_logbook = t.id AND score > 0)::text,
    //                     '-'
    //                 ) AS _score
    //             FROM t_logbook t
    //             LEFT JOIN m_user m_user ON t.id_user = m_user.id AND m_user.deleted_at IS NULL
    //             LEFT JOIN m_hospital m_hosp ON t.id_hospital = m_hosp.id
    //             LEFT JOIN m_action_category mac ON t.id_category = mac.id
    //             LEFT JOIN m_stase ON t.id_stase = m_stase.id
    //             WHERE
    //                 t.deleted_at IS NULL
    //                 AND t.id_action = :id_action
    //                 AND t.id_client = :id_client
    //                 AND ($roleFilter)
    //             ORDER BY t.date DESC, t.created_date DESC
    //         ";
    
    //         $command = Yii::app()->dbPrasi->createCommand($sql);
    //         foreach ($params as $key => $value) {
    //             $command->bindValue($key, $value);
    //         }
    
    //         $rows = $command->queryAll();
    
    //         // Susun nested structure
    //         $data = [];
    //         foreach ($rows as $row) {
    //             $logbook = [];
    
    //             // Field utama t_logbook
    //             foreach ($row as $key => $value) {
    //                 if (strpos($key, '_') !== 0) {
    //                     $logbook[$key] = $value;
    //                 }
    //             }
    
    //             // Nested m_user
    //             $logbook['m_user'] = [
    //                 'display_name' => $row['_peserta_display_name'],
    //             ];
    
    //             // Nested m_hospital
    //             $logbook['m_hospital'] = $row['_hospital_id'] ? [
    //                 'id'   => $row['_hospital_id'],
    //                 'name' => $row['_hospital_name'],
    //             ] : null;
    
    //             // Nested m_action_category
    //             $logbook['m_action_category'] = [
    //                 'id'   => $row['_category_id'],
    //                 'name' => $row['_category_name'],
    //             ];
    
    //             // Nested m_stase
    //             $logbook['m_stase'] = $row['_stase_id'] ? [
    //                 'id'        => $row['_stase_id'],
    //                 'name'      => $row['_stase_name'],
    //                 'id_stage'  => $row['_stase_id_stage'],
    //                 'id_client' => $row['_stase_id_client'],
    //                 'sequence'  => $row['_stase_sequence'],
    //             ] : null;
    
    //             // Nested t_logbook_status (ambil terpisah)
    //             $statusSql = "
    //                 SELECT tls.*, mar.role, mu.display_name AS staff_name
    //                 FROM t_logbook_status tls
    //                 LEFT JOIN m_action_role mar ON tls.id_action_role = mar.id
    //                 LEFT JOIN m_user mu ON tls.id_user = mu.id
    //                 WHERE tls.id_logbook = :id_logbook
    //             ";
    //             $logbook['t_logbook_status'] = Yii::app()->dbPrasi->createCommand($statusSql)
    //                 ->bindValue(':id_logbook', $row['id'])
    //                 ->queryAll();
    
    //             // Nested t_logbook_asm (ambil terpisah)
    //             $asmSql = "SELECT * FROM t_logbook_asm WHERE id_logbook = :id_logbook";
    //             $logbook['t_logbook_asm'] = Yii::app()->dbPrasi->createCommand($asmSql)
    //                 ->bindValue(':id_logbook', $row['id'])
    //                 ->queryAll();
    
    //             $logbook['status'] = $row['_status'];
    //             $logbook['score']  = $row['_score'];
    
    //             $data[] = $logbook;
    //         }
    
    //         echo json_encode([
    //             'success' => true,
    //             'total'   => count($data),
    //             'data'    => $data
    //         ]);
    
    //     } catch (Exception $e) {
    //         echo json_encode([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    
    //     Yii::app()->end();
    // }
    
    
public function actionGetNotification()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id_client','id_user') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success'=>false,'message'=>$field.' wajib berupa angka positif'));
            Yii::app()->end();
        }
    }
    $page = isset($post['page']) && preg_match('/^[1-9][0-9]*$/',(string)$post['page']) ? (int)$post['page'] : 1;
    $limit = isset($post['limit']) && preg_match('/^[1-9][0-9]*$/',(string)$post['limit']) ? min(100,(int)$post['limit']) : 30;
    $offset = ($page - 1) * $limit;
    try {
        $db=Yii::app()->dbPrasi;
        $params=array(':id_user'=>(string)$post['id_user'],':id_client'=>(string)$post['id_client']);
        $where='tn.id_user=:id_user AND tn.id_client=:id_client AND tn.deleted_at IS NULL';
        $total=(int)$db->createCommand('SELECT COUNT(*) FROM t_notif tn WHERE '.$where)->bindValues($params)->queryScalar();
        $unread=(int)$db->createCommand('SELECT COUNT(*) FROM t_notif tn WHERE '.$where.' AND tn.read=false')->bindValues($params)->queryScalar();
        $cmd=$db->createCommand("SELECT tn.id,tn.id_user,tn.id_client,tn.id_logbook,tn.type,tn.message,tn.url,tn.read,tn.date,
                ppds.display_name AS ppds_name,action.name AS action_name
            FROM t_notif tn
            LEFT JOIN t_logbook lb ON lb.id=tn.id_logbook AND lb.id_client=tn.id_client AND lb.deleted_at IS NULL
            LEFT JOIN m_user ppds ON ppds.id=lb.id_user AND ppds.id_client=lb.id_client AND ppds.deleted_at IS NULL
            LEFT JOIN m_action action ON action.id=lb.id_action AND action.id_client=lb.id_client
            WHERE {$where} ORDER BY tn.date DESC,tn.id DESC LIMIT :limit OFFSET :offset")
            ->bindValues($params);
        $cmd->bindValue(':limit',$limit,PDO::PARAM_INT);
        $cmd->bindValue(':offset',$offset,PDO::PARAM_INT);
        $rows=$cmd->queryAll();
        echo json_encode(array('success'=>true,'total'=>$total,'unread_total'=>$unread,'page'=>$page,'limit'=>$limit,'has_more'=>($offset+count($rows))<$total,'data'=>$rows));
    } catch (Throwable $e) {
        Yii::log('GetNotification failed: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
        echo json_encode(array('success'=>false,'message'=>'Gagal memuat notifikasi'));
    }
    Yii::app()->end();
}

public function actionReadNotification()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id','id_client','id_user') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success'=>false,'message'=>$field.' wajib berupa angka positif')); Yii::app()->end();
        }
    }
    $updated = Yii::app()->dbPrasi->createCommand(
        'UPDATE t_notif SET read=true WHERE id=:id AND id_user=:id_user AND id_client=:id_client AND deleted_at IS NULL'
    )->execute(array(':id'=>(string)$post['id'],':id_user'=>(string)$post['id_user'],':id_client'=>(string)$post['id_client']));
    echo json_encode(array('success'=>$updated===1,'message'=>$updated===1?'Notifikasi sudah dibaca':'Notifikasi tidak ditemukan'));
    Yii::app()->end();
}

public function actionReadAllNotifications()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id_client','id_user') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success'=>false,'message'=>$field.' wajib berupa angka positif')); Yii::app()->end();
        }
    }
    $updated = Yii::app()->dbPrasi->createCommand(
        'UPDATE t_notif SET read=true WHERE id_user=:id_user AND id_client=:id_client AND read=false AND deleted_at IS NULL'
    )->execute(array(':id_user'=>(string)$post['id_user'],':id_client'=>(string)$post['id_client']));
    echo json_encode(array('success'=>true,'message'=>'Notifikasi sudah ditandai dibaca','updated'=>$updated));
    Yii::app()->end();
}


    
    // todo list 
    
public function actionGetTodo()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id_client', 'id_user') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success' => false, 'message' => $field . ' wajib berupa angka positif'));
            Yii::app()->end();
        }
    }

    $page = isset($post['page']) && preg_match('/^[1-9][0-9]*$/', (string)$post['page']) ? (int)$post['page'] : 1;
    $limit = isset($post['limit']) && preg_match('/^[1-9][0-9]*$/', (string)$post['limit']) ? min((int)$post['limit'], 100) : 30;
    $offset = ($page - 1) * $limit;
    $params = array(':id_client' => (string)$post['id_client'], ':id_user' => (string)$post['id_user']);
    $filters = array();

    if (array_key_exists('ppds_ids', $post)) {
        if (!is_array($post['ppds_ids'])) {
            echo json_encode(array('success' => false, 'message' => 'ppds_ids harus berupa array ID PPDS'));
            Yii::app()->end();
        }
        $ppdsIds = array();
        foreach ($post['ppds_ids'] as $id) {
            if (!is_scalar($id) || !preg_match('/^[1-9][0-9]*$/', (string)$id)) {
                echo json_encode(array('success' => false, 'message' => 'ppds_ids harus berisi angka positif'));
                Yii::app()->end();
            }
            $ppdsIds[] = (string)$id;
        }
        $ppdsIds = array_values(array_unique($ppdsIds));
        $ppdsConditions = array();
        foreach ($ppdsIds as $index => $id) {
            $placeholder = ':ppds_' . $index;
            $params[$placeholder] = $id;
            $ppdsConditions[] = 'lb.id_user = ' . $placeholder;
        }
        if (!$ppdsIds) {
            $filters[] = '1 = 0';
        } else {
            // PPDS is a multi-select: one matching participant is enough.
            $filters[] = '(' . implode(' OR ', $ppdsConditions) . ')';
        }
    }

    foreach (array('id_action' => 'lb.id_action', 'id_hospital' => 'lb.id_hospital', 'id_stage' => 'ms.id_stage') as $field => $column) {
        if (array_key_exists($field, $post) && $post[$field] !== null && $post[$field] !== '') {
            if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
                echo json_encode(array('success' => false, 'message' => $field . ' harus berupa angka positif'));
                Yii::app()->end();
            }
            $placeholder = ':' . $field;
            $params[$placeholder] = (string)$post[$field];
            $filters[] = $column . ' = ' . $placeholder;
        }
    }

    foreach (array('start_date' => '>=', 'end_date' => '<=') as $field => $operator) {
        if (array_key_exists($field, $post) && $post[$field] !== null && $post[$field] !== '') {
            $date = (string)$post[$field];
            $parsed = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                echo json_encode(array('success' => false, 'message' => $field . ' harus berformat YYYY-MM-DD'));
                Yii::app()->end();
            }
            $params[':' . $field] = $date;
            $filters[] = 'lb.date::date ' . $operator . ' :' . $field;
        }
    }
    if (isset($params[':start_date'], $params[':end_date']) && $params[':start_date'] > $params[':end_date']) {
        echo json_encode(array('success' => false, 'message' => 'start_date tidak boleh setelah end_date'));
        Yii::app()->end();
    }

    try {
        $baseWhere = "
            lb.deleted_at IS NULL
            AND lb.verified = false
            AND lb.id_client = :id_client
            AND ma.identifier NOT IN ('exam', 'stase')
            AND mu.is_deleted = false
            AND mu.is_show = true
            AND mu.status = 'Active'";
        if ($filters) {
            $baseWhere .= ' AND ' . implode(' AND ', $filters);
        }
        $from = "
            FROM t_logbook lb
            INNER JOIN t_logbook_status tls ON tls.id_logbook = lb.id
            INNER JOIN m_action ma ON ma.id = lb.id_action
            INNER JOIN m_user mu ON mu.id = lb.id_user
            LEFT JOIN m_semester ms ON ms.id = lb.id_semester
            LEFT JOIN m_stage stg ON stg.id = ms.id_stage
            WHERE tls.id_user = :id_user
              AND tls.status IN ('pending', 'revised')
              AND tls.deleted_at IS NULL
              AND " . $baseWhere;

        $db = Yii::app()->dbPrasi;
        $total = (int)$db->createCommand('SELECT COUNT(DISTINCT lb.id) ' . $from)
            ->bindValues($params)->queryScalar();
        $sql = "
            SELECT DISTINCT
                lb.id, lb.id_action, lb.id_user, lb.id_hospital, lb.date,
                lb.id_category, lb.id_client, lb.verified,
                ma.name AS m_action_name, ma.identifier AS m_action_identifier,
                mu.display_name AS m_user_display_name,
                ms.id AS semester_id, ms.name AS semester_name, ms.id_stage,
                stg.id AS stage_id, stg.name AS stage_name, stg.label_color AS stage_label_color
            " . $from . "
            ORDER BY lb.date DESC, lb.id DESC
            LIMIT :limit OFFSET :offset";
        $command = $db->createCommand($sql)->bindValues($params);
        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);
        $rows = $command->queryAll();

        $data = array();
        foreach ($rows as $row) {
            $data[] = array(
                'id' => (int)$row['id'], 'id_action' => (int)$row['id_action'],
                'id_user' => (int)$row['id_user'], 'id_hospital' => $row['id_hospital'],
                'date' => $row['date'], 'id_category' => $row['id_category'],
                'id_client' => (int)$row['id_client'], 'verified' => $row['verified'],
                'm_action' => array('name' => $row['m_action_name'], 'identifier' => $row['m_action_identifier']),
                'm_user' => array('display_name' => $row['m_user_display_name']),
                'm_semester' => $row['semester_id'] ? array(
                    'id' => $row['semester_id'], 'name' => $row['semester_name'], 'id_stage' => $row['id_stage'],
                    'm_stage' => $row['stage_id'] ? array('id' => $row['stage_id'], 'name' => $row['stage_name'], 'label_color' => $row['stage_label_color']) : null,
                ) : null,
            );
        }
        echo json_encode(array(
            'success' => true, 'total' => $total, 'page' => $page, 'limit' => $limit,
            'has_more' => $page * $limit < $total, 'data' => $data,
        ));
    } catch (Exception $e) {
        Yii::log('GetTodo failed: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
        echo json_encode(array('success' => false, 'message' => 'Gagal memuat To Do'));
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
                    mac.points AS _mac_points,
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
                '_ma_id', '_ma_id_type', '_ma_name', '_ma_id_client', '_mac_id', '_mac_name', '_mac_points',
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
                'points' => $row['_mac_points'] === null ? null : (int) $row['_mac_points'],
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
    
    
    public function actionVerifyAllTodo()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id_client', 'id_user') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success'=>false, 'message'=>$field.' wajib berupa angka positif'));
            Yii::app()->end();
        }
    }

    $preview = isset($post['preview']) && ($post['preview'] === true || $post['preview'] === 1 || $post['preview'] === '1' || $post['preview'] === 'true');
    $params = array(':id_user'=>(string)$post['id_user'], ':id_client'=>(string)$post['id_client']);
    $filters = array();

    if (array_key_exists('ppds_ids', $post)) {
        if (!is_array($post['ppds_ids'])) { http_response_code(422); echo json_encode(array('success'=>false, 'message'=>'ppds_ids harus berupa array ID PPDS')); Yii::app()->end(); }
        $conditions = array();
        foreach (array_values(array_unique($post['ppds_ids'])) as $index=>$id) {
            if (!is_scalar($id) || !preg_match('/^[1-9][0-9]*$/', (string)$id)) { http_response_code(422); echo json_encode(array('success'=>false, 'message'=>'ppds_ids harus berisi angka positif')); Yii::app()->end(); }
            $key = ':ppds_' . $index;
            $params[$key] = (string)$id;
            $conditions[] = 'lb.id_user=' . $key;
        }
        $filters[] = $conditions ? '(' . implode(' OR ', $conditions) . ')' : '1=0';
    }
    foreach (array('id_action'=>'lb.id_action', 'id_hospital'=>'lb.id_hospital', 'id_stage'=>'ms.id_stage') as $field=>$column) {
        if (!array_key_exists($field, $post) || $post[$field] === null || $post[$field] === '') continue;
        if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) { http_response_code(422); echo json_encode(array('success'=>false, 'message'=>$field . ' harus berupa angka positif')); Yii::app()->end(); }
        $key = ':' . $field;
        $params[$key] = (string)$post[$field];
        $filters[] = $column . '=' . $key;
    }
    foreach (array('start_date'=>'>=', 'end_date'=>'<=') as $field=>$operator) {
        if (!array_key_exists($field, $post) || $post[$field] === null || $post[$field] === '') continue;
        if (!is_string($post[$field]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $post[$field])) { http_response_code(422); echo json_encode(array('success'=>false, 'message'=>$field . ' harus YYYY-MM-DD')); Yii::app()->end(); }
        $params[':' . $field] = $post[$field];
        $filters[] = 'DATE(lb.date) ' . $operator . ' :' . $field;
    }
    if (isset($params[':start_date'], $params[':end_date']) && $params[':start_date'] > $params[':end_date']) {
        http_response_code(422);
        echo json_encode(array('success'=>false, 'message'=>'start_date tidak boleh setelah end_date'));
        Yii::app()->end();
    }
    $filterSql = $filters ? ' AND ' . implode(' AND ', $filters) : '';

    $db = Yii::app()->dbPrasi;
    $transaction = null;
    try {
        $transaction = $db->beginTransaction();
        $joins = ' INNER JOIN t_logbook lb ON lb.id=tls.id_logbook INNER JOIN m_action ma ON ma.id=lb.id_action LEFT JOIN m_stase ms ON ms.id=lb.id_stase ';
        $baseWhere = "tls.id_user=:id_user AND tls.id_client=:id_client
            AND tls.deleted_at IS NULL AND LOWER(COALESCE(tls.status,'pending')) IN ('pending','revised')
            AND lb.id_client=:id_client AND lb.deleted_at IS NULL AND lb.verified=false" . $filterSql;

        $scoreSql = 'SELECT COUNT(*) FROM t_logbook_status tls ' . $joins . ' WHERE ' . $baseWhere . ' AND COALESCE(ma.has_score, false)=true';
        $specialSql = 'SELECT COUNT(*) FROM t_logbook_status tls ' . $joins . ' WHERE ' . $baseWhere . " AND (LOWER(COALESCE(ma.identifier,''))='morbiditas' OR LOWER(COALESCE(ma.name,'')) LIKE '%morbiditas%')";
        $skippedScore = (int)$db->createCommand($scoreSql)->bindValues($params)->queryScalar();
        $skippedSpecial = (int)$db->createCommand($specialSql)->bindValues($params)->queryScalar();

        $candidateSql = "SELECT tls.id, tls.id_logbook, tls.notes, lb.id_user AS ppds_id,
                lb.date AS logbook_date, ma.id AS id_action, ma.name AS action_name, ma.multiple_verification
            FROM t_logbook_status tls " . $joins . ' WHERE ' . $baseWhere . "
              AND COALESCE(ma.has_score, false)=false
              AND LOWER(COALESCE(ma.identifier,'')) NOT IN ('exam','stase','morbiditas')
              AND LOWER(COALESCE(ma.name,'')) NOT LIKE '%morbiditas%'
            ORDER BY CASE WHEN tls.notes='__staff_jejaring__' THEN 0 ELSE 1 END, tls.date_time ASC" . ($preview ? '' : ' FOR UPDATE OF tls, lb');
        $candidates = $db->createCommand($candidateSql)->bindValues($params)->queryAll();

        $verified = 0;
        $skippedSequential = 0;
        foreach ($candidates as $candidate) {
            if ((string)$candidate['notes'] !== '__staff_jejaring__') {
                $blocked = (int)$db->createCommand(
                    "SELECT COUNT(*) FROM t_logbook_status sibling
                     WHERE sibling.id_logbook=:id_logbook AND sibling.id_client=:id_client
                       AND sibling.deleted_at IS NULL AND sibling.notes='__staff_jejaring__'
                       AND LOWER(COALESCE(sibling.status,'pending')) IN ('pending','revised')"
                )->bindValues(array(':id_logbook'=>(string)$candidate['id_logbook'], ':id_client'=>(string)$post['id_client']))->queryScalar();
                if ($blocked > 0) { $skippedSequential++; continue; }
            }
            if ($preview) { $verified++; continue; }

            $changed = $db->createCommand(
                "UPDATE t_logbook_status SET status='verified'
                 WHERE id=:id AND id_user=:id_user AND id_client=:id_client
                   AND deleted_at IS NULL AND LOWER(COALESCE(status,'pending')) IN ('pending','revised')"
            )->execute(array(':id'=>(string)$candidate['id'], ':id_user'=>(string)$post['id_user'], ':id_client'=>(string)$post['id_client']));
            if ($changed !== 1) continue;
            $verified++;
            $parentVerified = false;
            if (!$candidate['multiple_verification']) $parentVerified = true;
            else {
                $remaining = (int)$db->createCommand(
                    "SELECT COUNT(*) FROM t_logbook_status sibling
                     WHERE sibling.id_logbook=:id_logbook AND sibling.id_client=:id_client
                       AND sibling.deleted_at IS NULL AND LOWER(COALESCE(sibling.status,'pending')) <> 'verified'"
                )->bindValues(array(':id_logbook'=>(string)$candidate['id_logbook'], ':id_client'=>(string)$post['id_client']))->queryScalar();
                $parentVerified = $remaining === 0;
            }
            if ($parentVerified) {
                $parentChanged = $db->createCommand(
                    "UPDATE t_logbook SET verified=true, verified_status='verified'
                     WHERE id=:id AND id_client=:id_client AND verified=false AND deleted_at IS NULL"
                )->execute(array(':id'=>(string)$candidate['id_logbook'], ':id_client'=>(string)$post['id_client']));
                if ($parentChanged === 1 && method_exists($this, 'logbookStatusNotifyPpds')) {
                    try {
                        $this->logbookStatusNotifyPpds($db, array('id'=>$candidate['id_logbook'], 'id_action'=>$candidate['id_action'], 'ppds_id'=>$candidate['ppds_id'], 'logbook_date'=>$candidate['logbook_date'], 'action_name'=>$candidate['action_name']), $post, 'verified');
                    } catch (Throwable $notificationError) {
                        Yii::log('VerifyAllTodo notification failed: '.$notificationError->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
                    }
                }
            }
        }
        $transaction->commit();
        echo json_encode(array('success'=>true, 'message'=>$preview ? 'Preview Verify All' : 'Verify All selesai', 'verified'=>$verified, 'skipped_score'=>$skippedScore, 'skipped_special'=>$skippedSpecial, 'skipped_sequential'=>$skippedSequential));
    } catch (CHttpException $e) {
        if ($transaction !== null && $transaction->active) $transaction->rollback();
        echo json_encode(array('success'=>false, 'message'=>$e->getMessage()));
    } catch (Throwable $e) {
        if ($transaction !== null && $transaction->active) $transaction->rollback();
        Yii::log('VerifyAllTodo failed: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
        echo json_encode(array('success'=>false, 'message'=>'Gagal menjalankan Verify All'));
    }
    Yii::app()->end();
}
    
    
    // public function actionUpdateLogbookStatus() {
    //     header('Content-Type: application/json');
    //     $rest_json = file_get_contents("php://input");
    //     $post = json_decode($rest_json, true);
    
    //     // Validasi wajib
    //     if (!isset($post['id_logbook'])) {
    //         echo json_encode(['success' => false, 'message' => 'id_logbook wajib diisi']);
    //         Yii::app()->end();
    //     }
    
    //     if (!isset($post['id_user'])) {
    //         echo json_encode(['success' => false, 'message' => 'id_user wajib diisi']);
    //         Yii::app()->end();
    //     }
    
    //     if (!isset($post['status'])) {
    //         echo json_encode(['success' => false, 'message' => 'status wajib diisi']);
    //         Yii::app()->end();
    //     }
    
    //     $allowedStatus = ['verified', 'revised', 'rejected'];
    //     if (!in_array($post['status'], $allowedStatus)) {
    //         echo json_encode(['success' => false, 'message' => 'Status tidak valid. Pilih: verified, revised, rejected']);
    //         Yii::app()->end();
    //     }
    
    //     try {
    //         // Cek logbook ada atau tidak
    //         $logbook = Yii::app()->db->createCommand()
    //             ->select('id, verified, verified_status')
    //             ->from('t_logbook')
    //             ->where('id = :id AND deleted_at IS NULL', [':id' => $post['id_logbook']])
    //             ->queryRow();
    
    //         if (!$logbook) {
    //             echo json_encode(['success' => false, 'message' => 'Logbook tidak ditemukan']);
    //             Yii::app()->end();
    //         }
    
    //         $status   = $post['status'];
    //         $notes    = isset($post['notes']) ? $post['notes'] : null;
    //         $dateTime = date('Y-m-d H:i:s');
    
    //         // Tentukan nilai verified berdasarkan status
    //         if ($status === 'verified') {
    //             $verified        = true;
    //             $verified_status = 'verified';
    //         } elseif ($status === 'revised') {
    //             $verified        = false;
    //             $verified_status = 'revised';
    //         } else {
    //             // rejected
    //             $verified        = false;
    //             $verified_status = 'rejected';
    //         }
    
    //         // Update t_logbook
    //         $updateLogbookSql = "
    //             UPDATE t_logbook 
    //             SET verified = :verified, verified_status = :verified_status
    //             WHERE id = :id
    //         ";
    //         Yii::app()->db->createCommand($updateLogbookSql)->execute([
    //             ':verified'        => $verified ? 'true' : 'false',
    //             ':verified_status' => $verified_status,
    //             ':id'              => $post['id_logbook'],
    //         ]);
    
    //         // Cek apakah t_logbook_status sudah ada untuk user ini
    //         $existingStatus = Yii::app()->db->createCommand()
    //             ->select('id')
    //             ->from('t_logbook_status')
    //             ->where('id_logbook = :id_logbook AND id_user = :id_user AND deleted_at IS NULL', [
    //                 ':id_logbook' => $post['id_logbook'],
    //                 ':id_user'    => $post['id_user'],
    //             ])
    //             ->queryRow();
    
    //         if ($existingStatus) {
    //             // notes hanya diupdate jika status rejected
    //             if ($status === 'rejected') {
    //                 $updateStatusSql = "
    //                     UPDATE t_logbook_status
    //                     SET status = :status, date_time = :date_time, notes = :notes
    //                     WHERE id = :id
    //                 ";
    //                 Yii::app()->db->createCommand($updateStatusSql)->execute([
    //                     ':status'    => $status,
    //                     ':date_time' => $dateTime,
    //                     ':notes'     => $notes,
    //                     ':id'        => $existingStatus['id'],
    //                 ]);
    //             } else {
    //                 // verified / revised - notes tidak diupdate
    //                 $updateStatusSql = "
    //                     UPDATE t_logbook_status
    //                     SET status = :status, date_time = :date_time
    //                     WHERE id = :id
    //                 ";
    //                 Yii::app()->db->createCommand($updateStatusSql)->execute([
    //                     ':status'    => $status,
    //                     ':date_time' => $dateTime,
    //                     ':id'        => $existingStatus['id'],
    //                 ]);
    //             }
    //         } else {
    //             // Insert t_logbook_status baru
    //             $insertStatusSql = "
    //                 INSERT INTO t_logbook_status (id_logbook, id_user, id_action_role, status, date_time, notes, id_client)
    //                 VALUES (:id_logbook, :id_user, :id_action_role, :status, :date_time, :notes, :id_client)
    //             ";
    //             Yii::app()->db->createCommand($insertStatusSql)->execute([
    //                 ':id_logbook'     => $post['id_logbook'],
    //                 ':id_user'        => $post['id_user'],
    //                 ':id_action_role' => isset($post['id_action_role']) ? $post['id_action_role'] : null,
    //                 ':status'         => $status,
    //                 ':date_time'      => $dateTime,
    //                 ':notes'          => $status === 'rejected' ? $notes : null,
    //                 ':id_client'      => isset($post['id_client']) ? $post['id_client'] : null,
    //             ]);
    //         }
    
    //         echo json_encode([
    //             'success' => true,
    //             'message' => 'Status logbook berhasil diupdate',
    //             'data'    => [
    //                 'id_logbook'      => $post['id_logbook'],
    //                 'verified'        => $verified,
    //                 'verified_status' => $verified_status,
    //                 'status'          => $status,
    //                 'date_time'       => $dateTime,
    //                 'notes'           => $notes,
    //             ]
    //         ]);
    
    //     } catch (Exception $e) {
    //         echo json_encode([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    
    //     Yii::app()->end();
    // }
    
    
    // ===========================================================================================================================================================
    // ====================================================================== Stase Section ======================================================================
    // ===========================================================================================================================================================
    
    // /////////////////////////////
    // get master data stase select
    // /////////////////////////////
    public function actionGetMasterStase()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post) || !isset($post['id_client']) || !preg_match('/^[1-9][0-9]*$/', (string)$post['id_client'])) {
        echo json_encode(array('success' => false, 'message' => 'id_client wajib berupa ID positif'));
        Yii::app()->end();
    }
    try {
        $data = Yii::app()->dbPrasi->createCommand('SELECT s.id, s.name, s.id_stage, s.id_client, s.sequence,
                stage.name AS stage_name
            FROM m_stase s
            LEFT JOIN m_stage stage ON stage.id=s.id_stage AND stage.id_client=s.id_client
            WHERE s.id_client=:client
            ORDER BY s.sequence ASC NULLS LAST, s.name ASC')
            ->bindValue(':client', (int)$post['id_client'])->queryAll();
        echo json_encode(array('success' => true, 'total' => count($data), 'data' => $data));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    }
    Yii::app()->end();
}


public function actionArchiveMilestone()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id', 'created_by', 'id_client') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success' => false, 'message' => $field . ' wajib berupa ID positif'));
            Yii::app()->end();
        }
    }

    $db = Yii::app()->dbPrasi;
    $id = (int)$post['id'];
    $actorId = (int)$post['created_by'];
    $clientId = (int)$post['id_client'];
    $transaction = $db->beginTransaction();
    try {
        $actor = $db->createCommand("SELECT u.id FROM m_user u JOIN m_role r ON r.id=u.id_role
            WHERE u.id=:id AND u.id_client=:client AND u.deleted_at IS NULL
              AND u.status='Active' AND u.is_show=true
              AND lower(r.name) IN ('staff', 'staff jejaring', 'institution', 'institution-admin')")
            ->bindValues(array(':id' => $actorId, ':client' => $clientId))->queryRow();
        if (!$actor) throw new RuntimeException('Aktor tidak berhak menghapus Stase');

        $milestone = $db->createCommand("SELECT lb.id FROM t_logbook lb
            JOIN m_action a ON a.id=lb.id_action AND a.id_client=lb.id_client
            WHERE lb.id=:id AND lb.id_client=:client AND lb.deleted_at IS NULL
              AND a.is_milestone=true AND a.show_on_milestone=true
              AND (lower(a.identifier)='stase' OR lower(a.name)='stase')
            FOR UPDATE OF lb")
            ->bindValues(array(':id' => $id, ':client' => $clientId))->queryRow();
        if (!$milestone) throw new RuntimeException('Milestone Stase tidak ditemukan');

        $db->createCommand()->update('t_logbook', array(
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actorId,
            'updated_date' => date('Y-m-d H:i:s'),
        ), 'id=:id AND id_client=:client AND deleted_at IS NULL', array(':id' => $id, ':client' => $clientId));
        $transaction->commit();
        echo json_encode(array('success' => true, 'message' => 'Milestone Stase berhasil diarsipkan', 'data' => array('id' => $id)));
    } catch (Exception $e) {
        if ($transaction->active) $transaction->rollback();
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    }
    Yii::app()->end();
}
    
    // /////////////////////////////
    // create stase logbook
    // /////////////////////////////
    public function actionCreateLogbookMilestone()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post)) {
        echo json_encode(array('success' => false, 'message' => 'Payload tidak valid'));
        Yii::app()->end();
    }

    foreach (array('created_by', 'id_client', 'id_user', 'id_stase', 'id_stage', 'id_semester', 'date') as $field) {
        if (!array_key_exists($field, $post) || $post[$field] === '' || $post[$field] === null) {
            echo json_encode(array('success' => false, 'message' => $field . ' wajib diisi'));
            Yii::app()->end();
        }
    }
    foreach (array('created_by', 'id_client', 'id_user', 'id_stase', 'id_stage', 'id_semester') as $field) {
        if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('success' => false, 'message' => $field . ' harus berupa ID positif'));
            Yii::app()->end();
        }
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$post['date'])) {
        echo json_encode(array('success' => false, 'message' => 'date harus berformat YYYY-MM-DD HH:mm:ss'));
        Yii::app()->end();
    }

    $db = Yii::app()->dbPrasi;
    $clientId = (int)$post['id_client'];
    $actorId = (int)$post['created_by'];
    $ppdsId = (int)$post['id_user'];
    $staseId = (int)$post['id_stase'];
    $stageId = (int)$post['id_stage'];
    $semesterId = (int)$post['id_semester'];
    $notes = isset($post['notes']) ? trim((string)$post['notes']) : null;
    $notes = $notes === '' ? null : $notes;
    $isRetake = filter_var(isset($post['is_retake']) ? $post['is_retake'] : false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($isRetake === null) {
        echo json_encode(array('success' => false, 'message' => 'is_retake harus boolean'));
        Yii::app()->end();
    }

    $transaction = $db->beginTransaction();
    try {
        $actor = $db->createCommand("SELECT u.id, r.name AS role_name
            FROM m_user u JOIN m_role r ON r.id = u.id_role
            WHERE u.id=:id AND u.id_client=:client AND u.deleted_at IS NULL
              AND u.status='Active' AND u.is_show=true")
            ->bindValues(array(':id' => $actorId, ':client' => $clientId))->queryRow();
        if (!$actor || !in_array(strtolower((string)$actor['role_name']), array('staff', 'staff jejaring', 'institution', 'institution-admin'), true)) {
            throw new RuntimeException('Aktor tidak berhak mengelola Stase');
        }

        $ppds = $db->createCommand("SELECT u.id, u.id_semester, u.id_stase FROM m_user u JOIN m_role r ON r.id=u.id_role
            WHERE u.id=:id AND u.id_client=:client AND u.deleted_at IS NULL
              AND u.status='Active' AND u.is_show=true AND r.name='ppds' FOR UPDATE")
            ->bindValues(array(':id' => $ppdsId, ':client' => $clientId))->queryRow();
        if (!$ppds) throw new RuntimeException('User PPDS tidak ditemukan untuk client ini');

        $stase = $db->createCommand('SELECT id, id_stage FROM m_stase WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id' => $staseId, ':client' => $clientId))->queryRow();
        $semester = $db->createCommand('SELECT id, id_stage FROM m_semester WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id' => $semesterId, ':client' => $clientId))->queryRow();
        if (!$stase || !$semester || (int)$stase['id_stage'] !== $stageId || (int)$semester['id_stage'] !== $stageId) {
            throw new RuntimeException('Stase dan Semester harus aktif pada Stage yang dipilih');
        }

        $action = $db->createCommand("SELECT id FROM m_action
            WHERE id_client=:client AND is_milestone=true AND show_on_milestone=true
              AND (lower(identifier)='stase' OR lower(name)='stase') LIMIT 1")
            ->bindValue(':client', $clientId)->queryRow();
        if (!$action) throw new RuntimeException('Action Stase milestone tidak ditemukan untuk client ini');

        $now = date('Y-m-d H:i:s');
        $id = isset($post['id']) && preg_match('/^[1-9][0-9]*$/', (string)$post['id']) ? (int)$post['id'] : null;
        if ($id) {
            $existing = $db->createCommand('SELECT id FROM t_logbook WHERE id=:id AND id_client=:client AND id_action=:action AND deleted_at IS NULL FOR UPDATE')
                ->bindValues(array(':id' => $id, ':client' => $clientId, ':action' => (int)$action['id']))->queryRow();
            if (!$existing) throw new RuntimeException('Milestone Stase tidak ditemukan');
            // Bind boolean explicitly: Yii's array insert/update can serialize false as
            // an empty string, which PostgreSQL rejects for a boolean column.
            $command = $db->createCommand('UPDATE t_logbook SET
                id_user=:id_user, id_stase=:id_stase, id_semester=:id_semester,
                date=:date, notes=:notes, is_retake=:is_retake,
                verified=true, verified_status=\'verified\',
                updated_by=:updated_by, updated_date=:updated_date
                WHERE id=:id AND id_client=:client');
            $command->bindValue(':id_user', $ppdsId, PDO::PARAM_INT);
            $command->bindValue(':id_stase', $staseId, PDO::PARAM_INT);
            $command->bindValue(':id_semester', $semesterId, PDO::PARAM_INT);
            $command->bindValue(':date', $post['date'], PDO::PARAM_STR);
            $notes === null ? $command->bindValue(':notes', null, PDO::PARAM_NULL) : $command->bindValue(':notes', $notes, PDO::PARAM_STR);
            $command->bindValue(':is_retake', $isRetake, PDO::PARAM_BOOL);
            $command->bindValue(':updated_by', $actorId, PDO::PARAM_INT);
            $command->bindValue(':updated_date', $now, PDO::PARAM_STR);
            $command->bindValue(':id', $id, PDO::PARAM_INT);
            $command->bindValue(':client', $clientId, PDO::PARAM_INT);
            $command->execute();
        } else {
            // `PDO::PARAM_BOOL` is required here; raw false became '' in the deployed
            // Yii insert path and caused SQLSTATE[22P02].
            $command = $db->createCommand('INSERT INTO t_logbook
                (id_action, id_user, id_stase, id_semester, id_client, date, notes,
                 is_retake, verified, verified_status, created_by, created_date)
                VALUES (:id_action, :id_user, :id_stase, :id_semester, :id_client, :date, :notes,
                        :is_retake, true, \'verified\', :created_by, :created_date)
                RETURNING id');
            $command->bindValue(':id_action', (int)$action['id'], PDO::PARAM_INT);
            $command->bindValue(':id_user', $ppdsId, PDO::PARAM_INT);
            $command->bindValue(':id_stase', $staseId, PDO::PARAM_INT);
            $command->bindValue(':id_semester', $semesterId, PDO::PARAM_INT);
            $command->bindValue(':id_client', $clientId, PDO::PARAM_INT);
            $command->bindValue(':date', $post['date'], PDO::PARAM_STR);
            $notes === null ? $command->bindValue(':notes', null, PDO::PARAM_NULL) : $command->bindValue(':notes', $notes, PDO::PARAM_STR);
            $command->bindValue(':is_retake', $isRetake, PDO::PARAM_BOOL);
            $command->bindValue(':created_by', $actorId, PDO::PARAM_INT);
            $command->bindValue(':created_date', $now, PDO::PARAM_STR);
            $created = $command->queryRow();
            $id = (int)$created['id'];
        }

        // Perubahan semester adalah sesi baru, baik naik maupun turun yang disengaja.
        // Snapshot poin sesi lama tetap tersimpan; sesi target selalu dimulai dari 0.
        if ((int)$ppds['id_semester'] !== $semesterId) {
            $this->transitionMorbiditasPointsSession(
                $db, $ppdsId, $clientId, (int)$ppds['id_semester'],
                $ppds['id_stase'] !== null ? (int)$ppds['id_stase'] : null,
                $semesterId, $staseId
            );
        }

        $db->createCommand()->update('m_user', array(
            'id_stase' => $staseId, 'id_semester' => $semesterId,
            'updated_by' => $actorId, 'updated_date' => $now,
        ), 'id=:id AND id_client=:client', array(':id' => $ppdsId, ':client' => $clientId));

        $row = $db->createCommand('SELECT * FROM t_logbook WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id' => $id, ':client' => $clientId))->queryRow();
        $transaction->commit();
        echo json_encode(array('success' => true, 'message' => isset($post['id']) ? 'Milestone Stase berhasil diperbarui' : 'Milestone Stase berhasil dibuat', 'data' => $row));
    } catch (Exception $e) {
        if ($transaction->active) $transaction->rollback();
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
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
    
            $rows = Yii::app()->dbPrasi->createCommand($sql)
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
    
    
    
    /* Replace the existing actionGetListExplorePpds() method with this entire method. */
public function actionGetListExplorePpds()
{
    header('Content-Type: application/json');
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post) || !isset($post['id_client']) || !preg_match('/^[1-9][0-9]*$/', (string)$post['id_client'])) {
        echo json_encode(array('success'=>false, 'message'=>'id_client wajib valid')); Yii::app()->end();
    }
    try {
        $params = array(':id_client'=>(int)$post['id_client']);
        $where = array("LOWER(TRIM(role.name)) = 'ppds'", 'mu.deleted_at IS NULL', 'mu.is_show = true', "mu.status = 'Active'", 'mu.id_client = :id_client');
        if (isset($post['ppds_ids'])) {
            if (!is_array($post['ppds_ids'])) throw new RuntimeException('ppds_ids harus array');
            $ids = array_values(array_unique(array_filter(array_map('intval', $post['ppds_ids']), function($id) { return $id > 0; })));
            if (!$ids) throw new RuntimeException('ppds_ids tidak valid');
            $keys = array(); foreach ($ids as $i=>$id) { $key=':ppds_'.$i; $keys[]=$key; $params[$key]=$id; }
            $where[] = 'mu.id IN ('.implode(',', $keys).')';
        }
        foreach (array('id_semester'=>'mu.id_semester', 'id_stage'=>'ms.id_stage', 'id_stase'=>'mu.id_stase') as $field=>$column) {
            if (isset($post[$field]) && $post[$field] !== '' && $post[$field] !== null) {
                if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) throw new RuntimeException($field.' tidak valid');
                $where[] = $column.' = :'.$field; $params[':'.$field]=(int)$post[$field];
            }
        }
        $sql = 'SELECT ms.id AS semester_id, ms.name AS semester_name, stg.id AS stage_id, stg.name AS stage_name, stg.label_color AS stage_color, '
            . 'st.id AS stase_id, st.name AS stase_name, mu.id AS user_id, mu.display_name, mu.code '
            . 'FROM m_user mu INNER JOIN m_role role ON role.id=mu.id_role '
            . 'LEFT JOIN m_semester ms ON mu.id_semester=ms.id LEFT JOIN m_stage stg ON ms.id_stage=stg.id '
            . 'LEFT JOIN m_stase st ON mu.id_stase=st.id WHERE '.implode(' AND ', $where).' ORDER BY ms.name ASC, mu.display_name ASC';
        $rows = Yii::app()->dbPrasi->createCommand($sql)->bindValues($params)->queryAll();
        $data = array();
        foreach ($rows as $row) $data[] = array(
            'user_id'=>$row['user_id'], 'display_name'=>$row['display_name'], 'code'=>$row['code'],
            'stase_name'=>$row['stase_name'], 'm_stase'=>$row['stase_id'] ? array('id'=>$row['stase_id'], 'name'=>$row['stase_name']) : null,
            'm_semester'=>$row['semester_id'] ? array('id'=>$row['semester_id'], 'name'=>$row['semester_name'], 'stage_color'=>$row['stage_color'], 'm_stage'=>$row['stage_id'] ? array('id'=>$row['stage_id'], 'name'=>$row['stage_name']) : null) : null,
        );
        echo json_encode(array('success'=>true, 'total'=>count($data), 'data'=>$data));
    } catch (Throwable $e) {
        Yii::log('GetListExplorePpds failed: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
        echo json_encode(array('success'=>false, 'message'=>'Gagal memuat Explore PPDS'));
    }
    Yii::app()->end();
}

    
    public function actionExploreDetailPpds() {
    header('Content-Type: application/json');
    $rest_json = file_get_contents("php://input");
    $post = json_decode($rest_json, true);

    if (!isset($post['id_client'])) {
        echo json_encode(['success' => false, 'message' => 'id_client wajib diisi']);
        Yii::app()->end();
    }

    if (!isset($post['id_user'])) {
        echo json_encode(['success' => false, 'message' => 'id_user wajib diisi']);
        Yii::app()->end();
    }

    try {
        $idClient = (int)$post['id_client'];
        $idUser = (int)$post['id_user'];

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
            ->bindValue(':id_client', $idClient)
            ->bindValue(':id_user', $idUser)
            ->queryAll();

        if (empty($rows)) {
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
            Yii::app()->end();
        }

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

        // === HITUNG POIN MORBIDITAS AKTIF ===
        $morbiditasPoints = 0;

        // Cari sesi aktif (ended_at IS NULL)
        $activeSession = Yii::app()->dbPrasi->createCommand(
            'SELECT id FROM t_morbiditas_points_history
             WHERE id_user=:user AND id_client=:client AND ended_at IS NULL
             ORDER BY id DESC LIMIT 1'
        )->bindValues([':user'=>$idUser, ':client'=>$idClient])->queryRow();

        if ($activeSession) {
            // Hitung poin dari Morbiditas yang terikat ke sesi aktif
            $bound = Yii::app()->dbPrasi->createCommand(
                "SELECT COALESCE(SUM(category.points), 0)::int AS total
                 FROM t_logbook lb
                 INNER JOIN m_action action ON action.id=lb.id_action AND action.id_client=lb.id_client
                 INNER JOIN m_action_category category ON category.id=lb.id_category
                 WHERE lb.id_morbiditas_period=:session_id
                   AND lb.deleted_at IS NULL AND category.points IS NOT NULL
                   AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, ''))='verified')
                   AND (LOWER(COALESCE(action.identifier, ''))='morbiditas'
                        OR LOWER(COALESCE(action.name, ''))='morbiditas' OR action.id=39)"
            )->bindValue(':session_id', (int)$activeSession['id'])->queryRow();

            $morbiditasPoints = (int)($bound['total'] ?? 0);
        }

        $user['morbiditas_points'] = $morbiditasPoints;

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
    
    
    
    
    
    
    
    // ini adalah createeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee
    
    
    
    
//     public function actionCreateLogbook()
// {
//     header('Content-Type: application/json');

//     // if (Yii::app()->user->isGuest || !Yii::app()->user->id) {
//     //     $this->jsonLogbookResponse(false, 'Autentikasi diperlukan.', 401);
//     //     return;
//     // }

//     $post = json_decode(file_get_contents('php://input'), true);
//     if (!is_array($post)) {
//         $this->jsonLogbookResponse(false, 'Payload JSON tidak valid.', 400);
//         return;
//     }

//     foreach (array('id_action', 'id_client', 'created_by', 'date') as $field) {
//         if (!array_key_exists($field, $post) || $post[$field] === '' || $post[$field] === null) {
//             $this->jsonLogbookResponse(false, $field . ' wajib diisi.', 422);
//             return;
//         }
//     }

//     $db = Yii::app()->dbPrasi;
//     $transaction = $db->beginTransaction();

//     try {
//         $this->logbookAssertColumns($db, 'm_user', array('id', 'id_client', 'id_role', 'id_semester', 'id_stase', 'deleted_at'));
//         $authenticatedUserId = (int)Yii::app()->user->id;
//         $authenticatedUser = $db->createCommand()
//             ->select('u.id, u.id_client, u.id_semester, u.id_stase, u.id_role, r.name AS role_name')
//             ->from('m_user u')
//             ->leftJoin('m_role r', 'r.id = u.id_role')
//             ->where('u.id = :id AND u.deleted_at IS NULL', array(':id' => $authenticatedUserId))
//             ->queryRow();
//         if (!$authenticatedUser) {
//             throw new CHttpException(403, 'Identitas pengguna tidak aktif.');
//         }

//         $idAction = (int)$post['id_action'];
//         $idClient = (int)$post['id_client'];
//         $createdBy = (int)$post['created_by'];
//         if ($idClient !== (int)$authenticatedUser['id_client'] || $createdBy !== $authenticatedUserId) {
//             throw new CHttpException(403, 'created_by dan id_client harus sesuai dengan identitas terautentikasi.');
//         }
//         $logbookId = isset($post['id']) && $post['id'] !== null && $post['id'] !== ''
//             ? (int)$post['id']
//             : null;

//         $this->logbookAssertColumns($db, 'm_action', array('id', 'id_client', 'is_exam', 'is_milestone'));
//         $action = $db->createCommand()
//             ->select('*')
//             ->from('m_action')
//             ->where('id = :id AND id_client = :id_client', array(
//                 ':id' => $idAction,
//                 ':id_client' => $idClient,
//             ))
//             ->queryRow();
//         if (!$action) {
//             throw new CHttpException(404, 'Action tidak ditemukan untuk client ini.');
//         }

//         $actor = $authenticatedUser;

//         $actorRole = strtolower(trim((string)$actor['role_name']));
//         $targetUser = $actor;

//         // Staff/institution membuat entri untuk PPDS tertentu.
//         if (in_array($actorRole, array('staff', 'institution'), true)) {
//             if (empty($post['id_user'])) {
//                 throw new CHttpException(422, 'id_user PPDS wajib diisi untuk staff/institution.');
//             }
//             $targetUser = $db->createCommand()
//                 ->select('u.id, u.id_client, u.id_semester, u.id_stase, r.name AS role_name')
//                 ->from('m_user u')
//                 ->leftJoin('m_role r', 'r.id = u.id_role')
//                 ->where('u.id = :id AND u.id_client = :id_client AND u.deleted_at IS NULL', array(
//                     ':id' => (int)$post['id_user'],
//                     ':id_client' => $idClient,
//                 ))
//                 ->queryRow();
//             if (!$targetUser || strtolower((string)$targetUser['role_name']) !== 'ppds') {
//                 throw new CHttpException(422, 'id_user harus merupakan PPDS aktif pada client yang sama.');
//             }
//         }

//         $isExam = $this->logbookBoolean($action, 'is_exam');
//         $isMilestone = $this->logbookBoolean($action, 'is_milestone');
//         $isCreate = $logbookId === null;
//         $now = date('Y-m-d H:i:s');

//         if (isset($post['verified_status']) && strtolower((string)$post['verified_status']) !== 'pending'
//             || isset($post['schedule_status']) && strtolower((string)$post['schedule_status']) !== 'pending') {
//             throw new CHttpException(422, 'Endpoint ini hanya menerima status pending.');
//         }

//         if (!$isCreate) {
//             $existing = $db->createCommand()
//                 ->select('id, id_user, id_client, created_by, verified_status, schedule_status')
//                 ->from('t_logbook')
//                 ->where('id = :id AND id_client = :id_client AND deleted_at IS NULL', array(
//                     ':id' => $logbookId,
//                     ':id_client' => $idClient,
//                 ))
//                 ->queryRow();
//             if (!$existing) {
//                 throw new CHttpException(404, 'Logbook tidak ditemukan.');
//             }

//             if ($existing['verified_status'] !== 'pending' || $existing['schedule_status'] !== 'pending') {
//                 throw new CHttpException(403, 'Hanya logbook berstatus pending yang dapat diperbarui di endpoint ini.');
//             }

//             // PPDS hanya dapat mengubah entri yang dibuatnya sendiri.
//             if ($actorRole === 'ppds') {
//                 if ((int)$existing['created_by'] !== $authenticatedUserId) {
//                     throw new CHttpException(403, 'Logbook ini tidak dapat diubah oleh PPDS saat ini.');
//                 }
//             } elseif (in_array($actorRole, array('staff', 'institution'), true)) {
//                 $isVerifier = (bool)$db->createCommand()
//                     ->select('id')
//                     ->from('t_logbook_status')
//                     ->where('id_logbook = :id_logbook AND id_user = :id_user AND id_client = :id_client AND deleted_at IS NULL', array(
//                         ':id_logbook' => $logbookId,
//                         ':id_user' => $authenticatedUserId,
//                         ':id_client' => $idClient,
//                     ))
//                     ->queryRow();
//                 if ((int)$existing['created_by'] !== $authenticatedUserId && !$isVerifier) {
//                     throw new CHttpException(403, 'Staff/institution hanya dapat mengubah logbook yang dibuatnya atau ditugaskan untuk diverifikasi.');
//                 }
//             }
//         }

//         $verified = false;
//         $verifiedStatus = 'pending';

//         $logbookData = array(
//             'id_action' => $idAction,
//             'id_client' => $idClient,
//             'id_user' => (int)$targetUser['id'],
//             'id_semester' => $targetUser['id_semester'] !== null ? (int)$targetUser['id_semester'] : null,
//             'id_stase' => $targetUser['id_stase'] !== null ? (int)$targetUser['id_stase'] : null,
//             'created_by' => $createdBy,
//             'date' => $post['date'],
//             'notes' => $this->logbookNullableText($post, 'notes'),
//             'verified' => $verified,
//             'verified_status' => $verifiedStatus,
//             'schedule_status' => 'pending',
//         );

//         // Field hanya dimasukkan apabila action mengizinkannya.
//         if ($this->logbookBoolean($action, 'has_title')) {
//             $logbookData['title'] = $this->logbookNullableText($post, 'title');
//         }
//         if ($this->logbookBoolean($action, 'has_hospital')) {
//             $logbookData['id_hospital'] = $this->logbookNullableInt($post, 'id_hospital');
//             $this->logbookValidateReference($db, 'm_hospital', $logbookData['id_hospital'], $idClient, null, 'id_hospital');
//         }
//         if ($this->logbookBoolean($action, 'has_category')) {
//             $logbookData['id_category'] = $this->logbookNullableInt($post, 'id_category');
//             $this->logbookValidateReference($db, 'm_category', $logbookData['id_category'], $idClient, $idAction, 'id_category');
//         }
//         if ($this->logbookBoolean($action, 'has_location')) {
//             $logbookData['location'] = $this->logbookNullableText($post, 'location');
//         }
//         if ($this->logbookBoolean($action, 'has_another_role')) {
//             $logbookData['id_another_role'] = $this->logbookNullableInt($post, 'id_another_role');
//             $this->logbookValidateReference($db, 'm_another_role', $logbookData['id_another_role'], $idClient, $idAction, 'id_another_role');
//         }
//         if ($this->logbookBoolean($action, 'has_presentation')) {
//             $logbookData['is_presentation'] = !empty($post['is_presentation']);
//         }
//         if ($isExam) {
//             $logbookData['exam_result'] = $this->logbookNullableText($post, 'exam_result');
//         }
//         if ($isMilestone) {
//             $logbookData['is_retake'] = !empty($post['is_retake']);
//             if (array_key_exists('id_stase', $post)) {
//                 $logbookData['id_stase'] = $this->logbookNullableInt($post, 'id_stase');
//                 $this->logbookValidateReference($db, 'm_stase', $logbookData['id_stase'], $idClient, $idAction, 'id_stase');
//             }
//         }
//         if ($logbookData['id_stase'] !== null) {
//             $this->logbookValidateReference($db, 'm_stase', (int)$logbookData['id_stase'], $idClient, $idAction, 'id_stase');
//         }
//         if ($this->logbookBoolean($action, 'has_operation_code')) {
//             $logbookData['operation_code'] = !empty($post['operation_code'])
//                 ? (string)$post['operation_code']
//                 : 'LB' . date('YmdHis');
//         }

//         $this->logbookAssertColumns($db, 't_logbook', array_keys($logbookData));

//         if ($isCreate) {
//             $logbookData['created_date'] = $now;
//             $this->logbookAssertColumns($db, 't_logbook', array('created_date'));
//             $db->createCommand()->insert('t_logbook', $logbookData);
//             $logbookId = (int)$db->getLastInsertID();
//         } else {
//             $logbookData['updated_date'] = $now;
//             $this->logbookAssertColumns($db, 't_logbook', array('updated_date'));
//             $db->createCommand()->update('t_logbook', $logbookData, 'id = :id AND id_client = :id_client', array(
//                 ':id' => $logbookId,
//                 ':id_client' => $idClient,
//             ));
//         }

//         // Relasi hanya diganti jika array dikirim. Payload tanpa array tidak menghapus data lama.
//         if (array_key_exists('t_logbook_emr', $post)) {
//             $this->logbookAssertChildEnabled($action, 'has_emr', 't_logbook_emr');
//             $this->logbookReplaceChildren($db, 't_logbook_emr', $logbookId, $post['t_logbook_emr'], array(
//                 'patient_name', 'age', 'month', 'gender', 'diagnosis', 'treatment', 'emr_number', 'id_client'
//             ), $idClient);
//         }
//         if (array_key_exists('t_logbook_attachment', $post)) {
//             $this->logbookAssertChildEnabled($action, 'has_attachment', 't_logbook_attachment');
//             $this->logbookReplaceChildren($db, 't_logbook_attachment', $logbookId, $post['t_logbook_attachment'], array(
//                 'name', 'url_file', 'file_name', 'file_type', 'url', 'path', 'id_client', 'created_date', 'deleted_at'
//             ), $idClient);
//         }
//         if (array_key_exists('t_logbook_asm', $post)) {
//             $this->logbookAssertChildEnabled($action, 'has_asm', 't_logbook_asm');
//             $this->logbookValidateAsmRows($db, $post['t_logbook_asm'], $idClient, $idAction);
//             $this->logbookReplaceChildren($db, 't_logbook_asm', $logbookId, $post['t_logbook_asm'], array(
//                 'id_asm_param', 'score', 'id_client'
//             ), $idClient);
//         }
//         if (array_key_exists('t_logbook_status', $post)) {
//             $this->logbookAssertChildEnabled($action, 'has_verifier', 't_logbook_status');
//             $this->logbookUpsertStatuses($db, $logbookId, $idClient, $idAction, $post['t_logbook_status'], $now);
//         }

//         $saved = $db->createCommand()
//             ->select('*')
//             ->from('t_logbook')
//             ->where('id = :id', array(':id' => $logbookId))
//             ->queryRow();

//         $transaction->commit();
//         $this->jsonLogbookResponse(true, $isCreate ? 'Logbook berhasil dibuat.' : 'Logbook berhasil diperbarui.', 200, $saved);
//     } catch (CHttpException $e) {
//         if ($transaction->active) {
//             $transaction->rollback();
//         }
//         $this->jsonLogbookResponse(false, $e->getMessage(), $e->statusCode);
//     } catch (Exception $e) {
//         if ($transaction->active) {
//             $transaction->rollback();
//         }
//         Yii::log('createLogbook: ' . $e->getMessage(), CLogger::LEVEL_ERROR);
//         $this->jsonLogbookResponse(false, 'Gagal menyimpan logbook.', 500);
//     }
// }

// /** Upsert verifier tanpa menghapus catatan verifier yang tidak dikirim. */
// private function logbookUpsertStatuses($db, $idLogbook, $idClient, $idAction, $rows, $now)
// {
//     if (!is_array($rows)) {
//         throw new CHttpException(422, 't_logbook_status harus berupa array.');
//     }

//     foreach ($rows as $row) {
//         if (!is_array($row) || empty($row['id_user']) || empty($row['id_action_role'])) {
//             throw new CHttpException(422, 'Setiap verifier memerlukan id_user dan id_action_role.');
//         }
//         $status = isset($row['status']) ? strtolower((string)$row['status']) : 'pending';
//         if ($status !== 'pending') {
//             throw new CHttpException(422, 'Endpoint ini hanya menerima status verifier pending.');
//         }

//         $this->logbookAssertColumns($db, 'm_action_rolemap', array('id', 'id_action', 'id_role'));
//         $this->logbookAssertColumns($db, 'm_user', array('id', 'id_client', 'id_role', 'deleted_at'));
//         $verifier = $db->createCommand()
//             ->select('u.id')
//             ->from('m_user u')
//             ->innerJoin('m_action_rolemap arm', 'arm.id_role = u.id_role')
//             ->where('u.id = :id_user AND u.id_client = :id_client AND u.deleted_at IS NULL AND arm.id = :id_action_role AND arm.id_action = :id_action', array(
//                 ':id_user' => (int)$row['id_user'],
//                 ':id_client' => $idClient,
//                 ':id_action_role' => (int)$row['id_action_role'],
//                 ':id_action' => $idAction,
//             ))
//             ->queryRow();
//         if (!$verifier) {
//             throw new CHttpException(422, 'Verifier harus aktif, berada pada client yang sama, dan memiliki action role yang sesuai.');
//         }

//         $existing = $db->createCommand()
//             ->select('id')
//             ->from('t_logbook_status')
//             ->where('id_logbook = :id_logbook AND id_user = :id_user AND id_action_role = :id_action_role AND deleted_at IS NULL', array(
//                 ':id_logbook' => $idLogbook,
//                 ':id_user' => (int)$row['id_user'],
//                 ':id_action_role' => (int)$row['id_action_role'],
//             ))
//             ->queryRow();

//         $data = array(
//             'id_logbook' => $idLogbook,
//             'id_user' => (int)$row['id_user'],
//             'id_action_role' => (int)$row['id_action_role'],
//             'id_client' => $idClient,
//             'status' => $status,
//             'date_time' => $now,
//         );
//         foreach (array('notes', 'verify_notes', 'reject_notes') as $field) {
//             if (array_key_exists($field, $row)) {
//                 $data[$field] = $row[$field] === '' ? null : $row[$field];
//             }
//         }
//         $this->logbookAssertColumns($db, 't_logbook_status', array('id_logbook', 'id_user', 'id_action_role', 'id_client', 'status', 'date_time'));
//         // Catatan verifier bersifat opsional lintas versi schema.
//         $data = $this->logbookFilterOptionalVerifierNotes($db, $data);

//         if ($existing) {
//             unset($data['id_logbook'], $data['id_user'], $data['id_action_role']);
//             $db->createCommand()->update('t_logbook_status', $data, 'id = :id', array(':id' => $existing['id']));
//         } else {
//             $db->createCommand()->insert('t_logbook_status', $data);
//         }
//     }
// }

// /** Mengganti isi child table hanya saat array child dikirim pada payload. */
// private function logbookReplaceChildren($db, $table, $idLogbook, $rows, $allowedFields, $idClient)
// {
//     if (!is_array($rows)) {
//         throw new CHttpException(422, $table . ' harus berupa array.');
//     }

//     $this->logbookAssertColumns($db, $table, array('id_logbook', 'id_client'));
//     $db->createCommand()->delete($table, 'id_logbook = :id_logbook AND id_client = :id_client', array(':id_logbook' => $idLogbook, ':id_client' => $idClient));
//     foreach ($rows as $row) {
//         if (!is_array($row)) {
//             throw new CHttpException(422, 'Baris ' . $table . ' tidak valid.');
//         }
//         $data = array('id_logbook' => $idLogbook);
//         foreach ($allowedFields as $field) {
//             if (array_key_exists($field, $row)) {
//                 $data[$field] = $row[$field];
//             }
//         }
//         if (in_array('id_client', $allowedFields, true)) {
//             $data['id_client'] = $idClient;
//         }
//         $this->logbookAssertColumns($db, $table, array_keys($data));
//         $db->createCommand()->insert($table, $data);
//     }
// }

// /** Required payload columns must exist; only verifier note fields may be omitted by older schemas. */
// private function logbookAssertColumns($db, $table, $columns)
// {
//     $schema = $db->schema->getTable($table);
//     if (!$schema) {
//         throw new CHttpException(500, 'Tabel ' . $table . ' tidak ditemukan.');
//     }
//     foreach ($columns as $column) {
//         if (!isset($schema->columns[$column])) {
//             throw new CHttpException(500, 'Kolom wajib ' . $table . '.' . $column . ' tidak ditemukan.');
//         }
//     }
//     return $schema;
// }

// private function logbookFilterOptionalVerifierNotes($db, $data)
// {
//     $schema = $this->logbookAssertColumns($db, 't_logbook_status', array('id_logbook', 'id_user', 'id_action_role', 'id_client', 'status', 'date_time'));
//     foreach (array('notes', 'verify_notes', 'reject_notes') as $column) {
//         if (array_key_exists($column, $data) && !isset($schema->columns[$column])) {
//             unset($data[$column]);
//         }
//     }
//     return $data;
// }

// private function logbookAssertChildEnabled($action, $flag, $field)
// {
//     if (!array_key_exists($flag, $action)) {
//         throw new CHttpException(500, 'Kolom wajib m_action.' . $flag . ' tidak ditemukan.');
//     }
//     if (!$this->logbookBoolean($action, $flag)) {
//         throw new CHttpException(422, $field . ' tidak diizinkan untuk action ini.');
//     }
// }

// private function logbookValidateReference($db, $table, $id, $idClient, $idAction, $field)
// {
//     if ($id === null) {
//         return;
//     }
//     $columns = array('id', 'id_client', 'deleted_at');
//     if ($idAction !== null) {
//         $columns[] = 'id_action';
//     }
//     $this->logbookAssertColumns($db, $table, $columns);
//     $condition = 'id = :id AND id_client = :id_client AND deleted_at IS NULL';
//     $params = array(':id' => $id, ':id_client' => $idClient);
//     if ($idAction !== null) {
//         $condition .= ' AND id_action = :id_action';
//         $params[':id_action'] = $idAction;
//     }
//     if (!$db->createCommand()->select('id')->from($table)->where($condition, $params)->queryRow()) {
//         throw new CHttpException(422, $field . ' tidak valid untuk client/action ini.');
//     }
// }

// private function logbookValidateAsmRows($db, $rows, $idClient, $idAction)
// {
//     if (!is_array($rows)) {
//         throw new CHttpException(422, 't_logbook_asm harus berupa array.');
//     }
//     foreach ($rows as $row) {
//         if (!is_array($row) || !array_key_exists('id_asm_param', $row) || $row['id_asm_param'] === '' || $row['id_asm_param'] === null) {
//             throw new CHttpException(422, 'Setiap ASM memerlukan id_asm_param.');
//         }
//         $this->logbookValidateReference($db, 'm_asm_param', (int)$row['id_asm_param'], $idClient, $idAction, 'id_asm_param');
//     }
// }

// private function logbookBoolean($row, $field)
// {
//     if (!isset($row[$field])) {
//         return false;
//     }
//     return in_array(strtolower((string)$row[$field]), array('1', 'true', 't', 'yes', 'y'), true);
// }

// private function logbookNullableInt($post, $field)
// {
//     return isset($post[$field]) && $post[$field] !== '' && $post[$field] !== null
//         ? (int)$post[$field]
//         : null;
// }

// private function logbookNullableText($post, $field)
// {
//     return isset($post[$field]) && $post[$field] !== '' ? (string)$post[$field] : null;
// }

// private function jsonLogbookResponse($success, $message, $statusCode, $data = null)
// {
//     if (!headers_sent()) {
//         http_response_code($statusCode);
//     }
//     $response = array('success' => (bool)$success, 'message' => $message);
//     if ($data !== null) {
//         $response['data'] = $data;
//     }
//     echo json_encode($response);
//     Yii::app()->end();
// }





//ini create logbokkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk

private function createLogbookAssertMorbiditasPayload($db, $payload, $action, $actor, $target, $idClient, $idAction)
    {
        if (!$this->createLogbookIsMorbiditas($action)) return;

        // Prasi: PPDS creates the clinical report; category is selected later
        // by the authorised Morbiditas officer, never by PPDS Create.
        if (strtolower(trim((string)$actor['role_name'])) === 'ppds' && array_key_exists('id_category', $payload)) {
            throw new CHttpException(422, 'Kategori Morbiditas tidak dipilih oleh PPDS saat membuat logbook.');
        }
        if (!isset($payload['notes']) || !is_scalar($payload['notes']) || trim((string)$payload['notes']) === '') {
            throw new CHttpException(422, 'Kronologi Morbiditas wajib diisi.');
        }
        if (!isset($payload['t_logbook_emr']) || !is_array($payload['t_logbook_emr']) || count($payload['t_logbook_emr']) !== 1) {
            throw new CHttpException(422, 'Morbiditas memerlukan satu data Nama PX, Umur, CM, dan DX Awal.');
        }
        $emr = $payload['t_logbook_emr'][0];
        if (!is_array($emr)
            || !isset($emr['patient_name']) || trim((string)$emr['patient_name']) === ''
            || !isset($emr['age']) || !$this->createLogbookInteger($emr['age']) || (int)$emr['age'] < 0 || (int)$emr['age'] > 150
            || !isset($emr['emr_number']) || trim((string)$emr['emr_number']) === ''
            || !isset($emr['diagnosis']) || trim((string)$emr['diagnosis']) === '') {
            throw new CHttpException(422, 'Morbiditas memerlukan Nama PX, Umur, CM, dan DX Awal yang valid.');
        }
        foreach (array('month', 'gender', 'treatment') as $forbidden) {
            if (array_key_exists($forbidden, $emr) && trim((string)$emr[$forbidden]) !== '') {
                throw new CHttpException(422, 'Field EMR ' . $forbidden . ' tidak digunakan pada Morbiditas.');
            }
        }

        $expected = $this->createLogbookMorbiditasRoleSlots($db, $idAction, $idClient);
        if (count($expected) !== 3) {
            throw new CHttpException(422, 'Rolemap Morbiditas harus berisi Pelapor, Penilai/GKM, dan KPS.');
        }
        $rows = isset($payload['t_logbook_status']) ? $payload['t_logbook_status'] : null;
        if (!is_array($rows) || count($rows) !== 3) {
            throw new CHttpException(422, 'Morbiditas memerlukan Pelapor, Penilai/GKM, dan KPS.');
        }
        $received = array();
        foreach ($rows as $row) {
            if (!is_array($row) || !$this->createLogbookPositiveId(isset($row['id_user']) ? $row['id_user'] : null)
                || !$this->createLogbookPositiveId(isset($row['id_action_role']) ? $row['id_action_role'] : null)
                || (isset($row['status']) && strtolower((string)$row['status']) !== 'pending')
                || array_key_exists('notes', $row)) {
                throw new CHttpException(422, 'Baris verifier Morbiditas tidak valid.');
            }
            $received[] = (int)$row['id_action_role'];
        }
        if ($received !== array_values($expected)) {
            throw new CHttpException(422, 'Urutan verifier Morbiditas harus Pelapor, Penilai/GKM, lalu KPS.');
        }
    }

    private function createLogbookIsMorbiditas($action)
    {
        $source = strtolower(trim((string)(isset($action['identifier']) ? $action['identifier'] : '') . ' '
            . (string)(isset($action['name']) ? $action['name'] : '') . ' '
            . (string)(isset($action['action_name']) ? $action['action_name'] : '')));
        return strpos($source, 'morbiditas') !== false;
    }

    /** Returns action-role IDs in Prasi order: Pelapor -> Penilai/GKM -> KPS. */
    private function createLogbookMorbiditasRoleSlots($db, $idAction, $idClient)
    {
        $rows = $db->createCommand(
            'SELECT ar.id, ar.role, ar.identifier FROM m_action_rolemap arm '
            . 'INNER JOIN m_action_role ar ON ar.id=arm.id_action_role '
            . "WHERE arm.id_action=:action AND ar.id_client=:client AND COALESCE(arm.type, 'verificator') IN ('verificator','approval') "
            . 'ORDER BY arm.id'
        )->bindValues(array(':action'=>(int)$idAction, ':client'=>(int)$idClient))->queryAll();
        $definitions = array(
            'pelapor'=>'/pelapor|reporter/i',
            'penilai'=>'/penilai|gkm|assessor/i',
            'kps'=>'/\\bkps\\b/i',
        );
        $result = array();
        foreach ($definitions as $slot=>$pattern) {
            foreach ($rows as $row) {
                $text = (string)$row['role'] . ' ' . (string)$row['identifier'];
                if (preg_match('/jejar/i', $text)) continue;
                if (preg_match($pattern, $text)) {
                    $result[$slot] = (int)$row['id'];
                    break;
                }
            }
        }
        // Same fallback as Prasi when legacy metadata has no identifiers: first
        // three non-jejaring verificators are interpreted Pelapor, Penilai, KPS.
        if (count($result) !== 3) {
            $fallback = array();
            foreach ($rows as $row) {
                if (!preg_match('/jejar/i', (string)$row['role'] . ' ' . (string)$row['identifier'])) {
                    $fallback[] = (int)$row['id'];
                }
            }
            if (count($fallback) >= 3) return array_slice($fallback, 0, 3);
        }
        return array_values($result);
    }
    
 /* Replace the existing three helper methods in ApiMobileServiceController:
 * ensureMorbiditasPointsSessionTable(), calculateMorbiditasSessionPoints(),
 * transitionMorbiditasPointsSession(). Do not append: this block also adds the
 * new reconcileLegacyMorbiditasSessionSnapshot() helper exactly once.
 */
private function ensureMorbiditasPointsSessionTable($db)
{
    $db->createCommand('CREATE TABLE IF NOT EXISTS t_morbiditas_points_history (
        id SERIAL PRIMARY KEY,
        id_user INT NOT NULL,
        id_client INT NOT NULL,
        id_semester INT NOT NULL,
        id_stase INT NULL,
        points INT NOT NULL DEFAULT 0,
        started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        ended_at TIMESTAMPTZ NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )')->execute();
    $db->createCommand('ALTER TABLE t_morbiditas_points_history ADD COLUMN IF NOT EXISTS id_stase INT NULL')->execute();
    $db->createCommand('ALTER TABLE t_morbiditas_points_history ADD COLUMN IF NOT EXISTS restored_at TIMESTAMPTZ NULL')->execute();
    $db->createCommand('CREATE INDEX IF NOT EXISTS idx_morbiditas_points_session_open
        ON t_morbiditas_points_history (id_user, id_client, ended_at)')->execute();
}

private function calculateMorbiditasSessionPoints($db, $idUser, $idClient, $idSemester, $startedAt)
{
    // Cari sesi aktif untuk user ini
    $activeSession = $db->createCommand('SELECT id, points, restored_at
        FROM t_morbiditas_points_history
        WHERE id_user=:user AND id_client=:client AND id_semester=:semester
          AND ended_at IS NULL ORDER BY id DESC LIMIT 1')
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient, ':semester'=>(int)$idSemester))
        ->queryRow();

    if (!$activeSession) {
        // Tidak ada sesi aktif, fallback ke hitungan lama
        return $this->calculateMorbiditasSessionPointsFallback($db, $idUser, $idClient, $idSemester, $startedAt);
    }

    $sessionId = (int)$activeSession['id'];

    // Hitung poin hanya dari Morbiditas yang terikat ke sesi ini
    $bound = $db->createCommand("SELECT COALESCE(SUM(category.points), 0)::int AS total
        FROM t_logbook lb
        INNER JOIN m_action action ON action.id=lb.id_action AND action.id_client=lb.id_client
        INNER JOIN m_action_category category ON category.id=lb.id_category
        WHERE lb.id_morbiditas_period=:session_id
          AND lb.deleted_at IS NULL AND category.points IS NOT NULL
          AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, ''))='verified')
          AND (LOWER(COALESCE(action.identifier, ''))='morbiditas'
               OR LOWER(COALESCE(action.name, ''))='morbiditas' OR action.id=39)")
        ->bindValue(':session_id', $sessionId)->queryRow();

    $boundPoints = (int)($bound['total'] ?? 0);

    // Cek apakah ada Morbiditas TANPA binding di semester ini (data lama)
    $unbound = $db->createCommand("SELECT COUNT(*) FROM t_logbook lb
        JOIN m_action a ON a.id=lb.id_action AND a.id_client=lb.id_client
        WHERE lb.id_user=:user AND lb.id_client=:client AND lb.id_semester=:semester
          AND lb.id_morbiditas_period IS NULL AND lb.deleted_at IS NULL
          AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, ''))='verified')
          AND (LOWER(COALESCE(a.identifier, ''))='morbiditas'
               OR LOWER(COALESCE(a.name, ''))='morbiditas' OR a.id=39)")
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient, ':semester'=>(int)$idSemester))
        ->queryScalar();

    if ((int)$unbound > 0) {
        // Ada data lama tanpa binding, fallback ke hitungan timestamp
        return $this->calculateMorbiditasSessionPointsFallback($db, $idUser, $idClient, $idSemester, $startedAt);
    }

    return $boundPoints;
}

/*
 * ADD helper fallback — ini adalah logika lama yang tetap dipakai
 * untuk data sebelum id_morbiditas_period diisi.
 */
private function calculateMorbiditasSessionPointsFallback($db, $idUser, $idClient, $idSemester, $startedAt)
{
    $restoredSession = $db->createCommand('SELECT points,restored_at FROM t_morbiditas_points_history
        WHERE id_user=:user AND id_client=:client AND id_semester=:semester
          AND ended_at IS NULL ORDER BY id DESC LIMIT 1')
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient, ':semester'=>(int)$idSemester))
        ->queryRow();
    if ($restoredSession && !empty($restoredSession['restored_at'])) {
        $newPoints = $db->createCommand("SELECT COALESCE(SUM(category.points), 0)::int AS total
            FROM t_logbook lb INNER JOIN m_action action ON action.id=lb.id_action
            INNER JOIN m_action_category category ON category.id=lb.id_category
            WHERE lb.id_user=:user AND lb.id_client=:client AND lb.id_semester=:semester
              AND lb.deleted_at IS NULL AND category.points IS NOT NULL
              AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, ''))='verified')
              AND (LOWER(COALESCE(action.identifier, ''))='morbiditas'
                   OR LOWER(COALESCE(action.name, ''))='morbiditas' OR action.id=39)
              AND lb.created_date >= :restored_at")
            ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient,
                ':semester'=>(int)$idSemester, ':restored_at'=>$restoredSession['restored_at']))->queryRow();
        return (int)$restoredSession['points'] + (int)($newPoints['total'] ?? 0);
    }
    $milestoneStartedAt = $db->createCommand("SELECT lb.created_date
        FROM t_logbook lb INNER JOIN m_action action ON action.id=lb.id_action
        WHERE lb.id_user=:user AND lb.id_client=:client AND lb.id_semester=:semester
          AND lb.deleted_at IS NULL AND action.is_milestone=true AND action.show_on_milestone=true
          AND (LOWER(COALESCE(action.identifier,''))='stase' OR LOWER(COALESCE(action.name,''))='stase')
        ORDER BY lb.created_date DESC,lb.id DESC LIMIT 1")
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient, ':semester'=>(int)$idSemester))
        ->queryScalar();
    if ($milestoneStartedAt && strtotime((string)$milestoneStartedAt) > strtotime((string)$startedAt)) {
        $startedAt = $milestoneStartedAt;
    }
    $row = $db->createCommand("SELECT COALESCE(SUM(category.points), 0)::int AS total
        FROM t_logbook lb
        INNER JOIN m_action action ON action.id=lb.id_action
        INNER JOIN m_action_category category ON category.id=lb.id_category
        WHERE lb.id_user=:user AND lb.id_client=:client AND lb.id_semester=:semester
          AND lb.deleted_at IS NULL AND category.points IS NOT NULL
          AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, ''))='verified')
          AND (LOWER(COALESCE(action.identifier, ''))='morbiditas'
               OR LOWER(COALESCE(action.name, ''))='morbiditas' OR action.id=39)
          AND lb.created_date >= :started_at")
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient,
            ':semester'=>(int)$idSemester, ':started_at'=>$startedAt))->queryRow();
    return (int)($row['total'] ?? 0);
}


/* Reconciles a legacy first session once. It never applies to an intentional
 * return to a semester because that semester already has an older ledger row. */
private function reconcileLegacyMorbiditasSessionSnapshot($db, $idUser, $idClient, $session)
{
    $startedAt = (string)$session['started_at'];
    $sessionPoints = $this->calculateMorbiditasSessionPoints($db, $idUser, $idClient,
        (int)$session['id_semester'], $startedAt);
    $hasOlderSameSemester = (int)$db->createCommand('SELECT COUNT(*) FROM t_morbiditas_points_history
        WHERE id_user=:user AND id_client=:client AND id_semester=:semester AND id<>:id')
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient,
            ':semester'=>(int)$session['id_semester'], ':id'=>(int)$session['id']))->queryScalar() > 0;
    if ($sessionPoints !== 0 || $hasOlderSameSemester || strtotime($startedAt) <= strtotime('1971-01-01')) {
        return array('points'=>$sessionPoints, 'started_at'=>$startedAt);
    }
    $legacyPoints = $this->calculateMorbiditasSessionPoints($db, $idUser, $idClient,
        (int)$session['id_semester'], '1970-01-01 00:00:00+00');
    if ($legacyPoints <= 0) return array('points'=>0, 'started_at'=>$startedAt);
    return array('points'=>$legacyPoints, 'started_at'=>'1970-01-01 00:00:00+00');
}

/* Deliberate Stase Save: close old session and always open a new target session at 0. */
private function transitionMorbiditasPointsSession($db, $idUser, $idClient, $oldSemester, $oldStase, $newSemester, $newStase)
{
    $this->ensureMorbiditasPointsSessionTable($db);
    $open = $db->createCommand('SELECT id, id_semester, id_stase, started_at
        FROM t_morbiditas_points_history
        WHERE id_user=:user AND id_client=:client AND ended_at IS NULL
        ORDER BY id DESC LIMIT 1 FOR UPDATE')
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient))->queryRow();

    if (!$open && $oldSemester > 0) {
        $db->createCommand('INSERT INTO t_morbiditas_points_history
            (id_user,id_client,id_semester,id_stase,points,started_at,ended_at)
            VALUES (:user,:client,:semester,:stase,0,\'1970-01-01 00:00:00+00\',NULL)')
            ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient,
                ':semester'=>(int)$oldSemester, ':stase'=>$oldStase))->execute();
        $open = $db->createCommand('SELECT id, id_semester, id_stase, started_at
            FROM t_morbiditas_points_history WHERE id_user=:user AND id_client=:client
              AND ended_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE')
            ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient))->queryRow();
    }

    if ($open) {
        $snapshot = $this->reconcileLegacyMorbiditasSessionSnapshot($db, $idUser, $idClient, $open);
        $db->createCommand('UPDATE t_morbiditas_points_history
            SET points=:points, started_at=:started_at, id_stase=COALESCE(:stase,id_stase), restored_at=NULL, ended_at=CURRENT_TIMESTAMP
            WHERE id=:id AND ended_at IS NULL')
            ->bindValues(array(':points'=>(int)$snapshot['points'], ':started_at'=>$snapshot['started_at'],
                ':stase'=>$oldStase, ':id'=>(int)$open['id']))->execute();
    }
    $db->createCommand('UPDATE t_morbiditas_points_history SET ended_at=CURRENT_TIMESTAMP
        WHERE id_user=:user AND id_client=:client AND ended_at IS NULL')
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient))->execute();
    $db->createCommand('INSERT INTO t_morbiditas_points_history
        (id_user,id_client,id_semester,id_stase,points,started_at,ended_at)
        VALUES (:user,:client,:semester,:stase,0,CURRENT_TIMESTAMP,NULL)')
        ->bindValues(array(':user'=>(int)$idUser, ':client'=>(int)$idClient,
            ':semester'=>(int)$newSemester, ':stase'=>$newStase))->execute();
}



public function actionGetMilestoneMorbiditasUndoInfo()
{
    header('Content-Type: application/json; charset=utf-8');
    $post=json_decode(file_get_contents('php://input'),true);
    foreach(array('created_by','id_client','id_user') as $field) if(!is_array($post)||!isset($post[$field])||!preg_match('/^[1-9][0-9]*$/',(string)$post[$field])) { echo json_encode(array('success'=>false,'message'=>$field.' wajib berupa ID positif')); Yii::app()->end(); }
    $db=Yii::app()->dbPrasi; $client=(int)$post['id_client']; $actor=(int)$post['created_by']; $user=(int)$post['id_user'];
    try {
        $allowed=$db->createCommand("SELECT u.id FROM m_user u JOIN m_role r ON r.id=u.id_role WHERE u.id=:actor AND u.id_client=:client AND u.deleted_at IS NULL AND u.status='Active' AND u.is_show=true AND lower(r.name) IN ('staff','staff jejaring','institution','institution-admin')")
            ->bindValues(array(':actor'=>$actor,':client'=>$client))->queryRow();
        if(!$allowed) throw new RuntimeException('Aktor tidak berhak melihat Undo Morbiditas');
        $ppds=$db->createCommand("SELECT u.id,u.display_name,u.id_semester,u.id_stase FROM m_user u JOIN m_role r ON r.id=u.id_role WHERE u.id=:user AND u.id_client=:client AND u.deleted_at IS NULL AND r.name='ppds'")
            ->bindValues(array(':user'=>$user,':client'=>$client))->queryRow();
        if(!$ppds) throw new RuntimeException('PPDS tidak ditemukan untuk client ini');
        $this->ensureMorbiditasPointsSessionTable($db);
        $open=$db->createCommand('SELECT id,id_semester,id_stase,points,started_at FROM t_morbiditas_points_history WHERE id_user=:user AND id_client=:client AND ended_at IS NULL ORDER BY id DESC LIMIT 1')
            ->bindValues(array(':user'=>$user,':client'=>$client))->queryRow();
        if(!$open) { echo json_encode(array('success'=>true,'data'=>array('id_user'=>$user,'display_name'=>$ppds['display_name'],'can_undo'=>false,'reason'=>'Belum ada sesi poin aktif'))); Yii::app()->end(); }
        $currentPoints=$this->calculateMorbiditasSessionPoints($db,$user,$client,(int)$open['id_semester'],$open['started_at']);
        $closed=$db->createCommand('SELECT id,id_semester,id_stase,points,started_at,ended_at FROM t_morbiditas_points_history WHERE id_user=:user AND id_client=:client AND ended_at IS NOT NULL ORDER BY ended_at DESC,id DESC LIMIT 1')
            ->bindValues(array(':user'=>$user,':client'=>$client))->queryRow();
        $currentName=$db->createCommand('SELECT name FROM m_semester WHERE id=:id AND id_client=:client')->bindValues(array(':id'=>(int)$open['id_semester'],':client'=>$client))->queryScalar();
        $base=array('id_user'=>$user,'display_name'=>$ppds['display_name'],'current_semester_id'=>(int)$open['id_semester'],'current_semester_name'=>$currentName,'current_active_points'=>$currentPoints,'can_undo'=>false);
        if(!$closed) { echo json_encode(array('success'=>true,'data'=>$base+array('reason'=>'Belum ada riwayat ganti semester'))); Yii::app()->end(); }
        /* A closed session's points are a historical snapshot. Never recalculate
         * a positive snapshot against a later Milestone boundary in preview. */
        if ((int)$closed['points'] > 0) {
            $restoreSnapshot=array('points'=>(int)$closed['points'],'started_at'=>$closed['started_at']);
            $legacySnapshotReconciled=false;
        } else {
            // Repair only an old zero-valued bootstrap session.
            $restoreSnapshot=$this->reconcileLegacyMorbiditasSessionSnapshot($db,$user,$client,$closed);
            $legacySnapshotReconciled=(string)$restoreSnapshot['started_at'] !== (string)$closed['started_at'];
            if ($legacySnapshotReconciled || (int)$restoreSnapshot['points'] !== (int)$closed['points']) {
                $db->createCommand('UPDATE t_morbiditas_points_history SET points=:points,started_at=:started_at WHERE id=:id AND id_user=:user AND id_client=:client')
                    ->bindValues(array(':points'=>(int)$restoreSnapshot['points'],':started_at'=>$restoreSnapshot['started_at'],':id'=>(int)$closed['id'],':user'=>$user,':client'=>$client))->execute();
                $closed['points']=(int)$restoreSnapshot['points']; $closed['started_at']=$restoreSnapshot['started_at'];
            }
        }
        $restorePoints=(int)$restoreSnapshot['points'];
        $restore=$db->createCommand('SELECT sem.name AS semester_name,st.name AS stase_name,sem.id_stage,stage.name AS stage_name FROM m_semester sem LEFT JOIN m_stase st ON st.id=:stase AND st.id_client=:client LEFT JOIN m_stage stage ON stage.id=sem.id_stage WHERE sem.id=:semester AND sem.id_client=:client')
            ->bindValues(array(':semester'=>(int)$closed['id_semester'],':stase'=>$closed['id_stase'],':client'=>$client))->queryRow();
        $canUndo=$currentPoints===0 && ($legacySnapshotReconciled || strtotime((string)$closed['started_at'])>strtotime('1971-01-01'));
        echo json_encode(array('success'=>true,'data'=>array_merge($base,array('can_undo'=>$canUndo,'reason'=>$canUndo?null:($currentPoints!==0?'Undo dikunci: sesi sekarang sudah memiliki poin aktif.':'Riwayat sebelumnya adalah bootstrap dan tidak aman untuk Undo.'),'restore_session_id'=>(int)$closed['id'],'restore_semester_id'=>(int)$closed['id_semester'],'restore_semester_name'=>$restore['semester_name']??null,'restore_stase_id'=>$closed['id_stase']!==null?(int)$closed['id_stase']:null,'restore_stase_name'=>$restore['stase_name']??null,'restore_stage_id'=>$restore['id_stage']??null,'restore_stage_name'=>$restore['stage_name']??null,'restore_points'=>$restorePoints?:((int)$closed['points'])))));
    } catch(Exception $e) { echo json_encode(array('success'=>false,'message'=>$e->getMessage())); }
    Yii::app()->end();
}


/* Add this new method inside ApiMobileServiceController.
 * Route: apiMobileService/undoMilestoneMorbiditasSemester
 */
public function actionUndoMilestoneMorbiditasSemester()
{
    header('Content-Type: application/json; charset=utf-8');
    $post=json_decode(file_get_contents('php://input'),true);
    foreach(array('created_by','id_client','id_user') as $field) if(!is_array($post)||!isset($post[$field])||!preg_match('/^[1-9][0-9]*$/',(string)$post[$field])) { echo json_encode(array('success'=>false,'message'=>$field.' wajib berupa ID positif')); Yii::app()->end(); }
    if(isset($post['id_logbook'])&&!preg_match('/^[1-9][0-9]*$/',(string)$post['id_logbook'])) { echo json_encode(array('success'=>false,'message'=>'id_logbook tidak valid')); Yii::app()->end(); }
    $db=Yii::app()->dbPrasi; $client=(int)$post['id_client']; $actor=(int)$post['created_by']; $user=(int)$post['id_user']; $targetLogbook=isset($post['id_logbook'])?(int)$post['id_logbook']:null;
    $tx=$db->beginTransaction();
    try {
        $allowed=$db->createCommand("SELECT u.id FROM m_user u JOIN m_role r ON r.id=u.id_role WHERE u.id=:actor AND u.id_client=:client AND u.deleted_at IS NULL AND u.status='Active' AND u.is_show=true AND lower(r.name) IN ('staff','staff jejaring','institution','institution-admin')")
            ->bindValues(array(':actor'=>$actor,':client'=>$client))->queryRow();
        if(!$allowed) throw new RuntimeException('Aktor tidak berhak menjalankan Undo Morbiditas');
        $this->ensureMorbiditasPointsSessionTable($db);
        $open=$db->createCommand('SELECT id,id_semester,id_stase,started_at FROM t_morbiditas_points_history WHERE id_user=:user AND id_client=:client AND ended_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE')
            ->bindValues(array(':user'=>$user,':client'=>$client))->queryRow();
        if(!$open) throw new RuntimeException('Tidak ada sesi aktif untuk di-Undo');
        $currentPoints=$this->calculateMorbiditasSessionPoints($db,$user,$client,(int)$open['id_semester'],$open['started_at']);
        if($currentPoints!==0) throw new RuntimeException('Undo ditolak: sesi sekarang sudah memiliki poin aktif');
        $restore=$db->createCommand('SELECT id,id_semester,id_stase,points,started_at FROM t_morbiditas_points_history WHERE id_user=:user AND id_client=:client AND ended_at IS NOT NULL ORDER BY ended_at DESC,id DESC LIMIT 1 FOR UPDATE')
            ->bindValues(array(':user'=>$user,':client'=>$client))->queryRow();
        if(!$restore || (strtotime((string)$restore['started_at'])<=strtotime('1971-01-01') && (int)$restore['points']===0)) throw new RuntimeException('Tidak ada sesi sebelumnya yang aman untuk di-Undo');
        $restoreStase=$restore['id_stase']!==null?(int)$restore['id_stase']:null;
        if(!$restoreStase) {
            $restoreStase=$db->createCommand("SELECT lb.id_stase FROM t_logbook lb JOIN m_action a ON a.id=lb.id_action WHERE lb.id_user=:user AND lb.id_client=:client AND lb.id_semester=:semester AND lb.deleted_at IS NULL AND lb.id_stase IS NOT NULL AND (lower(coalesce(a.identifier,''))='stase' OR lower(coalesce(a.name,''))='stase') ORDER BY lb.id DESC LIMIT 1")
                ->bindValues(array(':user'=>$user,':client'=>$client,':semester'=>(int)$restore['id_semester']))->queryScalar();
            $restoreStase=$restoreStase!==false&&$restoreStase!==null?(int)$restoreStase:null;
        }
        $db->createCommand('UPDATE t_morbiditas_points_history SET points=0,ended_at=CURRENT_TIMESTAMP WHERE id=:id AND ended_at IS NULL')->bindValue(':id',(int)$open['id'])->execute();
        /* Reopen the closed session with its saved point snapshot as baseline.
         * Do not recalculate it against a later Stase boundary: this explicit
         * Undo is the only operation that may restore historic points. */
        $db->createCommand('UPDATE t_morbiditas_points_history SET ended_at=NULL,restored_at=CURRENT_TIMESTAMP WHERE id=:id AND ended_at IS NOT NULL')->bindValue(':id',(int)$restore['id'])->execute();
        $now=date('Y-m-d H:i:s');
        $db->createCommand()->update('m_user',array('id_semester'=>(int)$restore['id_semester'],'id_stase'=>$restoreStase,'updated_by'=>$actor,'updated_date'=>$now),'id=:id AND id_client=:client',array(':id'=>$user,':client'=>$client));
        if($targetLogbook) {
            $staseAction=$db->createCommand("SELECT id FROM m_action WHERE id_client=:client AND is_milestone=true AND show_on_milestone=true AND (lower(identifier)='stase' OR lower(name)='stase') LIMIT 1")->bindValue(':client',$client)->queryScalar();
            $updated=$db->createCommand()->update('t_logbook',array('id_semester'=>(int)$restore['id_semester'],'id_stase'=>$restoreStase,'updated_by'=>$actor,'updated_date'=>$now),'id=:id AND id_user=:user AND id_client=:client AND id_action=:action AND deleted_at IS NULL',array(':id'=>$targetLogbook,':user'=>$user,':client'=>$client,':action'=>$staseAction));
            if(!$updated) throw new RuntimeException('Baris Stase yang akan di-Undo tidak ditemukan');
        }
        $active=$this->calculateMorbiditasSessionPoints($db,$user,$client,(int)$restore['id_semester'],$restore['started_at']);
        $names=$db->createCommand('SELECT sem.name AS semester_name,st.name AS stase_name,sem.id_stage,stage.name AS stage_name FROM m_semester sem LEFT JOIN m_stase st ON st.id=:stase AND st.id_client=:client LEFT JOIN m_stage stage ON stage.id=sem.id_stage WHERE sem.id=:semester AND sem.id_client=:client')
            ->bindValues(array(':semester'=>(int)$restore['id_semester'],':stase'=>$restoreStase,':client'=>$client))->queryRow();
        $tx->commit();
        echo json_encode(array('success'=>true,'message'=>'Semester, Stase, dan sesi poin Morbiditas berhasil dikembalikan','data'=>array('id_user'=>$user,'id_semester'=>(int)$restore['id_semester'],'semester_name'=>$names['semester_name']??null,'id_stase'=>$restoreStase,'stase_name'=>$names['stase_name']??null,'id_stage'=>$names['id_stage']??null,'stage_name'=>$names['stage_name']??null,'total_points'=>$active)));
    } catch(Exception $e) { if($tx->active) $tx->rollback(); echo json_encode(array('success'=>false,'message'=>$e->getMessage())); }
    Yii::app()->end();
}





    
     private function createLogbookStorageDate($value)
    {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $format = strlen((string)$value) === 10 ? '!Y-m-d' : '!Y-m-d H:i:s';
        $date = DateTime::createFromFormat($format, (string)$value, $timezone);
        if (!$date || $date->format(ltrim($format, '!')) !== $value) {
            throw new CHttpException(422, 'Tanggal tidak valid.');
        }
        return $date->format('Y-m-d H:i:sP');
    }
    
    private function updateLogbookStorageDate($value) { $timezone=new DateTimeZone('Asia/Jakarta'); $format=strlen((string)$value)===10?'!Y-m-d':'!Y-m-d H:i:s'; $date=DateTime::createFromFormat($format,(string)$value,$timezone); if (!$date || $date->format(ltrim($format,'!'))!==$value) throw new CHttpException(422,'Tanggal tidak valid.'); return $date->format('Y-m-d H:i:sP'); }


    
/* Replace the existing actionCreateLogbook() method with this entire method. */
public function actionCreateLogbook()
{
    header('Content-Type: application/json; charset=utf-8');
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) return $this->createLogbookJson(false, 'Payload JSON tidak valid.', 400);
    if (!$this->createLogbookPositiveId(isset($payload['created_by']) ? $payload['created_by'] : null)
        || !$this->createLogbookPositiveId(isset($payload['id_client']) ? $payload['id_client'] : null)) {
        return $this->createLogbookJson(false, 'created_by dan id_client dari hasil login wajib valid.', 422);
    }
    if (!$this->createLogbookPositiveId(isset($payload['id_action']) ? $payload['id_action'] : null)
        || !isset($payload['date']) || !$this->createLogbookDate($payload['date'])) {
        return $this->createLogbookJson(false, 'id_action dan date (YYYY-MM-DD) wajib valid.', 422);
    }

    $db = Yii::app()->dbPrasi;
    $transaction = null;
    try {
        $actor = $this->createLogbookActor($db, $payload);
        $idClient = (int)$actor['id_client'];
        $idAction = (int)$payload['id_action'];
        $action = $this->createLogbookAction($db, $idAction, $idClient);
        $target = $this->createLogbookTarget($db, $payload, $actor, $idClient);
        $this->createLogbookRejectUnsupportedFields($payload, $action);
        $this->createLogbookValidateScalarFields($payload);

        // Cek apakah ini Morbiditas (by metadata atau legacy action 39)
        $isMorbiditas = (int)$action['id'] === 39 || $this->createLogbookIsMorbiditas($action);
        if ($isMorbiditas) {
            $morbiditasValidationAction = $action;
            if ((int)$action['id'] === 39 && !$this->createLogbookIsMorbiditas($action)) {
                $morbiditasValidationAction['identifier'] = 'morbiditas';
            }
            $this->createLogbookAssertMorbiditasPayload($db, $payload, $morbiditasValidationAction, $actor, $target, $idClient, $idAction);
        }

        $transaction = $db->beginTransaction();
        $now = date('Y-m-d H:i:s');
        $isExam = $this->createLogbookFlag($action, 'is_exam');
        $data = array(
            'id_action' => $idAction,
            'id_client' => $idClient,
            'id_user' => (int)$target['id'],
            'id_semester' => $this->createLogbookNullableId($target['id_semester']),
            'id_stase' => $this->createLogbookNullableId($target['id_stase']),
            'created_by' => (int)$actor['id'],
            'date' => $this->createLogbookStorageDate($payload['date']),
            'notes' => $this->createLogbookText($payload, 'notes'),
            'verified' => $isExam,
            'verified_status' => $isExam ? 'verified' : 'pending',
            'schedule_status' => $isExam ? 'verified' : 'pending',
            'created_date' => $now,
        );

        // === PERUBAHAN: ikat Morbiditas ke sesi aktif ===
        if ($isMorbiditas) {
            $activeSession = $db->createCommand('SELECT id FROM t_morbiditas_points_history
                WHERE id_user=:user AND id_client=:client AND ended_at IS NULL
                ORDER BY id DESC LIMIT 1')
                ->bindValues(array(':user'=>(int)$target['id'], ':client'=>$idClient))
                ->queryRow();
            if ($activeSession) {
                $data['id_morbiditas_period'] = (int)$activeSession['id'];
            }
        }

        $this->createLogbookApplyFlaggedFields($db, $data, $payload, $action, $idClient, $idAction);
        if ($isExam && !in_array($data['exam_result'], array('Lolos', 'Remidi', 'Tidak Lolos'), true)) {
            throw new CHttpException(422, 'Status ujian harus Lolos, Remidi, atau Tidak Lolos.');
        }
        $this->createLogbookAssertColumns($db, 't_logbook', array_keys($data));
        $idLogbook = $this->createLogbookInsertReturningId($db, 't_logbook', $data);
        if ($idLogbook < 1) throw new RuntimeException('ID logbook baru tidak tersedia.');

        $this->createLogbookChildren($db, $idLogbook, $payload, $action, $actor, $target, $idClient, $idAction, $now);
        $saved = $db->createCommand()->select('*')->from('t_logbook')
            ->where('id = :id AND id_client = :client', array(':id' => $idLogbook, ':client' => $idClient))->queryRow();
        $transaction->commit();
        return $this->createLogbookJson(true, 'Logbook berhasil dibuat.', 201, $saved);
    } catch (CHttpException $e) {
        if ($transaction !== null && $transaction->active) $transaction->rollback();
        return $this->createLogbookJson(false, $e->getMessage(), $e->statusCode);
    } catch (Throwable $e) {
        if ($transaction !== null && $transaction->active) $transaction->rollback();
        Yii::log('createLogbook failed: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
        return $this->createLogbookJson(false, 'Create logbook error: '.$e->getMessage(), 500);
    }
}



    /**
     * Compatibility identity for the existing stateless mobile login.
     * This is weaker than a server session/token: it verifies the supplied user
     * exists and belongs to the supplied client, but cannot prove who sent it.
     */
    private function createLogbookActor($db, $payload)
    {
        $id = (int)$payload['created_by'];
        $idClient = (int)$payload['id_client'];
        $userSchema = $this->createLogbookAssertColumns($db, 'm_user', array('id', 'id_client', 'id_role', 'id_semester', 'id_stase'));
        $deletedWhere = isset($userSchema->columns['deleted_at']) ? ' AND u.deleted_at IS NULL' : '';
        $actor = $db->createCommand()->select('u.id,u.id_client,u.id_role,u.id_semester,u.id_stase,r.name role_name')
            ->from('m_user u')->leftJoin('m_role r', 'r.id=u.id_role')
            ->where('u.id=:id AND u.id_client=:client' . $deletedWhere, array(':id' => $id, ':client' => $idClient))->queryRow();
        if (!$actor) {
            throw new CHttpException(403, 'User login tidak aktif atau bukan milik client yang dipilih.');
        }
        return $actor;
    }

    private function createLogbookSessionValue($key)
    {
        $user = Yii::app()->user;
        if ($key === 'id') return $user->isGuest ? null : $user->id;
        return $user->getState($key);
    }

    private function createLogbookAction($db, $idAction, $idClient)
    {
        $this->createLogbookAssertColumns($db, 'm_action', array('id', 'id_client'));
        $row = $db->createCommand()->select('*')->from('m_action')
            ->where('id=:id AND id_client=:client', array(':id' => $idAction, ':client' => $idClient))->queryRow();
        if (!$row) throw new CHttpException(404, 'Action tidak ditemukan untuk client ini.');
        return $row;
    }

private function createLogbookActorKind($user)
{
    $name = preg_replace('/[\\s_-]+/', ' ', strtolower(trim((string)$user['role_name'])));
    if ($name === 'ppds') return 'ppds';
    if ($name === 'staff' || $name === 'staff jejaring' || strpos($name, 'staff pengajar') !== false) return 'staff';
    if (in_array($name, array('institution', 'institusi', 'institution admin', 'admin institution', 'admin institusi'), true)) return 'institution';
    return 'unknown';
}

private function createLogbookTarget($db, $payload, $actor, $idClient)
{
    $role = $this->createLogbookActorKind($actor);
    if ($role === 'ppds') return $actor;
    if (!in_array($role, array('staff', 'institution'), true)
        || !$this->createLogbookPositiveId(isset($payload['id_user']) ? $payload['id_user'] : null)) {
        throw new CHttpException(403, 'Hanya PPDS, staff, atau institution yang dapat membuat logbook.');
    }

    $userSchema = $db->schema->getTable('m_user');
    $deletedWhere = $userSchema && isset($userSchema->columns['deleted_at']) ? ' AND u.deleted_at IS NULL' : '';
    $target = $db->createCommand()->select('u.id,u.id_client,u.id_semester,u.id_stase,r.name role_name')
        ->from('m_user u')->leftJoin('m_role r', 'r.id=u.id_role')
        ->where('u.id=:id AND u.id_client=:client' . $deletedWhere,
            array(':id'=>(int)$payload['id_user'], ':client'=>$idClient))->queryRow();
    if (!$target || $this->createLogbookActorKind($target) !== 'ppds') {
        throw new CHttpException(422, 'id_user harus PPDS aktif pada client yang sama.');
    }

    $isExam = $db->createCommand('SELECT is_exam FROM m_action WHERE id=:id AND id_client=:client')
        ->bindValues(array(':id'=>(int)$payload['id_action'], ':client'=>$idClient))->queryScalar();
    // Prasi-Bun: Staff/Institution can create an immediately verified Exam for a PPDS.
    if ((int)$actor['id'] !== (int)$target['id']
        && !$this->createLogbookFlag(array('is_exam'=>$isExam), 'is_exam')) {
        $this->createLogbookAssertTargetUser($actor, $target);
    }
    return $target;
}

    

    /**
     * Default-deny policy: a cross-user create needs an explicit server-side
     * authorization rule (for example a PPDS/staff assignment table). Override
     * this method only with that authoritative relation; never trust a mobile
     * boolean or free-form role label.
     */
    private function createLogbookAssertTargetUser($actor, $target)
    {
        if ((int)$actor['id'] !== (int)$target['id']) {
            throw new CHttpException(403, 'Kebijakan penugasan staff ke PPDS belum dikonfigurasi.');
        }
    }

    private function createLogbookApplyFlaggedFields($db, &$data, $payload, $action, $idClient, $idAction)
    {
        $map = array(
            'has_title' => array('title', 'text'), 'has_location' => array('location', 'text'),
            'has_hospital' => array('id_hospital', 'reference:m_hospital'),
            'has_category' => array('id_category', 'reference:m_action_category'),
            'has_another_role' => array('id_another_role', 'anotherRole'),
            'has_presentation' => array('is_presentation', 'boolean'),
            'has_operation_code' => array('operation_code', 'text'),
        );
        foreach ($map as $flag => $spec) {
            if (!$this->createLogbookFlag($action, $flag)) continue;
            $field = $spec[0]; $type = $spec[1];
            if ($type === 'text') $data[$field] = $this->createLogbookText($payload, $field);
            elseif ($type === 'boolean') $data[$field] = $this->createLogbookBool($payload, $field, false);
            else {
                $value = $this->createLogbookRequiredOrNullableId($payload, $field);
                if ($type === 'anotherRole') $this->createLogbookAnotherRole($db, $value, $idClient, $idAction);
                else $this->createLogbookReference($db, substr($type, 10), $value, $idClient, $idAction, $field);
                $data[$field] = $value;
            }
        }
        if ($this->createLogbookFlag($action, 'is_exam')) $data['exam_result'] = $this->createLogbookText($payload, 'exam_result');
        if ($this->createLogbookFlag($action, 'is_milestone')) $data['is_retake'] = $this->createLogbookBool($payload, 'is_retake', false);
        if (array_key_exists('id_stase', $payload)) {
            $data['id_stase'] = $this->createLogbookRequiredOrNullableId($payload, 'id_stase');
        }
        if ($data['id_stase'] !== null) $this->createLogbookReference($db, 'm_stase', $data['id_stase'], $idClient, $idAction, 'id_stase');
    }

    private function createLogbookChildren($db, $idLogbook, $payload, $action, $actor, $target, $idClient, $idAction, $now)
    {
        // Prasi menyimpan t_logbook_status berdasarkan m_action_rolemap, bukan
        // flag has_verifier. Validasi rolemap dilakukan per row di
        // createLogbookStatuses(), sehingga status tidak boleh ditolak di sini.
        $children = array('t_logbook_emr'=>'has_emr', 't_logbook_attachment'=>'has_attachment', 't_logbook_asm'=>'has_score', 't_logbook_status'=>null);
        foreach ($children as $field => $flag) {
            if (!array_key_exists($field, $payload)) continue;
            if ($flag !== null && !$this->createLogbookFlag($action, $flag)) throw new CHttpException(422, $field . ' tidak diizinkan oleh action ini.');
            if (!is_array($payload[$field])) throw new CHttpException(422, $field . ' harus berupa array.');
        }
        if (isset($payload['t_logbook_emr'])) $this->createLogbookEmr($db, $idLogbook, $payload['t_logbook_emr'], $idClient);
        if (isset($payload['t_logbook_attachment'])) $this->createLogbookAttachments($db, $idLogbook, $payload['t_logbook_attachment'], $idClient);
        if (isset($payload['t_logbook_asm'])) $this->createLogbookAsm($db, $idLogbook, $payload['t_logbook_asm'], $idClient, $idAction, $action);
        if (isset($payload['t_logbook_status'])) $this->createLogbookStatuses($db, $idLogbook, $payload['t_logbook_status'], $actor, $target, $idClient, $idAction, $now, $action);
    }

    private function createLogbookEmr($db, $idLogbook, $rows, $idClient)
    {
        $allowed = array('patient_name','age','month','gender','diagnosis','treatment','emr_number');
        foreach ($rows as $row) {
            if (!is_array($row)) throw new CHttpException(422, 'Baris EMR tidak valid.');
            foreach (array('patient_name','diagnosis') as $required) if (!isset($row[$required]) || !is_scalar($row[$required]) || trim((string)$row[$required]) === '') throw new CHttpException(422, 'EMR memerlukan ' . $required . '.');
            if (isset($row['age']) && (!$this->createLogbookInteger($row['age']) || (int)$row['age'] < 0 || (int)$row['age'] > 150)) throw new CHttpException(422, 'Umur EMR tidak valid.');
            if (isset($row['month']) && (!$this->createLogbookInteger($row['month']) || (int)$row['month'] < 0 || (int)$row['month'] > 11)) throw new CHttpException(422, 'Bulan EMR harus 0-11.');
            if (isset($row['gender'])) {
                $gender = strtolower(trim((string)$row['gender']));
                // Mobile label is "Male (M)"/"Female (F)"; persist the
                // canonical legacy code expected by t_logbook_emr.
                $gender = preg_replace('/\\s*\\([a-z]\\)\\s*$/', '', $gender);
                if (!in_array($gender, array('male','female','m','f','l','p'), true)) throw new CHttpException(422, 'Gender EMR tidak valid.');
                $row['gender'] = in_array($gender, array('male','m','l'), true) ? 'M' : 'F';
            }
            $data = $this->createLogbookAllowedRow($row, $allowed, array('id_logbook'=>$idLogbook,'id_client'=>$idClient));
            $this->createLogbookInsert($db, 't_logbook_emr', $data);
        }
    }

    private function createLogbookAttachments($db, $idLogbook, $rows, $idClient)
    {
        foreach ($rows as $row) {
            if (!is_array($row)) throw new CHttpException(422, 'Baris attachment tidak valid.');
            $path = isset($row['path']) ? $row['path'] : (isset($row['url_file']) ? $row['url_file'] : (isset($row['url']) ? $row['url'] : null));
            if (!$this->createLogbookSafeAttachmentPath($path)) throw new CHttpException(422, 'Path attachment harus path relatif server yang aman.');
            $data = $this->createLogbookAllowedRow($row, array('name','url_file','file_name','file_type','url','path'), array('id_logbook'=>$idLogbook,'id_client'=>$idClient));
            if (isset($data['path'])) $data['path'] = $this->createLogbookNormalPath($data['path']);
            foreach (array('url_file','url') as $field) if (isset($data[$field])) $data[$field] = $this->createLogbookNormalPath($data[$field]);
            $this->createLogbookInsert($db, 't_logbook_attachment', $data);
        }
    }

    private function createLogbookAsm($db, $idLogbook, $rows, $idClient, $idAction, $action)
    {
        foreach ($rows as $row) {
            if (!is_array($row) || !$this->createLogbookPositiveId(isset($row['id_asm_param']) ? $row['id_asm_param'] : null) || !isset($row['score']) || !is_numeric($row['score'])) throw new CHttpException(422, 'ASM memerlukan id_asm_param dan score numerik.');
            $param = $db->createCommand('SELECT ap.id,ap.min_score,ap.max_score FROM m_asm_param ap INNER JOIN m_asm_action aa ON aa.id_asm_param=ap.id WHERE ap.id=:id AND aa.id_action=:action')
                ->bindValues(array(':id'=>(int)$row['id_asm_param'], ':action'=>$idAction))->queryRow();
            if (!$param) throw new CHttpException(422, 'Parameter ASM tidak tersedia untuk action ini.');
            $score = (float)$row['score'];
            if (($param['min_score'] !== null && $score < (float)$param['min_score']) || ($param['max_score'] !== null && $score > (float)$param['max_score'])) throw new CHttpException(422, 'Score ASM di luar batas parameter.');
            // Prasi applies m_score_option only to has_score_option actions;
            // numeric ASM actions validate their metadata parameter bounds only.
            if ($this->createLogbookFlag($action, 'has_score_option')) {
                $valid = $db->createCommand()->select('id')->from('m_score_option')->where('id_action=:action AND id_client=:client AND score=:score', array(':action'=>$idAction, ':client'=>$idClient, ':score'=>$score))->queryRow();
                if (!$valid) throw new CHttpException(422, 'Score ASM bukan pilihan yang diizinkan.');
            }
            $this->createLogbookInsert($db, 't_logbook_asm', array('id_logbook'=>$idLogbook,'id_client'=>$idClient,'id_asm_param'=>(int)$row['id_asm_param'],'score'=>$score));
        }
    }

    private function createLogbookStatuses($db, $idLogbook, $rows, $actor, $target, $idClient, $idAction, $now, $action)
{
    if (!is_array($rows)) throw new CHttpException(422, 'Status verifier harus berupa array.');

    $actionText = strtolower(trim((isset($action['name']) ? $action['name'] : '') . ' '
        . (isset($action['action_name']) ? $action['action_name'] : '') . ' '
        . (isset($action['identifier']) ? $action['identifier'] : '')));
    $isThesisOrSeminar = preg_match('/proposal[-_\s]*thesis|proposal[-_\s]*tesis|\bthesis\b|seminar[-_\s]*hasil/', $actionText);

    if ($isThesisOrSeminar) {
        $roleRows = $db->createCommand(
            'SELECT ar.id, ar.role, ar.identifier FROM m_action_rolemap arm '
            . 'INNER JOIN m_action_role ar ON ar.id=arm.id_action_role '
            . "WHERE arm.id_action=:action AND ar.id_client=:client AND COALESCE(arm.type, 'verificator') IN ('verificator','approval')"
        )->bindValues(array(':action'=>(int)$idAction, ':client'=>(int)$idClient))->queryAll();
        $requiredPatterns = array(
            'Pembimbing 1'=>'/pembimbing\s*(1|i\b)/i',
            'Pembimbing 2'=>'/pembimbing\s*(2|ii\b)/i',
            'Penguji 1'=>'/penguji\s*(1|i\b)/i',
        );
        $requiredRoleIds = array();
        foreach ($requiredPatterns as $label=>$pattern) {
            foreach ($roleRows as $roleRow) {
                if (preg_match($pattern, (string)$roleRow['role'] . ' ' . (string)$roleRow['identifier'])) {
                    $requiredRoleIds[] = (int)$roleRow['id'];
                    break;
                }
            }
        }
        if (count($requiredRoleIds) !== 3) throw new CHttpException(422, 'Rolemap harus memiliki Pembimbing 1, Pembimbing 2, dan Penguji 1.');
        $receivedRoleIds = array();
        foreach ($rows as $row) if (is_array($row) && isset($row['id_action_role'])) $receivedRoleIds[] = (int)$row['id_action_role'];
        foreach ($requiredRoleIds as $requiredRoleId) {
            if (!in_array($requiredRoleId, $receivedRoleIds, true)) {
                throw new CHttpException(422, 'Proposal Thesis dan Seminar Hasil memerlukan Pembimbing 1, Pembimbing 2, dan Penguji 1. Pembimbing 3 opsional.');
            }
        }
    }

    $notifiedUserIds = array();
    $notifyPendingStaff = function($recipientId) use ($db, $action, $actor, $idAction, $idLogbook, $idClient, $now, &$notifiedUserIds) {
        $recipientId = (int)$recipientId;
        if ($recipientId < 1 || in_array($recipientId, $notifiedUserIds, true)) return;
        $notifiedUserIds[] = $recipientId;
        try {
            $recipient = $db->createCommand('SELECT id, id_role FROM m_user WHERE id=:id AND id_client=:client')
                ->bindValues(array(':id'=>$recipientId, ':client'=>(int)$idClient))->queryRow();
            if (!$recipient) return;
            $actionName = trim((string)(isset($action['name']) ? $action['name'] : (isset($action['action_name']) ? $action['action_name'] : 'Logbook')));
            $ppdsName = trim((string)(isset($actor['display_name']) ? $actor['display_name'] : 'PPDS'));
            $message = $ppdsName . ' menambahkan data ' . $actionName . ' pada ' . date('d/m/Y H:i', strtotime($now)) . ', mohon berikan verifikasi Anda.';
            $db->createCommand()->insert('t_notif', array(
                'message'=>$message,
                'date'=>$now,
                'type'=>'verify',
                'id_user'=>$recipientId,
                'url'=>'/staff/action/' . (int)$idAction . '/' . (int)$idLogbook,
                'id_role'=>$recipient['id_role'],
                'read'=>new CDbExpression('FALSE'),
                'id_client'=>(int)$idClient,
                'id_logbook'=>(int)$idLogbook,
            ));
        } catch (Throwable $notificationError) {
            Yii::log('CreateLogbook staff notification failed: ' . $notificationError->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
        }
    };

    foreach ($rows as $row) {
        if (!is_array($row) || !$this->createLogbookPositiveId(isset($row['id_user']) ? $row['id_user'] : null) || !$this->createLogbookPositiveId(isset($row['id_action_role']) ? $row['id_action_role'] : null)) throw new CHttpException(422, 'Verifier memerlukan id_user dan id_action_role.');
        $isJejaring = array_key_exists('notes', $row) && $row['notes'] === '__staff_jejaring__';
        if (array_key_exists('notes', $row) && !$isJejaring) throw new CHttpException(422, 'Catatan status verifier tidak diizinkan.');
        if (isset($row['status']) && strtolower((string)$row['status']) !== 'pending') throw new CHttpException(422, 'Status verifier awal harus pending.');
        if ($isJejaring) {
            $this->createLogbookAssertStaffJejaringStatus($db, $row, $target, $idClient, $idAction);
            $this->createLogbookInsert($db, 't_logbook_status', array('id_logbook'=>$idLogbook,'id_client'=>$idClient,'id_user'=>(int)$row['id_user'],'id_action_role'=>(int)$row['id_action_role'],'status'=>'pending','notes'=>'__staff_jejaring__','date_time'=>$now));
            $notifyPendingStaff((int)$row['id_user']);
            continue;
        }
        $rolemap = $db->createCommand('SELECT arm.id FROM m_action_rolemap arm INNER JOIN m_action_role ar ON ar.id=arm.id_action_role WHERE arm.id_action=:action AND arm.id_action_role=:role AND ar.id_client=:client')
            ->bindValues(array(':action'=>$idAction, ':role'=>(int)$row['id_action_role'], ':client'=>$idClient))->queryRow();
        if (!$rolemap) throw new CHttpException(422, 'Rolemap verifier tidak tersedia untuk action ini.');
        $userSchema = $db->schema->getTable('m_user');
        $activeWhere = $userSchema && isset($userSchema->columns['deleted_at']) ? ' AND u.deleted_at IS NULL' : '';
        if ($userSchema && isset($userSchema->columns['status'])) $activeWhere .= " AND LOWER(u.status)='active'";
        $staffRoles = strpos($actionText, 'morbid') !== false
            ? "(LOWER(TRIM(r.name))='staff' OR LOWER(TRIM(r.name))='staff jejaring')"
            : "LOWER(TRIM(r.name))='staff'";
        $verifier = $db->createCommand('SELECT u.id FROM m_user u INNER JOIN m_role r ON r.id=u.id_role WHERE u.id=:user AND u.id_client=:client' . $activeWhere . ' AND ' . $staffRoles)
            ->bindValues(array(':user'=>(int)$row['id_user'], ':client'=>$idClient))->queryRow();
        if (!$verifier) throw new CHttpException(422, 'Verifier bukan staff aktif yang diizinkan.');
        $this->createLogbookInsert($db, 't_logbook_status', array('id_logbook'=>$idLogbook,'id_client'=>$idClient,'id_user'=>(int)$row['id_user'],'id_action_role'=>(int)$row['id_action_role'],'status'=>'pending','date_time'=>$now));
        $notifyPendingStaff((int)$row['id_user']);
    }
}


    private function createLogbookAssertStaffJejaringStatus($db, $row, $target, $idClient, $idAction)
    {
        $staseSchema = $db->schema->getTable('m_stase');
        if (!$staseSchema || !isset($staseSchema->columns['has_staff_jejaring']) || !$this->createLogbookPositiveId(isset($target['id_stase']) ? $target['id_stase'] : null)) throw new CHttpException(422, 'Staff Pengajar Jejaring tidak tersedia untuk stase PPDS ini.');
        $stase = $db->createCommand('SELECT has_staff_jejaring FROM m_stase WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id'=>(int)$target['id_stase'], ':client'=>$idClient))->queryRow();
        if (!$stase || !$this->createLogbookTruthy($stase['has_staff_jejaring'])) throw new CHttpException(422, 'Staff Pengajar Jejaring tidak tersedia untuk stase PPDS ini.');

        $rolemap = $db->createCommand('SELECT ar.role, ar.identifier FROM m_action_rolemap arm INNER JOIN m_action_role ar ON ar.id=arm.id_action_role WHERE arm.id_action=:action AND arm.id_action_role=:role AND ar.id_client=:client')
            ->bindValues(array(':action'=>$idAction, ':role'=>(int)$row['id_action_role'], ':client'=>$idClient))->queryRow();
        if (!$rolemap || !$this->createLogbookIsMainStaffPengajar($rolemap['role'], $rolemap['identifier'])) throw new CHttpException(422, 'Role Staff Pengajar utama tidak cocok.');

        $userSchema = $db->schema->getTable('m_user');
        $where = 'u.id=:user AND u.id_client=:client AND LOWER(r.name)=:role';
        if ($userSchema && isset($userSchema->columns['deleted_at'])) $where .= ' AND u.deleted_at IS NULL';
        if ($userSchema && isset($userSchema->columns['status'])) $where .= " AND LOWER(u.status)='active'";
        $user = $db->createCommand('SELECT u.id FROM m_user u INNER JOIN m_role r ON r.id=u.id_role WHERE ' . $where)
            ->bindValues(array(':user'=>(int)$row['id_user'], ':client'=>$idClient, ':role'=>'staff jejaring'))->queryRow();
        if (!$user) throw new CHttpException(422, 'Staff Pengajar Jejaring tidak aktif atau tidak cocok.');
    }

    private function createLogbookTruthy($value) { return $value === true || in_array(strtolower(trim((string)$value)), array('1','true','t','yes','y'), true); }
    private function createLogbookIsMainStaffPengajar($role, $identifier) { $name=preg_replace('/[\\s_-]+/', ' ', strtolower(trim((string)$role))); $id=preg_replace('/[\\s_-]+/', ' ', strtolower(trim((string)$identifier))); return (strpos($name,'staff pengajar')!==false || strpos($id,'staff pengajar')!==false) && strpos($name,'jejaring')===false && strpos($id,'jejaring')===false; }

    private function createLogbookRejectUnsupportedFields($payload, $action)
    {
        $fields = array('title'=>'has_title','location'=>'has_location','id_hospital'=>'has_hospital','id_category'=>'has_category','id_another_role'=>'has_another_role','is_presentation'=>'has_presentation','operation_code'=>'has_operation_code','exam_result'=>'is_exam','is_retake'=>'is_milestone');
        foreach ($fields as $field=>$flag) if (array_key_exists($field, $payload) && !$this->createLogbookFlag($action, $flag)) throw new CHttpException(422, $field . ' tidak diizinkan oleh action ini.');
    }

    private function createLogbookReference($db, $table, $id, $idClient, $idAction, $field)
    {
        if ($id === null) return;
        $schema = $this->createLogbookAssertColumns($db, $table, array('id','id_client'));
        $where = 'id=:id AND id_client=:client'; $params = array(':id'=>$id, ':client'=>$idClient);
        if (isset($schema->columns['id_action'])) { $where .= ' AND id_action=:action'; $params[':action']=$idAction; }
        if (isset($schema->columns['deleted_at'])) $where .= ' AND deleted_at IS NULL';
        if (!$db->createCommand()->select('id')->from($table)->where($where, $params)->queryRow()) throw new CHttpException(422, $field . ' tidak valid.');
    }

    private function createLogbookAnotherRole($db, $id, $idClient, $idAction)
    {
        if ($id === null) return;
        $ok = $db->createCommand('SELECT ar.id FROM m_another_role ar INNER JOIN m_action_another_role map ON map.id_another_role=ar.id WHERE ar.id=:id AND ar.id_client=:client AND map.id_action=:action')
            ->bindValues(array(':id'=>$id, ':client'=>$idClient, ':action'=>$idAction))->queryRow();
        if (!$ok) throw new CHttpException(422, 'id_another_role tidak tersedia untuk action ini.');
    }

    private function createLogbookInsert($db, $table, $data) { $this->createLogbookAssertColumns($db, $table, array_keys($data)); $db->createCommand()->insert($table, $data); }
    private function createLogbookInsertReturningId($db, $table, $data)
    {
        $this->createLogbookAssertColumns($db, $table, array_keys($data));
        $columns = array(); $placeholders = array(); $params = array(); $index = 0;
        foreach ($data as $column => $value) {
            // Column names come from fixed backend data and schema validation.
            $columns[] = '"' . str_replace('"', '""', $column) . '"';
            $placeholder = ':create_value_' . $index++;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        }
        $sql = 'INSERT INTO "' . str_replace('"', '""', $table) . '" (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ') RETURNING "id"';
        $command = $db->createCommand($sql);
        foreach ($params as $placeholder => $value) {
            $type = $value === null ? PDO::PARAM_NULL : (is_bool($value) ? PDO::PARAM_BOOL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
            $command->bindValue($placeholder, $value, $type);
        }
        return (int)$command->queryScalar();
    }
    private function createLogbookAllowedRow($row, $allowed, $fixed) { $data=$fixed; foreach ($allowed as $field) if (array_key_exists($field,$row)) { if (!is_scalar($row[$field]) && $row[$field]!==null) throw new CHttpException(422, $field.' harus scalar.'); $data[$field]=$row[$field]; } return $data; }
    private function createLogbookFlag($action, $field) { return isset($action[$field]) && in_array(strtolower((string)$action[$field]), array('1','true','t','yes','y'), true); }
    private function createLogbookPositiveId($value) { return is_scalar($value) && preg_match('/^[1-9][0-9]*$/',(string)$value)===1; }
    private function createLogbookNullableId($value) { return $this->createLogbookPositiveId($value) ? (int)$value : null; }
    private function createLogbookRequiredOrNullableId($payload,$field) { if (!array_key_exists($field,$payload) || $payload[$field]==='' || $payload[$field]===null) return null; if (!$this->createLogbookPositiveId($payload[$field])) throw new CHttpException(422,$field.' harus ID positif.'); return (int)$payload[$field]; }
    private function createLogbookInteger($value) { return is_scalar($value) && preg_match('/^-?[0-9]+$/',(string)$value)===1; }
    private function createLogbookDate($value)
    {
        if (!is_string($value)) return false;
        foreach (array('!Y-m-d', '!Y-m-d H:i:s') as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date && $date->format(ltrim($format, '!')) === $value) return true;
        }
        return false;
    }
    private function createLogbookText($payload,$field) { if (!array_key_exists($field,$payload) || $payload[$field]===null || $payload[$field]==='') return null; if (!is_scalar($payload[$field])) throw new CHttpException(422,$field.' harus teks.'); return trim((string)$payload[$field]); }
    private function createLogbookBool($payload,$field,$default) { if (!array_key_exists($field,$payload)) return $default; if (is_bool($payload[$field])) return $payload[$field]; if (in_array((string)$payload[$field],array('0','1'),true)) return $payload[$field]==='1'; throw new CHttpException(422,$field.' harus boolean.'); }
    private function createLogbookSafeAttachmentPath($path) { return is_string($path) && $path!=='' && preg_match('#^(?![\\\\/]|[A-Za-z]:)(?!.*(?:^|[\\\\/])\.\.(?:[\\\\/]|$))[A-Za-z0-9][A-Za-z0-9._/\\\\-]*$#',$path)===1; }
    private function createLogbookNormalPath($path) { return str_replace('\\\\','/',(string)$path); }
    private function createLogbookValidateScalarFields($payload) { foreach (array('notes','title','location','operation_code','exam_result') as $field) if (isset($payload[$field]) && !is_scalar($payload[$field])) throw new CHttpException(422,$field.' harus teks.'); }
    private function createLogbookAssertColumns($db,$table,$columns) { $schema=$db->schema->getTable($table); if (!$schema) throw new CHttpException(500,'Tabel '.$table.' tidak ditemukan.'); foreach ($columns as $column) if (!isset($schema->columns[$column])) throw new CHttpException(500,'Kolom '.$table.'.'.$column.' tidak ditemukan.'); return $schema; }
    private function createLogbookJson($success,$message,$status,$data=null) { if (!headers_sent()) http_response_code($status); $out=array('success'=>(bool)$success,'message'=>$message); if ($data!==null) $out['data']=$data; echo json_encode($out); Yii::app()->end(); }
    
    
    
    // update logbookkkkkkkkkkkk
    /* REPLACE the entire existing actionSaveLogbookUpdate() method; do not append. */
    public function actionSaveLogbookUpdate()
    {
        header('Content-Type: application/json; charset=utf-8');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) return $this->updateLogbookJson(false, 'Payload JSON tidak valid.', 400);
        foreach (array('id', 'created_by', 'id_client') as $field) {
            if (!$this->updateLogbookPositiveId(isset($payload[$field]) ? $payload[$field] : null)) {
                return $this->updateLogbookJson(false, 'Permintaan tidak valid.', 422);
            }
        }
        $db = Yii::app()->dbPrasi;
        $transaction = null;
        try {
            $transaction = $db->beginTransaction();
            $actor = $this->updateLogbookActor($db, $payload);
            $parent = $this->updateLogbookLockedParent($db, (int)$payload['id'], (int)$actor['id_client'], (int)$actor['id']);
            $action = $db->createCommand('SELECT * FROM m_action WHERE id=:id AND id_client=:id_client')
                ->bindValues(array(':id'=>(int)$parent['id_action'], ':id_client'=>(int)$actor['id_client']))->queryRow();
            if (!$action) throw new CHttpException(404, 'Logbook tidak ditemukan.');
            $data = $this->updateLogbookScalarFields($db, $payload, $action, (int)$actor['id_client']);
            if (!$data) throw new CHttpException(422, 'Tidak ada perubahan yang diizinkan.');
            $now = date('Y-m-d H:i:s');
            $schema = $this->updateLogbookSchema($db, 't_logbook');
            if (isset($schema['updated_date'])) $data['updated_date'] = $now;
            $this->updateLogbookBoundUpdate($db, 't_logbook', $data,
                'id=:id AND id_client=:id_client AND id_user=:actor_id',
                array(':id'=>(int)$parent['id'], ':id_client'=>(int)$actor['id_client'], ':actor_id'=>(int)$actor['id']));
            if (array_key_exists('t_logbook_emr', $payload)) {
                $this->updateLogbookUpsertEmrByLogbook($db, $payload['t_logbook_emr'], $action, (int)$parent['id'], (int)$actor['id_client'], $now);
            }
            // Child rows stay unchanged unless the client supplies server-issued IDs.
            // IDs are locked and scoped below; no client field can create or re-parent a child.
            if (array_key_exists('t_logbook_attachment', $payload)) {
                $this->updateLogbookServerIssuedChildren($db, 't_logbook_attachment', $payload['t_logbook_attachment'],
                    array('name','url_file','file_name','file_type','url','path'), (int)$parent['id'], (int)$actor['id_client'], $now);
            }
            if (array_key_exists('t_logbook_status', $payload)) {
                $this->updateLogbookUpdateVerifierAssignments($db, $payload['t_logbook_status'], $action,
                    (int)$parent['id'], (int)$actor['id_client'], $now);
            }

            /* A PPDS re-save after any rejection/revision is a revision request.
             * Detect child state too: the parent can be `pending` when another
             * verifier is still pending alongside a rejected Staff Jejaring row. */
            $previousParentStatus = strtolower(trim((string)($parent['verified_status'] ?? 'pending')));
            $hasReviewState = in_array($previousParentStatus, array('rejected', 'revised', 'revision'), true)
                || (bool)$db->createCommand("SELECT 1 FROM t_logbook_status WHERE id_logbook=:id_logbook AND LOWER(COALESCE(status, 'pending')) IN ('rejected','revised','revision') LIMIT 1")
                    ->bindValue(':id_logbook', (int)$parent['id'])->queryScalar();
            if ($hasReviewState) {
                $statusSchema = $this->updateLogbookSchema($db, 't_logbook_status', false);
                if ($statusSchema && isset($statusSchema['status'])) {
                    $statusWhere = 'id_logbook = :id_logbook AND LOWER(COALESCE(status, \'pending\')) <> \'verified\'';
                    $statusParams = array(':id_logbook' => (int)$parent['id']);
                    if (isset($statusSchema['id_client'])) {
                        $statusWhere .= ' AND id_client = :id_client';
                        $statusParams[':id_client'] = (int)$actor['id_client'];
                    }
                    if (isset($statusSchema['deleted_at'])) $statusWhere .= ' AND deleted_at IS NULL';
                    $statusData = array('status' => 'revised');
                    if (isset($statusSchema['date_time'])) $statusData['date_time'] = $now;
                    $this->updateLogbookBoundUpdate($db, 't_logbook_status', $statusData, $statusWhere, $statusParams);
                }
                $revisedParent = array();
                if (isset($schema['verified'])) $revisedParent['verified'] = false;
                if (isset($schema['verified_status'])) $revisedParent['verified_status'] = 'revised';
                if (isset($schema['updated_date'])) $revisedParent['updated_date'] = $now;
                if ($revisedParent) {
                    $this->updateLogbookBoundUpdate($db, 't_logbook', $revisedParent,
                        'id = :id AND id_client = :id_client AND id_user = :actor_id',
                        array(':id'=>(int)$parent['id'], ':id_client'=>(int)$actor['id_client'], ':actor_id'=>(int)$actor['id']));
                }
            }
            $saved = $db->createCommand()->select('*')->from('t_logbook')
                ->where('id=:id AND id_client=:id_client', array(':id'=>(int)$parent['id'], ':id_client'=>(int)$actor['id_client']))->queryRow();
            $transaction->commit();
            return $this->updateLogbookJson(true, 'Logbook berhasil diperbarui.', 200, $saved);
        } catch (CHttpException $e) {
            if ($transaction !== null && $transaction->active) $transaction->rollback();
            return $this->updateLogbookJson(false, $e->getMessage(), $e->statusCode);
        } catch (Throwable $e) {
            if ($transaction !== null && $transaction->active) $transaction->rollback();
            Yii::log('UpdateLogbook failed: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
            /* TEMPORARY: remove data.detail after the Morbiditas failure is identified. */
            return $this->updateLogbookJson(false, 'Gagal memperbarui logbook.', 500, array(
                'detail' => $e->getMessage(),
                'error_type' => get_class($e),
            ));
        }
    }

 private function updateLogbookActor($db, $payload)
{
    /*
     * Resolve the actor from the record being edited, not m_user role or
     * lifecycle columns. This request is PPDS-owner scoped: the submitted
     * actor must be exactly t_logbook.id_user for this record and client.
     */
    $columns = $this->updateLogbookSchema($db, 't_logbook');
    $where = 'lb.id=:logbook_id AND lb.id_user=:actor_id AND lb.id_client=:id_client';
    if (isset($columns['deleted_at'])) {
        $where .= ' AND lb.deleted_at IS NULL';
    }

    $actor = $db->createCommand(
        'SELECT lb.id_user AS id, lb.id_client FROM t_logbook lb WHERE ' . $where
    )->bindValues(array(
        ':logbook_id' => (int) $payload['id'],
        ':actor_id' => (int) $payload['created_by'],
        ':id_client' => (int) $payload['id_client'],
    ))->queryRow();

    if (!$actor) {
        throw new CHttpException(403, 'Logbook bukan milik pengguna ini.');
    }

    return $actor;
}

    private function updateLogbookLockedParent($db, $id, $idClient, $actorId)
    {
        $columns = $this->updateLogbookSchema($db, 't_logbook');
        foreach (array('id','id_client','id_user','id_action') as $column) if (!isset($columns[$column])) throw new RuntimeException('Required logbook schema is unavailable.');
        // Prasi ownership is the PPDS record owner. `created_by` is legacy audit
        // data and may differ for migrated entries, so it must not block edits.
        $where = 'lb.id=:id AND lb.id_client = :id_client AND lb.id_user = :actor_id';
        if (isset($columns['deleted_at'])) $where .= ' AND lb.deleted_at IS NULL';
        if (isset($columns['verified_status'])) $where .= " AND LOWER(COALESCE(lb.verified_status, 'pending')) IN ('pending','revised','rejected')";
        // Prasi `action_save.show()` gates PPDS edits only by the parent
        // verified_status. It does not reject an edit merely because a sibling
        // verifier row has already transitioned.
        $parent = $db->createCommand('SELECT lb.* FROM t_logbook lb WHERE ' . $where . ' FOR UPDATE')
            ->bindValues(array(':id'=>$id, ':id_client'=>$idClient, ':actor_id'=>$actorId))->queryRow();
        if (!$parent) throw new CHttpException(403, 'Logbook tidak dapat diubah.');
        return $parent;
    }

    private function updateLogbookScalarFields($db, $payload, $action, $idClient)
    {
        $schema = $this->updateLogbookSchema($db, 't_logbook');
        $allowed = array('date'=>'date','notes'=>'text','title'=>'text','location'=>'text','operation_code'=>'text','exam_result'=>'text','is_retake'=>'bool','is_presentation'=>'bool','id_hospital'=>'id','id_category'=>'id','id_another_role'=>'id','id_stase'=>'id');
        $flagged = array('title'=>'has_title','location'=>'has_location','operation_code'=>'has_operation_code','exam_result'=>'is_exam','is_retake'=>'is_milestone','is_presentation'=>'has_presentation','id_hospital'=>'has_hospital','id_category'=>'has_category','id_another_role'=>'has_another_role');
        $data = array();
        foreach ($allowed as $field=>$type) {
            if (!array_key_exists($field, $payload)) continue;
            if (!isset($schema[$field])) throw new CHttpException(422, 'Field tidak didukung.');
            if (isset($flagged[$field]) && !$this->updateLogbookFlag($action, $flagged[$field])) throw new CHttpException(422, 'Field tidak diizinkan.');
            if ($type === 'date') { if (!$this->updateLogbookDate($payload[$field])) throw new CHttpException(422, 'Tanggal tidak valid.'); $data[$field]=$this->updateLogbookStorageDate($payload[$field]); }

            elseif ($type === 'text') { if ($payload[$field] !== null && !is_scalar($payload[$field])) throw new CHttpException(422, 'Field tidak valid.'); $data[$field]=$payload[$field] === null ? null : trim((string)$payload[$field]); }
            elseif ($type === 'bool') { $data[$field]=$this->updateLogbookBool($payload[$field]); }
            else { $data[$field]=$this->updateLogbookNullableId($payload[$field]); }
        }
        return $data;
    }

    /**
     * Prasi upserts EMR by its trusted parent logbook relation. Mobile must not
     * know or invent a child-row ID; the locked parent scope is the authority.
     */
    private function updateLogbookUpsertEmrByLogbook($db, $rows, $action, $idLogbook, $idClient, $now)
{
    if (!$this->updateLogbookFlag($action, 'has_emr')) throw new CHttpException(422, 'EMR tidak diizinkan.');
    if (!is_array($rows) || count($rows) !== 1 || !is_array($rows[0])) throw new CHttpException(422, 'Baris EMR tidak valid.');
    $schema = $this->updateLogbookSchema($db, 't_logbook_emr', false);
    if (!$schema || !isset($schema['id']) || !isset($schema['id_logbook'])) throw new CHttpException(422, 'EMR tidak didukung oleh schema.');

    $data = array();
    foreach (array('patient_name','age','month','gender','diagnosis','treatment','emr_number') as $field) {
        if (!array_key_exists($field, $rows[0]) || !isset($schema[$field])) continue;
        if ($rows[0][$field] !== null && !is_scalar($rows[0][$field])) throw new CHttpException(422, 'EMR tidak valid.');

        if ($field === 'age' || $field === 'month') {
            $data[$field] = $this->updateLogbookEmrNullableInteger($rows[0][$field], $field);
            if ($field === 'month' && $data[$field] !== null && ($data[$field] < 0 || $data[$field] > 11)) {
                throw new CHttpException(422, 'Bulan EMR harus 0-11.');
            }
            continue;
        }

        $data[$field] = $rows[0][$field];
    }
    if (!$data) return;
    if (isset($schema['updated_date'])) $data['updated_date'] = $now;

    $where = 'id_logbook=:id_logbook';
    $params = array(':id_logbook'=>$idLogbook);
    if (isset($schema['id_client'])) { $where .= ' AND id_client=:id_client'; $params[':id_client']=$idClient; }
    if (isset($schema['deleted_at'])) $where .= ' AND deleted_at IS NULL';
    $existing = $db->createCommand('SELECT id FROM t_logbook_emr WHERE ' . $where . ' FOR UPDATE')->bindValues($params)->queryRow();
    if ($existing) {
        $this->updateLogbookBoundUpdate($db, 't_logbook_emr', $data, 'id=:id', array(':id'=>(int)$existing['id']));
        return;
    }

    $data['id_logbook'] = $idLogbook;
    if (isset($schema['id_client'])) $data['id_client'] = $idClient;
    if (isset($schema['created_date'])) $data['created_date'] = $now;
    $db->createCommand()->insert('t_logbook_emr', $data);
}

private function updateLogbookEmrNullableInteger($value, $field)
{
    if ($value === null || (is_string($value) && trim($value) === '')) return null;
    if (!is_scalar($value) || !preg_match('/^\d+$/', trim((string)$value))) {
        throw new CHttpException(422, ucfirst($field) . ' EMR harus berupa angka.');
    }
    return (int)$value;
}

    /**
     * Prasi saves verifier selections by action-role.  The mobile form sends
     * real IDs from metadata; changing a pending/revised assignment resets only
     * that slot to pending and never rewrites an unrelated verifier row.
     */
    /* REPLACE the entire existing updateLogbookUpdateVerifierAssignments() method; do not append. */
    private function updateLogbookUpdateVerifierAssignments($db, $rows, $action, $idLogbook, $idClient, $now)
    {
        if (!is_array($rows)) throw new CHttpException(422, 'Status verifier tidak valid.');
        $schema = $this->updateLogbookSchema($db, 't_logbook_status', false);
        if (!$schema || !isset($schema['id']) || !isset($schema['id_logbook']) || !isset($schema['id_user']) || !isset($schema['id_action_role'])) {
            throw new CHttpException(422, 'Status verifier tidak didukung oleh schema.');
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !$this->updateLogbookPositiveId(isset($row['id_user']) ? $row['id_user'] : null)
                || !$this->updateLogbookPositiveId(isset($row['id_action_role']) ? $row['id_action_role'] : null)) {
                throw new CHttpException(422, 'Status verifier tidak valid.');
            }
            $rolemap = $db->createCommand('SELECT arm.id FROM m_action_rolemap arm INNER JOIN m_action_role ar ON ar.id=arm.id_action_role WHERE arm.id_action=:action AND arm.id_action_role=:role AND ar.id_client=:client')
                ->bindValues(array(':action'=>(int)$action['id'], ':role'=>(int)$row['id_action_role'], ':client'=>$idClient))->queryRow();
            if (!$rolemap) throw new CHttpException(422, 'Rolemap verifier tidak tersedia untuk action ini.');
            $user = $db->createCommand("SELECT u.id FROM m_user u INNER JOIN m_role r ON r.id=u.id_role WHERE u.id=:user AND u.id_client=:client AND LOWER(TRIM(r.name)) IN ('staff', 'staff jejaring')")
                ->bindValues(array(':user'=>(int)$row['id_user'], ':client'=>$idClient))->queryRow();
            if (!$user) throw new CHttpException(422, 'Verifier bukan staff aktif yang diizinkan.');

            /* Main Staff and Staff Jejaring can intentionally share one
             * id_action_role. The Jejaring marker is therefore part of the
             * natural slot key; matching only action role swaps assignments. */
            $isJejaringSlot = isset($row['notes'])
                && strpos((string)$row['notes'], '__staff_jejaring__') === 0;
            $where = 'id_logbook=:id_logbook AND id_action_role=:role';
            $params = array(':id_logbook'=>$idLogbook, ':role'=>(int)$row['id_action_role']);
            if (isset($schema['id_client'])) { $where .= ' AND id_client=:id_client'; $params[':id_client']=$idClient; }
            if (isset($schema['notes'])) {
                $where .= $isJejaringSlot
                    ? " AND COALESCE(notes, '') LIKE :jejaring_marker"
                    : " AND COALESCE(notes, '') NOT LIKE :jejaring_marker";
                $params[':jejaring_marker'] = '__staff_jejaring__%';
            } elseif ($isJejaringSlot) {
                throw new CHttpException(422, 'Schema status tidak mendukung penanda Staff Jejaring.');
            }
            if (isset($schema['deleted_at'])) $where .= ' AND deleted_at IS NULL';
            $existing = $db->createCommand('SELECT * FROM t_logbook_status WHERE ' . $where . ' FOR UPDATE')->bindValues($params)->queryRow();
            /* Older reject rows used notes for the human reason and therefore
             * lack the Jejaring marker. Reclaim that exact unresolved user/role
             * row before inserting; otherwise PPDS Edit creates a duplicate. */
            if (!$existing && $isJejaringSlot && isset($schema['notes'])) {
                $legacyWhere = 'id_logbook=:id_logbook AND id_action_role=:role AND id_user=:user'
                    . " AND COALESCE(notes, '') NOT LIKE :jejaring_marker"
                    . " AND LOWER(COALESCE(status, 'pending')) IN ('rejected','revised','revision')";
                $legacyParams = array(
                    ':id_logbook'=>$idLogbook,
                    ':role'=>(int)$row['id_action_role'],
                    ':user'=>(int)$row['id_user'],
                    ':jejaring_marker'=>'__staff_jejaring__%',
                );
                if (isset($schema['id_client'])) { $legacyWhere .= ' AND id_client=:id_client'; $legacyParams[':id_client']=$idClient; }
                if (isset($schema['deleted_at'])) $legacyWhere .= ' AND deleted_at IS NULL';
                $existing = $db->createCommand('SELECT * FROM t_logbook_status WHERE ' . $legacyWhere . ' FOR UPDATE')
                    ->bindValues($legacyParams)->queryRow();
                if ($existing) {
                    $legacyReason = trim((string)($existing['notes'] ?? ''));
                    $markerNotes = '__staff_jejaring__' . ($legacyReason !== '' ? "\n" . $legacyReason : '');
                    $this->updateLogbookBoundUpdate($db, 't_logbook_status', array('notes'=>$markerNotes), 'id=:id', array(':id'=>(int)$existing['id']));
                    $existing['notes'] = $markerNotes;
                }
            }
            if ($existing) {
                if ((int)$existing['id_user'] === (int)$row['id_user']) continue;
                $oldStatus = strtolower((string)(isset($existing['status']) ? $existing['status'] : 'pending'));
                if ($oldStatus === 'verified') throw new CHttpException(409, 'Verifier yang sudah verified tidak dapat diganti.');
                $data = array('id_user'=>(int)$row['id_user']);
                if (isset($schema['status'])) $data['status']='pending';
                if (isset($schema['date_time'])) $data['date_time']=$now;
                if (isset($schema['verify_notes'])) $data['verify_notes']=null;
                if (isset($schema['reject_notes'])) $data['reject_notes']=null;
                $this->updateLogbookBoundUpdate($db, 't_logbook_status', $data, 'id=:id', array(':id'=>(int)$existing['id']));
                continue;
            }
            $data = array('id_logbook'=>$idLogbook, 'id_user'=>(int)$row['id_user'], 'id_action_role'=>(int)$row['id_action_role']);
            if (isset($schema['id_client'])) $data['id_client']=$idClient;
            if ($isJejaringSlot && isset($schema['notes'])) $data['notes']='__staff_jejaring__';
            if (isset($schema['status'])) $data['status']='pending';
            if (isset($schema['date_time'])) $data['date_time']=$now;
            $db->createCommand()->insert('t_logbook_status', $data);
        }
    }

    private function updateLogbookServerIssuedChildren($db, $table, $rows, $allowed, $idLogbook, $idClient, $now)
    {
        if (!$this->updateLogbookServerIssuedRows($rows)) throw new CHttpException(422, 'Perubahan child tidak diizinkan.');
        $schema=$this->updateLogbookSchema($db, $table, false);
        if (!$schema || !isset($schema['id']) || !isset($schema['id_logbook'])) throw new CHttpException(422, 'Child tidak didukung oleh schema.');
        foreach ($rows as $row) {
            $lockedWhere='id=:id AND id_logbook=:id_logbook'; $lockedParams=array(':id'=>(int)$row['id'], ':id_logbook'=>$idLogbook);
            if (isset($schema['id_client'])) { $lockedWhere.=' AND id_client=:id_client'; $lockedParams[':id_client']=$idClient; }
            $owned=$db->createCommand('SELECT id FROM "'.$table.'" WHERE '.$lockedWhere.' FOR UPDATE')->bindValues($lockedParams)->queryRow();
            if (!$owned) throw new CHttpException(422, 'Child tidak valid.');
            $data=array(); foreach ($allowed as $field) if (array_key_exists($field,$row) && isset($schema[$field])) { if ($row[$field] !== null && !is_scalar($row[$field])) throw new CHttpException(422, 'Child tidak valid.'); $data[$field]=$row[$field]; }
            if (isset($schema['updated_date'])) $data['updated_date']=$now;
            if ($data) $this->updateLogbookBoundUpdate($db, $table, $data, $lockedWhere, $lockedParams);
        }
    }
    private function updateLogbookServerIssuedRows($rows) { if (!is_array($rows)) return false; foreach ($rows as $row) if (!is_array($row) || !$this->updateLogbookPositiveId(isset($row['id']) ? $row['id'] : null) || empty($row['server_issued'])) return false; return true; }
    private function updateLogbookBoundUpdate($db, $table, $data, $where, $whereParams)
{
    $sets = array();
    $bindings = array();
    $index = 0;

    foreach ($data as $column => $value) {
        $key = ':value_' . $index++;
        $sets[] = '"' . $column . '"=' . $key;
        $bindings[$key] = $value;
    }

    foreach ($whereParams as $key => $value) {
        $bindings[$key] = $value;
    }

    $command = $db->createCommand(
        'UPDATE "' . $table . '" SET ' . implode(',', $sets) . ' WHERE ' . $where
    );

    foreach ($bindings as $key => $value) {
        if (is_bool($value)) {
            $command->bindValue($key, $value, PDO::PARAM_BOOL);
        } elseif (is_int($value)) {
            $command->bindValue($key, $value, PDO::PARAM_INT);
        } elseif ($value === null) {
            $command->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $command->bindValue($key, (string)$value, PDO::PARAM_STR);
        }
    }

    $command->execute();
}
    private function updateLogbookSchema($db, $table, $required=true) { $schema=$db->schema->getTable($table); if (!$schema) { if ($required) throw new RuntimeException('Required schema is unavailable.'); return array(); } $out=array(); foreach ($schema->columns as $name=>$column) $out[$name]=true; return $out; }
    private function updateLogbookPositiveId($value) { return is_scalar($value) && preg_match('/^[1-9][0-9]*$/',(string)$value)===1; }
    private function updateLogbookNullableId($value) { if ($value === null || $value === '') return null; if (!$this->updateLogbookPositiveId($value)) throw new CHttpException(422, 'ID tidak valid.'); return (int)$value; }
    private function updateLogbookBool($value) { if (is_bool($value)) return $value; if ($value==='0' || $value===0) return false; if ($value==='1' || $value===1) return true; throw new CHttpException(422, 'Boolean tidak valid.'); }
    private function updateLogbookDate($value) { if (!is_string($value)) return false; foreach (array('!Y-m-d','!Y-m-d H:i:s') as $format) { $d=DateTime::createFromFormat($format,$value); if ($d && $d->format(ltrim($format,'!'))===$value) return true; } return false; }
    private function updateLogbookFlag($action, $field) { return isset($action[$field]) && in_array(strtolower((string)$action[$field]),array('1','true','t','yes','y'),true); }
    private function updateLogbookJson($success,$message,$status,$data=null) { if (!headers_sent()) http_response_code($status); $out=array('success'=>(bool)$success,'message'=>$message); if ($data!==null) $out['data']=$data; echo json_encode($out); Yii::app()->end(); }



    
    
    
    
    // delete logbokkkkkkkkkkkkkkkkkkkkkk
    public function actionArchiveLogbook()
    {
        header('Content-Type: application/json; charset=utf-8');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) return $this->deleteLogbookJson(false, 'Payload JSON tidak valid.', 400);
        foreach (array('id', 'created_by', 'id_client') as $field) {
            if (!$this->deleteLogbookPositiveId(isset($payload[$field]) ? $payload[$field] : null)) return $this->deleteLogbookJson(false, 'Permintaan tidak valid.', 422);
        }
        $db = Yii::app()->dbPrasi;
        $transaction = null;
        try {
            $transaction = $db->beginTransaction();
            $actor = $this->deleteLogbookActor($db, $payload);
            $parent = $this->deleteLogbookLockedParent($db, (int)$payload['id'], (int)$actor['id_client'], (int)$actor['id']);
            $now = date('Y-m-d H:i:s');
            $this->deleteLogbookSoftDeleteChildren($db, (int)$parent['id'], (int)$actor['id_client'], $now);
            $parentSchema = $this->deleteLogbookSchema($db, 't_logbook');
            if (!isset($parentSchema['deleted_at'])) throw new RuntimeException('Soft-delete schema is unavailable.');
            $data = array('deleted_at'=>$now);
            if (isset($parentSchema['updated_date'])) $data['updated_date']=$now;
            $this->deleteLogbookBoundUpdate($db, 't_logbook', $data,
                'id=:id AND id_client=:id_client AND id_user=:actor_id AND created_by=:actor_id AND deleted_at IS NULL',
                array(':id'=>(int)$parent['id'], ':id_client'=>(int)$actor['id_client'], ':actor_id'=>(int)$actor['id']));
            $transaction->commit();
            return $this->deleteLogbookJson(true, 'Logbook berhasil dihapus.', 200, array('id'=>(int)$parent['id']));
        } catch (CHttpException $e) {
            if ($transaction !== null && $transaction->active) $transaction->rollback();
            return $this->deleteLogbookJson(false, $e->getMessage(), $e->statusCode);
        } catch (Throwable $e) {
            if ($transaction !== null && $transaction->active) $transaction->rollback();
            Yii::log('DeleteLogbook failed: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
            return $this->deleteLogbookJson(false, 'Gagal menghapus logbook.', 500);
        }
    }

    private function deleteLogbookActor($db, $payload)
    {
        $columns=$this->deleteLogbookSchema($db, 'm_user');
        $where='u.id=:id AND u.id_client=:id_client AND LOWER(r.name) = :ppds_role';
        if (isset($columns['deleted_at'])) $where.=' AND u.deleted_at IS NULL';
        $actor=$db->createCommand('SELECT u.id,u.id_client FROM m_user u INNER JOIN m_role r ON r.id=u.id_role WHERE '.$where)
            ->bindValues(array(':id'=>(int)$payload['created_by'], ':id_client'=>(int)$payload['id_client'], ':ppds_role'=>'ppds'))->queryRow();
        if (!$actor) throw new CHttpException(403, 'Akses tidak diizinkan.');
        return $actor;
    }

    private function deleteLogbookLockedParent($db, $id, $idClient, $actorId)
    {
        $columns=$this->deleteLogbookSchema($db, 't_logbook');
        foreach (array('id','id_client','id_user','created_by','deleted_at') as $column) if (!isset($columns[$column])) throw new RuntimeException('Required logbook schema is unavailable.');
        $where="lb.id=:id AND lb.id_client = :id_client AND lb.id_user = :actor_id AND lb.created_by = :actor_id AND lb.deleted_at IS NULL";
        if (isset($columns['verified_status'])) $where.=" AND LOWER(COALESCE(lb.verified_status, 'pending')) IN ('pending','revised','rejected')";
        $statusColumns=$this->deleteLogbookSchema($db, 't_logbook_status', false);
        if ($statusColumns && isset($statusColumns['id_logbook']) && isset($statusColumns['status'])) {
            $where.=" AND NOT EXISTS (SELECT 1 FROM t_logbook_status s WHERE s.id_logbook=lb.id".(isset($statusColumns['id_client']) ? ' AND s.id_client=lb.id_client' : '').(isset($statusColumns['deleted_at']) ? ' AND s.deleted_at IS NULL' : '')." AND LOWER(COALESCE(s.status, 'pending')) NOT IN ('pending','revised','rejected'))";
        }
        $parent=$db->createCommand('SELECT lb.* FROM t_logbook lb WHERE '.$where.' FOR UPDATE')
            ->bindValues(array(':id'=>$id, ':id_client'=>$idClient, ':actor_id'=>$actorId))->queryRow();
        if (!$parent) throw new CHttpException(403, 'Logbook tidak dapat dihapus.');
        return $parent;
    }

    private function deleteLogbookSoftDeleteChildren($db, $idLogbook, $idClient, $now)
    {
        foreach (array('t_logbook_attachment','t_logbook_asm','t_logbook_emr','t_logbook_status','t_notif') as $table) {
            $columns=$this->deleteLogbookSchema($db, $table, false);
            // Do not guess a foreign key: skip tables that lack the established relation
            // or soft-delete column. All table names are fixed backend constants.
            if (!$columns || !isset($columns['id_logbook']) || !isset($columns['deleted_at'])) continue;
            $data=array('deleted_at'=>$now); if (isset($columns['updated_date'])) $data['updated_date']=$now;
            $where='id_logbook=:id_logbook'; $params=array(':id_logbook'=>$idLogbook);
            if (isset($columns['id_client'])) { $where.=' AND id_client=:id_client'; $params[':id_client']=$idClient; }
            $where.=' AND deleted_at IS NULL';
            $this->deleteLogbookBoundUpdate($db, $table, $data, $where, $params);
        }
    }

    private function deleteLogbookBoundUpdate($db, $table, $data, $where, $whereParams)
    {
        $sets=array(); $params=$whereParams; $i=0;
        foreach ($data as $column=>$value) { $key=':value_'.$i++; $sets[]='"'.$column.'"='.$key; $params[$key]=$value; }
        $db->createCommand('UPDATE "'.$table.'" SET '.implode(',',$sets).' WHERE '.$where)->execute($params);
    }
    private function deleteLogbookSchema($db, $table, $required=true) { $schema=$db->schema->getTable($table); if (!$schema) { if ($required) throw new RuntimeException('Required schema is unavailable.'); return array(); } $out=array(); foreach ($schema->columns as $name=>$column) $out[$name]=true; return $out; }
    private function deleteLogbookPositiveId($value) { return is_scalar($value) && preg_match('/^[1-9][0-9]*$/',(string)$value)===1; }
    private function deleteLogbookJson($success,$message,$status,$data=null) { if (!headers_sent()) http_response_code($status); $out=array('success'=>(bool)$success,'message'=>$message); if ($data!==null) $out['data']=$data; echo json_encode($out); Yii::app()->end(); }

    
    
    
    
    // ini adalah verifieddddddddddddddddddddddddddddddddddddd
    /* REPLACE the entire existing actionUpdateLogbookStatus() method; do not append. */
    public function actionUpdateLogbookStatus()
    {
        header('Content-Type: application/json; charset=utf-8');

        $post = json_decode(file_get_contents('php://input'), true);
        if (!is_array($post)) {
            return $this->logbookStatusJson(false, 'Payload JSON tidak valid');
        }

        foreach (array('id_logbook', 'id_user', 'id_action_role', 'id_client') as $field) {
            if (!isset($post[$field]) || !$this->logbookStatusIsPositiveNumericId($post[$field])) {
                return $this->logbookStatusJson(false, $field . ' wajib berupa angka positif');
            }
        }

        $status = isset($post['status']) && is_string($post['status']) ? strtolower(trim($post['status'])) : null;
        if (!in_array($status, array('verified', 'rejected', 'revised'), true)) {
            return $this->logbookStatusJson(false, 'Status tidak valid. Pilih: verified, rejected, revised');
        }

        foreach (array('verify_notes', 'reject_notes', 'notes') as $field) {
            if (isset($post[$field]) && !is_scalar($post[$field])) {
                return $this->logbookStatusJson(false, $field . ' harus berupa teks');
            }
        }

        $db = Yii::app()->dbPrasi;
        $transaction = null;
        try {
            /* Introspection is optional so older deployments without the dedicated note columns still work. */
            $statusColumns = $this->logbookStatusColumns($db, 't_logbook_status');
            $logbookColumns = $this->logbookStatusColumns($db, 't_logbook');
            // `notes` is part of the established status-table protocol; only
            // the newer dedicated note columns are optional.
            $statusColumns['notes'] = true;
            $transaction = $db->beginTransaction();

            $parentWhere = 'lb.id = :id_logbook AND ma.id_client = :id_client';
            if (isset($logbookColumns['deleted_at'])) {
                $parentWhere .= ' AND lb.deleted_at IS NULL';
            }
            $parent = $db->createCommand(
                'SELECT lb.id, lb.id_action, lb.id_user AS ppds_id, lb.date AS logbook_date, lb.id_category, lb.verified, lb.verified_status, ma.name AS action_name, ma.identifier AS action_identifier '
                . 'FROM t_logbook lb INNER JOIN m_action ma ON ma.id = lb.id_action '
                . 'WHERE ' . $parentWhere . ' FOR UPDATE'
            )->bindValues(array(
                ':id_logbook' => (string) $post['id_logbook'],
                ':id_client' => (string) $post['id_client'],
            ))->queryRow();

            if (!$parent) {
                throw new RuntimeException('Logbook tidak ditemukan untuk client ini');
            }

            /* The complete natural key is mandatory: never fall back to a first row or a supplied row ID. */
            $targetWhere = 'tls.id_logbook = :id_logbook AND tls.id_user = :id_user AND tls.id_action_role = :id_action_role';
            if (isset($statusColumns['id_client'])) {
                $targetWhere .= ' AND tls.id_client = :id_client';
            }
            $targetParams = array(
                ':id_logbook' => (string) $post['id_logbook'],
                ':id_user' => (string) $post['id_user'],
                ':id_action_role' => (string) $post['id_action_role'],
            );
            if (isset($statusColumns['id_client'])) {
                $targetParams[':id_client'] = (string) $post['id_client'];
            }
            $targetRows = $db->createCommand(
                'SELECT tls.id, tls.status, tls.notes, mar.role AS action_role_name, mar.identifier AS action_role_identifier '
                . 'FROM t_logbook_status tls '
                . 'LEFT JOIN m_action_role mar ON mar.id = tls.id_action_role '
                . 'WHERE ' . $targetWhere . ' FOR UPDATE OF tls'
            )->bindValues($targetParams)->queryAll();

            if (count($targetRows) !== 1) {
                throw new RuntimeException('Status verifier tidak ditemukan atau tidak unik');
            }
            $targetId = $targetRows[0]['id'];
            $statusChanged = strtolower((string)$targetRows[0]['status']) !== $status;
            $targetIds = array($targetId);

            /* Prasi-Bun behavior: one Staff who is both Pelapor and Penilai/GKM
             * completes both of those Morbiditas duties in one verification.
             * KPS and every other role remain independent. */
            $actionText = strtolower((string)$parent['action_identifier'] . ' ' . (string)$parent['action_name']);
            // Legacy deployments can omit the word "morbiditas" from the action label.
            // ID 39 is the tenant's canonical Morbiditas action.
            $isMorbiditasAction = preg_match('/morbiditas/', $actionText) || (int)$parent['id_action'] === 39;
            $targetRoleText = strtolower((string)$targetRows[0]['action_role_identifier'] . ' ' . (string)$targetRows[0]['action_role_name']);
            $isMorbiditasReporterOrAssessor = $isMorbiditasAction
                && preg_match('/pelapor|penilai|gkm|reporter|assessor/', $targetRoleText);

            /* A Pelapor/Penilai may only Verify or Reject after a category is selected.
             * Category IDs are validated against this exact action and tenant; a client
             * cannot satisfy this gate using a category from another activity. */
            if (in_array($status, array('verified', 'rejected'), true) && $isMorbiditasReporterOrAssessor) {
                $categoryId = array_key_exists('id_category', $post) ? $post['id_category'] : $parent['id_category'];
                if (!$this->logbookStatusIsPositiveNumericId($categoryId)) {
                    throw new RuntimeException('Kategori Morbiditas wajib dipilih sebelum verifikasi atau reject');
                }
                $category = $db->createCommand(
                    'SELECT id FROM m_action_category WHERE id = :id_category AND id_action = :id_action AND id_client = :id_client'
                )->bindValues(array(
                    ':id_category' => (string)$categoryId,
                    ':id_action' => (string)$parent['id_action'],
                    ':id_client' => (string)$post['id_client'],
                ))->queryRow();
                if (!$category) {
                    throw new RuntimeException('Kategori Morbiditas tidak valid untuk aktivitas ini');
                }
                if ((int)$parent['id_category'] !== (int)$categoryId) {
                    $db->createCommand(
                        'UPDATE t_logbook SET id_category = :id_category WHERE id = :id_logbook'
                    )->execute(array(
                        ':id_category' => (string)$categoryId,
                        ':id_logbook' => (string)$parent['id'],
                    ));
                    $parent['id_category'] = (int)$categoryId;
                }
            }
            if ($status === 'verified' && $isMorbiditasAction
                && preg_match('/pelapor|penilai|gkm/', $targetRoleText)) {
                $sameUserMorbiditasRows = $db->createCommand(
                    'SELECT tls.id FROM t_logbook_status tls '
                    . 'INNER JOIN m_action_role mar ON mar.id = tls.id_action_role '
                    . 'WHERE tls.id_logbook = :id_logbook AND tls.id_user = :id_user '
                    . "AND LOWER(COALESCE(mar.identifier, '') || ' ' || COALESCE(mar.role, '')) "
                    . "~ '(pelapor|penilai|gkm)' AND LOWER(COALESCE(tls.status, 'pending')) <> 'verified' FOR UPDATE"
                )->bindValues(array(
                    ':id_logbook' => (string)$post['id_logbook'],
                    ':id_user' => (string)$post['id_user'],
                ))->queryAll();
                foreach ($sameUserMorbiditasRows as $row) {
                    $targetIds[] = $row['id'];
                }
                $targetIds = array_values(array_unique($targetIds));
            }

            $hasSpecialSequence = $isMorbiditasAction || preg_match('/proposal[-_\\s]*thesis|proposal[-_\\s]*tesis|seminar[-_\\s]*hasil/', $actionText);
            /* Server-authoritative Morbiditas sequence: Pelapor -> Penilai/GKM -> KPS.
             * The targetIds batch is treated as one atomic approval, so a Staff who
             * holds adjacent Pelapor/Penilai slots approves both in one action. */
            if ($status === 'verified' && $isMorbiditasAction) {
                $slotFor = function ($text) {
                    if (preg_match('/pelapor|reporter/', $text)) return 0;
                    if (preg_match('/penilai|gkm|assessor/', $text)) return 1;
                    if (preg_match('/\bkps\b/', $text)) return 2;
                    return 99;
                };
                $targetSlot = $slotFor($targetRoleText);
                if ($targetSlot === 99) throw new RuntimeException('Role Morbiditas tidak dikenali');
                $morbiditasRows = $db->createCommand(
                    'SELECT tls.id, tls.status, mar.role, mar.identifier FROM t_logbook_status tls '
                    . 'LEFT JOIN m_action_role mar ON mar.id=tls.id_action_role '
                    . 'WHERE tls.id_logbook=:id_logbook FOR UPDATE OF tls'
                )->bindValue(':id_logbook', (string)$post['id_logbook'])->queryAll();
                for ($previousSlot = 0; $previousSlot < $targetSlot; $previousSlot++) {
                    $predecessors = array();
                    foreach ($morbiditasRows as $morbiditasRow) {
                        $rowSlot = $slotFor(strtolower((string)$morbiditasRow['identifier'].' '.(string)$morbiditasRow['role']));
                        if ($rowSlot === $previousSlot) $predecessors[] = $morbiditasRow;
                    }
                    $ready = $predecessors && !array_filter($predecessors, function ($row) use ($targetIds) {
                        return strtolower((string)$row['status']) !== 'verified' && !in_array($row['id'], $targetIds, true);
                    });
                    if (!$ready) {
                        $label = $previousSlot === 0 ? 'Pelapor' : ($previousSlot === 1 ? 'Penilai/GKM' : 'KPS');
                        throw new RuntimeException('Menunggu verifikasi '.$label.' terlebih dahulu');
                    }
                }
            }

            /* Staff Jejaring ordering applies only while creating a verification
             * or a reject.  Unverif must always be possible for that Staff,
             * including after a rejected row, so it cannot be blocked by another
             * verifier's current state. */
            if (!$hasSpecialSequence && $status !== 'revised') {
                $sequenceRows = $db->createCommand(
                    'SELECT id_user, status, notes FROM t_logbook_status WHERE id_logbook = :id_logbook FOR UPDATE'
                )->bindValue(':id_logbook', (string)$post['id_logbook'])->queryAll();
                $targetIsJejaring = false;
                $hasPendingJejaring = false;
                foreach ($sequenceRows as $sequenceRow) {
                    $isJejaring = strpos(strtolower((string)$sequenceRow['notes']), '__staff_jejaring__') !== false;
                    if ($isJejaring && (int)$sequenceRow['id_user'] === (int)$post['id_user']) {
                        $targetIsJejaring = true;
                    }
                    if ($isJejaring && strtolower((string)$sequenceRow['status']) !== 'verified') {
                        $hasPendingJejaring = true;
                    }
                }
                if (!$targetIsJejaring && $hasPendingJejaring) {
                    throw new RuntimeException('Menunggu verifikasi Staff Pengajar Jejaring terlebih dahulu');
                }
            }

            if ($status === 'verified') {
                $this->logbookStatusReplaceScores($db, $post, $parent);
            }

            $assignments = array('status = :status');
            $params = array(
                ':status' => $status,
                ':id_logbook' => (string) $post['id_logbook'],
                ':id_user' => (string) $post['id_user'],
            );
            $targetPlaceholders = array();
            foreach ($targetIds as $index => $id) {
                $placeholder = ':status_id_' . $index;
                $targetPlaceholders[] = $placeholder;
                $params[$placeholder] = (string)$id;
            }
            if (isset($statusColumns['date_time'])) {
                $assignments[] = 'date_time = CURRENT_TIMESTAMP';
            }
            if ($status === 'verified' && isset($statusColumns['verify_notes']) && array_key_exists('verify_notes', $post)) {
                $assignments[] = 'verify_notes = :verify_notes';
                $params[':verify_notes'] = $post['verify_notes'];
            }
            if ($status === 'rejected') {
                $rejectNote = array_key_exists('reject_notes', $post) ? trim((string)$post['reject_notes']) : '';
                if ($rejectNote !== '') {
                    /* On this deployed schema reject_notes is SMALLINT, not a text note.
                     * Store the human rejection reason only in a text-compatible column. */
                    if ($this->logbookStatusTextColumn($db, 'notes')) {
                        /* `notes` is also the Staff Jejaring slot key. Retain
                         * its marker together with the readable reject reason so
                         * a later PPDS Edit updates this row rather than inserting
                         * a duplicate verifier. */
                        $isJejaringTarget = strpos((string)$targetRows[0]['notes'], '__staff_jejaring__') === 0;
                        $assignments[] = 'notes = :notes';
                        $params[':notes'] = $isJejaringTarget
                            ? '__staff_jejaring__' . "\n" . $rejectNote
                            : $rejectNote;
                    } elseif ($this->logbookStatusTextColumn($db, 'reject_notes')) {
                        $assignments[] = 'reject_notes = :reject_notes';
                        $params[':reject_notes'] = $rejectNote;
                    } else {
                        throw new RuntimeException('Schema status tidak memiliki kolom teks untuk catatan reject');
                    }
                }
            }

            $updated = $db->createCommand(
                'UPDATE t_logbook_status SET ' . implode(', ', $assignments)
                . ' WHERE id IN (' . implode(', ', $targetPlaceholders) . ')'
                . ' AND id_logbook = :id_logbook AND id_user = :id_user'
            )->execute($params);
            if ($updated !== count($targetIds)) {
                throw new RuntimeException('Status verifier gagal diupdate');
            }

            $allWhere = 'id_logbook = :id_logbook';
            $allRows = $db->createCommand(
                'SELECT status FROM t_logbook_status WHERE ' . $allWhere . ' FOR UPDATE'
            )->bindValue(':id_logbook', (string) $post['id_logbook'])->queryAll();
            if (!$allRows) {
                throw new RuntimeException('Tidak ada status verifier untuk logbook');
            }

            $allVerified = true;
            $allRejected = true;
            foreach ($allRows as $row) {
                $allVerified = $allVerified && $row['status'] === 'verified';
                $allRejected = $allRejected && $row['status'] === 'rejected';
            }
            if ($allVerified) {
                $parentVerified = true;
                $parentStatus = 'verified';
            } elseif ($allRejected) {
                $parentVerified = false;
                $parentStatus = 'rejected';
            } else {
                $parentVerified = false;
                $parentStatus = $status === 'revised' ? 'revised' : 'pending';
            }

            $db->createCommand(
                'UPDATE t_logbook SET verified = :verified, verified_status = :verified_status WHERE id = :id_logbook'
            )->execute(array(
                ':verified' => $parentVerified ? 'true' : 'false',
                ':verified_status' => $parentStatus,
                ':id_logbook' => (string) $post['id_logbook'],
            ));

            if ($statusChanged && in_array($status, array('verified', 'rejected', 'revised'), true)) {
                $this->logbookStatusNotifyPpds($db, $parent, $post, $status);
            }

            $returnColumns = array('id', 'id_user', 'id_action_role', 'status');
            foreach (array('notes', 'verify_notes', 'reject_notes') as $column) {
                if (isset($statusColumns[$column])) {
                    $returnColumns[] = $column;
                }
            }
            $returnedStatuses = $db->createCommand(
                'SELECT ' . implode(', ', $returnColumns) . ' FROM t_logbook_status WHERE ' . $allWhere . ' ORDER BY id'
            )->bindValue(':id_logbook', (string) $post['id_logbook'])->queryAll();

            $transaction->commit();
            return $this->logbookStatusJson(true, 'Status logbook berhasil diupdate', array(
                'parent' => array(
                    'id' => $parent['id'],
                    'verified' => $parentVerified,
                    'verified_status' => $parentStatus,
                ),
                't_logbook_status' => $returnedStatuses,
            ));
        } catch (Throwable $e) {
            if ($transaction !== null && $transaction->active) {
                $transaction->rollback();
            }
            Yii::log('Update logbook status failed: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook');
            /* TEMPORARY diagnostic for the development mobile endpoint.
             * Remove `detail` once the current live error has been fixed. */
            return $this->logbookStatusJson(false, 'Gagal memperbarui status logbook', array(
                'detail' => $e->getMessage(),
                'error_type' => get_class($e),
            ));
        }
    }


    private function logbookStatusReplaceScores($db, $post, $parent)
{
    $fields = array('score_psikomotor'=>4, 'score_knowledge'=>5, 'score_affective'=>6);
    $provided = array();
    foreach ($fields as $field => $paramId) {
        if (array_key_exists($field, $post) && $post[$field] !== null && $post[$field] !== '') {
            if (!is_scalar($post[$field]) || !is_numeric($post[$field])) {
                throw new RuntimeException('Score ASM harus numerik');
            }
            $provided[$paramId] = (float) $post[$field];
        }
    }
    if (!$provided) return;
    if (count($provided) !== count($fields)) {
        throw new RuntimeException('Lengkapi semua skor ASM sebelum verifikasi');
    }

    foreach ($provided as $paramId => $score) {
        $param = $db->createCommand(
            'SELECT ap.id, ap.min_score, ap.max_score FROM m_asm_param ap '
            . 'INNER JOIN m_asm_action aa ON aa.id_asm_param = ap.id '
            . 'WHERE ap.id = :id AND aa.id_action = :id_action'
        )->bindValues(array(':id'=>$paramId, ':id_action'=>(string)$parent['id_action']))->queryRow();
        if (!$param
            || ($param['min_score'] !== null && $score < (float)$param['min_score'])
            || ($param['max_score'] !== null && $score > (float)$param['max_score'])) {
            throw new RuntimeException('Score ASM tidak valid untuk action ini');
        }
    }

    // Scope replacement to the authenticated verifier. Other verifier scores survive.
    $db->createCommand(
        'DELETE FROM t_logbook_asm WHERE id_logbook = :id_logbook '
        . 'AND id_client = :id_client AND created_date = :verifier_id '
        . 'AND id_asm_param IN (4, 5, 6)'
    )->execute(array(
        ':id_logbook'=>(string)$parent['id'],
        ':id_client'=>(string)$post['id_client'],
        ':verifier_id'=>(string)$post['id_user'],
    ));

    foreach ($provided as $paramId => $score) {
        $db->createCommand(
            'INSERT INTO t_logbook_asm '
            . '(id_logbook, id_client, id_asm_param, score, created_by, created_date) '
            . 'VALUES (:id_logbook, :id_client, :id_asm_param, :score, CURRENT_TIMESTAMP, :verifier_id)'
        )->execute(array(
            ':id_logbook'=>(string)$parent['id'],
            ':id_client'=>(string)$post['id_client'],
            ':id_asm_param'=>$paramId,
            ':score'=>$score,
            ':verifier_id'=>(string)$post['id_user'],
        ));
    }
}

    private function logbookStatusNotifyPpds($db, $parent, $post, $status)
    {
        $ppds = $db->createCommand('SELECT id, id_role FROM m_user WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id'=>(string)$parent['ppds_id'], ':client'=>(string)$post['id_client']))->queryRow();
        $actor = $db->createCommand('SELECT display_name FROM m_user WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id'=>(string)$post['id_user'], ':client'=>(string)$post['id_client']))->queryRow();
        if (!$ppds) return;
        $actorName = trim((string)(isset($actor['display_name']) ? $actor['display_name'] : 'Staff'));
        $labels = array('verified'=>'telah diverifikasi', 'rejected'=>'ditolak', 'revised'=>'dibatalkan verifikasinya');
        $message = 'Data ' . $parent['action_name'] . ' ' . $parent['logbook_date'] . ' ' . $labels[$status] . ' oleh ' . $actorName;
        $db->createCommand()->insert('t_notif', array(
            'message'=>$message, 'date'=>new CDbExpression('CURRENT_TIMESTAMP'), 'type'=>$status,
            'id_user'=>(int)$ppds['id'], 'id_role'=>$ppds['id_role'], 'read'=>new CDbExpression('FALSE'),
            'id_client'=>(int)$post['id_client'], 'id_logbook'=>(int)$parent['id'],
            'url'=>'/ppds/action/' . $parent['id_action'] . '/' . $parent['id'],
        ));
    }

    private function logbookStatusTextColumn($db, $columnName)
    {
        $schema = $db->schema->getTable('t_logbook_status');
        if (!$schema || !isset($schema->columns[$columnName])) return false;
        $type = strtolower((string)$schema->columns[$columnName]->type);
        return in_array($type, array('string', 'text'), true);
    }

    private function logbookStatusIsPositiveNumericId($value)
    {
        return is_scalar($value) && preg_match('/^[1-9][0-9]*$/', (string) $value) === 1;
    }

    private function logbookStatusColumns($db, $table)
    {
        try {
            $rows = $db->createCommand(
                'SELECT column_name FROM information_schema.columns '
                . 'WHERE table_name = :table_name AND table_schema = ANY(current_schemas(false))'
            )->bindValue(':table_name', $table)->queryAll();
            $columns = array();
            foreach ($rows as $row) {
                $columns[$row['column_name']] = true;
            }
            return $columns;
        } catch (Throwable $e) {
            Yii::log('Cannot inspect columns for ' . $table . ': ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'api.logbook');
            return array();
        }
    }

    private function logbookStatusJson($success, $message, $data = null)
    {
        $response = array('success' => $success, 'message' => $message);
        if ($data !== null) {
            $response['data'] = $data;
        }
        echo json_encode($response);
        Yii::app()->end();
    }


    
    
    // untuk create Morbiditasssssssssssssssssssssssssssssssssss
    
    private function ensureMorbiditasPointsHistoryTable($db)
{
    $db->createCommand('CREATE TABLE IF NOT EXISTS t_morbiditas_points_history (
        id SERIAL PRIMARY KEY,
        id_user INT NOT NULL,
        id_client INT NOT NULL,
        id_semester INT NOT NULL,
        points INT NOT NULL DEFAULT 0,
        started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        ended_at TIMESTAMPTZ NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )')->execute();
    $db->createCommand('CREATE INDEX IF NOT EXISTS idx_morbiditas_points_history_open
        ON t_morbiditas_points_history (id_user, id_client, ended_at)')->execute();
}

private function computeActiveMorbiditasPoints($db, $idUser, $idClient, $idSemester, $startedAt = null)
{
    $whereStarted = $startedAt === null ? '' : ' AND lb.created_date >= :started_at';
    $row = $db->createCommand(
        'SELECT COALESCE(SUM(mac.points), 0)::int AS total
         FROM t_logbook lb
         INNER JOIN m_action ma ON ma.id = lb.id_action
         INNER JOIN m_action_category mac ON mac.id = lb.id_category
         WHERE lb.id_user = :id_user
           AND lb.id_client = :id_client
           AND lb.id_semester = :id_semester
           AND lb.deleted_at IS NULL
           AND mac.points IS NOT NULL
           AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, \'\')) = \'verified\')
           AND (LOWER(COALESCE(ma.identifier, \'\')) = \'morbiditas\'
             OR LOWER(COALESCE(ma.name, \'\')) = \'morbiditas\'
             OR ma.id = 39)' . $whereStarted
    )->bindValues(array_filter(array(
        ':id_user' => (int) $idUser,
        ':id_client' => (int) $idClient,
        ':id_semester' => (int) $idSemester,
        ':started_at' => $startedAt,
    ), function ($value) { return $value !== null; }))->queryRow();
    return (int) ($row['total'] ?? 0);
}

private function ensureMorbiditasPointsSession($db, $idUser, $idClient, $idSemester)
{
    $this->ensureMorbiditasPointsHistoryTable($db);
    $open = $db->createCommand(
        'SELECT id, id_semester, started_at
         FROM t_morbiditas_points_history
         WHERE id_user = :id_user AND id_client = :id_client AND ended_at IS NULL
         ORDER BY id DESC LIMIT 1 FOR UPDATE'
    )->bindValues(array(':id_user' => (int) $idUser, ':id_client' => (int) $idClient))->queryRow();

    if ($open && (int) $open['id_semester'] === (int) $idSemester) return;

    if ($open) {
        $closedPoints = $this->computeActiveMorbiditasPoints(
            $db, $idUser, $idClient, (int) $open['id_semester'], $open['started_at']
        );
        $db->createCommand(
            'UPDATE t_morbiditas_points_history
             SET points = :points, ended_at = CURRENT_TIMESTAMP
             WHERE id = :id AND ended_at IS NULL'
        )->execute(array(':points' => $closedPoints, ':id' => $open['id']));
    }

    $db->createCommand(
        'INSERT INTO t_morbiditas_points_history
         (id_user, id_client, id_semester, points, started_at, ended_at)
         VALUES (:id_user, :id_client, :id_semester, 0, CURRENT_TIMESTAMP, NULL)'
    )->execute(array(
        ':id_user' => (int) $idUser,
        ':id_client' => (int) $idClient,
        ':id_semester' => (int) $idSemester,
    ));
}

    
    
    // menampilkan dynamic logbook Field
    
  public function actionGetLogbookFormMetadata()
{
    header('Content-Type: application/json');

    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post)) {
        $this->logbookMetadataResponse(false, 'Payload JSON tidak valid.', null, 400);
        return;
    }

    foreach (array('id_client', 'id_action') as $field) {
        if (!isset($post[$field]) || !is_numeric($post[$field]) || (int)$post[$field] <= 0) {
            $this->logbookMetadataResponse(false, $field . ' wajib berupa angka positif.', null, 422);
            return;
        }
    }

    $idClient = (int)$post['id_client'];
    $idAction = (int)$post['id_action'];
    $db = Yii::app()->dbPrasi;

    try {
        // m_action pada backend acuan dimiliki client melalui m_action_type.
        // ma.* sengaja dikembalikan agar seluruh flag has_* tersedia tanpa
        // membuat endpoint baru setiap ada field action baru.
        $action = $db->createCommand('
            SELECT ma.*, mat.name AS action_type_name
            FROM m_action ma
            INNER JOIN m_action_type mat ON mat.id = ma.id_type
            WHERE ma.id = :id_action
              AND ma.id_client = :id_client
        ')->bindValues(array(
            ':id_action' => $idAction,
            ':id_client' => $idClient,
        ))->queryRow();

        if (!$action) {
            $this->logbookMetadataResponse(false, 'Action tidak ditemukan untuk client ini.', null, 404);
            return;
        }

        // Role/verifier yang terikat langsung ke action.
        $rolemaps = $db->createCommand('
            SELECT
                arm.*,
                ar.id AS action_role_id,
                ar.role,
                ar.identifier,
                ar.id_client AS action_role_id_client
            FROM m_action_rolemap arm
            INNER JOIN m_action_role ar ON ar.id = arm.id_action_role
            WHERE arm.id_action = :id_action
              AND ar.id_client = :id_client
            ORDER BY arm.id ASC
        ')->bindValues(array(
            ':id_action' => $idAction,
            ':id_client' => $idClient,
        ))->queryAll();

        // Some older tenant schemas predate these soft-delete/status fields.
        // Feature-detect them so metadata remains available during rollout.
        $userSchema = $db->schema->getTable('m_user');
        $staseSchema = $db->schema->getTable('m_stase');
        // FormLogbook Prasi tidak pernah mengisi Staff Pengajar dari seluruh
        // m_user. Untuk aktivitas biasa pilih role persis "staff"; Morbiditas
        // boleh menambahkan "staff jejaring". Ini mencegah admin/institusi ikut
        // tampil di dropdown Staff Pengajar.
        $actionText = strtolower(trim(
            (isset($action['identifier']) ? $action['identifier'] : '') . ' ' .
            (isset($action['name']) ? $action['name'] : '') . ' ' .
            (isset($action['action_name']) ? $action['action_name'] : '')
        ));
        $isMorbiditas = strpos($actionText, 'morbid') !== false;
        $staffWhere = array('u.id_client = :id_client');
        if ($userSchema && isset($userSchema->columns['status'])) $staffWhere[] = "LOWER(u.status) = 'active'";
        if ($userSchema && isset($userSchema->columns['deleted_at'])) $staffWhere[] = 'u.deleted_at IS NULL';
        // PostgreSQL memakai tipe boolean, bukan integer 1/0.
        if ($userSchema && isset($userSchema->columns['is_show'])) $staffWhere[] = '(u.is_show IS NULL OR u.is_show IS TRUE)';
        if ($userSchema && isset($userSchema->columns['is_deleted'])) $staffWhere[] = '(u.is_deleted IS NULL OR u.is_deleted IS FALSE)';
        $staffRoleClause = $isMorbiditas
            ? "(LOWER(TRIM(r.name)) = 'staff' OR LOWER(TRIM(r.name)) = 'staff jejaring')"
            : "LOWER(TRIM(r.name)) = 'staff'";
        $staffUsers = $db->createCommand('
            SELECT u.id, u.display_name, u.username, u.id_role
            FROM m_user u
            INNER JOIN m_role r ON r.id = u.id_role
            WHERE ' . implode(' AND ', $staffWhere) . '
              AND ' . $staffRoleClause . '
            ORDER BY u.display_name ASC, u.username ASC
        ')->bindValue(':id_client', $idClient)->queryAll();

        $jejaringWhere = array('u.id_client = :id_client', "LOWER(TRIM(r.name)) = 'staff jejaring'");
        if ($userSchema && isset($userSchema->columns['status'])) $jejaringWhere[] = "LOWER(u.status) = 'active'";
        if ($userSchema && isset($userSchema->columns['deleted_at'])) $jejaringWhere[] = 'u.deleted_at IS NULL';
        if ($userSchema && isset($userSchema->columns['is_show'])) $jejaringWhere[] = '(u.is_show IS NULL OR u.is_show IS TRUE)';
        if ($userSchema && isset($userSchema->columns['is_deleted'])) $jejaringWhere[] = '(u.is_deleted IS NULL OR u.is_deleted IS FALSE)';
        $staffJejaringUsers = $db->createCommand('
            SELECT u.id, u.display_name, u.username
            FROM m_user u
            INNER JOIN m_role r ON r.id = u.id_role
            WHERE ' . implode(' AND ', $jejaringWhere) . '
            ORDER BY u.display_name ASC, u.username ASC
        ')->bindValue(':id_client', $idClient)->queryAll();

        foreach ($rolemaps as &$rolemap) {
            $rolemap['id_action_role'] = (int)$rolemap['action_role_id'];
            $rolemap['m_action_role'] = array(
                'id' => (int)$rolemap['action_role_id'],
                'role' => $rolemap['role'],
                'identifier' => $rolemap['identifier'],
            );
            // ID staff dikirim agar mobile tidak pernah mengirim nama display sebagai ID.
            $rolemap['users'] = $staffUsers;
            unset(
                $rolemap['action_role_id'],
                $rolemap['role'],
                $rolemap['identifier'],
                $rolemap['action_role_id_client']
            );
        }
        unset($rolemap);

        $categories = $db->createCommand('
            SELECT id, name, required_asm
            FROM m_action_category
            WHERE id_action = :id_action
              AND id_client = :id_client
            ORDER BY name ASC
        ')->bindValues(array(
            ':id_action' => $idAction,
            ':id_client' => $idClient,
        ))->queryAll();

        $anotherRoles = $db->createCommand('
            SELECT
                maar.id_action,
                maar.id_another_role,
                maar.required_asm,
                mar.id,
                mar.role_name AS name
            FROM m_action_another_role maar
            INNER JOIN m_another_role mar ON mar.id = maar.id_another_role
            WHERE maar.id_action = :id_action
              AND mar.id_client = :id_client
            ORDER BY mar.role_name ASC
        ')->bindValues(array(
            ':id_action' => $idAction,
            ':id_client' => $idClient,
        ))->queryAll();

        $asmActions = $db->createCommand('
            SELECT
                aa.id_action,
                aa.id_asm_param,
                ap.id,
                ap.name,
                ap.min_score,
                ap.max_score
            FROM m_asm_action aa
            INNER JOIN m_asm_param ap ON ap.id = aa.id_asm_param
            WHERE aa.id_action = :id_action
            ORDER BY aa.id ASC
        ')->bindValue(':id_action', $idAction)->queryAll();

        foreach ($asmActions as &$asmAction) {
            $asmAction['m_asm_param'] = array(
                'id' => (int)$asmAction['id'],
                'name' => $asmAction['name'],
                'min_score' => $asmAction['min_score'],
                'max_score' => $asmAction['max_score'],
            );
            unset($asmAction['id'], $asmAction['name'], $asmAction['min_score'], $asmAction['max_score']);
        }
        unset($asmAction);

        // This table does not consistently have a deleted_at column across
        // deployments. Rows scoped to this action/client are the available
        // score choices; do not add a soft-delete predicate here.
        $scoreOptions = $db->createCommand('
            SELECT id, score
            FROM m_score_option
            WHERE id_action = :id_action
              AND id_client = :id_client
              AND score IS NOT NULL
            ORDER BY score ASC, id ASC
        ')->bindValues(array(
            ':id_action' => $idAction,
            ':id_client' => $idClient,
        ))->queryAll();

        foreach ($scoreOptions as &$scoreOption) {
            $scoreOption['id'] = (int)$scoreOption['id'];
            $scoreOption['score'] = (float)$scoreOption['score'];
        }
        unset($scoreOption);

        $hospitals = $db->createCommand('
            SELECT id, name
            FROM m_hospital
            WHERE id_client = :id_client
            ORDER BY name ASC
        ')->bindValue(':id_client', $idClient)->queryAll();

        $staseJejaringColumn = $staseSchema && isset($staseSchema->columns['has_staff_jejaring'])
            ? 'has_staff_jejaring'
            : '0 AS has_staff_jejaring';
        $stases = $db->createCommand('
            SELECT id, name, id_stage, sequence, ' . $staseJejaringColumn . '
            FROM m_stase
            WHERE id_client = :id_client
            ORDER BY sequence ASC, name ASC
        ')->bindValue(':id_client', $idClient)->queryAll();

        $action['id'] = (int)$action['id'];
        $action['m_action_rolemap'] = $rolemaps;
        $action['m_action_category'] = $categories;
        $action['m_action_another_role'] = $anotherRoles;
        $action['m_asm_action'] = $asmActions;
        $action['score_options'] = $scoreOptions;
        $action['hospitals'] = $hospitals;
        $action['stases'] = $stases;
        $action['staff_jejaring_users'] = $staffJejaringUsers;

        $this->logbookMetadataResponse(true, 'OK', $action, 200);
    } catch (Throwable $e) {
        Yii::log($e->getMessage(), CLogger::LEVEL_ERROR, 'api.logbook.metadata');
        $message = defined('YII_DEBUG') && YII_DEBUG
            ? 'Metadata error: ' . $e->getMessage()
            : 'Gagal memuat metadata form logbook.';
        $this->logbookMetadataResponse(false, $message, null, 500);
    }
}

private function logbookMetadataResponse($success, $message, $data = null, $statusCode = 200)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
    }

    echo json_encode(array(
        'success' => (bool)$success,
        'message' => $message,
        'data' => $data,
    ));
    Yii::app()->end();
}
    
    
}