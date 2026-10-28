<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

class ApiWebServiceController extends Controller {
    public $enableCsrf = false;
    
    // public function filters() {
    //     // Use access control filter
    //     return ['accessControl'];
    // }
    
    // public function accessRules() {
    //     // Only allow authenticated users
    //     return [['allow', 'users' => ['@']], ['deny']];
    // }
    
    public function actiongetHaped() {
        echo("HALO MAS HAPED");die;
    }

    // === AUTH STAGE ===
    public function actionLogin() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['username']) || !isset($post['password'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Username dan password wajib diisi!'
            ]);
            Yii::app()->end();
        }
        
        $username = $post['username'];
        $password = $post['password'];
        
        $user = MUser::model()->findByAttributes(
                    ['username' => $username],
                    ['select' => 'id, username, password']
            );
        
        if (!$user) {
            echo json_encode([
                'status' => false,
                'message' => 'Username tidak ditemukan!'
            ]);
            Yii::app()->end();
        }
        
        if (!password_verify($password, $user->password)) {
            echo json_encode([
                'status' => false,
                'message' => 'Password salah!'
            ]);   
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    mu.*,
                    mr.name AS "role_name",
                    mc.name AS "client_name"
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                LEFT JOIN m_client mc ON mc.id = mu.id_client
                WHERE
                    mu.id = :id_user';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_user', $user->id)
            ->queryRow();

        unset($res['password']);

        echo json_encode([
            'status'  => true,
            'message' => "Login berhasil!",
            'data'    => $res
        ]);
    }
    
    public function actionUpdateProfile() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_user']) ||
            !isset($post['display_name'])
        ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $user = MUser::model()->findByPk($post["id_user"]);
        
        if (!$user) {
            echo json_encode([
                'status'  => false,
                'message' => 'User tidak ditemukan!'
            ]);
            Yii::app()->end();
        }
        
        $user->display_name  = $post['display_name'];
        $user->email         = $post['email'] ?? null;
        $user->phone         = $post['phone'] ?? null;
        $user->address       = $post['address'] ?? null;
        $user->date_of_birth = $post['date_of_birth'] ?? null;
        $user->code          = $post['code'] ?? null;
        
        if (!$user->save()) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data gagal diupdate!'
            ]);
            Yii::app()->end();
        }
    
        echo json_encode([
            'status'  => true,
            'message' => 'Data berhasil diupdate!',
            'data'    => [
                'id_user' => $user->id,
            ]
        ]);
    }
    
    public function actionChangePassword() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['updated_by']) ||
            !isset($post['id_user']) ||
            !isset($post['password']) ||
            !isset($post['confirm_password'])
        ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $password         = $post['password'];
        $confirm_password = $post['confirm_password'];

        if ($password !== $confirm_password) {
            echo json_encode([
                'status' => false,
                'message' => 'Password not match!'
            ]);
            Yii::app()->end();
        }
        
        $user = MUser::model()->findByPk($post["id_user"]);
        
        if (!$user) {
            echo json_encode([
                'status'  => false,
                'message' => 'User tidak ditemukan!'
            ]);
            Yii::app()->end();
        }
        
        try {
            $user->password       = password_hash($post['password'], PASSWORD_BCRYPT);
            $user->updated_date   = date('Y-m-d H:i:s');
            $user->updated_by     = $post['updated_by'];
            $user->save(false);
        
            echo json_encode([
                'status'  => true,
                'message' => 'Data berhasil diupdate!',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }
    // === AUTH STAGE===
    
    

    // === MASTER STAGE ===
    // option : ppds (Active) | staff (?)
    public function actionGetMasterUser() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_client']) ||
            !isset($post['role_name'])
            ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    mu.id,
                    mu.display_name AS "name"
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                WHERE
                    mu.status     = :status 
                AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL 
                AND mu.id_client  = :id_client
                AND mr.name       = :role_name
                ORDER BY 
                    mu.display_name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':status', 'Active')
            ->bindValue(':is_show', true)
            ->bindValue(':id_client', $post['id_client'])
            ->bindValue(':role_name', $post['role_name'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }

    public function actionGetMasterPPDS() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_client']) ||
            !isset($post['role_name'])
            ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    mu.id,
                    mu.display_name AS "name"
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                WHERE
                    mu.deleted_at IS NULL 
                AND mu.id_client  = :id_client
                AND mr.name       = :role_name
                ORDER BY 
                    mu.display_name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->bindValue(':role_name', $post['role_name'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }

    public function actionGetMasterPPDSActive() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_client']) ||
            !isset($post['role_name'])
            ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    mu.id,
                    mu.display_name AS "name"
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                WHERE
                    mu.status     = :status 
                AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL 
                AND mu.id_client  = :id_client
                AND mr.name       = :role_name
                ORDER BY 
                    mu.display_name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':status', 'Active')
            ->bindValue(':is_show', true)
            ->bindValue(':id_client', $post['id_client'])
            ->bindValue(':role_name', $post['role_name'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }

    public function actionGetMasterPPDSInactive() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_client']) ||
            !isset($post['role_name'])
            ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    mu.id,
                    mu.display_name AS "name"
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                WHERE
                    mu.status IN (:status1, :status2)
                AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL 
                AND mu.id_client  = :id_client
                AND mr.name       = :role_name
                ORDER BY 
                    mu.display_name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':status1', 'Inactive')
            ->bindValue(':status2', 'Lulus')
            ->bindValue(':is_show', true)
            ->bindValue(':id_client', $post['id_client'])
            ->bindValue(':role_name', $post['role_name'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }
    
    // option : stase
    public function actionGetMasterStase() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    ms.id,
                    ms.name,
                    ms.id_stage
                FROM m_stase ms
                WHERE
                    ms.id_client = :id_client
                ORDER BY
                    ms.sequence ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }
    
    public function actionGetMasterRoleStaff() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = "SELECT
                    mr.id,
                    mr.name
                FROM m_role mr
                WHERE
                    mr.id_client = :id_client
                    AND mr.name ILIKE '%staff%'
                ORDER BY
                    mr.id ASC";
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }
    
    public function actionGetMasterStaff() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $sql = 'SELECT DISTINCT ON (mu.display_name)
                    mu.id,
                    mu.display_name AS "name"
                FROM t_logbook_status tls
                INNER JOIN m_user mu ON mu.id = tls.id_user
                INNER JOIN m_action_role mar ON mar.id = tls.id_action_role
                WHERE
                    tls.deleted_at IS NULL
                AND mu.deleted_at  IS NULL
                AND mu.is_show    = true
                AND mar.id_client = :id_client
                AND mu.id_client = :id_client
                AND mar.role     != :role_name
                ORDER BY
                    mu.display_name ASC';
    
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->bindValue(':role_name', 'Peserta')
            ->queryAll();
    
        echo json_encode([
            'status' => true,
            'total'  => count($res),
            'data'   => $res
        ]);
    }
    
    public function actionGetMasterActivity() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    ma.id,
                    ma.name
                FROM m_action ma
                WHERE
                    ma.id_client = :id_client
                ORDER BY 
                    name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }

    public function actionGetMasterStage() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = 'SELECT
                    ms.id,
                    ms.name
                FROM m_stage ms
                WHERE
                    ms.id_client = :id_client
                ORDER BY
                    ms.name ASC';

        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->queryAll();

        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }

    public function actionGetStageByStase() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_stase'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = 'SELECT
                    mst.id_stage,
                    ms.name
                FROM m_stase mst
                LEFT JOIN m_stage ms ON ms.id = mst.id_stage
                WHERE mst.id = :id_stase';

        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_stase', $post['id_stase'])
            ->queryAll();

        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }

    public function actionGetMasterSemester() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_stage'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = 'SELECT
                    ms.id,
                    ms.name
                FROM m_semester ms
                WHERE
                    ms.id_stage = :id_stage
                ORDER BY
                    ms.name ASC';

        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_stage', $post['id_stage'])
            ->queryAll();

        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);
    }
    
    public function actionGetMasterHospital() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $sql = 'SELECT
                    mh.id,
                    mh.name
                FROM m_hospital mh
                WHERE
                    mh.id_client = :id_client
                ORDER BY
                    mh.name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
        ]);        
    }
    // === MASTER STAGE ===
    
    
    
    // === PPDS STAGE ===
    public function actionGetListPPDS() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        // pagination default
        $page  = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        // sorting (default ASC)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'desc') ? 'DESC' : 'ASC';
    
        $sql = 'SELECT
                    mu.id,
                    mu.display_name,
                    mu.username,
                    mu.email,
                    mu.phone,
                    mu.address,
                    mu.date_of_birth,
                    mu.code AS nim,
                    mr.name AS role_name,
                    ms.name AS stase_name,
                    (
                        SELECT COUNT(*)
                        FROM t_logbook tl
                        WHERE 
                            tl.id_user = mu.id
                        AND tl.deleted_at IS NULL
                    ) AS total_logbook
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                WHERE 
                    mu.id_client  = :id_client
                AND mu.status     = :status
                AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL
                AND mr.name       = :role_name';
        
        $countSql = 'SELECT COUNT(*)
                    FROM m_user mu
                    LEFT JOIN m_role mr ON mr.id = mu.id_role
                    LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                    WHERE 
                        mu.id_client  = :id_client
                    AND mu.status     = :status
                    AND mu.is_show    = :is_show
                    AND mu.deleted_at IS NULL
                    AND mr.name       = :role_name';
    
        $params = [
            ':id_client' => $post['id_client'],
            ':status'    => 'Active',
            ':is_show'   => true,
            ':role_name' => 'ppds'
        ];

        // 🔥 optional filter
        if (!empty($post['ppds'])) {
            $sql      .= ' AND mu.id = :ppds';
            $countSql .= ' AND mu.id = :ppds';
            $params[':ppds'] = $post['ppds'];
        }

        if (!empty($post['stase'])) {
            $sql      .= ' AND mu.id_stase = :stase';
            $countSql .= ' AND mu.id_stase = :stase';
            $params[':stase'] = $post['stase'];
        }

        if (!empty($post['nim'])) {
            $sql      .= ' AND mu.code ILIKE :nim';
            $countSql .= ' AND mu.code ILIKE :nim';
            $params[':nim'] = '%' . $post['nim'] . '%';
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
                mu.display_name ILIKE :search
                OR mu.username ILIKE :search
                OR mu.email ILIKE :search
                OR mu.phone ILIKE :search
                OR mu.address ILIKE :search
                OR mu.code ILIKE :search
                OR mr.name ILIKE :search
                OR ms.name ILIKE :search
            )';
            $countSql .= ' AND (
                mu.display_name ILIKE :search
                OR mu.username ILIKE :search
                OR mu.email ILIKE :search
                OR mu.phone ILIKE :search
                OR mu.address ILIKE :search
                OR mu.code ILIKE :search
                OR mr.name ILIKE :search
                OR ms.name ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        // sorting + pagination
        $sql .= " ORDER BY
                    mu.display_name
                    $sort
                LIMIT :limit
                OFFSET :offset";

        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            $countCommand->bindValue($key, $val);
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);
    
        $res   = $command->queryAll();
        $total = $countCommand->queryScalar();
    
        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $res,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
            ]
        ]);
    }
    
    public function actionRemovePPDS() {
        // echo json_encode([
        //     'db' => Yii::app()->db->connectionString
        // ]);die;
        
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_user'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        try {
            $user = MUser::model()->findByPk($post["id_user"]);
            
            if (!$user) {
                echo json_encode([
                    'status'  => false,
                    'message' => 'User tidak ditemukan!'
                ]);
                Yii::app()->end();
            }
            
            $user->deleted_at = new CDbExpression('NOW()');
            $user->is_deleted = true;
            $user->save(false);

            echo json_encode([
                'status' => true,
                'message' => 'Data berhasil dihapus!'
            ]);
        
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }
    
    public function actionGetDetailPPDS() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_user'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $sql = 'SELECT
                    mu.id,
                    mu.display_name,
                    mu.username,
                    mu.email,
                    mu.phone,
                    mu.address,
                    mu.date_of_birth,
                    mu.code AS nim,
                    mu.status,
                    mu.inactive_at,
                    mu.inactive_notes,
                    mu.reactivate_date,
                    mr.name AS role_name,
                    ms.name AS stase_name,
                    (
                        SELECT COUNT(*)
                        FROM t_logbook tl
                        WHERE 
                            tl.id_user = mu.id
                        AND tl.deleted_at IS NULL
                    ) AS total_logbook
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                WHERE 
                    mu.id = :id_user';
                    
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_user', $post['id_user'])
            ->queryRow();
            
        if (!$res) {
            echo json_encode([
                'status'  => false,
                'message' => 'User tidak ditemukan!'
            ]);
            Yii::app()->end();
        }
        
        echo json_encode([
            'status'  => true,
            'data'    => $res
        ]);
    }
    
    public function actionUpdatePPDS() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_user']) ||
            !isset($post['display_name']) ||
            !isset($post['username']) ||
            !isset($post['email']) ||
            !isset($post['phone'])
        ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $user = MUser::model()->findByPk($post["id_user"]);
        
        if (!$user) {
            echo json_encode([
                'status'  => false,
                'message' => 'User tidak ditemukan!'
            ]);
            Yii::app()->end();
        }
        
        $user->display_name   = $post['display_name'];
        $user->username       = $post['username'];
        $user->email          = $post['email'];
        $user->phone          = $post['phone'];
        $user->address        = $post['address'] ?? null;
        $user->date_of_birth  = $post['date_of_birth'] ?? null;
        $user->code           = $post['nim'] ?? null;
        $user->status         = $post['status'] ?? null;
        $user->inactive_at    = $post['inactive_at'] ?? null;
        $user->inactive_notes = $post['inactive_notes'] ?? null;
        $user->reactivate_date = $post['reactivate_date'] ?? null;    
        
        if (!$user->save()) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data gagal diupdate!'
            ]);
            Yii::app()->end();
        }
    
        echo json_encode([
            'status'  => true,
            'message' => 'Data berhasil diupdate!',
            'data'    => [
                'id_user' => $user->id,
            ]
        ]);
    }
    
    public function actionCreatePPDS() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_client']) ||
            !isset($post['display_name']) ||
            !isset($post['username']) ||
            !isset($post['email']) ||
            !isset($post['phone']) ||
            !isset($post['password']) ||
            !isset($post['confirm_password'])
        ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $password         = $post['password'];
        $confirm_password = $post['confirm_password'];

        if ($password !== $confirm_password) {
            echo json_encode([
                'status' => false,
                'message' => 'Password not match!'
            ]);
            Yii::app()->end();
        }
        
        $role = MRole::model()->findByAttributes(
                    [
                        'id_client' => $post['id_client'],
                        'name'      => 'ppds'
                    ],
                    ['select' => 'id']
            );
        
        try {
            $user                 = new MUser;
            $user->id_client      = $post['id_client'];
            $user->id_role        = $role->id;
            $user->display_name   = $post['display_name'];
            $user->username       = $post['username'];
            $user->email          = $post['email'];
            $user->phone          = $post['phone'];
            $user->address        = $post['address'] ?? null;
            $user->date_of_birth  = $post['date_of_birth'] ?? null;
            $user->code           = $post['nim'] ?? null;
            $user->password       = password_hash($post['password'], PASSWORD_BCRYPT);
            $user->created_date   = date('Y-m-d H:i:s');
            $user->save(false);
        
            echo json_encode([
                'status'  => true,
                'message' => 'Data berhasil dibuat!',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }
    
    public function actionGetListPPDSLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        // pagination default
        $page  = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default DESC)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc') ? 'ASC' : 'DESC';

        $baseCte = "
            WITH staff_ids_cte AS (
                SELECT
                    tls.id_logbook,
                    ARRAY_AGG(DISTINCT tls.id_user) AS staff_ids,
                    ARRAY_AGG(DISTINCT mu.display_name) FILTER (WHERE mar.role != 'Peserta') AS staff_names
                FROM t_logbook_status tls
                INNER JOIN m_action_role mar
                    ON mar.id = tls.id_action_role
                INNER JOIN m_user mu
                    ON mu.id = tls.id_user
                WHERE mar.role != 'Peserta'
                GROUP BY tls.id_logbook
            )
        ";

        $sql = "{$baseCte}
            SELECT
                tl.id,
                tl.date,
                tl.title,
                tl.notes,
                tl.verified_status,
                mu.display_name AS ppds_name,
                mu.code AS nim,
                ma.name AS action,
                mh.name AS hospital,
                ms.name AS semester,
                st.name AS stase_name
            FROM t_logbook tl
            LEFT JOIN m_user mu ON tl.id_user = mu.id
            LEFT JOIN m_action ma ON tl.id_action = ma.id
            LEFT JOIN m_hospital mh ON tl.id_hospital = mh.id
            LEFT JOIN m_semester ms ON tl.id_semester = ms.id
            LEFT JOIN m_stase st ON tl.id_stase = st.id
            LEFT JOIN staff_ids_cte sic ON sic.id_logbook = tl.id
            WHERE
                tl.id_client = :id_client
            AND tl.deleted_at IS NULL";

        $countSql = "{$baseCte}
            SELECT COUNT(DISTINCT tl.id)
            FROM t_logbook tl
            LEFT JOIN m_user mu ON tl.id_user = mu.id
            LEFT JOIN m_action ma ON tl.id_action = ma.id
            LEFT JOIN m_hospital mh ON tl.id_hospital = mh.id
            LEFT JOIN m_stase st ON tl.id_stase = st.id
            LEFT JOIN staff_ids_cte sic ON sic.id_logbook = tl.id
            WHERE
                tl.id_client = :id_client
            AND tl.deleted_at IS NULL";

        $params = [
            ':id_client' => $post['id_client'],
        ];

        // optional filter
        if (!empty($post['id_ppds'])) {
            $sql      .= ' AND tl.id_user = :id_ppds';
            $countSql .= ' AND tl.id_user = :id_ppds';
            $params[':id_ppds'] = $post['id_ppds'];
        }

        if (!empty($post['id_staff'])) {
            $sql      .= ' AND :id_staff = ANY(sic.staff_ids)';
            $countSql .= ' AND :id_staff = ANY(sic.staff_ids)';
            $params[':id_staff'] = $post['id_staff'];
        }

        if (!empty($post['id_activity'])) {
            $sql      .= ' AND tl.id_action = :id_activity';
            $countSql .= ' AND tl.id_action = :id_activity';
            $params[':id_activity'] = $post['id_activity'];
        }

        if (!empty($post['id_stase'])) {
            $sql      .= ' AND tl.id_stase = :id_stase';
            $countSql .= ' AND tl.id_stase = :id_stase';
            $params[':id_stase'] = $post['id_stase'];
        }

        if (!empty($post['start_date'])) {
            $sql      .= ' AND tl.date >= :start_date';
            $countSql .= ' AND tl.date >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND tl.date < :end_date';
            $countSql .= ' AND tl.date < :end_date';
            $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );
        }

        if (!empty($post['status'])) {
            $sql      .= ' AND tl.verified_status = :status';
            $countSql .= ' AND tl.verified_status = :status';
            $params[':status'] = $post['status'];
        }

        // 🔥 search filter - ILIKE across multiple fields + staff search
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            // Check if search is numeric (staff ID) or text (staff name)
            if (is_numeric($post['search'])) {
                // Numeric: search by staff ID in staff_ids array
                $sql      .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
                $countSql .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
            } else {
                // Text: search by staff name in staff_names array + other fields
                $sql      .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR tl.title ILIKE :search
                    OR tl.notes ILIKE :search
                    OR ma.name ILIKE :search
                    OR mh.name ILIKE :search
                    OR st.name ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
                $countSql .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR tl.title ILIKE :search
                    OR tl.notes ILIKE :search
                    OR ma.name ILIKE :search
                    OR mh.name ILIKE :search
                    OR st.name ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
            }
            $params[':search'] = $searchTerm;
        }

        // sorting + pagination
        $sql .= " ORDER BY
                    tl.date
                    $sort
                LIMIT :limit
                OFFSET :offset";

        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            // $countCommand->bindValue($key, $val);
            if ($key !== ':role_action') {
                $countCommand->bindValue($key, $val);
            }
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $data   = $command->queryAll();
        $total = $countCommand->queryScalar();

        // Query staff data separately and merge
        if (!empty($data)) {
            $logbookIds = array_column($data, 'id');

            $staffSql = "
                SELECT
                    tls.id_logbook,
                    tls.id_user AS id,
                    mu.display_name AS name
                FROM t_logbook_status tls
                INNER JOIN m_action_role mar
                    ON mar.id = tls.id_action_role
                INNER JOIN m_user mu
                    ON mu.id = tls.id_user
                WHERE tls.id_logbook IN (" . implode(',', $logbookIds) . ")
                    AND mar.role != 'Peserta'
                ORDER BY
                    tls.id_logbook,
                    mu.display_name
            ";
            $staffCommand = Yii::app()->db->createCommand($staffSql);
            $staffData = $staffCommand->queryAll();

            // Group staff by logbook_id
            $staffByLogbook = [];
            foreach ($staffData as $staff) {
                $idLogbook = $staff['id_logbook'];
                if (!isset($staffByLogbook[$idLogbook])) {
                    $staffByLogbook[$idLogbook] = [];
                }
                $staffByLogbook[$idLogbook][] = [
                    'id' => $staff['id'],
                    'name' => $staff['name'],
                ];
            }

            // Merge staff data into result
            foreach ($data as &$row) {
                $row['staff'] = $staffByLogbook[$row['id']] ?? [];
            }
        } else {
            foreach ($data as &$row) {
                $row['staff'] = [];
            }
        }

        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $data,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
            ]
        ]);
    }

    public function actionGetDetailPPDSLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_logbook'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                tl.id,
                mu.display_name AS ppds_name,
                mu.code AS nim,
                mu.inisial_code,
                tl.date,
                tl.notes,
                tl.verified_status AS status_logbook,
                mh.name AS hospital_name,
                ma.name AS action_name
            FROM t_logbook tl
            LEFT JOIN m_user mu
                ON mu.id = tl.id_user
            LEFT JOIN m_action ma
                ON ma.id = tl.id_action
            LEFT JOIN m_hospital mh
                ON mh.id = tl.id_hospital
            WHERE
                tl.id = :id_logbook
                AND tl.deleted_at IS NULL
            LIMIT 1
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_logbook', $post['id_logbook']);
        $data = $command->queryRow();

        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Logbook not found'
            ]);
            Yii::app()->end();
        }

        // Query staff separately to get all verifying staff
        $staffSql = "
            SELECT
                mu.display_name AS name,
                mar.role AS role,
                tls.status
            FROM t_logbook_status tls
            INNER JOIN m_action_role mar
                ON mar.id = tls.id_action_role
            INNER JOIN m_user mu
                ON mu.id = tls.id_user
            WHERE
                tls.id_logbook = :id_logbook
                AND mar.role != 'Peserta'
            ORDER BY mu.display_name
        ";
        $staffCommand = Yii::app()->db->createCommand($staffSql);
        $staffCommand->bindValue(':id_logbook', $post['id_logbook']);
        $staffData = $staffCommand->queryAll();

        $data['staff'] = $staffData;

        echo json_encode([
            'status' => true,
            'data'   => $data
        ]);
    }

    public function actionGetListPPDSInactive() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        // pagination default
        $page  = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        // sorting (default ASC)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'desc') ? 'DESC' : 'ASC';
    
        $sql = 'SELECT
                    mu.id,
                    mu.display_name,
                    mu.username,
                    mu.email,
                    mu.phone,
                    mu.address,
                    mu.date_of_birth,
                    mu.code AS nim,
                    mu.inactive_at,
                    mu.inactive_notes,
                    mr.name AS role_name,
                    ms.name AS stase_name,
                    (
                        SELECT COUNT(*)
                        FROM t_logbook tl
                        WHERE 
                            tl.id_user = mu.id
                        AND tl.deleted_at IS NULL
                    ) AS total_logbook
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                WHERE 
                    mu.id_client  = :id_client
                -- AND mu.status IN (:status1, :status2)
                -- AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL
                AND mr.name       = :role_name';
        
        $countSql = 'SELECT COUNT(*)
                    FROM m_user mu
                    LEFT JOIN m_role mr ON mr.id = mu.id_role
                    LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                    WHERE 
                        mu.id_client  = :id_client
                    -- AND mu.status IN (:status1, :status2)
                    -- AND mu.is_show    = :is_show
                    AND mu.deleted_at IS NULL
                    AND mr.name       = :role_name';
    
        $params = [
            ':id_client' => $post['id_client'],
            // ':status1'   => 'Inactive',
            // ':status2'   => 'Lulus',
            // ':is_show'   => true,
            ':role_name' => 'ppds'
        ];
    
        // 🔥 optional filter
        if (!empty($post['ppds'])) {
            $sql      .= ' AND mu.id = :ppds';
            $countSql .= ' AND mu.id = :ppds';
            $params[':ppds'] = $post['ppds'];
        }
    
        if (!empty($post['stase'])) {
            $sql      .= ' AND mu.id_stase = :stase';
            $countSql .= ' AND mu.id_stase = :stase';
            $params[':stase'] = $post['stase'];
        }
    
        if (!empty($post['nim'])) {
            $sql      .= ' AND mu.code ILIKE :nim';
            $countSql .= ' AND mu.code ILIKE :nim';
            $params[':nim'] = '%' . $post['nim'] . '%';
        }
        
        if (!empty($post['status'])) {
            $sql      .= ' AND mu.status = :status';
            $countSql .= ' AND mu.status = :status';
            $params[':status'] = $post['status'];
        } else {
            $sql      .= ' AND mu.status IN (:status1, :status2)';
            $countSql .= ' AND mu.status IN (:status1, :status2)';
            $params[':status1'] = 'Inactive';
            $params[':status2'] = 'Lulus';
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
                mu.display_name ILIKE :search
                OR mu.username ILIKE :search
                OR mu.email ILIKE :search
                OR mu.phone ILIKE :search
                OR mu.address ILIKE :search
                OR mu.code ILIKE :search
                OR mr.name ILIKE :search
                OR ms.name ILIKE :search
            )';
            $countSql .= ' AND (
                mu.display_name ILIKE :search
                OR mu.username ILIKE :search
                OR mu.email ILIKE :search
                OR mu.phone ILIKE :search
                OR mu.address ILIKE :search
                OR mu.code ILIKE :search
                OR mr.name ILIKE :search
                OR ms.name ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        // sorting + pagination
        $sql .= " ORDER BY 
                    mu.display_name 
                    $sort 
                LIMIT :limit 
                OFFSET :offset";
    
        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);
        
        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            $countCommand->bindValue($key, $val);
        }
    
        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);
    
        $res   = $command->queryAll();
        $total = $countCommand->queryScalar();
    
        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $res,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
            ]
        ]);
    }
    // === PPDS STAGE ===



    // === STAFF STAGE ===    
    public function actionGetListStaff() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        // pagination default
        $page  = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default ASC)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'desc') ? 'DESC' : 'ASC';

        $sql = "SELECT
                    mu.id,
                    mu.display_name,
                    mu.username,
                    mu.email,
                    mu.phone,
                    mu.address,
                    mu.location,
                    mu.date_of_birth,
                    mu.code AS nim,
                    mu.id_role,
                    mr.name AS role_name,
                    ms.name AS stase_name,
                    (
                        SELECT COUNT(DISTINCT tls.id_logbook)
                        FROM t_logbook_status tls
                        JOIN m_action_role mar ON tls.id_action_role = mar.id
                        WHERE tls.id_user = mu.id
                        AND mar.role != :role_action
                    ) AS total_logbook
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                WHERE
                    mu.id_client  = :id_client
                AND mu.status     = :status
                AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL
                AND mr.name ILIKE :role_name";
        
        $countSql = "SELECT COUNT(*)
                    FROM m_user mu
                    LEFT JOIN m_role mr ON mr.id = mu.id_role
                    LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                    WHERE 
                        mu.id_client  = :id_client
                    AND mu.status     = :status
                    AND mu.is_show    = :is_show
                    AND mu.deleted_at IS NULL";
    
        $params = [
            ':id_client' => $post['id_client'],
            ':status'    => 'Active',
            ':is_show'   => true,
            ':role_action' => 'Peserta'
        ];
    
        // optional filter
        if (!empty($post['staff'])) {
            $sql      .= ' AND mu.id = :staff';
            $countSql .= ' AND mu.id = :staff';
            $params[':staff'] = $post['staff'];
        }

        if (!empty($post['nim'])) {
            $sql      .= ' AND mu.code ILIKE :nim';
            $countSql .= ' AND mu.code ILIKE :nim';
            $params[':nim'] = '%' . $post['nim'] . '%';
        }
        
        if (!empty($post['role'])) {
            $sql      .= ' AND mr.name = :role_name';
            $countSql .= ' AND mr.name = :role_name';
            $params[':role_name'] = $post['role'];
        } else {
            $sql      .= ' AND mr.name ILIKE :role_name';
            $countSql .= ' AND mr.name ILIKE :role_name';
            $params[':role_name'] = '%staff%';
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
                mu.display_name ILIKE :search
                OR mu.username ILIKE :search
                OR mu.email ILIKE :search
                OR mu.phone ILIKE :search
                OR mu.address ILIKE :search
                OR mu.code ILIKE :search
                OR mr.name ILIKE :search
                OR ms.name ILIKE :search
            )';
            $countSql .= ' AND (
                mu.display_name ILIKE :search
                OR mu.username ILIKE :search
                OR mu.email ILIKE :search
                OR mu.phone ILIKE :search
                OR mu.address ILIKE :search
                OR mu.code ILIKE :search
                OR mr.name ILIKE :search
                OR ms.name ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        // sorting + pagination
        $sql .= " ORDER BY
                    mu.display_name
                    $sort
                LIMIT :limit
                OFFSET :offset";

        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            if ($key !== ':role_action') {
                $countCommand->bindValue($key, $val);
            }
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $res   = $command->queryAll();
        $total = $countCommand->queryScalar();

        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $res,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
            ]
        ]);
    }

    public function actionRemoveStaff() {
        // echo json_encode([
        //     'db' => Yii::app()->db->connectionString
        // ]);die;
        
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_user'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        try {
            $user = MUser::model()->findByPk($post["id_user"]);
            
            if (!$user) {
                echo json_encode([
                    'status'  => false,
                    'message' => 'User tidak ditemukan!'
                ]);
                Yii::app()->end();
            }
            
            $user->deleted_at = new CDbExpression('NOW()');
            $user->is_deleted = true;
            $user->save(false);

            echo json_encode([
                'status' => true,
                'message' => 'Data berhasil dihapus!'
            ]);
        
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }
    
    public function actionGetDetailStaff() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_user'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $sql = 'SELECT
                    mu.id,
                    mu.display_name,
                    mu.username,
                    mu.email,
                    mu.phone,
                    mu.address,
                    mu.location,
                    mu.date_of_birth,
                    mu.code AS nim,
                    mu.id_role,
                    mr.name AS role_name,
                    ms.name AS stase_name,
                    (
                        SELECT COUNT(DISTINCT tls.id_logbook)
                        FROM t_logbook_status tls
                        JOIN m_action_role mar ON tls.id_action_role = mar.id
                        WHERE tls.id_user = mu.id
                        AND mar.role != :role_action
                    ) AS total_logbook
                FROM m_user mu
                LEFT JOIN m_role mr ON mr.id = mu.id_role
                LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                WHERE
                    mu.id = :id_user';

        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_user', $post['id_user'])
            ->bindValue(':role_action', 'Peserta')
            ->queryRow();
            
        if (!$res) {
            echo json_encode([
                'status'  => false,
                'message' => 'User tidak ditemukan!'
            ]);
            Yii::app()->end();
        }
        
        echo json_encode([
            'status'  => true,
            'data'    => $res
        ]);
    }
    
    public function actionUpdateStaff() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_user']) ||
            !isset($post['display_name']) ||
            !isset($post['username']) ||
            !isset($post['email']) ||
            !isset($post['phone']) ||
            !isset($post['id_role'])
        ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $user = MUser::model()->findByPk($post["id_user"]);
        
        if (!$user) {
            echo json_encode([
                'status'  => false,
                'message' => 'User tidak ditemukan!'
            ]);
            Yii::app()->end();
        }
        
        $user->updated_by     = $post['updated_by'];
        $user->updated_date   = date('Y-m-d H:i:s');
        $user->display_name   = $post['display_name'];
        $user->username       = $post['username'];
        $user->email          = $post['email'];
        $user->phone          = $post['phone'];
        $user->address        = $post['address'] ?? null;
        $user->location       = $post['location'] ?? null;
        $user->date_of_birth  = $post['date_of_birth'] ?? null;
        $user->code           = $post['nim'] ?? null;
        $user->id_role        = $post['id_role'];
        
        if (!$user->save()) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data gagal diupdate!'
            ]);
            Yii::app()->end();
        }
    
        echo json_encode([
            'status'  => true,
            'message' => 'Data berhasil diupdate!',
            'data'    => [
                'id_user' => $user->id,
            ]
        ]);
    }
    
    public function actionCreateStaff() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_client']) ||
            !isset($post['created_by']) ||
            !isset($post['display_name']) ||
            !isset($post['username']) ||
            !isset($post['email']) ||
            !isset($post['phone']) ||
            !isset($post['password']) ||
            !isset($post['confirm_password']) ||
            !isset($post['id_role'])
        ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $password         = $post['password'];
        $confirm_password = $post['confirm_password'];

        if ($password !== $confirm_password) {
            echo json_encode([
                'status' => false,
                'message' => 'Password not match!'
            ]);
            Yii::app()->end();
        }
        
        try {
            $user                 = new MUser;
            $user->id_client      = $post['id_client'];
            $user->created_by     = $post['created_by'];
            $user->created_date   = date('Y-m-d H:i:s');
            $user->id_role        = $post['id_role'];
            $user->display_name   = $post['display_name'];
            $user->username       = $post['username'];
            $user->email          = $post['email'];
            $user->phone          = $post['phone'];
            $user->address        = $post['address'] ?? null;
            $user->location       = $post['location'] ?? null;
            $user->date_of_birth  = $post['date_of_birth'] ?? null;
            $user->code           = $post['nim'] ?? null;
            $user->password       = password_hash($post['password'], PASSWORD_BCRYPT);
            $user->save(false);
        
            echo json_encode([
                'status'  => true,
                'message' => 'Data berhasil dibuat!',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }

    public function actionGetListStaffLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        // pagination default
        $page  = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default DESC)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc') ? 'ASC' : 'DESC';

        $baseCte = "
            WITH staff_ids_cte AS (
                SELECT
                    tls.id_logbook,
                    ARRAY_AGG(DISTINCT tls.id_user) AS staff_ids,
                    ARRAY_AGG(DISTINCT mu.display_name) FILTER (WHERE mar.role != 'Peserta') AS staff_names
                FROM t_logbook_status tls
                INNER JOIN m_action_role mar
                    ON mar.id = tls.id_action_role
                INNER JOIN m_user mu
                    ON mu.id = tls.id_user
                WHERE mar.role != 'Peserta'
                GROUP BY tls.id_logbook
            )
        ";

        $sql = "{$baseCte}
            SELECT
                tl.id,
                tl.date,
                tl.title,
                tl.notes,
                tl.verified_status,
                mu.display_name AS ppds_name,
                mu.code AS nim,
                ma.name AS action_name,
                mh.name AS hospital_name,
                ms.name AS semester,
                st.name AS stase_name,
                COALESCE(
                    (
                        SELECT mu2.display_name
                        FROM t_logbook_status tls2
                        INNER JOIN m_action_role mar2 ON mar2.id = tls2.id_action_role
                        INNER JOIN m_user mu2 ON mu2.id = tls2.id_user
                        WHERE tls2.id_logbook = tl.id
                        AND mar2.role != 'Peserta'
                        AND tls2.id_user = :id_staff
                        LIMIT 1
                    ),
                    sic.staff_names[1]
                ) AS staff_name
            FROM t_logbook tl
            LEFT JOIN m_user mu ON tl.id_user = mu.id
            LEFT JOIN m_action ma ON tl.id_action = ma.id
            LEFT JOIN m_hospital mh ON tl.id_hospital = mh.id
            LEFT JOIN m_semester ms ON tl.id_semester = ms.id
            LEFT JOIN m_stase st ON tl.id_stase = st.id
            LEFT JOIN staff_ids_cte sic ON sic.id_logbook = tl.id
            WHERE
                tl.id_client = :id_client
            AND tl.deleted_at IS NULL";

        $countSql = "{$baseCte}
            SELECT COUNT(DISTINCT tl.id)
            FROM t_logbook tl
            LEFT JOIN m_user mu ON tl.id_user = mu.id
            LEFT JOIN m_action ma ON tl.id_action = ma.id
            LEFT JOIN m_hospital mh ON tl.id_hospital = mh.id
            LEFT JOIN m_semester ms ON tl.id_semester = ms.id
            LEFT JOIN m_stase st ON tl.id_stase = st.id
            LEFT JOIN staff_ids_cte sic ON sic.id_logbook = tl.id
            WHERE
                tl.id_client = :id_client
            AND tl.deleted_at IS NULL";

        $params = [
            ':id_client' => $post['id_client'],
            ':id_staff' => $post['id_staff'] ?? null,
        ];

        // optional filter
        if (!empty($post['id_ppds'])) {
            $sql      .= ' AND tl.id_user = :id_ppds';
            $countSql .= ' AND tl.id_user = :id_ppds';
            $params[':id_ppds'] = $post['id_ppds'];
        }

        if (!empty($post['id_staff'])) {
            $sql      .= ' AND :id_staff = ANY(sic.staff_ids)';
            $countSql .= ' AND :id_staff = ANY(sic.staff_ids)';
        }

        if (!empty($post['id_activity'])) {
            $sql      .= ' AND tl.id_action = :id_activity';
            $countSql .= ' AND tl.id_action = :id_activity';
            $params[':id_activity'] = $post['id_activity'];
        }

        if (!empty($post['id_stase'])) {
            $sql      .= ' AND tl.id_stase = :id_stase';
            $countSql .= ' AND tl.id_stase = :id_stase';
            $params[':id_stase'] = $post['id_stase'];
        }

        if (!empty($post['start_date'])) {
            $sql      .= ' AND tl.date >= :start_date';
            $countSql .= ' AND tl.date >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND tl.date < :end_date';
            $countSql .= ' AND tl.date < :end_date';
                        $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );;
        }

        if (!empty($post['status'])) {
            $sql      .= ' AND tl.verified_status = :status';
            $countSql .= ' AND tl.verified_status = :status';
            $params[':status'] = $post['status'];
        }

        // 🔥 search filter - ILIKE across multiple fields + staff search
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            // Check if search is numeric (staff ID) or text (staff name)
            if (is_numeric($post['search'])) {
                // Numeric: search by staff ID in staff_ids array
                $sql      .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
                $countSql .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
            } else {
                // Text: search by staff name in staff_names array + other fields
                $sql      .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR tl.title ILIKE :search
                    OR tl.notes ILIKE :search
                    OR ma.name ILIKE :search
                    OR mh.name ILIKE :search
                    OR st.name ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
                $countSql .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR tl.title ILIKE :search
                    OR tl.notes ILIKE :search
                    OR ma.name ILIKE :search
                    OR mh.name ILIKE :search
                    OR st.name ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
            }
            $params[':search'] = $searchTerm;
        }

        // sorting + pagination
        $sql .= " ORDER BY
                    tl.date
                    $sort
                LIMIT :limit
                OFFSET :offset";

        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            $countCommand->bindValue($key, $val);
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $res   = $command->queryAll();
        $total = $countCommand->queryScalar();

        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $res,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
            ]
        ]);
    }

    public function actionGetDetailStaffLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_logbook'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                tl.id,
                mu.display_name AS ppds_name,
                mu.code AS nim,
                mu.inisial_code,
                tl.date,
                tl.notes,
                tl.verified_status AS status_logbook,
                mh.name AS hospital_name,
                ma.name AS action_name
            FROM t_logbook tl
            LEFT JOIN m_user mu
                ON mu.id = tl.id_user
            LEFT JOIN m_action ma
                ON ma.id = tl.id_action
            LEFT JOIN m_hospital mh
                ON mh.id = tl.id_hospital
            WHERE
                tl.id = :id_logbook
                AND tl.deleted_at IS NULL
            LIMIT 1
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_logbook', $post['id_logbook']);
        $data = $command->queryRow();

        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Logbook not found'
            ]);
            Yii::app()->end();
        }

        // Query staff separately to get all verifying staff
        $staffSql = "
            SELECT
                mu.display_name AS name,
                mar.role AS role,
                tls.status AS status
            FROM t_logbook_status tls
            INNER JOIN m_action_role mar
                ON mar.id = tls.id_action_role
            INNER JOIN m_user mu
                ON mu.id = tls.id_user
            WHERE
                tls.id_logbook = :id_logbook
                AND mar.role != 'Peserta'
            ORDER BY mu.display_name
        ";
        $staffCommand = Yii::app()->db->createCommand($staffSql);
        $staffCommand->bindValue(':id_logbook', $post['id_logbook']);
        $staffData = $staffCommand->queryAll();

        $data['staff'] = $staffData;

        echo json_encode([
            'status' => true,
            'data'   => $data
        ]);
    }
    // === STAFF STAGE ===
    
    // === HOSPITAL ===
    public function actionGetListHospital() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        // pagination default
        $page  = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default ASC)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'desc') ? 'DESC' : 'ASC';

        $sql = 'SELECT
                    mh.id,
                    mh.name,
                    mh.address,
                    mh.code
                FROM m_hospital mh
                WHERE
                    mh.id_client = :id_client
                    AND mh.deleted_at IS NULL';
        
        $countSql = 'SELECT COUNT(*)
                FROM m_hospital mh
                WHERE
                    mh.id_client = :id_client';
    
        $params = [
            ':id_client' => $post['id_client'],
        ];

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
                mh.name ILIKE :search
                OR mh.code ILIKE :search
                OR mh.address ILIKE :search
            )';
            $countSql .= ' AND (
                mh.name ILIKE :search
                OR mh.code ILIKE :search
                OR mh.address ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        // sorting + pagination
        $sql .= " ORDER BY
                    mh.name
                    $sort
                LIMIT :limit
                OFFSET :offset";

        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            $countCommand->bindValue($key, $val);
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $res   = $command->queryAll();
        $total = $countCommand->queryScalar();

        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $res,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
            ]
        ]);
    }
    
    public function actionRemoveHospital() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        $hospital = MHospital::model()->findByPk($post['id']);

        if (!$hospital) {
            echo json_encode([
                'status' => false,
                'message' => 'Hospital not found!'
            ]);
            Yii::app()->end();
        }

        try {
            $hospital->deleted_at = new CDbExpression('NOW()');
            $hospital->save(false);

            echo json_encode([
                'status'  => true,
                'message' => 'Data berhasil didelete!',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }
    
    public function actionCreateHospital() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (
            !isset($post['id_client']) ||
            !isset($post['created_by']) ||
            !isset($post['name']) ||
            !isset($post['code'])
        ) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        try {
            $hospital               = new MHospital;
            $hospital->id_client    = $post['id_client'];
            $hospital->created_date = date('Y-m-d H:i:s');
            $hospital->created_by   = $post['created_by'];
            $hospital->name         = $post['name'];
            $hospital->code         = $post['code'];
            $hospital->address      = $post['address'] ?? null;
            $hospital->notes        = $post['notes'] ?? null;
            $hospital->save(false);

            echo json_encode([
                'status'  => true,
                'message' => 'Data berhasil dibuat!',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }
    
    public function actionUpdateHospital() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

       if (
            !isset($post['id']) ||
            !isset($post['id_client']) ||
            !isset($post['updated_by']) ||
            !isset($post['name']) ||
            !isset($post['code'])
        ) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $hospital = MHospital::model()->findByPk($post['id']);

        if (!$hospital) {
            echo json_encode([
                'status' => false,
                'message' => 'Hospital not found!'
            ]);
            Yii::app()->end();
        }

        try {
            $hospital->id_client    = $post['id_client'];
            $hospital->updated_date = date('Y-m-d H:i:s');
            $hospital->updated_by   = $post['updated_by'];
            $hospital->name         = $post['name'] ?? $hospital->name;
            $hospital->code         = $post['code'] ?? $hospital->code;
            $hospital->address      = $post['address'] ?? $hospital->address;
            $hospital->notes        = $post['notes'] ?? $hospital->notes;
            $hospital->save(false);

            echo json_encode([
                'status'  => true,
                'message' => 'Data berhasil diupdate!',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
        Yii::app()->end();
    }
    
    public function actionGetDetailHospital() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id'])) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                mh.id,
                mh.name,
                mh.code,
                mh.address,
                mh.notes
            FROM m_hospital mh
            WHERE
                mh.id = :id";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id', $post['id']);
        $data = $command->queryRow();

        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data not found'
            ]);
            Yii::app()->end();
        }

        echo json_encode([
            'status' => true,
            'data'   => $data
        ]);
    }
    // === HOSPITAL ===
    
    // === MORBIDITY ===
        public function actionGetListMorbidity()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post) || !isset($post['id_client']) || !preg_match('/^[1-9][0-9]*$/', (string)$post['id_client'])) {
        echo json_encode(array('status'=>false, 'message'=>'Invalid parameter!'));
        Yii::app()->end();
    }

    $page = max(1, isset($post['page']) ? (int)$post['page'] : 1);
    $limit = min(100, max(1, isset($post['limit']) ? (int)$post['limit'] : 10));
    $offset = ($page - 1) * $limit;
    $sort = isset($post['sort']) && strtolower((string)$post['sort']) === 'asc' ? 'ASC' : 'DESC';

    try {
        $db = Yii::app()->db;

        $baseSql = "
            SELECT
                u.id AS id,
                u.id AS id_user,
                u.id_stase,
                u.id_semester,
                u.display_name,
                u.code,

                /* Active Point: hitung hanya dari Morbiditas yang terikat
                 * ke sesi aktif user (id_morbiditas_period = session.id).
                 * Jika ada Morbiditas tanpa binding (data lama), fallback
                 * ke logika timestamp. */
                CASE
                    WHEN session.restored_after_logbook_id IS NOT NULL THEN
                        session.points - COALESCE(session.restored_bound_points,0) +
                        COALESCE(SUM(CASE
                            WHEN ma.id IS NOT NULL
                             AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status,''))='verified')
                             AND (lb.id_morbiditas_period=session.id OR
                                 (lb.id_morbiditas_period IS NULL AND lb.id>session.restored_after_logbook_id AND lb.id_semester=u.id_semester))
                            THEN category.points ELSE 0 END),0)::int
                    WHEN session.id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM t_logbook unbound_lb
                        JOIN m_action unbound_a ON unbound_a.id = unbound_lb.id_action
                            AND unbound_a.id_client = unbound_lb.id_client
                        WHERE unbound_lb.id_user = u.id
                          AND unbound_lb.id_client = u.id_client
                          AND unbound_lb.id_semester = u.id_semester
                          AND unbound_lb.id_morbiditas_period IS NULL
                          AND unbound_lb.deleted_at IS NULL
                          AND (unbound_lb.verified IS TRUE
                               OR LOWER(COALESCE(unbound_lb.verified_status, '')) = 'verified')
                          AND (LOWER(COALESCE(unbound_a.identifier, '')) = 'morbiditas'
                               OR LOWER(COALESCE(unbound_a.name, '')) = 'morbiditas' OR unbound_a.id=39)
                    )
                    THEN
                        /* Semua Morbiditas sudah ter-binding: hitung dari binding */
                        COALESCE(SUM(CASE
                            WHEN ma.id IS NOT NULL
                             AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, '')) = 'verified')
                             AND category.points IS NOT NULL
                             AND lb.id_morbiditas_period = session.id
                            THEN category.points ELSE 0
                        END), 0)::int
                    ELSE
                        /* Fallback: logika timestamp untuk data lama */
                        COALESCE(SUM(CASE
                            WHEN ma.id IS NOT NULL
                             AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, '')) = 'verified')
                             AND category.points IS NOT NULL
                             AND lb.id_semester = u.id_semester
                             AND lb.created_date >= GREATEST(
                                COALESCE(session.started_at, '1970-01-01 00:00:00+00'::timestamptz),
                                COALESCE(stase_boundary.created_date, '1970-01-01 00:00:00+00'::timestamptz)
                             )
                            THEN category.points ELSE 0
                        END), 0)::int
                END AS poin_aktif,

                COUNT(lb.id) FILTER (WHERE ma.id IS NOT NULL) AS jml_logbook,
                COUNT(lb.id) FILTER (
                    WHERE ma.id IS NOT NULL
                      AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, '')) = 'verified')
                ) AS verified
            FROM m_user u
            INNER JOIN m_role role ON role.id = u.id_role
            LEFT JOIN LATERAL (
                SELECT mph.id, mph.started_at, mph.points, mph.restored_after_logbook_id, mph.restored_bound_points
                FROM t_morbiditas_points_history mph
                WHERE mph.id_user = u.id
                  AND mph.id_client = u.id_client
                  AND mph.ended_at IS NULL
                ORDER BY mph.id DESC
                LIMIT 1
            ) session ON TRUE
            LEFT JOIN LATERAL (
                SELECT milestone.created_date
                FROM t_logbook milestone
                INNER JOIN m_action milestone_action ON milestone_action.id = milestone.id_action
                WHERE milestone.id_user = u.id
                  AND milestone.id_client = u.id_client
                  AND milestone.id_semester = u.id_semester
                  AND milestone.deleted_at IS NULL
                  AND milestone_action.is_milestone = true
                  AND milestone_action.show_on_milestone = true
                  AND (LOWER(COALESCE(milestone_action.identifier, '')) = 'stase'
                       OR LOWER(COALESCE(milestone_action.name, '')) = 'stase')
                ORDER BY milestone.created_date DESC, milestone.id DESC
                LIMIT 1
            ) stase_boundary ON TRUE
            LEFT JOIN t_logbook lb
                ON lb.id_user = u.id
               AND lb.id_client = u.id_client
               AND lb.deleted_at IS NULL
            LEFT JOIN m_action ma
                ON ma.id = lb.id_action
               AND ma.id_client = lb.id_client
               AND (LOWER(COALESCE(ma.identifier, '')) = 'morbiditas'
                    OR LOWER(COALESCE(ma.name, '')) = 'morbiditas' OR ma.id=39)
            LEFT JOIN m_action_category category
                ON category.id = lb.id_category
               AND category.id_action = lb.id_action
               AND category.id_client = lb.id_client
            WHERE u.id_client = :id_client
              AND u.deleted_at IS NULL
              AND u.status = 'Active'
              AND LOWER(role.name) = 'ppds'
              AND EXISTS (
                  SELECT 1
                  FROM t_logbook morbidity_lb
                  INNER JOIN m_action morbidity_action
                      ON morbidity_action.id = morbidity_lb.id_action
                     AND morbidity_action.id_client = morbidity_lb.id_client
                  WHERE morbidity_lb.id_user = u.id
                    AND morbidity_lb.id_client = u.id_client
                    AND morbidity_lb.deleted_at IS NULL
                    AND (LOWER(COALESCE(morbidity_action.identifier, '')) = 'morbiditas'
                         OR LOWER(COALESCE(morbidity_action.name, '')) = 'morbiditas' OR morbidity_action.id=39)
              )
            GROUP BY u.id, u.id_stase, u.id_semester, u.display_name, u.code,
                     session.id, session.started_at, session.points, session.restored_after_logbook_id, session.restored_bound_points,
                     stase_boundary.created_date
        ";

        $sql = "SELECT * FROM ({$baseSql}) AS x";
        $countSql = "SELECT COUNT(*) FROM ({$baseSql}) AS x";
        $params = array(':id_client'=>(int)$post['id_client']);
        if (!empty($post['search'])) {
            $where = " WHERE (display_name ILIKE :search OR code ILIKE :search OR CAST(poin_aktif AS TEXT) ILIKE :search)";
            $sql .= $where;
            $countSql .= $where;
            $params[':search'] = '%' . trim((string)$post['search']) . '%';
        }
        $sql .= " ORDER BY poin_aktif {$sort}, display_name ASC LIMIT :limit OFFSET :offset";

        $command = $db->createCommand($sql);
        $countCommand = $db->createCommand($countSql);
        foreach ($params as $key=>$value) {
            $command->bindValue($key, $value);
            $countCommand->bindValue($key, $value);
        }
        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);
        $rows = $command->queryAll();
        $total = (int)$countCommand->queryScalar();

        echo json_encode(array(
            'status'=>true,
            'total'=>$total,
            'data'=>$rows,
            'pagination'=>array('page'=>$page, 'limit'=>$limit),
        ));
    } catch (Exception $e) {
        Yii::log('GetListMorbidity failed: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'api.morbiditas');
        echo json_encode(array('status'=>false, 'message'=>'Gagal memuat Morbiditas'));
    }
    Yii::app()->end();
}


    
    public function actionGetListMorbidityByUser() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id_client']) || !isset($post['id_user'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $page   = isset($post['page']) ? (int)$post['page'] : 1;
        $limit  = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc')
            ? 'ASC'
            : 'DESC';
    
        $baseSql = 'SELECT
                        l.id,
                        u.display_name,
    
                        COALESCE(emr.patient_name, \'-\') AS patient_name,
    
                        l.date,
    
                        COALESCE(sem.name, \'-\') AS semester,
    
                        CASE
                            WHEN cat.points IS NOT NULL
                                THEN cat.name || \' (\' || cat.points || \')\'
                            ELSE COALESCE(cat.name, \'-\')
                        END AS category,
    
                        COALESCE(pelapor.display_name, \'-\') AS staff_pelapor,
                        COALESCE(penilai.display_name, \'-\') AS staff_penilai,
                        COALESCE(kps.display_name, \'-\') AS staff_kps,
    
                        CASE
                            WHEN l.verified = TRUE
                                OR LOWER(COALESCE(l.verified_status, \'\')) = \'verified\'
                                THEN \'Verified\'
    
                            WHEN LOWER(COALESCE(l.verified_status, \'\')) = \'rejected\'
                                THEN \'Rejected\'
    
                            WHEN LOWER(COALESCE(l.verified_status, \'\')) = \'revised\'
                                THEN \'Revised\'
    
                            WHEN LOWER(COALESCE(l.verified_status, \'\')) = \'pending\'
                                THEN \'Pending\'
    
                            ELSE COALESCE(l.verified_status, \'-\')
                        END AS status
    
                    FROM t_logbook l
    
                    JOIN m_user u
                        ON u.id = l.id_user
    
                    JOIN m_action ma
                        ON ma.id = l.id_action
                        AND ma.id_client = l.id_client
                        AND (
                            LOWER(COALESCE(ma.identifier, \'\')) = \'morbiditas\'
                            OR LOWER(COALESCE(ma.name, \'\')) = \'morbiditas\'
                        )
    
                    LEFT JOIN m_semester sem
                        ON sem.id = l.id_semester
    
                    LEFT JOIN m_action_category cat
                        ON cat.id = l.id_category
    
                    LEFT JOIN LATERAL (
                        SELECT
                            e.patient_name
                        FROM t_logbook_emr e
                        WHERE
                            e.id_logbook = l.id
                            AND e.deleted_at IS NULL
                        ORDER BY e.id
                        LIMIT 1
                    ) emr ON TRUE
    
                    LEFT JOIN LATERAL (
                        SELECT
                            su.display_name
                        FROM t_logbook_status ls
                        JOIN m_action_role ar
                            ON ar.id = ls.id_action_role
                        JOIN m_user su
                            ON su.id = ls.id_user
                        WHERE
                            ls.id_logbook = l.id
                            AND ls.deleted_at IS NULL
                            AND (
                                LOWER(COALESCE(ar.identifier, \'\')) = \'staff_pelapor\'
                                OR LOWER(
                                    ar.role || \' \' || COALESCE(ar.identifier, \'\')
                                ) LIKE \'%pelapor%\'
                            )
                        ORDER BY ls.id
                        LIMIT 1
                    ) pelapor ON TRUE
    
                    LEFT JOIN LATERAL (
                        SELECT
                            su.display_name
                        FROM t_logbook_status ls
                        JOIN m_action_role ar
                            ON ar.id = ls.id_action_role
                        JOIN m_user su
                            ON su.id = ls.id_user
                        WHERE
                            ls.id_logbook = l.id
                            AND ls.deleted_at IS NULL
                            AND LOWER(COALESCE(ar.identifier, \'\')) NOT LIKE \'%kps%\'
                            AND (
                                LOWER(COALESCE(ar.identifier, \'\')) IN (
                                    \'staff_penilai\',
                                    \'penilai_gkm\'
                                )
                                OR LOWER(
                                    ar.role || \' \' || COALESCE(ar.identifier, \'\')
                                ) LIKE \'%penilai%\'
                                OR LOWER(
                                    ar.role || \' \' || COALESCE(ar.identifier, \'\')
                                ) LIKE \'%gkm%\'
                            )
                    ) penilai ON TRUE
    
                    LEFT JOIN LATERAL (
                        SELECT
                            su.display_name
                        FROM t_logbook_status ls
                        JOIN m_action_role ar
                            ON ar.id = ls.id_action_role
                        JOIN m_user su
                            ON su.id = ls.id_user
                        WHERE
                            ls.id_logbook = l.id
                            AND ls.deleted_at IS NULL
                            AND (
                                LOWER(COALESCE(ar.identifier, \'\')) IN (
                                    \'staff_kps\',
                                    \'kps\'
                                )
                                OR LOWER(
                                    ar.role || \' \' || COALESCE(ar.identifier, \'\')
                                ) LIKE \'%kps%\'
                            )
                        ORDER BY ls.id
                        LIMIT 1
                    ) kps ON TRUE
    
                    WHERE
                        l.id_client = :id_client
                        AND l.id_user = :id_user
                        AND l.deleted_at IS NULL';
    
        $sql = "SELECT *
                FROM (
                    {$baseSql}
                ) AS x";
    
        $countSql = "SELECT COUNT(*)
                     FROM (
                         {$baseSql}
                     ) AS x";
    
        $params = [
            ':id_client' => $post['id_client'],
            ':id_user'   => $post['id_user'],
        ];
    
        // === FILTER STATUS ===
        if (!empty($post['status'])) {
            $sql .= ' WHERE LOWER(status) = LOWER(:status)';
            $countSql .= ' WHERE LOWER(status) = LOWER(:status)';
    
            $params[':status'] = $post['status'];
        }
    
        // === SEARCH ===
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
    
            $searchWhere = '
                patient_name ILIKE :search
                OR category ILIKE :search
                OR staff_kps ILIKE :search
            ';
    
            if (!empty($post['status'])) {
                $sql .= ' AND (' . $searchWhere . ')';
                $countSql .= ' AND (' . $searchWhere . ')';
            } else {
                $sql .= ' WHERE (' . $searchWhere . ')';
                $countSql .= ' WHERE (' . $searchWhere . ')';
            }
    
            $params[':search'] = $searchTerm;
        }
    
        $sql .= " ORDER BY
                    date $sort
                LIMIT :limit
                OFFSET :offset";
    
        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);
    
        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            $countCommand->bindValue($key, $val);
        }
    
        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);
    
        $res   = $command->queryAll();
        $total = $countCommand->queryScalar();
    
        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $res,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
            ]
        ]);
    }
    
    public function actionGetDetailMorbidityByUser() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $sql = 'SELECT
                    l.id,
                    u.display_name,
    
                    COALESCE(emr.patient_name, \'-\') AS patient_name,
                    COALESCE(emr.age::text, \'-\') AS umur,
                    COALESCE(emr.emr_number, \'-\') AS cm,
                    COALESCE(emr.diagnosis, \'-\') AS dx_awal,
    
                    l.date,
    
                    COALESCE(sem.name, \'-\') AS semester,
    
                    CASE
                        WHEN cat.points IS NOT NULL
                            THEN cat.name || \' (\' || cat.points || \')\'
                        ELSE COALESCE(cat.name, \'-\')
                    END AS category,
    
                    COALESCE(pelapor.display_name, \'-\') AS staff_pelapor,
                    COALESCE(penilai.display_name, \'-\') AS staff_penilai,
                    COALESCE(kps.display_name, \'-\') AS staff_kps,
    
                    COALESCE(l.notes, \'-\') AS kronologi_morbiditas,
    
                    COALESCE(att.lampiran, \'-\') AS lampiran,
    
                    CASE
                        WHEN l.verified = TRUE
                            OR LOWER(COALESCE(l.verified_status, \'\')) = \'verified\'
                            THEN \'Verified\'
    
                        WHEN LOWER(COALESCE(l.verified_status, \'\')) = \'rejected\'
                            THEN \'Rejected\'
    
                        WHEN LOWER(COALESCE(l.verified_status, \'\')) = \'revised\'
                            THEN \'Revised\'
    
                        WHEN LOWER(COALESCE(l.verified_status, \'\')) = \'pending\'
                            THEN \'Pending\'
    
                        ELSE COALESCE(l.verified_status, \'-\')
                    END AS status
    
                FROM t_logbook l
    
                JOIN m_user u
                    ON u.id = l.id_user
    
                LEFT JOIN m_semester sem
                    ON sem.id = l.id_semester
    
                LEFT JOIN m_action_category cat
                    ON cat.id = l.id_category
    
                LEFT JOIN LATERAL (
                    SELECT
                        patient_name,
                        age,
                        emr_number,
                        diagnosis
                    FROM t_logbook_emr
                    WHERE
                        id_logbook = l.id
                        AND deleted_at IS NULL
                    ORDER BY id ASC
                    LIMIT 1
                ) emr ON TRUE
    
                LEFT JOIN LATERAL (
                    SELECT
                        su.display_name
                    FROM t_logbook_status ls
                    JOIN m_action_role ar
                        ON ar.id = ls.id_action_role
                    JOIN m_user su
                        ON su.id = ls.id_user
                    WHERE
                        ls.id_logbook = l.id
                        AND ls.deleted_at IS NULL
                        AND (
                            LOWER(COALESCE(ar.identifier, \'\')) = \'staff_pelapor\'
                            OR LOWER(
                                ar.role || \' \' || COALESCE(ar.identifier, \'\')
                            ) LIKE \'%pelapor%\'
                        )
                    ORDER BY ls.id
                    LIMIT 1
                ) pelapor ON TRUE
    
                LEFT JOIN LATERAL (
                    SELECT
                        su.display_name
                    FROM t_logbook_status ls
                    JOIN m_action_role ar
                        ON ar.id = ls.id_action_role
                    JOIN m_user su
                        ON su.id = ls.id_user
                    WHERE
                        ls.id_logbook = l.id
                        AND ls.deleted_at IS NULL
                        AND LOWER(COALESCE(ar.identifier, \'\')) NOT LIKE \'%kps%\'
                        AND (
                            LOWER(COALESCE(ar.identifier, \'\')) IN (
                                \'staff_penilai\',
                                \'penilai_gkm\'
                            )
                            OR LOWER(
                                ar.role || \' \' || COALESCE(ar.identifier, \'\')
                            ) LIKE \'%penilai%\'
                            OR LOWER(
                                ar.role || \' \' || COALESCE(ar.identifier, \'\')
                            ) LIKE \'%gkm%\'
                        )
                    ORDER BY ls.id
                    LIMIT 1
                ) penilai ON TRUE
    
                LEFT JOIN LATERAL (
                    SELECT
                        su.display_name
                    FROM t_logbook_status ls
                    JOIN m_action_role ar
                        ON ar.id = ls.id_action_role
                    JOIN m_user su
                        ON su.id = ls.id_user
                    WHERE
                        ls.id_logbook = l.id
                        AND ls.deleted_at IS NULL
                        AND (
                            LOWER(COALESCE(ar.identifier, \'\')) IN (
                                \'staff_kps\',
                                \'kps\'
                            )
                            OR LOWER(
                                ar.role || \' \' || COALESCE(ar.identifier, \'\')
                            ) LIKE \'%kps%\'
                        )
                    ORDER BY ls.id
                    LIMIT 1
                ) kps ON TRUE
    
                LEFT JOIN LATERAL (
                    SELECT
                        STRING_AGG(
                            CONCAT(
                                COALESCE(a.name, \'Lampiran\'),
                                \': \',
                                a.url_file
                            ),
                            E\'\\n\'
                            ORDER BY a.id
                        ) AS lampiran
                    FROM t_logbook_attachment a
                    WHERE
                        a.id_logbook = l.id
                        AND a.deleted_at IS NULL
                ) att ON TRUE
    
                WHERE
                    l.id = :id
                    AND l.deleted_at IS NULL
    
                LIMIT 1';
    
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id', $post['id']);
    
        $data = $command->queryRow();
    
        if (!$data) {
            echo json_encode([
                'status' => false,
                'message' => 'Morbidity data not found!'
            ]);
            Yii::app()->end();
        }
    
        // === STAFF STATUS (now includes IDs for verifier reassignment) ===
        $staffSql = '
            SELECT
                tls.id AS status_id,
                tls.id_user,
                tls.id_action_role,
                mu.display_name AS name,
                mar.role AS role,
                mar.identifier AS role_identifier,
                tls.status,
                tls.verify_notes
            FROM t_logbook_status tls
    
            INNER JOIN m_action_role mar
                ON mar.id = tls.id_action_role
    
            INNER JOIN m_user mu
                ON mu.id = tls.id_user
    
            WHERE
                tls.id_logbook = :id_logbook
                AND tls.deleted_at IS NULL
                AND mar.role != \'Peserta\'
    
            ORDER BY tls.id';
    
        $staffCommand = Yii::app()->db->createCommand($staffSql);
        $staffCommand->bindValue(':id_logbook', $post['id']);
    
        $staffData = $staffCommand->queryAll();
    
        $data['staff'] = $staffData;
    
        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
    }
    
    /* The supplied Web login only admits role institution. Do not import Mobile Staff permissions.
 * No server session/token verifier exists in the supplied controller; request IDs remain legacy protocol.
 * If deployment adds authenticated middleware, it MUST bind actor/client before entering these methods. */
private function webMorbiditasActor($db,$post)
{
    foreach(array('created_by','id_client') as $key) {
        if(!is_array($post)||!isset($post[$key])||!is_scalar($post[$key])||!preg_match('/^[1-9][0-9]*$/',(string)$post[$key]))
            throw new RuntimeException($key.' wajib berupa ID positif');
    }
    // actionLogin returns mu.id/id_client/role from Yii::app()->db, NOT db.
    // Never resolve this ID against a different database or fall back to a same-ID PPDS/Staff.
    // Institution status permits Active or legacy NULL/blank, not other statuses.
    // is_show is display-only for Institution; PPDS visibility checks stay unchanged.
    $identityDb=Yii::app()->db;
    $actor=$identityDb->createCommand("SELECT u.id FROM m_user u JOIN m_role r ON r.id=u.id_role
        WHERE u.id=:actor AND u.id_client=:client AND u.deleted_at IS NULL
        AND lower(r.name)='institution'
        AND lower(btrim(coalesce(u.status,''))) IN ('','active')")
        ->bindValues(array(':actor'=>(int)$post['created_by'],':client'=>(int)$post['id_client']))->queryRow();
    if(!$actor) throw new RuntimeException('Aktor Institution tidak valid pada database login (db): periksa created_by/id_client, role, deleted_at, status nonaktif/tidak dikenal; login ulang jika sesi lama. ID db tidak dipakai sebagai pengganti identitas login.');
    // Data/session schema still belongs to db (the caller's transaction).
    $columns=$db->createCommand("SELECT column_name FROM information_schema.columns
        WHERE table_schema=current_schema() AND table_name='t_morbiditas_points_history'") ->queryAll();
    $present=array_column($columns,'column_name');
    foreach(array('transition_logbook_id','previous_session_id','undone_at','restored_at','restored_after_logbook_id','restored_bound_points') as $column) {
        if(!in_array($column,$present,true)) throw new RuntimeException('Migration morbiditas-semester-undo.sql belum berhasil: kolom '.$column.' belum tersedia. Jangan instal controller sebelum migration COMMIT.');
    }
}


/* Add once inside ApiWebServiceController. Requires morbiditas-semester-undo.sql.
 * Web login-compatible Institution role only. Supplied controller has no server-authenticated identity; membership is not sender authentication.
 * No timestamp inference: legacy rows need explicitly audited session-to-milestone links.
 */
private function morbiditasSemesterUndoContext($db, $post, $write = false)
{
    foreach (array('created_by','id_client','id_user') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !is_scalar($post[$field])
            || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            throw new RuntimeException($field.' wajib berupa ID positif');
        }
    }
    $client=(int)$post['id_client']; $actor=(int)$post['created_by']; $user=(int)$post['id_user'];
    $this->webMorbiditasActor($db,$post);
    // Same lock order as Milestone Create: PPDS -> sessions -> milestone.
    $ppds=$db->createCommand("SELECT u.id,u.display_name,u.id_semester,u.id_stase FROM m_user u JOIN m_role r ON r.id=u.id_role
        WHERE u.id=:user AND u.id_client=:client AND u.deleted_at IS NULL
          AND u.status='Active' AND u.is_show=true AND lower(r.name)='ppds' FOR UPDATE OF u")
        ->bindValues(array(':user'=>$user,':client'=>$client))->queryRow();
    if (!$ppds) throw new RuntimeException('PPDS aktif tidak ditemukan untuk client ini');
    if ($write) {
        // Also cover legacy/unbound writers which do not acquire the PPDS lock.
        // Short, conservative global write lock; never hold across network calls.
        $db->createCommand('LOCK TABLE t_logbook IN SHARE ROW EXCLUSIVE MODE')->execute();
    }
    $sessions=$db->createCommand('SELECT * FROM t_morbiditas_points_history
        WHERE id_user=:user AND id_client=:client AND ended_at IS NULL FOR UPDATE')
        ->bindValues(array(':user'=>$user,':client'=>$client))->queryAll();
    if (count($sessions)!==1) throw new RuntimeException('Harus ada tepat satu sesi aktif; muat ulang atau perbaiki riwayat sesi');
    $open=$sessions[0];
    if (!empty($open['undone_at']) || empty($open['transition_logbook_id']) || empty($open['previous_session_id'])) {
        throw new RuntimeException('Sesi aktif tidak memiliki bukti transisi semester; riwayat lama perlu ditautkan melalui audit, bukan ditebak');
    }
    if ((int)$ppds['id_semester']!==(int)$open['id_semester'] || $ppds['id_stase']!==$open['id_stase']) {
        throw new RuntimeException('Profil PPDS tidak cocok dengan sesi transisi aktif; Undo hanya untuk perubahan semester terakhir');
    }
    $newer=$db->createCommand('SELECT id FROM t_morbiditas_points_history
        WHERE id_user=:user AND id_client=:client AND id>:id AND undone_at IS NULL LIMIT 1')
        ->bindValues(array(':user'=>$user,':client'=>$client,':id'=>(int)$open['id']))->queryRow();
    if ($newer) throw new RuntimeException('Sesi aktif bukan transisi terakhir yang sah');
    $restore=$db->createCommand('SELECT * FROM t_morbiditas_points_history
        WHERE id=:id AND id_user=:user AND id_client=:client AND ended_at=:transition_started AND undone_at IS NULL FOR UPDATE')
        ->bindValues(array(':id'=>(int)$open['previous_session_id'],':user'=>$user,':client'=>$client,':transition_started'=>$open['started_at']))->queryRow();
    if (!$restore || (int)$restore['id'] >= (int)$open['id'] || (int)$restore['id_semester']===(int)$open['id_semester']) {
        throw new RuntimeException('Tidak ada sesi perubahan semester sebelumnya yang terbukti aman untuk Undo');
    }
    $target=$db->createCommand("SELECT lb.* FROM t_logbook lb JOIN m_action a ON a.id=lb.id_action AND a.id_client=lb.id_client
        WHERE lb.id=:id AND lb.id_user=:user AND lb.id_client=:client AND lb.deleted_at IS NULL
          AND lb.id_semester=:semester AND lb.id_stase IS NOT DISTINCT FROM :stase
          AND a.is_milestone=true AND a.show_on_milestone=true
          AND (lower(coalesce(a.identifier,''))='stase' OR lower(coalesce(a.name,''))='stase') FOR UPDATE OF lb")
        ->bindValues(array(':id'=>(int)$open['transition_logbook_id'],':user'=>$user,':client'=>$client,
            ':semester'=>(int)$open['id_semester'],':stase'=>$open['id_stase']))->queryRow();
    if (!$target) throw new RuntimeException('Milestone transisi aktif tidak cocok atau sudah diarsipkan');
    if (isset($post['id_logbook']) && (!is_scalar($post['id_logbook'])
        || !preg_match('/^[1-9][0-9]*$/',(string)$post['id_logbook']) || (int)$post['id_logbook']!==(int)$target['id'])) {
        throw new RuntimeException('id_logbook bukan milestone transisi aktif terakhir; Refresh status Undo');
    }
    $points=$this->calculateMorbiditasSessionPoints($db,$user,$client,(int)$open['id_semester'],$open['started_at']);
    $names=$db->createCommand('SELECT sem.name AS semester_name,st.name AS stase_name,sem.id_stage,stage.name AS stage_name
        FROM m_semester sem LEFT JOIN m_stase st ON st.id=:stase AND st.id_client=:client
        LEFT JOIN m_stage stage ON stage.id=sem.id_stage WHERE sem.id=:semester AND sem.id_client=:client')
        ->bindValues(array(':semester'=>(int)$restore['id_semester'],':stase'=>$restore['id_stase'],':client'=>$client))->queryRow();
    if (!$names || ($restore['id_stase']!==null && $names['stase_name']===null)) throw new RuntimeException('Master semester/Stase pemulihan tidak ditemukan untuk client ini');
    $currentName=$db->createCommand('SELECT name FROM m_semester WHERE id=:id AND id_client=:client')
        ->bindValues(array(':id'=>(int)$open['id_semester'],':client'=>$client))->queryScalar();
    $currentStaseName=$db->createCommand('SELECT name FROM m_stase WHERE id=:id AND id_client=:client')
        ->bindValues(array(':id'=>$open['id_stase'],':client'=>$client))->queryScalar();
    $info=array('id_user'=>$user,'display_name'=>$ppds['display_name'],'can_undo'=>$points===0,
        'reason'=>$points===0?null:'Undo dikunci: sesi sekarang sudah memiliki poin aktif.',
        'current_session_id'=>(int)$open['id'],'current_semester_id'=>(int)$open['id_semester'],
        'current_semester_name'=>$currentName,'current_stase_name'=>$currentStaseName,'current_active_points'=>$points,
        'archive_semester_name'=>$currentName,'archive_stase_name'=>$currentStaseName,
        'archive_logbook_id'=>(int)$target['id'],'archive_semester_id'=>(int)$target['id_semester'],
        'archive_stase_id'=>$target['id_stase']!==null?(int)$target['id_stase']:null,
        'archive_created_date'=>$target['created_date'],'archive_activity_date'=>$target['date'],
        'will_archive_transition'=>true,'restore_session_id'=>(int)$restore['id'],
        'restore_semester_id'=>(int)$restore['id_semester'],'restore_semester_name'=>$names['semester_name'],
        'restore_stase_id'=>$restore['id_stase']!==null?(int)$restore['id_stase']:null,'restore_stase_name'=>$names['stase_name'],
        'restore_stage_id'=>$names['id_stage'],'restore_stage_name'=>$names['stage_name'],
        // Zero, including an epoch bootstrap, is an authoritative saved snapshot.
        'restore_points'=>(int)$restore['points']);
    return array('open'=>$open,'restore'=>$restore,'target'=>$target,'info'=>$info);
}

/* Current bound sum captured at restore; later verification changes remain a delta, not a new baseline. */
private function morbiditasSemesterUndoBoundPoints($db, $user, $client, $session)
{
    return (int)$db->createCommand("SELECT COALESCE(SUM(category.points),0)::int FROM t_logbook lb
        JOIN m_action a ON a.id=lb.id_action AND a.id_client=lb.id_client
        JOIN m_action_category category ON category.id=lb.id_category
        WHERE lb.id_user=:user AND lb.id_client=:client AND lb.id_morbiditas_period=:session
          AND lb.deleted_at IS NULL
          AND (lb.verified IS TRUE OR lower(coalesce(lb.verified_status,''))='verified')
          AND (lower(coalesce(a.identifier,''))='morbiditas' OR lower(coalesce(a.name,''))='morbiditas' OR a.id=39)")
        ->bindValues(array(':user'=>(int)$user,':client'=>(int)$client,':session'=>(int)$session))->queryScalar();
}

/* REPLACE existing helper. Caller holds PPDS lock + transaction; explicit milestone link is mandatory. */
private function transitionMorbiditasPointsSession($db, $idUser, $idClient, $oldSemester, $oldStase, $newSemester, $newStase, $milestoneId)
{
    if ((int)$oldSemester===(int)$newSemester) return;
    $scope=array(':user'=>(int)$idUser,':client'=>(int)$idClient);
    $rows=$db->createCommand('SELECT * FROM t_morbiditas_points_history WHERE id_user=:user AND id_client=:client AND ended_at IS NULL FOR UPDATE')
        ->bindValues($scope)->queryAll();
    if (count($rows)>1) throw new RuntimeException('Riwayat memiliki lebih dari satu sesi aktif; transisi dibatalkan');
    $open=count($rows)?$rows[0]:null;
    if (!$open && (int)$oldSemester>0) {
        $open=$db->createCommand("INSERT INTO t_morbiditas_points_history
            (id_user,id_client,id_semester,id_stase,points,started_at) VALUES (:user,:client,:semester,:stase,0,'1970-01-01 00:00:00+00') RETURNING *")
            ->bindValues($scope+array(':semester'=>(int)$oldSemester,':stase'=>$oldStase))->queryRow();
    }
    if ($open) {
        if ((int)$open['id_semester']!==(int)$oldSemester) throw new RuntimeException('Semester profil tidak cocok dengan sesi aktif');
        $points=$this->calculateMorbiditasSessionPoints($db,$idUser,$idClient,(int)$oldSemester,$open['started_at']);
        $changed=$db->createCommand('UPDATE t_morbiditas_points_history SET points=:points,id_stase=:stase,ended_at=CURRENT_TIMESTAMP
            WHERE id=:id AND id_user=:user AND id_client=:client AND ended_at IS NULL')
            ->bindValues($scope+array(':points'=>$points,':stase'=>$oldStase,':id'=>(int)$open['id']))->execute();
        if ($changed!==1) throw new RuntimeException('Sesi lama berubah; transisi dibatalkan');
    }
    $new=$db->createCommand('INSERT INTO t_morbiditas_points_history
        (id_user,id_client,id_semester,id_stase,points,started_at,transition_logbook_id,previous_session_id)
        VALUES (:user,:client,:semester,:stase,0,CURRENT_TIMESTAMP,:milestone,:previous) RETURNING id')
        ->bindValues($scope+array(':semester'=>(int)$newSemester,':stase'=>$newStase,':milestone'=>(int)$milestoneId,
            ':previous'=>$open?(int)$open['id']:null))->queryRow();
    if (!$new) throw new RuntimeException('Sesi baru tidak tersimpan');
}

/* REPLACE existing helper. Authoritative restored zero/positive snapshots are never re-derived. */
private function calculateMorbiditasSessionPoints($db, $idUser, $idClient, $idSemester, $startedAt)
{
    // Cari sesi aktif untuk user ini
    $activeSession = $db->createCommand('SELECT id, points, restored_at, restored_after_logbook_id, restored_bound_points
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
    if ($activeSession['restored_after_logbook_id'] !== null) {
        // Snapshot remains authoritative (also zero); current bound SUM minus its
        // restore-time SUM preserves later verification changes to old pending rows.
        $boundDelta=$this->morbiditasSemesterUndoBoundPoints($db,$idUser,$idClient,$sessionId)
            -(int)$activeSession['restored_bound_points'];
        // Only newly inserted unbound legacy rows use the ID watermark.
        $delta=$db->createCommand("SELECT COALESCE(SUM(category.points),0)::int FROM t_logbook lb
            JOIN m_action a ON a.id=lb.id_action AND a.id_client=lb.id_client
            JOIN m_action_category category ON category.id=lb.id_category
            WHERE lb.id_user=:user AND lb.id_client=:client AND lb.deleted_at IS NULL
              AND lb.id>:watermark AND lb.id_morbiditas_period IS NULL AND lb.id_semester=:semester
              AND (lb.verified IS TRUE OR lower(coalesce(lb.verified_status,''))='verified')
              AND (lower(coalesce(a.identifier,''))='morbiditas' OR lower(coalesce(a.name,''))='morbiditas' OR a.id=39)")
            ->bindValues(array(':user'=>(int)$idUser,':client'=>(int)$idClient,':watermark'=>(int)$activeSession['restored_after_logbook_id'],
                ':semester'=>(int)$idSemester))->queryScalar();
        return (int)$activeSession['points']+$boundDelta+(int)$delta;
    }

    // Hitung poin hanya dari Morbiditas yang terikat ke sesi ini
    $bound = $db->createCommand("SELECT COALESCE(SUM(category.points), 0)::int AS total
        FROM t_logbook lb
        INNER JOIN m_action action ON action.id=lb.id_action AND action.id_client=lb.id_client
        INNER JOIN m_action_category category ON category.id=lb.id_category
        WHERE lb.id_morbiditas_period=:session_id AND lb.id_user=:user AND lb.id_client=:client
          AND lb.deleted_at IS NULL AND category.points IS NOT NULL
          AND (lb.verified IS TRUE OR LOWER(COALESCE(lb.verified_status, ''))='verified')
          AND (LOWER(COALESCE(action.identifier, ''))='morbiditas'
               OR LOWER(COALESCE(action.name, ''))='morbiditas' OR action.id=39)")
        ->bindValues(array(':session_id'=>$sessionId,':user'=>(int)$idUser,':client'=>(int)$idClient))->queryRow();

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
/* REPLACE existing method (Create + Edit). Preserve dates/boolean/role gates; Edit never changes profile/session. */
private function webSaveStaseSemester($post, $isEdit)
{
    header('Content-Type: application/json; charset=utf-8');
    if ($isEdit && is_array($post)) {
        $post['created_by']=$post['updated_by'] ?? null;
        if(!isset($post['id_logbook'])||!is_scalar($post['id_logbook'])||!preg_match('/^[1-9][0-9]*$/',(string)$post['id_logbook'])) {
            echo json_encode(array('status'=>false,'message'=>'id_logbook wajib berupa ID positif')); Yii::app()->end();
        }
        $post['id']=$post['id_logbook'];
    } elseif (is_array($post)) { unset($post['id']); }
    if(is_array($post)&&isset($post['date'])&&is_string($post['date'])&&preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$post['date'])) $post['date'].=' 00:00:00';
    if (!is_array($post)) {
        echo json_encode(array('status' => false, 'message' => 'Payload tidak valid'));
        Yii::app()->end();
    }

    foreach (array('created_by', 'id_client', 'id_user', 'id_stase', 'id_stage', 'id_semester', 'date') as $field) {
        if (!array_key_exists($field, $post) || $post[$field] === '' || $post[$field] === null) {
            echo json_encode(array('status' => false, 'message' => $field . ' wajib diisi'));
            Yii::app()->end();
        }
    }
    foreach (array('created_by', 'id_client', 'id_user', 'id_stase', 'id_stage', 'id_semester') as $field) {
        if (!preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('status' => false, 'message' => $field . ' harus berupa ID positif'));
            Yii::app()->end();
        }
    }
    // Interpret offsetless mobile dates as WIB, not the PHP/DB session timezone.
    // Explicit offsets (or Z) retain their instant; bind an offset on create AND edit.
    $dateInput = $post['date'];
    if (!is_string($dateInput) || !preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})[ T]([0-9]{2}):([0-9]{2}):([0-9]{2})(Z|[+-](?:(?:0[0-9]|1[0-3]):[0-5][0-9]|14:00))?\z/', $dateInput, $dateParts)) {
        echo json_encode(array('status' => false, 'message' => 'date harus berformat YYYY-MM-DD HH:mm:ss, dengan offset opsional seperti +07:00 atau Z'));
        Yii::app()->end();
        return;
    }
    if (!checkdate((int)$dateParts[2], (int)$dateParts[3], (int)$dateParts[1])
        || (int)$dateParts[4] > 23 || (int)$dateParts[5] > 59 || (int)$dateParts[6] > 59) {
        echo json_encode(array('status' => false, 'message' => 'date tidak valid'));
        Yii::app()->end();
        return;
    }
    $dateText = substr($dateInput, 0, 10) . ' ' . substr($dateInput, 11, 8);
    $dateOffset = isset($dateParts[7]) ? $dateParts[7] : '';
    $dateFormat = '!Y-m-d H:i:s';
    if ($dateOffset !== '') {
        $dateText .= $dateOffset === 'Z' ? '+00:00' : $dateOffset;
        $dateFormat .= 'P';
    }
    $parsedDate = DateTime::createFromFormat($dateFormat, $dateText, new DateTimeZone('Asia/Jakarta'));
    $dateErrors = DateTime::getLastErrors();
    if ($parsedDate === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        echo json_encode(array('status' => false, 'message' => 'date tidak valid'));
        Yii::app()->end();
        return;
    }
    $dateValue = $parsedDate->format('Y-m-d H:i:sP');

    $db = Yii::app()->db;
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
        echo json_encode(array('status' => false, 'message' => 'is_retake harus boolean'));
        Yii::app()->end();
    }

    $transaction = $db->beginTransaction();
    try {
        $this->webMorbiditasActor($db,$post);
        $ppds = $db->createCommand("SELECT u.id, u.id_semester, u.id_stase FROM m_user u JOIN m_role r ON r.id=u.id_role
            WHERE u.id=:id AND u.id_client=:client AND u.deleted_at IS NULL
              AND u.status='Active' AND u.is_show=true AND r.name='ppds' FOR UPDATE OF u")
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
            $existing = $db->createCommand('SELECT id,id_user,id_stase,id_semester FROM t_logbook WHERE id=:id AND id_client=:client AND id_action=:action AND deleted_at IS NULL FOR UPDATE')
                ->bindValues(array(':id' => $id, ':client' => $clientId, ':action' => (int)$action['id']))->queryRow();
            if (!$existing) throw new RuntimeException('Milestone Stase tidak ditemukan');
            if ((int)$existing['id_user']!==$ppdsId || (int)$existing['id_stase']!==$staseId || (int)$existing['id_semester']!==$semesterId) {
                throw new RuntimeException('Edit tidak boleh mengubah PPDS, semester, atau Stase historis; buat transisi baru atau gunakan Undo');
            }
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
            $command->bindValue(':date', $dateValue, PDO::PARAM_STR);
            $notes === null ? $command->bindValue(':notes', null, PDO::PARAM_NULL) : $command->bindValue(':notes', $notes, PDO::PARAM_STR);
            $command->bindValue(':is_retake', $isRetake, PDO::PARAM_BOOL);
            $command->bindValue(':updated_by', $actorId, PDO::PARAM_INT);
            $command->bindValue(':updated_date', $now, PDO::PARAM_STR);
            $command->bindValue(':id', $id, PDO::PARAM_INT);
            $command->bindValue(':client', $clientId, PDO::PARAM_INT);
            $changed=$command->execute();
            if ($changed!==1) throw new RuntimeException('Milestone berubah; edit dibatalkan');
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
            $command->bindValue(':date', $dateValue, PDO::PARAM_STR);
            $notes === null ? $command->bindValue(':notes', null, PDO::PARAM_NULL) : $command->bindValue(':notes', $notes, PDO::PARAM_STR);
            $command->bindValue(':is_retake', $isRetake, PDO::PARAM_BOOL);
            $command->bindValue(':created_by', $actorId, PDO::PARAM_INT);
            $command->bindValue(':created_date', $now, PDO::PARAM_STR);
            $created = $command->queryRow();
            $id = (int)$created['id'];
        }

        // Perubahan semester adalah sesi baru, baik naik maupun turun yang disengaja.
        // Snapshot poin sesi lama tetap tersimpan; sesi target selalu dimulai dari 0.
        if (!$isEdit && (int)$ppds['id_semester'] !== $semesterId) {
            $this->transitionMorbiditasPointsSession(
                $db, $ppdsId, $clientId, (int)$ppds['id_semester'],
                $ppds['id_stase'] !== null ? (int)$ppds['id_stase'] : null,
                $semesterId, $staseId, $id
            );
        }

        if (!$isEdit) {
        $updatedProfile=$db->createCommand()->update('m_user', array(
            'id_stase' => $staseId, 'id_semester' => $semesterId,
            'updated_by' => $actorId, 'updated_date' => $now,
        ), 'id=:id AND id_client=:client', array(':id' => $ppdsId, ':client' => $clientId));
        if ($updatedProfile!==1) throw new RuntimeException('Profil PPDS tidak tersimpan');
        }

        $row = $db->createCommand('SELECT * FROM t_logbook WHERE id=:id AND id_client=:client')
            ->bindValues(array(':id' => $id, ':client' => $clientId))->queryRow();
        $transaction->commit();
        echo json_encode(array('status' => true, 'message' => isset($post['id']) ? 'Milestone Stase berhasil diperbarui' : 'Milestone Stase berhasil dibuat', 'data' => $row));
    } catch (Throwable $e) {
        if ($transaction->active) $transaction->rollback();
        echo json_encode(array('status' => false, 'message' => $e->getMessage()));
    }
    Yii::app()->end();
}


    
    
    public function actionUpdateMorbiditasVerifier() {
        header('Content-Type: application/json; charset=utf-8');
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        // Validate required fields
        $required = ['id_logbook', 'status_id', 'new_id_user'];
        foreach ($required as $field) {
            if (!isset($post[$field]) || !is_numeric($post[$field])) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Parameter ' . $field . ' wajib diisi dan berupa angka!'
                ]);
                Yii::app()->end();
            }
        }

        $idLogbook  = (int) $post['id_logbook'];
        $statusId   = (int) $post['status_id'];
        $newIdUser  = (int) $post['new_id_user'];

        // 1. Verify the status row exists and belongs to the logbook
        $statusRow = Yii::app()->db->createCommand('
            SELECT
                tls.id,
                tls.id_logbook,
                tls.id_user,
                tls.status,
                tls.id_action_role,
                mar.role,
                mar.identifier AS role_identifier,
                l.id_user AS logbook_owner_id
            FROM t_logbook_status tls
            INNER JOIN m_action_role mar ON mar.id = tls.id_action_role
            INNER JOIN t_logbook l ON l.id = tls.id_logbook
            WHERE
                tls.id = :status_id
                AND tls.id_logbook = :id_logbook
                AND tls.deleted_at IS NULL
                AND l.deleted_at IS NULL
        ')
        ->bindValue(':status_id', $statusId)
        ->bindValue(':id_logbook', $idLogbook)
        ->queryRow();

        if (!$statusRow) {
            echo json_encode([
                'status' => false,
                'message' => 'Status verifikasi tidak ditemukan atau tidak sesuai dengan logbook!'
            ]);
            Yii::app()->end();
        }

        // 2. Reject if already verified
        if (strtolower($statusRow['status']) === 'verified') {
            echo json_encode([
                'status' => false,
                'message' => 'Tidak dapat mengubah verifier yang sudah berstatus verified!'
            ]);
            Yii::app()->end();
        }

        // 3. Validate the new user exists and is active
        $newUser = Yii::app()->db->createCommand('
            SELECT id, display_name, id_client, status
            FROM m_user
            WHERE
                id = :id_user
                AND deleted_at IS NULL
        ')
        ->bindValue(':id_user', $newIdUser)
        ->queryRow();

        if (!$newUser) {
            echo json_encode([
                'status' => false,
                'message' => 'User yang dipilih tidak ditemukan atau tidak aktif!'
            ]);
            Yii::app()->end();
        }

        // 4. Validate same client: get the logbook owner's client
        $logbookOwner = Yii::app()->db->createCommand('
            SELECT mu.id_client
            FROM m_user mu
            WHERE mu.id = :id_user
        ')
        ->bindValue(':id_user', $statusRow['logbook_owner_id'])
        ->queryRow();

        if ($logbookOwner && $newUser['id_client'] != $logbookOwner['id_client']) {
            echo json_encode([
                'status' => false,
                'message' => 'User yang dipilih tidak berada di client yang sama!'
            ]);
            Yii::app()->end();
        }

        // 5. Update the verifier assignment
        $rowsAffected = Yii::app()->db->createCommand('
            UPDATE t_logbook_status
            SET id_user = :new_id_user,
                date_time = NOW()
            WHERE id = :status_id
        ')
        ->bindValue(':new_id_user', $newIdUser)
        ->bindValue(':status_id', $statusId)
        ->execute();

        if ($rowsAffected > 0) {
            echo json_encode([
                'status' => true,
                'message' => 'Verifier berhasil diubah menjadi ' . $newUser['display_name'] . '!'
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'message' => 'Gagal mengubah verifier!'
            ]);
        }
    }
    
    
    public function actionGetMorbiditasUndoInfo()
{
    header('Content-Type: application/json; charset=utf-8');
    $post=json_decode(file_get_contents('php://input'),true);
    $db=Yii::app()->db; $tx=null;
    try {
        $tx=$db->beginTransaction();
        $context=$this->morbiditasSemesterUndoContext($db,$post);
        $tx->commit();
        echo json_encode(array('status'=>true,'data'=>$context['info']));
    } catch (Throwable $e) {
        if ($tx!==null && $tx->active) $tx->rollback();
        echo json_encode(array('status'=>false,'message'=>$e->getMessage()));
    }
    Yii::app()->end();
}


public function actionUndoMorbiditasStase()
{
    header('Content-Type: application/json; charset=utf-8');
    $post=json_decode(file_get_contents('php://input'),true);
    $db=Yii::app()->db; $tx=null;
    try {
        foreach (array('expected_current_session_id','expected_restore_session_id','id_logbook') as $field) {
            if (!is_array($post) || !isset($post[$field]) || !is_scalar($post[$field])
                || !preg_match('/^[1-9][0-9]*$/',(string)$post[$field])) {
                throw new RuntimeException($field.' wajib dari preview terbaru; Refresh status Undo');
            }
        }
        $tx=$db->beginTransaction();
        $context=$this->morbiditasSemesterUndoContext($db,$post,true);
        $open=$context['open']; $restore=$context['restore']; $info=$context['info'];
        if ((int)$post['expected_current_session_id']!==(int)$open['id']
            || (int)$post['expected_restore_session_id']!==(int)$restore['id']) {
            throw new RuntimeException('Sesi sudah berubah sejak preview; Refresh status Undo');
        }
        if (!$info['can_undo']) throw new RuntimeException($info['reason']);
        $scope=array(':user'=>(int)$post['id_user'],':client'=>(int)$post['id_client']);
        $changed=$db->createCommand('UPDATE t_morbiditas_points_history SET points=0,ended_at=CURRENT_TIMESTAMP,undone_at=CURRENT_TIMESTAMP
            WHERE id=:id AND id_user=:user AND id_client=:client AND ended_at IS NULL AND undone_at IS NULL')
            ->bindValues($scope+array(':id'=>(int)$open['id']))->execute();
        if ($changed!==1) throw new RuntimeException('Sesi aktif berubah; Undo dibatalkan');
        $boundAtRestore=$this->morbiditasSemesterUndoBoundPoints($db,(int)$post['id_user'],(int)$post['id_client'],(int)$restore['id']);
        $changed=$db->createCommand('UPDATE t_morbiditas_points_history SET ended_at=NULL,restored_at=CURRENT_TIMESTAMP,
            restored_after_logbook_id=(SELECT COALESCE(MAX(id),0) FROM t_logbook),restored_bound_points=:bound
            WHERE id=:id AND id_user=:user AND id_client=:client AND ended_at IS NOT NULL AND undone_at IS NULL')
            ->bindValues($scope+array(':id'=>(int)$restore['id'],':bound'=>$boundAtRestore))->execute();
        if ($changed!==1) throw new RuntimeException('Sesi pemulihan berubah; Undo dibatalkan');
        $changed=$db->createCommand('UPDATE m_user SET id_semester=:semester,id_stase=:stase,updated_by=:actor,updated_date=CURRENT_TIMESTAMP
            WHERE id=:user AND id_client=:client AND id_semester=:current_semester AND id_stase IS NOT DISTINCT FROM :current_stase AND deleted_at IS NULL')
            ->bindValues($scope+array(':semester'=>(int)$restore['id_semester'],':stase'=>$restore['id_stase'],':actor'=>(int)$post['created_by'],
                ':current_semester'=>(int)$open['id_semester'],':current_stase'=>$open['id_stase']))->execute();
        if ($changed!==1) throw new RuntimeException('Profil PPDS berubah; Undo dibatalkan');
        $changed=$db->createCommand('UPDATE t_logbook SET deleted_at=CURRENT_TIMESTAMP,updated_by=:actor,updated_date=CURRENT_TIMESTAMP
            WHERE id=:id AND id_user=:user AND id_client=:client AND deleted_at IS NULL
              AND id_semester=:semester AND id_stase IS NOT DISTINCT FROM :stase')
            ->bindValues($scope+array(':id'=>$info['archive_logbook_id'],':actor'=>(int)$post['created_by'],
                ':semester'=>(int)$open['id_semester'],':stase'=>$open['id_stase']))->execute();
        if ($changed!==1) throw new RuntimeException('Milestone transisi berubah; Undo dibatalkan');
        // Read back every mutated target before committing; no old card or child/activity row is touched.
        $check=$db->createCommand('SELECT lb.deleted_at FROM t_logbook lb WHERE lb.id=:id AND lb.id_user=:user AND lb.id_client=:client')
            ->bindValues($scope+array(':id'=>$info['archive_logbook_id']))->queryRow();
        if (!$check || empty($check['deleted_at'])) throw new RuntimeException('Arsip transisi tidak terkonfirmasi');
        $total=$this->calculateMorbiditasSessionPoints($db,(int)$post['id_user'],(int)$post['id_client'],(int)$restore['id_semester'],$restore['started_at']);
        if ($total!==(int)$restore['points']) throw new RuntimeException('Snapshot pemulihan tidak konsisten; Undo dibatalkan');
        $tx->commit();
        echo json_encode(array('status'=>true,'message'=>'Semester, Stase, dan sesi poin dipulihkan; milestone transisi terakhir diarsipkan.',
            'data'=>array('id_user'=>(int)$post['id_user'],'id_semester'=>(int)$restore['id_semester'],
                'id_stase'=>$info['restore_stase_id'],'semester_name'=>$info['restore_semester_name'],
                'stase_name'=>$info['restore_stase_name'],'id_stage'=>$info['restore_stage_id'],'stage_name'=>$info['restore_stage_name'],
                'total_points'=>$total,'active_session_id'=>(int)$restore['id'],'archived_logbook_id'=>$info['archive_logbook_id'])));
    } catch (Throwable $e) {
        if ($tx!==null && $tx->active) $tx->rollback();
        echo json_encode(array('status'=>false,'message'=>$e->getMessage()));
    }
    Yii::app()->end();
}
    // === MORBIDITY ===



    // === PENILAIAN LOGBOOK STAGE ===
    public function actionGetListPenilaianLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = 'SELECT
                    ma.id,
                    ma.name,
                    COUNT(*) FILTER (WHERE tla.id_logbook IS NULL) AS unscored,
                    COUNT(*) FILTER (WHERE tla.id_logbook IS NOT NULL) AS scored
                FROM t_logbook tl
                JOIN m_action ma
                    ON ma.id = tl.id_action
                LEFT JOIN m_action_category mac
                    ON mac.id = tl.id_category
                LEFT JOIN (
                    SELECT DISTINCT id_logbook
                    FROM t_logbook_asm
                ) tla
                    ON tla.id_logbook = tl.id
                WHERE
                    tl.deleted_at IS NULL
                    AND tl.id_client = :id_client
                    AND ma.has_score = :has_score
                    AND ma.has_score_option = :has_score_option
                    AND COALESCE(mac.required_asm, TRUE) = :required_asm
                    AND NOT EXISTS (
                        SELECT 1
                        FROM m_action_another_role maar
                        INNER JOIN m_another_role eligible_role
                            ON eligible_role.id = maar.id_another_role
                        WHERE eligible_role.id = tl.id_another_role
                            AND eligible_role.id_client = tl.id_client
                            AND maar.id_action = tl.id_action
                            AND maar.id_client = tl.id_client
                            AND maar.required_asm = FALSE
                    )
                GROUP BY
                    ma.id,
                    ma.name
                ORDER BY
                    ma.name ASC';
                    
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->bindValue(':has_score', true)
            ->bindValue(':has_score_option', true)
            ->bindValue(':required_asm', true)
            ->queryAll();

        echo json_encode([
            'status'  => true,
            'data'    => $res
        ]);
    }


    public function actionGetDetailPenilaianLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_action'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = 'SELECT
                    ma.id,
                    ma.name,
                    COUNT(*) FILTER (WHERE tla.id_logbook IS NULL) AS unscored,
                    COUNT(*) FILTER (WHERE tla.id_logbook IS NOT NULL) AS scored
                FROM t_logbook tl
                JOIN m_action ma
                    ON ma.id = tl.id_action
                LEFT JOIN m_action_category mac
                    ON mac.id = tl.id_category
                LEFT JOIN (
                    SELECT DISTINCT id_logbook
                    FROM t_logbook_asm
                ) tla
                    ON tla.id_logbook = tl.id
                WHERE
                    tl.deleted_at IS NULL
                    AND ma.id = :id_action
                    AND ma.has_score = :has_score
                    AND ma.has_score_option = :has_score_option
                    AND COALESCE(mac.required_asm, TRUE) = :required_asm
                    AND NOT EXISTS (
                        SELECT 1
                        FROM m_action_another_role maar
                        INNER JOIN m_another_role eligible_role
                            ON eligible_role.id = maar.id_another_role
                        WHERE eligible_role.id = tl.id_another_role
                            AND eligible_role.id_client = tl.id_client
                            AND maar.id_action = tl.id_action
                            AND maar.id_client = tl.id_client
                            AND maar.required_asm = FALSE
                    )
                GROUP BY
                    ma.id,
                    ma.name
                ORDER BY
                    ma.name ASC';
                    
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_action', $post['id_action'])
            ->bindValue(':has_score', true)
            ->bindValue(':has_score_option', true)
            ->bindValue(':required_asm', true)
            ->queryRow();

        echo json_encode([
            'status'  => true,
            'data'    => $res
        ]);
    }


    public function actionGetListByStatusPenilaianLogbook()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (
            !isset($post['id_action']) ||
            !isset($post['type'])
        ) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $page   = isset($post['page']) ? (int)$post['page'] : 1;
        $limit  = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default DESC - newest first)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc') ? 'ASC' : 'DESC';

        $baseCte = "
            WITH asm_scores AS (
                SELECT
                    tla.id_logbook,
                    map.name AS asm_param,
                    AVG(tla.score) AS avg_score
                FROM t_logbook_asm tla
                INNER JOIN m_asm_param map
                    ON map.id = tla.id_asm_param
                GROUP BY
                    tla.id_logbook,
                    map.name
            ),
            total_scores AS (
                SELECT
                    id_logbook,
                    AVG(avg_score) AS total_score
                FROM asm_scores
                GROUP BY id_logbook
            ),
            staff_ids_cte AS (
                SELECT
                    tls.id_logbook,
                    ARRAY_AGG(DISTINCT tls.id_user) AS staff_ids,
                    ARRAY_AGG(DISTINCT mu.display_name) FILTER (WHERE mar.role != 'Peserta') AS staff_names
                FROM t_logbook_status tls
                INNER JOIN m_action_role mar
                    ON mar.id = tls.id_action_role
                INNER JOIN m_user mu
                    ON mu.id = tls.id_user
                WHERE mar.role != 'Peserta'
                GROUP BY tls.id_logbook
            )
        ";

        $baseWhere = "
            FROM t_logbook tl
            INNER JOIN m_user mu
                ON mu.id = tl.id_user
            INNER JOIN m_action ma
                ON ma.id = tl.id_action
            LEFT JOIN m_semester ms
                ON ms.id = tl.id_semester
            LEFT JOIN m_stase mst
                ON mst.id = tl.id_stase
            LEFT JOIN m_stage mstage
                ON mstage.id = mst.id_stage
            LEFT JOIN m_another_role mar
                ON mar.id = tl.id_another_role
            LEFT JOIN m_action_category mac
                ON mac.id = tl.id_category
            LEFT JOIN asm_scores asm
                ON asm.id_logbook = tl.id
            LEFT JOIN total_scores ts
                ON ts.id_logbook = tl.id
            LEFT JOIN staff_ids_cte sic
                ON sic.id_logbook = tl.id
            WHERE
                tl.deleted_at IS NULL
                AND tl.id_action = :id_action
                AND COALESCE(mac.required_asm, TRUE) = TRUE
                    AND NOT EXISTS (
                        SELECT 1
                        FROM m_action_another_role maar
                        INNER JOIN m_another_role eligible_role
                            ON eligible_role.id = maar.id_another_role
                        WHERE eligible_role.id = tl.id_another_role
                            AND eligible_role.id_client = tl.id_client
                            AND maar.id_action = tl.id_action
                            AND maar.id_client = tl.id_client
                            AND maar.required_asm = FALSE
                    )
                AND (
                    (:type = 'scored' AND ts.id_logbook IS NOT NULL)
                    OR
                    (:type = 'unscored' AND ts.id_logbook IS NULL)
                )
        ";

        $sql = "
            {$baseCte}
            SELECT
                tl.id,
                tl.date,
                tl.title,

                mu.display_name AS ppds_name,
                mu.code,
                mu.inisial_code,

                ms.name AS semester_name,
                mst.name AS stase_name,
                mstage.name AS stage_name,

                ma.name AS action_name,
                mar.role_name,
                mac.name AS category,

                ROUND(
                    MAX(
                        CASE
                            WHEN asm.asm_param = 'Psikomotor'
                            THEN asm.avg_score
                        END
                    )::numeric,
                    2
                ) AS psikomotor,

                ROUND(
                    MAX(
                        CASE
                            WHEN asm.asm_param = 'Knowledge'
                            THEN asm.avg_score
                        END
                    )::numeric,
                    2
                ) AS knowledge,

                ROUND(
                    MAX(
                        CASE
                            WHEN asm.asm_param = 'Afektif'
                            THEN asm.avg_score
                        END
                    )::numeric,
                    2
                ) AS afektif,

                ROUND(ts.total_score::numeric, 2) AS total

            {$baseWhere}
        ";

        $countSql = "
            {$baseCte}
            SELECT COUNT(DISTINCT tl.id)
            {$baseWhere}
        ";

        $params = [
            ':id_action' => $post['id_action'],
            ':type'      => $post['type'],
        ];

        if (!empty($post['id_ppds'])) {
            $sql .= ' AND tl.id_user = :id_ppds';
            $countSql .= ' AND tl.id_user = :id_ppds';
            $params[':id_ppds'] = $post['id_ppds'];
        }

        if (!empty($post['id_staff'])) {
            $sql .= ' AND :id_staff = ANY(sic.staff_ids)';
            $countSql .= ' AND :id_staff = ANY(sic.staff_ids)';
            $params[':id_staff'] = $post['id_staff'];
        }

        if (!empty($post['id_stase'])) {
            $sql .= ' AND tl.id_stase = :id_stase';
            $countSql .= ' AND tl.id_stase = :id_stase';
            $params[':id_stase'] = $post['id_stase'];
        }

        if (!empty($post['start_date'])) {
            $sql .= ' AND tl.date >= :start_date';
            $countSql .= ' AND tl.date >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }

        if (!empty($post['end_date'])) {
            $sql .= ' AND tl.date < :end_date';
            $countSql .= ' AND tl.date < :end_date';
                        $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );;
        }

        // 🔥 search filter - ILIKE across multiple fields + staff search
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            if (is_numeric($post['search'])) {
                $sql      .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
                $countSql .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
            } else {
                $sql      .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR mu.inisial_code ILIKE :search
                    OR ms.name ILIKE :search
                    OR mst.name ILIKE :search
                    OR mstage.name ILIKE :search
                    OR ma.name ILIKE :search
                    OR mar.role_name ILIKE :search
                    OR mac.name ILIKE :search
                    OR tl.title ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
                $countSql .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR mu.inisial_code ILIKE :search
                    OR ms.name ILIKE :search
                    OR mst.name ILIKE :search
                    OR mstage.name ILIKE :search
                    OR ma.name ILIKE :search
                    OR mar.role_name ILIKE :search
                    OR mac.name ILIKE :search
                    OR tl.title ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
            }
            $params[':search'] = $searchTerm;
        }

        $sql .= "
            GROUP BY
                tl.id,
                tl.date,
                tl.title,
                tl.verified_status,
                mu.display_name,
                mu.code,
                mu.inisial_code,
                ms.name,
                mst.name,
                mstage.name,
                ma.name,
                mar.role_name,
                mac.name,
                sic.staff_ids,
                ts.total_score
            ORDER BY
                tl.date
                {$sort}
            LIMIT :limit
            OFFSET :offset
        ";

        $command = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
            $countCommand->bindValue($key, $value);
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $data  = $command->queryAll();
        $total = (int)$countCommand->queryScalar();

        // Query staff data separately and merge
        if (!empty($data)) {
            $logbookIds = array_column($data, 'id');

            $staffSql = "
                SELECT
                    tls.id_logbook,
                    tls.id_user AS id,
                    mu.display_name AS name
                FROM t_logbook_status tls
                INNER JOIN m_action_role mar
                    ON mar.id = tls.id_action_role
                INNER JOIN m_user mu
                    ON mu.id = tls.id_user
                WHERE tls.id_logbook IN (" . implode(',', $logbookIds) . ")
                    AND mar.role != 'Peserta'
                ORDER BY
                    tls.id_logbook,
                    mu.display_name
            ";
            $staffCommand = Yii::app()->db->createCommand($staffSql);
            $staffData = $staffCommand->queryAll();

            // Group staff by logbook_id
            $staffByLogbook = [];
            foreach ($staffData as $staff) {
                $idLogbook = $staff['id_logbook'];
                if (!isset($staffByLogbook[$idLogbook])) {
                    $staffByLogbook[$idLogbook] = [];
                }
                $staffByLogbook[$idLogbook][] = [
                    'id' => $staff['id'],
                    'name' => $staff['name'],
                ];
            }

            // Merge staff data into result
            foreach ($data as &$row) {
                $row['staff'] = $staffByLogbook[$row['id']] ?? [];
            }
        } else {
            foreach ($data as &$row) {
                $row['staff'] = [];
            }
        }

        echo json_encode([
            'status'     => true,
            'total'      => $total,
            'data'       => $data,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit
            ],
        ]);
    }


    public function actionGetDetailByStatusPenilaianLogbook()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_logbook'])) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            WITH asm_scores AS (
                SELECT
                    tla.id_logbook,
                    map.name AS asm_param,
                    ROUND(AVG(tla.score)::numeric, 2) AS avg_score
                FROM t_logbook_asm tla
                INNER JOIN m_asm_param map
                    ON map.id = tla.id_asm_param
                WHERE tla.id_logbook = :id_logbook
                GROUP BY
                    tla.id_logbook,
                    map.name
            ),
            total_scores AS (
                SELECT
                    id_logbook,
                    ROUND(AVG(avg_score)::numeric, 2) AS total_score
                FROM asm_scores
                GROUP BY id_logbook
            )
            SELECT
                tl.id,
                tl.date,
                tl.title,
                tl.notes,

                mu.display_name AS ppds_name,
                mu.code,
                mu.inisial_code,
                mu.email,
                mu.phone,

                ms.name AS semester_name,
                mst.name AS stase_name,
                mstage.name AS stage_name,

                ma.name AS action_name,
                mar.role_name,
                mac.name AS category,

                ROUND(
                    MAX(
                        CASE
                            WHEN asm.asm_param = 'Psikomotor'
                            THEN asm.avg_score
                        END
                    )::numeric,
                    2
                ) AS psikomotor,

                ROUND(
                    MAX(
                        CASE
                            WHEN asm.asm_param = 'Knowledge'
                            THEN asm.avg_score
                        END
                    )::numeric,
                    2
                ) AS knowledge,

                ROUND(
                    MAX(
                        CASE
                            WHEN asm.asm_param = 'Afektif'
                            THEN asm.avg_score
                        END
                    )::numeric,
                    2
                ) AS afektif,

                ROUND(ts.total_score::numeric, 2) AS total
            FROM t_logbook tl
            INNER JOIN m_user mu
                ON mu.id = tl.id_user
            INNER JOIN m_action ma
                ON ma.id = tl.id_action
            LEFT JOIN m_semester ms
                ON ms.id = tl.id_semester
            LEFT JOIN m_stase mst
                ON mst.id = tl.id_stase
            LEFT JOIN m_stage mstage
                ON mstage.id = mst.id_stage
            LEFT JOIN m_hospital mh
                ON mh.id = tl.id_hospital
            LEFT JOIN m_another_role mar
                ON mar.id = tl.id_another_role
            LEFT JOIN m_action_category mac
                ON mac.id = tl.id_category
            LEFT JOIN asm_scores asm
                ON asm.id_logbook = tl.id
            LEFT JOIN total_scores ts
                ON ts.id_logbook = tl.id
            WHERE
                tl.id = :id_logbook
                AND tl.deleted_at IS NULL
            GROUP BY
                tl.id,
                tl.created_date,
                mu.id,
                ma.id,
                ms.id,
                mst.id,
                mstage.id,
                mh.id,
                mar.id,
                mac.id,
                ts.total_score
            LIMIT 1
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_logbook', $post['id_logbook']);
        $data = $command->queryRow();

        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Logbook not found'
            ]);
            Yii::app()->end();
        }

        // Query staff separately to avoid JSON in GROUP BY issue
        $staffSql = "
            SELECT
                mu.display_name AS name,
                mar.role AS role,
                tls.status
            FROM t_logbook_status tls
            INNER JOIN m_action_role mar
                ON mar.id = tls.id_action_role
            INNER JOIN m_user mu
                ON mu.id = tls.id_user
            WHERE
                tls.id_logbook = :id_logbook
                AND mar.role != 'Peserta'
            ORDER BY mu.display_name
        ";
        $staffCommand = Yii::app()->db->createCommand($staffSql);
        $staffCommand->bindValue(':id_logbook', $post['id_logbook']);
        $staffData = $staffCommand->queryAll();

        $data['staff'] = $staffData;

        echo json_encode([
            'status' => true,
            'data'   => $data
        ]);
    }
    // === PENILAIAN LOGBOOK STAGE ===

    

    // === STASE STAGE ===
    public function actionGetListStase()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $page   = isset($post['page']) ? (int)$post['page'] : 1;
        $limit  = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $action = MAction::model()->findByAttributes([
                "id_client" => $post['id_client'],
                "name"      => "Stase"
            ]);

        if (!$action) {
            echo json_encode([
                'status'  => false,
                'message' => 'Action Not Found'
            ]);
            Yii::app()->end();
        }
        
        $params = [
            ':id_client' => $post['id_client'],
            ':id_action' => $action->id
        ];

        $whereClause = "
            WHERE
                tl.deleted_at IS NULL
                AND tl.id_client = :id_client
                AND tl.id_semester IS NOT NULL
                AND tl.id_action = :id_action
        ";

        if (!empty($post['id_ppds'])) {
            $whereClause .= ' AND tl.id_user = :id_ppds';
            $params[':id_ppds'] = $post['id_ppds'];
        }

        if (!empty($post['id_stase'])) {
            $whereClause .= ' AND tl.id_stase = :id_stase';
            $params[':id_stase'] = $post['id_stase'];
        }

        if (!empty($post['start_date'])) {
            $whereClause .= ' AND tl.date >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }

        if (!empty($post['end_date'])) {
            $whereClause .= ' AND tl.date < :end_date';
                        $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );;
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $whereClause .= ' AND (
                mu.display_name ILIKE :search
                OR ms.name ILIKE :search
                OR tl.notes ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        $sql = "
            SELECT
                tl.id,
                mu.display_name AS user_name,
                ms.name AS stase_name,
                tl.date,
                tl.notes,
                tl.is_retake
            FROM t_logbook tl
            LEFT JOIN m_user mu ON mu.id = tl.id_user
            LEFT JOIN m_stase ms ON ms.id = tl.id_stase
            {$whereClause}
            ORDER BY tl.created_date DESC
            LIMIT :limit
            OFFSET :offset
        ";

        $countSql = "
            SELECT COUNT(*)
            FROM t_logbook tl
            LEFT JOIN m_user mu ON mu.id = tl.id_user
            LEFT JOIN m_stase ms ON ms.id = tl.id_stase
            {$whereClause}
        ";

        $command = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
            $countCommand->bindValue($key, $value);
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $data  = $command->queryAll();
        $total = (int)$countCommand->queryScalar();

        echo json_encode([
            'status'     => true,
            'total'      => $total,
            'data'       => $data,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit
            ],
        ]);
    }

    public function actionGetDetailStase()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_logbook'])) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                tl.id,
                tl.id_user,
                tl.id_stase,
                tl.id_semester,
                tl.date,
                tl.notes,
                tl.is_retake,
                ms.id_stage
            FROM t_logbook tl
            LEFT JOIN m_stase ms ON ms.id = tl.id_stase
            WHERE
                tl.id = :id_logbook
            AND tl.deleted_at IS NULL
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_logbook', $post['id_logbook']);
        $data = $command->queryRow();

        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data not found'
            ]);
            Yii::app()->end();
        }

        echo json_encode([
            'status' => true,
            'data'   => $data
        ]);
    }

    public function actionRemoveStase()
{
    header('Content-Type: application/json; charset=utf-8');
    $post = json_decode(file_get_contents('php://input'), true);
    foreach (array('id_logbook', 'created_by', 'id_client') as $field) {
        if (!is_array($post) || !isset($post[$field]) || !preg_match('/^[1-9][0-9]*$/', (string)$post[$field])) {
            echo json_encode(array('status' => false, 'message' => $field . ' wajib berupa ID positif'));
            Yii::app()->end();
        }
    }

    $db = Yii::app()->db;
    $id = (int)$post['id_logbook'];
    $actorId = (int)$post['created_by'];
    $clientId = (int)$post['id_client'];
    $transaction = $db->beginTransaction();
    try {
        $this->webMorbiditasActor($db,$post);
        $owner=$db->createCommand('SELECT id_user FROM t_logbook WHERE id=:id AND id_client=:client AND deleted_at IS NULL')
            ->bindValues(array(':id'=>$id,':client'=>$clientId))->queryRow();
        if (!$owner) throw new RuntimeException('Milestone Stase tidak ditemukan');
        $locked=$db->createCommand('SELECT id FROM m_user WHERE id=:user AND id_client=:client AND deleted_at IS NULL FOR UPDATE')
            ->bindValues(array(':user'=>(int)$owner['id_user'],':client'=>$clientId))->queryRow();
        if (!$locked) throw new RuntimeException('PPDS tidak ditemukan');
        $linked=$db->createCommand('SELECT id FROM t_morbiditas_points_history WHERE transition_logbook_id=:id AND id_user=:user AND id_client=:client LIMIT 1')
            ->bindValues(array(':id'=>$id,':user'=>(int)$owner['id_user'],':client'=>$clientId))->queryRow();
        if ($linked) throw new RuntimeException('Milestone pembentuk sesi tidak boleh dihapus terpisah; gunakan Undo semester');
        $milestone = $db->createCommand("SELECT lb.id FROM t_logbook lb
            JOIN m_action a ON a.id=lb.id_action AND a.id_client=lb.id_client
            WHERE lb.id=:id AND lb.id_client=:client AND lb.deleted_at IS NULL
              AND a.is_milestone=true AND a.show_on_milestone=true
              AND (lower(a.identifier)='stase' OR lower(a.name)='stase')
            FOR UPDATE OF lb")
            ->bindValues(array(':id' => $id, ':client' => $clientId))->queryRow();
        if (!$milestone) throw new RuntimeException('Milestone Stase tidak ditemukan');

        $archived=$db->createCommand()->update('t_logbook', array(
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actorId,
            'updated_date' => date('Y-m-d H:i:s'),
        ), 'id=:id AND id_client=:client AND deleted_at IS NULL', array(':id' => $id, ':client' => $clientId));
        if ($archived!==1) throw new RuntimeException('Milestone berubah; arsip dibatalkan');
        $transaction->commit();
        echo json_encode(array('status' => true, 'message' => 'Milestone Stase berhasil diarsipkan', 'data' => array('id' => $id)));
    } catch (Throwable $e) {
        if ($transaction->active) $transaction->rollback();
        echo json_encode(array('status' => false, 'message' => $e->getMessage()));
    }
    Yii::app()->end();
}

    public function actionCreateStase()
{
    $post=json_decode(file_get_contents('php://input'),true);
    $this->webSaveStaseSemester($post,false);
}



    public function actionUpdateStase()
{
    $post=json_decode(file_get_contents('php://input'),true);
    $this->webSaveStaseSemester($post,true);
}
    // === STASE STAGE ===



    // === DASHBOARD STAGE===
    public function actionGetDashboardKinerjaDPJP() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                mu.id,
                mu.display_name,
                mu.picture,
                tls.date_time,
                tls.status,
                tls.id_logbook
            FROM m_user mu
            INNER JOIN m_role mr ON mr.id = mu.id_role
            INNER JOIN t_logbook_status tls ON tls.id_user = mu.id
            WHERE mu.id_client = :id_client
                AND mu.deleted_at IS NULL
                AND mu.is_show = true
                AND mr.name = 'staff'
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $data = $command->queryAll();

        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
    }

    public function actionGetDashboardKinerjaPPDS() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                mu.id,
                mu.display_name,
                mu.picture,
                ms.name AS stase_name,
                tl.id AS logbook_id,
                tl.date,
                tl.verified_status,
                ma.identifier
            FROM m_user mu
            INNER JOIN m_role mr ON mr.id = mu.id_role
            LEFT JOIN m_stase ms ON ms.id = mu.id_stase
            INNER JOIN t_logbook tl ON tl.id_user = mu.id
            INNER JOIN m_action ma ON ma.id = tl.id_action
            WHERE mu.id_client = :id_client
                AND mu.deleted_at IS NULL
                AND mu.is_show = true
                AND mr.name = 'ppds'
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $data = $command->queryAll();

        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
    }

    public function actionGetDashboardPpdsBaru() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                mu.id,
                mu.display_name,
                mu.created_date
            FROM m_user mu
            INNER JOIN m_role mr ON mr.id = mu.id_role
            WHERE mu.id_client = :id_client
                AND mu.deleted_at IS NULL
                AND mr.name = 'ppds'
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $data = $command->queryAll();

        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
    }

    public function actionGetDashboardPPDS() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                mu.id,
                mu.display_name,
                mu.status,
                mu.is_show,
                ms.name AS stase_name,
                mst.name AS stage_name,
                mr.name AS role_name
            FROM m_user mu
            INNER JOIN m_role mr ON mr.id = mu.id_role
            LEFT JOIN m_stase ms ON ms.id = mu.id_stase
            LEFT JOIN m_stage mst ON mst.id = ms.id_stage
            WHERE mu.id_client = :id_client
                AND mu.deleted_at IS NULL
                -- AND mu.is_show    = :is_show
                AND mr.name IN ('ppds', 'staff')
        ";
        
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        // $command->bindValue(':is_show', true);
        $data = $command->queryAll();

        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
    }

    public function actionGetDashboardWaitingVerification() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        // Get logbook IDs with pending/revised status
        $sql = "
            SELECT DISTINCT tls.id_logbook
            FROM t_logbook_status tls
            INNER JOIN t_logbook tl ON tl.id = tls.id_logbook
            INNER JOIN m_action ma ON ma.id = tl.id_action
            WHERE tls.id_client = :id_client
                AND tls.status IN ('pending', 'revised')
                AND tl.verified = false
                AND ma.identifier NOT IN ('exam', 'stase')
                AND tls.deleted_at IS NULL
                AND tl.deleted_at IS NULL
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $logbookIds = $command->queryColumn();

        if (empty($logbookIds)) {
            echo json_encode([
                'status' => true,
                'data' => []
            ]);
            Yii::app()->end();
        }

        // Get logbooks with details
        $inParams = [];
        foreach ($logbookIds as $index => $id) {
            $inParams[":logbook_id_{$index}"] = $id;
        }
        $inClause = implode(',', array_keys($inParams));

        $sql2 = "
            SELECT
                tl.id,
                tl.id_action,
                ma.name AS action_name,
                mu.display_name AS ppds_name,
                tl.date,
                tls_staff.id AS tls_id,
                tls_staff.status AS tls_status,
                mu_staff.display_name AS staff_name
            FROM t_logbook tl
            INNER JOIN m_action ma ON ma.id = tl.id_action
            INNER JOIN m_user mu ON mu.id = tl.id_user
            INNER JOIN t_logbook_status tls_staff ON tls_staff.id_logbook = tl.id
            INNER JOIN m_user mu_staff ON mu_staff.id = tls_staff.id_user
            WHERE tl.id IN ({$inClause})
                AND tl.id_client = :id_client
                AND tls_staff.status IN ('pending', 'revised')
                AND tls_staff.deleted_at IS NULL
            ORDER BY tl.date DESC
        ";

        $command2 = Yii::app()->db->createCommand($sql2);
        foreach ($inParams as $key => $value) {
            $command2->bindValue($key, $value);
        }
        $command2->bindValue(':id_client', $post['id_client']);
        $data = $command2->queryAll();

        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
    }

    public function actionGetDashboardLogbookByStatus() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                verified_status AS status,
                COUNT(*) AS count
            FROM t_logbook tl
            INNER JOIN m_action ma ON ma.id = tl.id_action
            WHERE tl.id_client = :id_client
                AND tl.deleted_at IS NULL
                AND ma.identifier != 'stase'
                AND tl.verified_status != 'approved'
            GROUP BY verified_status
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $data = $command->queryAll();

        echo json_encode([
            'status' => true,
            'data' => $data
        ]);
    }

    public function actionGetDashboardStageCount() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "SELECT COUNT(*) AS count FROM m_stage WHERE id_client = :id_client";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $result = $command->queryRow();

        echo json_encode([
            'status' => true,
            'data' => ['count' => (int)$result['count']]
        ]);
    }

    public function actionGetDashboardActionCount() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "SELECT COUNT(*) AS count FROM m_action WHERE id_client = :id_client";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $result = $command->queryRow();

        echo json_encode([
            'status' => true,
            'data' => ['count' => (int)$result['count']]
        ]);
    }

    public function actionGetDashboardLogActivity()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $page = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 20;
        $offset = ($page - 1) * $limit;
    
        $sql = "
            SELECT
                n.id AS id_notifikasi,
                n.message AS log_aktivitas,
                n.type AS tipe,
                n.date AT TIME ZONE 'Asia/Bangkok' AS tanggal_aktivitas,
                u.display_name AS user_terkait,
                r.name AS role_user,
                n.url AS url_tujuan,
                n.read AS sudah_dibaca
            FROM t_notif n
            LEFT JOIN m_user u
                ON u.id = n.id_user
            LEFT JOIN m_role r
                ON r.id = u.id_role
            WHERE
                n.id_client = :id_client
                AND n.deleted_at IS NULL
            ORDER BY
                n.date DESC,
                n.id DESC
            LIMIT :limit
            OFFSET :offset
        ";
    
        $countSql = "
            SELECT COUNT(*)
            FROM t_notif n
            WHERE
                n.id_client = :id_client
                AND n.deleted_at IS NULL
        ";
    
        $params = [
            ':id_client' => $post['id_client'],
            ':limit' => $limit,
            ':offset' => $offset
        ];
    
        $command = Yii::app()->db->createCommand($sql);
    
        foreach ($params as $key => $value) {
            $command->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    
        $data = $command->queryAll();
    
        $countCommand = Yii::app()->db->createCommand($countSql);
        $countCommand->bindValue(':id_client', $post['id_client']);
    
        $total = (int)$countCommand->queryScalar();
        $pageCount = $limit > 0 ? (int)ceil($total / $limit) : 0;
    
        echo json_encode([
            'status' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pageCount' => $pageCount
            ]
        ]);
    }

    public function actionGetListUnverifiedLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_client'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        // pagination default
        $page  = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default DESC)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc') ? 'ASC' : 'DESC';

        $baseCte = "
            WITH staff_ids_cte AS (
                SELECT
                    tls.id_logbook,
                    ARRAY_AGG(DISTINCT tls.id_user) AS staff_ids,
                    ARRAY_AGG(DISTINCT mu.display_name) FILTER (WHERE mar.role != 'Peserta') AS staff_names
                FROM t_logbook_status tls
                INNER JOIN m_action_role mar
                    ON mar.id = tls.id_action_role
                INNER JOIN m_user mu
                    ON mu.id = tls.id_user
                WHERE mar.role != 'Peserta'
                GROUP BY tls.id_logbook
            )
        ";

        $sql = "{$baseCte}
            SELECT
                tl.id,
                tl.date,
                tl.title,
                tl.notes,
                tl.verified_status,
                mu.display_name AS ppds_name,
                mu.code AS nim,
                ma.name AS action_name,
                mh.name AS hospital_name,
                ms.name AS semester,
                st.name AS stase_name
            FROM t_logbook tl
            LEFT JOIN m_user mu ON tl.id_user = mu.id
            LEFT JOIN m_action ma ON tl.id_action = ma.id
            LEFT JOIN m_hospital mh ON tl.id_hospital = mh.id
            LEFT JOIN m_semester ms ON tl.id_semester = ms.id
            LEFT JOIN m_stase st ON tl.id_stase = st.id
            LEFT JOIN staff_ids_cte sic ON sic.id_logbook = tl.id
            WHERE
                tl.id_client = :id_client
            AND tl.deleted_at IS NULL
            AND tl.verified_status = :verified_status
            AND ma.identifier != :identifier";

        $countSql = "{$baseCte}
            SELECT COUNT(DISTINCT tl.id)
            FROM t_logbook tl
            LEFT JOIN m_user mu ON tl.id_user = mu.id
            LEFT JOIN m_action ma ON tl.id_action = ma.id
            LEFT JOIN m_hospital mh ON tl.id_hospital = mh.id
            LEFT JOIN m_semester ms ON tl.id_semester = ms.id
            LEFT JOIN m_stase st ON tl.id_stase = st.id
            LEFT JOIN staff_ids_cte sic ON sic.id_logbook = tl.id
            WHERE
                tl.id_client = :id_client
            AND tl.deleted_at IS NULL
            AND tl.verified_status = :verified_status
            AND ma.identifier != :identifier";

        $params = [
            ':id_client' => $post['id_client'],
            ':verified_status' => "pending",
            ':identifier' => "stase",
        ];

        // optional filter
        if (!empty($post['id_ppds'])) {
            $sql      .= ' AND tl.id_user = :id_ppds';
            $countSql .= ' AND tl.id_user = :id_ppds';
            $params[':id_ppds'] = $post['id_ppds'];
        }

        if (!empty($post['id_staff'])) {
            $sql      .= ' AND :id_staff = ANY(sic.staff_ids)';
            $countSql .= ' AND :id_staff = ANY(sic.staff_ids)';
            $params[':id_staff'] = $post['id_staff'];
        }

        if (!empty($post['id_activity'])) {
            $sql      .= ' AND tl.id_action = :id_activity';
            $countSql .= ' AND tl.id_action = :id_activity';
            $params[':id_activity'] = $post['id_activity'];
        }

        if (!empty($post['id_stase'])) {
            $sql      .= ' AND tl.id_stase = :id_stase';
            $countSql .= ' AND tl.id_stase = :id_stase';
            $params[':id_stase'] = $post['id_stase'];
        }

        if (!empty($post['start_date'])) {
            $sql      .= ' AND tl.date >= :start_date';
            $countSql .= ' AND tl.date >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND tl.date < :end_date';
            $countSql .= ' AND tl.date < :end_date';
                        $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );;
        }

        if (!empty($post['status'])) {
            $sql      .= ' AND tl.verified_status = :status';
            $countSql .= ' AND tl.verified_status = :status';
            $params[':status'] = $post['status'];
        }

        // 🔥 search filter - ILIKE across multiple fields + staff search
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            // Check if search is numeric (staff ID) or text (staff name)
            if (is_numeric($post['search'])) {
                // Numeric: search by staff ID in staff_ids array
                $sql      .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
                $countSql .= ' AND CAST(:search AS integer) = ANY(sic.staff_ids)';
            } else {
                // Text: search by staff name in staff_names array + other fields
                $sql      .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR tl.title ILIKE :search
                    OR tl.notes ILIKE :search
                    OR ma.name ILIKE :search
                    OR mh.name ILIKE :search
                    OR st.name ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
                $countSql .= ' AND (
                    mu.display_name ILIKE :search
                    OR mu.code ILIKE :search
                    OR tl.title ILIKE :search
                    OR tl.notes ILIKE :search
                    OR ma.name ILIKE :search
                    OR mh.name ILIKE :search
                    OR st.name ILIKE :search
                    OR EXISTS (SELECT 1 FROM unnest(sic.staff_names) AS sn WHERE sn ILIKE :search)
                )';
            }
            $params[':search'] = $searchTerm;
        }

        // sorting + pagination
        $sql .= " ORDER BY
                    tl.date
                    $sort
                LIMIT :limit
                OFFSET :offset";

        $command      = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $val) {
            $command->bindValue($key, $val);
            // $countCommand->bindValue($key, $val);
            if ($key !== ':role_action') {
                $countCommand->bindValue($key, $val);
            }
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $data   = $command->queryAll();
        $total = $countCommand->queryScalar();

        // Query staff data separately and merge
        if (!empty($data)) {
            $logbookIds = array_column($data, 'id');

            $staffSql = "
                SELECT
                    tls.id_logbook,
                    tls.id_user AS id,
                    mu.display_name AS name
                FROM t_logbook_status tls
                INNER JOIN m_action_role mar
                    ON mar.id = tls.id_action_role
                INNER JOIN m_user mu
                    ON mu.id = tls.id_user
                WHERE tls.id_logbook IN (" . implode(',', $logbookIds) . ")
                    AND mar.role != 'Peserta'
                ORDER BY
                    tls.id_logbook,
                    mu.display_name
            ";
            $staffCommand = Yii::app()->db->createCommand($staffSql);
            $staffData = $staffCommand->queryAll();

            // Group staff by logbook_id
            $staffByLogbook = [];
            foreach ($staffData as $staff) {
                $idLogbook = $staff['id_logbook'];
                if (!isset($staffByLogbook[$idLogbook])) {
                    $staffByLogbook[$idLogbook] = [];
                }
                $staffByLogbook[$idLogbook][] = [
                    'id' => $staff['id'],
                    'name' => $staff['name'],
                ];
            }

            // Merge staff data into result
            foreach ($data as &$row) {
                $row['staff'] = $staffByLogbook[$row['id']] ?? [];
            }
        } else {
            foreach ($data as &$row) {
                $row['staff'] = [];
            }
        }

        echo json_encode([
            'status' => true,
            'total'  => (int)$total,
            'data'   => $data,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
            ]
        ]);
    }

    public function actionGetDetailUnverifiedLogbook() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_logbook'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                tl.id,
                mu.display_name AS ppds_name,
                mu.code AS nim,
                mu.inisial_code,
                tl.date,
                tl.notes,
                tl.verified_status AS status_logbook,
                mh.name AS hospital_name,
                ma.name AS action_name
            FROM t_logbook tl
            LEFT JOIN m_user mu
                ON mu.id = tl.id_user
            LEFT JOIN m_action ma
                ON ma.id = tl.id_action
            LEFT JOIN m_hospital mh
                ON mh.id = tl.id_hospital
            WHERE
                tl.id = :id_logbook
                AND tl.deleted_at IS NULL
            LIMIT 1
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_logbook', $post['id_logbook']);
        $data = $command->queryRow();

        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Logbook not found'
            ]);
            Yii::app()->end();
        }

        // Query staff separately to get all verifying staff
        $staffSql = "
            SELECT
                mu.display_name AS name,
                mar.role AS role,
                tls.status AS status
            FROM t_logbook_status tls
            INNER JOIN m_action_role mar
                ON mar.id = tls.id_action_role
            INNER JOIN m_user mu
                ON mu.id = tls.id_user
            WHERE
                tls.id_logbook = :id_logbook
                AND mar.role != 'Peserta'
            ORDER BY mu.display_name
        ";
        $staffCommand = Yii::app()->db->createCommand($staffSql);
        $staffCommand->bindValue(':id_logbook', $post['id_logbook']);
        $staffData = $staffCommand->queryAll();

        $data['staff'] = $staffData;

        echo json_encode([
            'status' => true,
            'data'   => $data
        ]);
    }
    // === DASHBOARD STAGE ===



    // === REKAP STAGE ===
    public function actionGetListRekapReport()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            Yii::app()->end();
        }
    
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        $page   = isset($post['page']) ? (int)$post['page'] : 1;
        $limit  = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc')
            ? 'ASC'
            : 'DESC';
    
        $sql = "
            SELECT
                u.id,
                u.display_name AS name,
                'Active' AS status,
                'ppds' AS role,
                s.name AS semester,
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Kegiatan Jaga / IGD / Emergency'
                ) AS \"jaga_igd_emergency\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Ilmiah Stase'
                ) AS \"ilmiah_stase\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Kegiatan Poli Klinik'
                ) AS \"poli_klinik\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Kegiatan Kamar Operasi'
                ) AS \"kamar_operasi\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Seminar Hasil'
                ) AS \"seminar_hasil\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Review Artikel'
                ) AS \"review_artikel\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Proposal Thesis'
                ) AS \"proposal_thesis\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Exam'
                ) AS \"exam\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Stase'
                ) AS \"stase\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Bimbingan Operasi'
                ) AS \"bimbingan_operasi\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Kegiatan Bangsal'
                ) AS \"kegiatan_bangsal\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Publikasi'
                ) AS \"publikasi\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Ilmiah Non Stase'
                ) AS \"ilmiah_non_stase\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Course'
                ) AS \"course\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Ekstrakulikuler'
                ) AS \"ekstrakulikuler\",
    
                COUNT(rl.id) FILTER (
                    WHERE rl.action = 'Pengabdian Masyarakat'
                ) AS \"pengabdian_masyarakat\",
    
                COUNT(rl.id) AS total
    
            FROM m_user u
    
            JOIN m_role r
                ON r.id = u.id_role
    
            JOIN m_semester s
                ON s.id = u.id_semester
    
            LEFT JOIN v_logbook_summary_general rl
                ON rl.ppds = u.display_name
                AND rl.id_client = u.id_client
                AND rl.date >= :start_date
                AND rl.date <= :end_date
    
            WHERE
                u.is_show = TRUE
                AND u.id_semester IS NOT NULL
                AND r.name = 'ppds'
                AND u.id_client = :id_client
    
            GROUP BY
                u.id,
                u.display_name,
                s.name
    
            ORDER BY
                total {$sort},
                CAST(
                    REGEXP_REPLACE(s.name, '[^0-9]', '', 'g')
                    AS INTEGER
                ) DESC NULLS LAST,
                u.display_name
    
            LIMIT :limit
            OFFSET :offset
        ";
    
        $countSql = "
            SELECT COUNT(*)
            FROM m_user u
    
            JOIN m_role r
                ON r.id = u.id_role
    
            JOIN m_semester s
                ON s.id = u.id_semester
    
            WHERE
                u.is_show = TRUE
                AND u.id_semester IS NOT NULL
                AND r.name = 'ppds'
                AND u.id_client = :id_client
        ";
    
        $params = [
            ':id_client' => $post['id_client'],
            ':start_date' => !empty($post['start_date'])
                ? $post['start_date']
                : date('Y-m-01 00:00:00'),
            ':end_date' => !empty($post['end_date'])
                ? $post['end_date']
                : date('Y-m-t 23:59:59')
        ];
    
        $command = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);
    
        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
        }
    
        $countCommand->bindValue(':id_client', $post['id_client']);
    
        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);
    
        $data = $command->queryAll();
        $total = $countCommand->queryScalar();
    
        /*
         * DASHBOARD SUMMARY
         */
        $summarySql = "
            SELECT
                COUNT(DISTINCT u.id) AS total_ppds,
                COUNT(rl.id) AS total_logbooks,
                COUNT(DISTINCT s.name) AS total_semesters
    
            FROM m_user u
    
            JOIN m_role r
                ON r.id = u.id_role
    
            JOIN m_semester s
                ON s.id = u.id_semester
    
            LEFT JOIN v_logbook_summary_general rl
                ON rl.ppds = u.display_name
                AND rl.id_client = u.id_client
                AND rl.date >= :start_date
                AND rl.date <= :end_date
    
            WHERE
                u.is_show = TRUE
                AND u.id_semester IS NOT NULL
                AND r.name = 'ppds'
                AND u.id_client = :id_client
        ";
    
        $summaryCommand = Yii::app()->db->createCommand($summarySql);
    
        $summaryCommand->bindValue(':id_client', $post['id_client']);
        $summaryCommand->bindValue(':start_date', $params[':start_date']);
        $summaryCommand->bindValue(':end_date', $params[':end_date']);
    
        $summary = $summaryCommand->queryRow();
    
        $totalPpds = (int)$summary['total_ppds'];
        $totalLogbooks = (int)$summary['total_logbooks'];
    
        $summary['total_ppds'] = $totalPpds;
        $summary['total_logbooks'] = $totalLogbooks;
        $summary['avg_per_ppds'] = $totalPpds > 0
            ? round($totalLogbooks / $totalPpds, 1)
            : 0;
        $summary['total_semesters'] = (int)$summary['total_semesters'];
    
        echo json_encode([
            'status' => true,
            'message' => 'Success',
            'data' => $data,
            'summary' => $summary,
            'total' => (int)$total,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }
    
    // public function actionGetDetailRekapReport()
    // {
    //     if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    //         http_response_code(204);
    //         Yii::app()->end();
    //     }
    
    //     $rest_json = file_get_contents("php://input");
    //     $post = json_decode($rest_json, true);
    
    //     if (!isset($post['id_client']) || !isset($post['id'])) {
    //         echo json_encode([
    //             'status' => false,
    //             'message' => 'Invalid parameter!'
    //         ]);
    //         Yii::app()->end();
    //     }
    
    //     $page = isset($post['page']) ? (int)$post['page'] : 1;
    //     $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
    //     $offset = ($page - 1) * $limit;
    
    //     $params = [
    //         ':id_client' => $post['id_client'],
    //         ':id' => $post['id'],
    //         ':start_date' => !empty($post['start_date'])
    //             ? $post['start_date']
    //             : date('Y-m-01'),
    //         ':end_date' => !empty($post['end_date'])
    //             ? $post['end_date']
    //             : date('Y-m-t')
    //     ];
    
    //     /*
    //      * SUMMARY
    //      */
    //     $summarySql = "
    //         SELECT
    //             u.display_name AS ppds,
    //             MIN(v.date)::date AS periode_mulai,
    //             MAX(v.date)::date AS periode_selesai,
    //             COUNT(v.id) AS total_logbooks,
    //             MAX(v.semester) AS semester,
    //             'Active' AS status
    //         FROM v_logbook_summary_general v
    //         JOIN m_user u
    //             ON u.display_name = v.ppds
    //             AND u.id_client = v.id_client
    //         WHERE
    //             u.id = :id
    //             AND v.id_client = :id_client
    //             AND v.date >= :start_date
    //             AND v.date < (:end_date::date + INTERVAL '1 day')
    //         GROUP BY
    //             u.id,
    //             u.display_name
    //     ";
    
    //     $summaryCommand = Yii::app()->db->createCommand($summarySql);
    
    //     foreach ($params as $key => $value) {
    //         $summaryCommand->bindValue($key, $value);
    //     }
    
    //     $summary = $summaryCommand->queryRow();
    
    //     /*
    //      * ACTIVITY BREAKDOWN
    //      */
    //     $breakdownSql = "
    //         SELECT
    //             v.action AS aktivitas,
    //             COUNT(v.id) AS jumlah
    //         FROM v_logbook_summary_general v
    //         JOIN m_user u
    //             ON u.display_name = v.ppds
    //             AND u.id_client = v.id_client
    //         WHERE
    //             u.id = :id
    //             AND v.id_client = :id_client
    //             AND v.date >= :start_date
    //             AND v.date < (:end_date::date + INTERVAL '1 day')
    //         GROUP BY
    //             v.action
    //         ORDER BY
    //             jumlah DESC,
    //             aktivitas ASC
    //     ";
    
    //     $breakdownCommand = Yii::app()->db->createCommand($breakdownSql);
    
    //     foreach ($params as $key => $value) {
    //         $breakdownCommand->bindValue($key, $value);
    //     }
    
    //     $breakdown = $breakdownCommand->queryAll();
    
    //     /*
    //      * DETAIL LOGBOOK
    //      */
    //     $sql = "
    //         SELECT
    //             v.date::date AS tanggal,
    //             v.action AS aktivitas,
    //             COALESCE(v.title, '-') AS judul,
    //             COALESCE(v.stase, '-') AS stase,
    //             COALESCE(v.status, 'pending') AS status,
    //             v.staff,
    //             v.nim,
    //             v.semester,
    //             v.pin,
    //             v.category,
    //             v.peran,
    //             v.attachment,
    //             v.emr_number,
    //             v.diagnosis,
    //             v.treatment,
    //             v.patient,
    //             v.id AS id_logbook
    //         FROM v_logbook_summary_general v
    //         JOIN m_user u
    //             ON u.display_name = v.ppds
    //             AND u.id_client = v.id_client
    //         WHERE
    //             u.id = :id
    //             AND v.id_client = :id_client
    //             AND v.date >= :start_date
    //             AND v.date < (:end_date::date + INTERVAL '1 day')
    //         ORDER BY
    //             v.date DESC,
    //             v.id DESC
    //         LIMIT :limit
    //         OFFSET :offset
    //     ";
    
    //     $countSql = "
    //         SELECT COUNT(*)
    //         FROM v_logbook_summary_general v
    //         JOIN m_user u
    //             ON u.display_name = v.ppds
    //             AND u.id_client = v.id_client
    //         WHERE
    //             u.id = :id
    //             AND v.id_client = :id_client
    //             AND v.date >= :start_date
    //             AND v.date < (:end_date::date + INTERVAL '1 day')
    //     ";
    
    //     $command = Yii::app()->db->createCommand($sql);
    //     $countCommand = Yii::app()->db->createCommand($countSql);
    
    //     foreach ($params as $key => $value) {
    //         $command->bindValue($key, $value);
    //         $countCommand->bindValue($key, $value);
    //     }
    
    //     $command->bindValue(':limit', $limit, PDO::PARAM_INT);
    //     $command->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    //     $data = $command->queryAll();
    //     $total = (int)$countCommand->queryScalar();
    
    //     echo json_encode([
    //         'status' => true,
    //         'message' => 'Success',
    //         'summary' => $summary ?: [
    //             'ppds' => null,
    //             'periode_mulai' => null,
    //             'periode_selesai' => null,
    //             'total_logbooks' => 0,
    //             'semester' => null,
    //             'status' => 'Active'
    //         ],
    //         'activity_breakdown' => $breakdown,
    //         'data' => $data,
    //         'total' => (int)$total,
    //         'pagination' => [
    //             'page' => $page,
    //             'limit' => $limit
    //         ]
    //     ]);
    // }
    public function actionGetDetailRekapReport()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            Yii::app()->end();
        }
    
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id_client']) || !isset($post['id'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $page = isset($post['page']) ? (int)$post['page'] : 1;
        $limit = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;
    
        $params = [
            ':id_client' => $post['id_client'],
            ':id' => $post['id'],
            ':start_date' => !empty($post['start_date'])
                ? $post['start_date']
                : date('Y-m-01'),
            ':end_date' => !empty($post['end_date'])
                ? $post['end_date']
                : date('Y-m-t')
        ];
    
        /*
         * SUMMARY
         */
        $summarySql = "
            SELECT
                u.display_name AS ppds,
                COUNT(v.id) AS total_logbooks,
                MAX(v.semester) AS semester,
                'Active' AS status
            FROM m_user u
            LEFT JOIN v_logbook_summary_general v
                ON v.ppds = u.display_name
                AND v.id_client = u.id_client
                AND v.date >= :start_date
                AND v.date < (:end_date::date + INTERVAL '1 day')
            WHERE
                u.id = :id
                AND u.id_client = :id_client
            GROUP BY
                u.id,
                u.display_name
        ";
    
        $summaryCommand = Yii::app()->db->createCommand($summarySql);
    
        foreach ($params as $key => $value) {
            $summaryCommand->bindValue($key, $value);
        }
    
        $summary = $summaryCommand->queryRow();
    
        if (!$summary) {
            $summary = [
                'ppds' => null,
                'total_logbooks' => 0,
                'semester' => null,
                'status' => 'Active'
            ];
        }
    
        $summary['periode_mulai'] = $params[':start_date'];
        $summary['periode_selesai'] = $params[':end_date'];
        $summary['total_logbooks'] = (int)$summary['total_logbooks'];
    
        /*
         * ACTIVITY BREAKDOWN
         */
        $activitySql = "
            SELECT
                v.action AS aktivitas,
                COUNT(v.id) AS jumlah
            FROM v_logbook_summary_general v
            JOIN m_user u
                ON u.display_name = v.ppds
                AND u.id_client = v.id_client
            WHERE
                u.id = :id
                AND v.id_client = :id_client
                AND v.date >= :start_date
                AND v.date < (:end_date::date + INTERVAL '1 day')
            GROUP BY
                v.action
            ORDER BY
                jumlah DESC,
                aktivitas ASC
        ";
    
        $activityCommand = Yii::app()->db->createCommand($activitySql);
    
        foreach ($params as $key => $value) {
            $activityCommand->bindValue($key, $value);
        }
    
        $activity = $activityCommand->queryAll();
    
        /*
         * DETAIL LOGBOOK
         */
        $sql = "
            SELECT
                v.id AS id_logbook,
                v.date::date AS tanggal,
                v.action AS aktivitas,
                COALESCE(v.title, '-') AS judul,
                COALESCE(v.stase, '-') AS stase,
                COALESCE(v.status, 'pending') AS status
            FROM v_logbook_summary_general v
            LEFT JOIN t_logbook tl ON tl.id = v.id
            JOIN m_user u
                ON u.display_name = v.ppds
                AND u.id_client = v.id_client
            WHERE
                u.id = :id
                AND v.id_client = :id_client
                AND v.date >= :start_date
                AND v.date < (:end_date::date + INTERVAL '1 day')
            ORDER BY
                tl.created_date DESC,
                v.id DESC
            LIMIT :limit
            OFFSET :offset
        ";
    
        $countSql = "
            SELECT COUNT(*)
            FROM v_logbook_summary_general v
            LEFT JOIN t_logbook tl ON tl.id = v.id
            JOIN m_user u
                ON u.display_name = v.ppds
                AND u.id_client = v.id_client
            WHERE
                u.id = :id
                AND v.id_client = :id_client
                AND v.date >= :start_date
                AND v.date < (:end_date::date + INTERVAL '1 day')
        ";
    
        $command = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);
    
        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
            $countCommand->bindValue($key, $value);
        }
    
        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);
    
        $data = $command->queryAll();
        $total = (int)$countCommand->queryScalar();
        
        echo json_encode([
            'status' => true,
            'message' => 'Success',
            'summary' => $summary,
            'activity' => $activity,
            'data' => $data,
            'total' => (int)$total,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }

    public function actionGetListRekapPenilaian()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        $page   = isset($post['page']) ? (int)$post['page'] : 1;
        $limit  = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default DESC - newest first)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc') ? 'ASC' : 'DESC';

        $baseWhere = "
            FROM v_ppds_scoring_fixed vp
            LEFT JOIN t_logbook tl ON tl.id = vp.id_logbook
            WHERE 1=1
        ";

        $sql = "
            SELECT
                vp.id_logbook,
                vp.ppds,
                vp.nim,
                vp.inisial_code,
                vp.semester,
                vp.stase,
                vp.pin,
                vp.staff,
                vp.action,
                vp.date_logbook,
                vp.title,
                vp.notes,
                vp.peran,
                vp.category,
                ROUND(vp.psikomotor::numeric, 2) AS psikomotor,
                ROUND(vp.knowledge::numeric, 2) AS knowledge,
                ROUND(vp.afektif::numeric, 2) AS afektif,
                ROUND(vp.total::numeric, 2) AS total
            {$baseWhere}
        ";

        $countSql = "
            SELECT COUNT(*) {$baseWhere}
        ";

        $params = [];

        if (!empty($post['id_client'])) {
            $sql      .= ' AND vp.id_client = :id_client';
            $countSql .= ' AND vp.id_client = :id_client';
            $params[':id_client'] = $post['id_client'];
        }

        if (!empty($post['ppds_name'])) {
            $sql      .= ' AND vp.ppds = :ppds_name';
            $countSql .= ' AND vp.ppds = :ppds_name';
            $params[':ppds_name'] = $post['ppds_name'];
        }

        if (!empty($post['staff_name'])) {
            $sql      .= ' AND vp.staff = :staff_name';
            $countSql .= ' AND vp.staff = :staff_name';
            $params[':staff_name'] = $post['staff_name'];
        }

        if (!empty($post['activity_name'])) {
            $sql      .= ' AND vp.action = :activity_name';
            $countSql .= ' AND vp.action = :activity_name';
            $params[':activity_name'] = $post['activity_name'];
        }

        if (!empty($post['stase_name'])) {
            $sql      .= ' AND vp.stase = :stase_name';
            $countSql .= ' AND vp.stase = :stase_name';
            $params[':stase_name'] = $post['stase_name'];
        }

        if (!empty($post['start_date'])) {
            $sql      .= ' AND vp.date_logbook >= :start_date';
            $countSql .= ' AND vp.date_logbook >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND vp.date_logbook <= :end_date';
            $countSql .= ' AND vp.date_logbook <= :end_date';
                        $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );;
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
                vp.ppds ILIKE :search
                OR vp.nim ILIKE :search
                OR vp.inisial_code ILIKE :search
                OR vp.semester ILIKE :search
                OR vp.stase ILIKE :search
                OR vp.pin ILIKE :search
                OR vp.staff ILIKE :search
                OR vp.action ILIKE :search
                OR vp.title ILIKE :search
                OR vp.notes ILIKE :search
                OR vp.peran ILIKE :search
                OR vp.category ILIKE :search
            )';
            $countSql .= ' AND (
                vp.ppds ILIKE :search
                OR vp.nim ILIKE :search
                OR vp.inisial_code ILIKE :search
                OR vp.semester ILIKE :search
                OR vp.stase ILIKE :search
                OR vp.pin ILIKE :search
                OR vp.staff ILIKE :search
                OR vp.action ILIKE :search
                OR vp.title ILIKE :search
                OR vp.notes ILIKE :search
                OR vp.peran ILIKE :search
                OR vp.category ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        $sql .= "
            ORDER BY
                tl.created_date
                {$sort}
            LIMIT :limit
            OFFSET :offset
        ";

        $command = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
            $countCommand->bindValue($key, $value);
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $data = $command->queryAll();
        $total = $countCommand->queryScalar();

        echo json_encode([
            'status'  => true,
            'message'  => 'Success',
            'data'     => $data,
            'total'    => (int)$total,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
            ],
        ]);
    }
    
    public function actionGetAverageRekapPenilaian()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        $baseWhere = "
            FROM v_ppds_scoring_fixed
            WHERE 1=1
        ";
    
        $sql = "
            SELECT
                ROUND(AVG(total::numeric), 2) AS average
            {$baseWhere}
        ";
    
        $params = [];
    
        if (!empty($post['id_client'])) {
            $sql .= ' AND id_client = :id_client';
            $params[':id_client'] = $post['id_client'];
        }
    
        if (!empty($post['ppds_name'])) {
            $sql .= ' AND ppds = :ppds_name';
            $params[':ppds_name'] = $post['ppds_name'];
        }
    
        if (!empty($post['staff_name'])) {
            $sql .= ' AND staff = :staff_name';
            $params[':staff_name'] = $post['staff_name'];
        }
    
        if (!empty($post['activity_name'])) {
            $sql .= ' AND action = :activity_name';
            $params[':activity_name'] = $post['activity_name'];
        }
    
        if (!empty($post['stase_name'])) {
            $sql .= ' AND stase = :stase_name';
            $params[':stase_name'] = $post['stase_name'];
        }
    
        if (!empty($post['start_date'])) {
            $sql .= ' AND date_logbook >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }
    
        if (!empty($post['end_date'])) {
            $sql .= ' AND date_logbook <= :end_date';
                        $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );;
        }
    
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
    
            $sql .= ' AND (
                ppds ILIKE :search
                OR nim ILIKE :search
                OR inisial_code ILIKE :search
                OR semester ILIKE :search
                OR stase ILIKE :search
                OR pin ILIKE :search
                OR staff ILIKE :search
                OR action ILIKE :search
                OR title ILIKE :search
                OR notes ILIKE :search
                OR peran ILIKE :search
                OR category ILIKE :search
            )';
    
            $params[':search'] = $searchTerm;
        }
    
        $command = Yii::app()->db->createCommand($sql);
    
        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
        }
    
        $average = $command->queryScalar();
    
        echo json_encode([
            'status' => true,
            'message' => 'Success',
            'data' => $average,
        ]);
    }
    
    public function actionGetDetailRekapPenilaian()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (!isset($post['id_logbook'])) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $sql = "
            SELECT
                id_logbook,
                id_client,
                ppds,
                nim,
                inisial_code,
                semester,
                stase,
                pin,
                staff,
                action,
                date_logbook,
                title,
                notes,
                peran,
                category,
                ROUND(psikomotor::numeric, 2) AS psikomotor,
                ROUND(knowledge::numeric, 2) AS knowledge,
                ROUND(afektif::numeric, 2) AS afektif,
                ROUND(total::numeric, 2) AS total,
                uuid
            FROM v_ppds_scoring_fixed
            WHERE id_logbook = :id_logbook
            LIMIT 1
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_logbook', $post['id_logbook'], PDO::PARAM_INT);
        $data = $command->queryRow();

        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data not found'
            ]);
            Yii::app()->end();
        }

        echo json_encode([
            'status'  => true,
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

        public function actionGetListRekapLogbook()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        $page   = isset($post['page']) ? (int)$post['page'] : 1;
        $limit  = isset($post['limit']) ? (int)$post['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // sorting (default DESC - newest first)
        $sort = (isset($post['sort']) && strtolower($post['sort']) === 'asc') ? 'ASC' : 'DESC';

        $baseWhere = "
            FROM v_logbook_summary_general vl
            LEFT JOIN t_logbook tl ON tl.id = vl.id
            WHERE 1=1
        ";

        $sql = "
            SELECT
                CONCAT(vl.id, '-', COALESCE(vl.staff, '')) AS row_key,
                vl.id,
                vl.date,
                vl.ppds,
                vl.nim,
                vl.action,
                vl.semester,
                vl.stase,
                vl.pin,
                vl.peran,
                vl.category,
                vl.staff,
                vl.status,
                vl.attachment,
                vl.emr_number,
                vl.diagnosis,
                vl.treatment,
                vl.patient,
                vl.title,
                tl.notes AS notes
            {$baseWhere}
        ";

        $countSql = "
            SELECT COUNT(*) {$baseWhere}
        ";

        $params = [];

        if (!empty($post['id_client'])) {
            $sql      .= ' AND vl.id_client = :id_client';
            $countSql .= ' AND vl.id_client = :id_client';
            $params[':id_client'] = $post['id_client'];
        }

        if (!empty($post['ppds_name'])) {
            $sql      .= ' AND vl.ppds = :ppds_name';
            $countSql .= ' AND vl.ppds = :ppds_name';
            $params[':ppds_name'] = $post['ppds_name'];
        }

        if (!empty($post['staff_name'])) {
            $sql      .= ' AND vl.staff = :staff_name';
            $countSql .= ' AND vl.staff = :staff_name';
            $params[':staff_name'] = $post['staff_name'];
        }

        if (!empty($post['activity_name'])) {
            $sql      .= ' AND vl.action = :activity_name';
            $countSql .= ' AND vl.action = :activity_name';
            $params[':activity_name'] = $post['activity_name'];
        } else {
            $sql      .= " AND vl.action != 'Stase'";
            $countSql .= " AND vl.action != 'Stase'";
        }

        if (!empty($post['stase_name'])) {
            $sql      .= ' AND vl.stase = :stase_name';
            $countSql .= ' AND vl.stase = :stase_name';
            $params[':stase_name'] = $post['stase_name'];
        }

        if (!empty($post['start_date'])) {
            $sql      .= ' AND vl.date >= :start_date';
            $countSql .= ' AND vl.date >= :start_date';
            $params[':start_date'] = $post['start_date'] . ' 00:00:00';
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND vl.date <= :end_date';
            $countSql .= ' AND vl.date <= :end_date';
                        $params[':end_date'] = date(
                'Y-m-d 00:00:00',
                strtotime($post['end_date'] . ' +1 day')
            );;
        }
        
        if (!empty($post['status'])) {
            $sql      .= ' AND LOWER(vl.status) = :status';
            $countSql .= ' AND LOWER(vl.status) = :status';
            $params[':status'] = strtolower($post['status']);
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
                vl.ppds ILIKE :search
                OR vl.nim ILIKE :search
                OR vl.semester ILIKE :search
                OR vl.stase ILIKE :search
                OR vl.pin ILIKE :search
                OR vl.staff ILIKE :search
                OR vl.action ILIKE :search
                OR vl.title ILIKE :search
                OR vl.peran ILIKE :search
                OR vl.category ILIKE :search
                OR vl.patient ILIKE :search
                OR vl.diagnosis ILIKE :search
                OR vl.treatment ILIKE :search
                OR vl.emr_number ILIKE :search
            )';
            $countSql .= ' AND (
                vl.ppds ILIKE :search
                OR vl.nim ILIKE :search
                OR vl.semester ILIKE :search
                OR vl.stase ILIKE :search
                OR vl.pin ILIKE :search
                OR vl.staff ILIKE :search
                OR vl.action ILIKE :search
                OR vl.title ILIKE :search
                OR vl.peran ILIKE :search
                OR vl.category ILIKE :search
                OR vl.patient ILIKE :search
                OR vl.diagnosis ILIKE :search
                OR vl.treatment ILIKE :search
                OR vl.emr_number ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        $sql .= "
            ORDER BY
                tl.created_date
                {$sort}
            LIMIT :limit
            OFFSET :offset
        ";

        $command = Yii::app()->db->createCommand($sql);
        $countCommand = Yii::app()->db->createCommand($countSql);

        foreach ($params as $key => $value) {
            $command->bindValue($key, $value);
            $countCommand->bindValue($key, $value);
        }

        $command->bindValue(':limit', $limit, PDO::PARAM_INT);
        $command->bindValue(':offset', $offset, PDO::PARAM_INT);

        $data = $command->queryAll();
        $total = $countCommand->queryScalar();

        echo json_encode([
            'status'  => true,
            'message'  => 'Success',
            'data'     => $data,
            'total'    => (int)$total,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
            ],
        ]);
    }


    public function actionGetDetailRekapLogbook()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (!isset($post['id'])) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $sql = "
            SELECT
                vl.id,
                vl.id_client,
                vl.date,
                vl.ppds,
                vl.nim,
                vl.action,
                vl.semester,
                vl.stase,
                vl.pin,
                vl.peran,
                vl.category,
                vl.staff,
                vl.status,
                vl.attachment,
                vl.emr_number,
                vl.diagnosis,
                vl.treatment,
                vl.patient,
                vl.title,
                tl.notes
            FROM v_logbook_summary_general vl
            LEFT JOIN t_logbook tl ON tl.id = vl.id
            WHERE vl.id = :id
        ";
    
        if (!empty($post['staff'])) {
            $sql .= " AND TRIM(vl.staff) = :staff";
        }
    
        $sql .= " LIMIT 1";
    
        $command = Yii::app()->db->createCommand($sql);
    
        $command->bindValue(':id', $post['id'], PDO::PARAM_INT);
    
        if (!empty($post['staff'])) {
            $command->bindValue(':staff', trim($post['staff']), PDO::PARAM_STR);
        }
    
        $data = $command->queryRow();
    
        if (!$data) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data not found'
            ]);
            Yii::app()->end();
        }
    
        echo json_encode([
            'status'  => true,
            'message' => 'Success',
            'data'    => $data,
        ]);
    }
    
    public function actionUpdateMultipleLogbook()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            Yii::app()->end();
        }
    
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
    
        if (
            !isset($post['id_client']) ||
            !isset($post['id_stase']) ||
            !isset($post['updated_by']) ||
            !isset($post['data']) ||
            !is_array($post['data'])
        ) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
    
        $id_logbooks = [];
    
        foreach ($post['data'] as $item) {
            $parts = explode('-', $item, 2);
            $id = (int)$parts[0];
    
            if ($id > 0) {
                $id_logbooks[$id] = $id;
            }
        }
    
        $transaction = Yii::app()->db->beginTransaction();
    
        try {
            $updated = 0;
    
            foreach ($id_logbooks as $id) {
                $updated += Yii::app()->db->createCommand()->update(
                    't_logbook',
                    [
                        'id_stase' => $post['id_stase'],
                        'updated_by' => $post['updated_by'],
                        'updated_date' => date('Y-m-d H:i:s')
                    ],
                    'id = :id AND id_client = :id_client',
                    [
                        ':id' => $id,
                        ':id_client' => $post['id_client']
                    ]
                );
            }
    
            $transaction->commit();
    
            echo json_encode([
                'status' => true,
                'message' => 'Berhasil memperbarui data logbook.',
                'data' => [
                    'total_logbook' => count($id_logbooks),
                    'total_updated' => $updated
                    ]
            ]);
        } catch (Exception $e) {
            $transaction->rollback();
    
            echo json_encode([
                'status' => false,
                'message' => 'Gagal memperbarui data logbook.'
            ]);
        }
    
        Yii::app()->end();
    }
    // === REKAP STAGE ===
}