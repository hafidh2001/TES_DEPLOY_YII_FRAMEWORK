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
                    mu.status     = :status 
                -- AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL 
                AND mu.id_client  = :id_client
                AND mr.name       = :role_name
                ORDER BY 
                    mu.display_name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':status', 'Inactive')
            // ->bindValue(':is_show', true)
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
                LEFT JOIN m_user mu ON mu.id = tls.id_user
                LEFT JOIN m_action_role mar ON mar.id = tls.id_action_role
                WHERE
                    mu.deleted_at IS NULL
                AND mar.id_client = :id_client
                AND mar.role      != :role_name 
                ORDER BY
                    mu.display_name ASC';
        
        $res = Yii::app()->db->createCommand($sql)
            ->bindValue(':id_client', $post['id_client'])
            ->bindValue(':role_name', 'Peserta')
            ->queryAll();
        
        echo json_encode([
            'status'  => true,
            'total'   => count($res),
            'data'    => $res
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
    
    public function actionDeletePPDS() {
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
            $params[':start_date'] = $post['start_date'];
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND tl.date <= :end_date';
            $countSql .= ' AND tl.date <= :end_date';
            $params[':end_date'] = $post['end_date'];
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
                -- AND mu.is_show    = :is_show
                AND mu.deleted_at IS NULL
                AND mr.name       = :role_name';
        
        $countSql = 'SELECT COUNT(*)
                    FROM m_user mu
                    LEFT JOIN m_role mr ON mr.id = mu.id_role
                    LEFT JOIN m_stase ms ON ms.id = mu.id_stase
                    WHERE 
                        mu.id_client  = :id_client
                    AND mu.status     = :status
                    -- AND mu.is_show    = :is_show
                    AND mu.deleted_at IS NULL
                    AND mr.name       = :role_name';
    
        $params = [
            ':id_client' => $post['id_client'],
            ':status'    => 'Inactive',
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
            ':role_name' => 'staff',
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

    public function actionDeleteStaff() {
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
                    mu.date_of_birth,
                    mu.code AS nim,
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
        
        $user->updated_by     = $post['updated_by'];
        $user->updated_date   = date('Y-m-d H:i:s');
        $user->display_name   = $post['display_name'];
        $user->username       = $post['username'];
        $user->email          = $post['email'];
        $user->phone          = $post['phone'];
        $user->address        = $post['address'] ?? null;
        $user->date_of_birth  = $post['date_of_birth'] ?? null;
        $user->code           = $post['nim'] ?? null;
        
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
                        'name'      => 'staff'
                    ],
                    ['select' => 'id']
            );
        
        try {
            $user                 = new MUser;
            $user->id_client      = $post['id_client'];
            $user->created_by     = $post['created_by'];
            $user->created_date   = date('Y-m-d H:i:s');
            $user->id_role        = $role->id;
            $user->display_name   = $post['display_name'];
            $user->username       = $post['username'];
            $user->email          = $post['email'];
            $user->phone          = $post['phone'];
            $user->address        = $post['address'] ?? null;
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
            $params[':start_date'] = $post['start_date'];
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND tl.date <= :end_date';
            $countSql .= ' AND tl.date <= :end_date';
            $params[':end_date'] = $post['end_date'];
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
            $params[':start_date'] = $post['start_date'];
        }

        if (!empty($post['end_date'])) {
            $sql .= ' AND tl.date <= :end_date';
            $countSql .= ' AND tl.date <= :end_date';
            $params[':end_date'] = $post['end_date'];
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

        $params = [
            ':id_client' => $post['id_client'],
        ];

        $whereClause = "
            WHERE
                tl.deleted_at IS NULL
                AND tl.id_client = :id_client
                AND tl.id_semester IS NOT NULL
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
            $params[':start_date'] = $post['start_date'];
        }

        if (!empty($post['end_date'])) {
            $whereClause .= ' AND tl.date <= :end_date';
            $params[':end_date'] = $post['end_date'];
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
                tl.notes
            FROM t_logbook tl
            LEFT JOIN m_user mu ON mu.id = tl.id_user
            LEFT JOIN m_stase ms ON ms.id = tl.id_stase
            {$whereClause}
            ORDER BY tl.date DESC
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

    public function actionDeleteStase() {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);
        
        if (!isset($post['id_logbook'])) {
            echo json_encode([
                'status' => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }
        
        try {
            $logbook = TLogbook::model()->findByPk($post["id_logbook"]);
            
            if (!$logbook) {
                echo json_encode([
                    'status'  => false,
                    'message' => 'User tidak ditemukan!'
                ]);
                Yii::app()->end();
            }
            
            $logbook->deleted_at = new CDbExpression('NOW()');
            $logbook->save(false);

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

    public function actionCreateStase()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (
            !isset($post['id_client']) ||
            !isset($post['created_by']) ||
            !isset($post['id_user']) ||
            !isset($post['id_stase']) ||
            !isset($post['id_semester']) ||
            !isset($post['date'])
        ) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        try {
            $action = MAction::model()->find(
                    'id_client = :id_client AND identifier = :identifier', 
                    [
                        ':id_client' => $post['id_client'],
                        ':identifier' => 'stase'
                    ]);

            if (!$action) {
                echo json_encode([
                    'status'  => false,
                    'message' => 'Action "stase" not found for the client!'
                ]);
                Yii::app()->end();
            }

            $logbook               = new TLogbook;
            $logbook->id_client    = $post['id_client'];
            $logbook->id_action    = $action->id ?? null;
            $logbook->id_user      = $post['id_user'];
            $logbook->id_stase     = $post['id_stase'];
            $logbook->id_semester  = $post['id_semester'];
            $logbook->date         = $post['date'];
            $logbook->notes        = $post['notes'] ?? null;
            $logbook->is_retake    = $post['is_retake'] ?? false;
            $logbook->created_date = date('Y-m-d H:i:s');
            $logbook->created_by   = $post['created_by'];
            $logbook->save(false);

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

    public function actionUpdateStase()
    {
        $rest_json = file_get_contents("php://input");
        $post = json_decode($rest_json, true);

        if (
            !isset($post['id_logbook']) ||
            !isset($post['updated_by']) ||
            !isset($post['id_user']) ||
            !isset($post['id_stase']) ||
            !isset($post['id_semester']) ||
            !isset($post['date'])
        ) {
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid parameter!'
            ]);
            Yii::app()->end();
        }

        $logbook = TLogbook::model()->findByPk($post['id_logbook']);

        if (!$logbook) {
            echo json_encode([
                'status'  => false,
                'message' => 'Data tidak ditemukan!'
            ]);
            Yii::app()->end();
        }

        try {
            $logbook->id_user      = $post['id_user'];
            $logbook->id_stase     = $post['id_stase'];
            $logbook->id_semester  = $post['id_semester'];
            $logbook->date         = $post['date'];
            $logbook->notes        = $post['notes'] ?? null;
            $logbook->is_retake    = $post['is_retake'] ?? false;
            $logbook->updated_date = date('Y-m-d H:i:s');
            $logbook->updated_by   = $post['updated_by'];
            $logbook->save(false);

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
                AND mr.name IN ('ppds', 'staff')
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
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

    public function actionGetDashboardLogActivity() {
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
                date,
                message
            FROM t_notif
            WHERE id_client = :id_client
            ORDER BY date DESC
            LIMIT 10
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id_client', $post['id_client']);
        $data = $command->queryAll();

        echo json_encode([
            'status' => true,
            'data' => $data
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
            $params[':start_date'] = $post['start_date'];
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND tl.date <= :end_date';
            $countSql .= ' AND tl.date <= :end_date';
            $params[':end_date'] = $post['end_date'];
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
    public function actionGetListRekapReport() {

    }
    
    public function actionGetDetailRekapReport() {

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
            FROM v_ppds_scoring_fixed
            WHERE 1=1
        ";

        $sql = "
            SELECT
                id_logbook,
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
                ROUND(total::numeric, 2) AS total
            {$baseWhere}
        ";

        $countSql = "
            SELECT COUNT(*) {$baseWhere}
        ";

        $params = [];

        if (!empty($post['id_client'])) {
            $sql      .= ' AND id_client = :id_client';
            $countSql .= ' AND id_client = :id_client';
            $params[':id_client'] = $post['id_client'];
        }

        if (!empty($post['ppds_name'])) {
            $sql      .= ' AND ppds = :ppds_name';
            $countSql .= ' AND ppds = :ppds_name';
            $params[':ppds_name'] = $post['ppds_name'];
        }

        if (!empty($post['staff_name'])) {
            $sql      .= ' AND staff = :staff_name';
            $countSql .= ' AND staff = :staff_name';
            $params[':staff_name'] = $post['staff_name'];
        }

        if (!empty($post['activity_name'])) {
            $sql      .= ' AND action = :activity_name';
            $countSql .= ' AND action = :activity_name';
            $params[':activity_name'] = $post['activity_name'];
        }

        if (!empty($post['stase_name'])) {
            $sql      .= ' AND stase = :stase_name';
            $countSql .= ' AND stase = :stase_name';
            $params[':stase_name'] = $post['stase_name'];
        }

        if (!empty($post['start_date'])) {
            $sql      .= ' AND date_logbook >= :start_date';
            $countSql .= ' AND date_logbook >= :start_date';
            $params[':start_date'] = $post['start_date'];
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND date_logbook <= :end_date';
            $countSql .= ' AND date_logbook <= :end_date';
            $params[':end_date'] = $post['end_date'];
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
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
            $countSql .= ' AND (
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

        $sql .= "
            ORDER BY
                date_logbook
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
            FROM v_logbook_summary_general
            WHERE 1=1
        ";

        $sql = "
            SELECT
                id,
                date,
                ppds,
                nim,
                action,
                semester,
                stase,
                pin,
                peran,
                category,
                staff,
                status,
                attachment,
                emr_number,
                diagnosis,
                treatment,
                patient,
                title
            {$baseWhere}
        ";

        $countSql = "
            SELECT COUNT(*) {$baseWhere}
        ";

        $params = [];

        if (!empty($post['id_client'])) {
            $sql      .= ' AND id_client = :id_client';
            $countSql .= ' AND id_client = :id_client';
            $params[':id_client'] = $post['id_client'];
        }

        if (!empty($post['ppds_name'])) {
            $sql      .= ' AND ppds = :ppds_name';
            $countSql .= ' AND ppds = :ppds_name';
            $params[':ppds_name'] = $post['ppds_name'];
        }

        if (!empty($post['staff_name'])) {
            $sql      .= ' AND staff = :staff_name';
            $countSql .= ' AND staff = :staff_name';
            $params[':staff_name'] = $post['staff_name'];
        }

        if (!empty($post['activity_name'])) {
            $sql      .= ' AND action = :activity_name';
            $countSql .= ' AND action = :activity_name';
            $params[':activity_name'] = $post['activity_name'];
        }

        if (!empty($post['stase_name'])) {
            $sql      .= ' AND stase = :stase_name';
            $countSql .= ' AND stase = :stase_name';
            $params[':stase_name'] = $post['stase_name'];
        }

        if (!empty($post['start_date'])) {
            $sql      .= ' AND date >= :start_date';
            $countSql .= ' AND date >= :start_date';
            $params[':start_date'] = $post['start_date'];
        }

        if (!empty($post['end_date'])) {
            $sql      .= ' AND date <= :end_date';
            $countSql .= ' AND date <= :end_date';
            $params[':end_date'] = $post['end_date'];
        }

        // 🔥 search filter - ILIKE across multiple fields
        if (!empty($post['search'])) {
            $searchTerm = '%' . $post['search'] . '%';
            $sql      .= ' AND (
                ppds ILIKE :search
                OR nim ILIKE :search
                OR semester ILIKE :search
                OR stase ILIKE :search
                OR pin ILIKE :search
                OR staff ILIKE :search
                OR action ILIKE :search
                OR title ILIKE :search
                OR peran ILIKE :search
                OR category ILIKE :search
                OR patient ILIKE :search
                OR diagnosis ILIKE :search
                OR treatment ILIKE :search
                OR emr_number ILIKE :search
            )';
            $countSql .= ' AND (
                ppds ILIKE :search
                OR nim ILIKE :search
                OR semester ILIKE :search
                OR stase ILIKE :search
                OR pin ILIKE :search
                OR staff ILIKE :search
                OR action ILIKE :search
                OR title ILIKE :search
                OR peran ILIKE :search
                OR category ILIKE :search
                OR patient ILIKE :search
                OR diagnosis ILIKE :search
                OR treatment ILIKE :search
                OR emr_number ILIKE :search
            )';
            $params[':search'] = $searchTerm;
        }

        $sql .= "
            ORDER BY
                date
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
                id,
                id_client,
                date,
                ppds,
                nim,
                action,
                semester,
                stase,
                pin,
                peran,
                category,
                staff,
                status,
                attachment,
                emr_number,
                diagnosis,
                treatment,
                patient,
                title
            FROM v_logbook_summary_general
            WHERE id = :id
            LIMIT 1
        ";

        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(':id', $post['id'], PDO::PARAM_INT);
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
    // === REKAP STAGE ===
}