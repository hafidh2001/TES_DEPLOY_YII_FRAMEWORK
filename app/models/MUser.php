<?php

class MUser extends ActiveRecord
{

	public function tableName()
	{
		return 'm_user';
	}

	public function rules()
	{
		return array(
			array('display_name, id_role, created_date, id_client', 'required'),
			array('id_role, created_by, updated_by, id_institution, id_sub_category, id_semester, id_stase, id_client, id_year', 'numerical', 'integerOnly'=>true),
			array('password', 'length', 'max'=>255),
			array('username, email, is_deleted, updated_date, phone, address, date_of_birth, code, picture, gender, status, inisial_code, is_show, deleted_at, inactive_at, inactive_notes, reactivate_date', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'mHospitals' => array(self::HAS_MANY, 'MHospital', 'created_by'),
			'mHospitals1' => array(self::HAS_MANY, 'MHospital', 'updated_by'),
			'tAuditTrails' => array(self::HAS_MANY, 'TAuditTrails', 'id_user'),
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_user'),
			'idInstitution' => array(self::BELONGS_TO, 'MClient', 'id_institution'),
			'idSemester' => array(self::BELONGS_TO, 'MSemester', 'id_semester'),
			'idStase' => array(self::BELONGS_TO, 'MStase', 'id_stase'),
			'idSubCategory' => array(self::BELONGS_TO, 'MActionCategory', 'id_sub_category'),
			'idYear' => array(self::BELONGS_TO, 'MAcademicYear', 'id_year'),
			'createdBy' => array(self::BELONGS_TO, 'MUser', 'created_by'),
			'mUsers' => array(self::HAS_MANY, 'MUser', 'created_by'),
			'idRole' => array(self::BELONGS_TO, 'MRole', 'id_role'),
			'updatedBy' => array(self::BELONGS_TO, 'MUser', 'updated_by'),
			'mUsers1' => array(self::HAS_MANY, 'MUser', 'updated_by'),
			'tLogbookStatuses' => array(self::HAS_MANY, 'TLogbookStatus', 'id_user'),
			'tMenus' => array(self::HAS_MANY, 'TMenu', 'created_by'),
			'tMenus1' => array(self::HAS_MANY, 'TMenu', 'updated_by'),
			'mUserActions' => array(self::HAS_MANY, 'MUserAction', 'id_user'),
			'mSessions' => array(self::HAS_MANY, 'MSession', 'id_user'),
			'tNotifs' => array(self::HAS_MANY, 'TNotif', 'id_user'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'display_name' => 'Display Name',
			'username' => 'Username',
			'email' => 'Email',
			'password' => 'Password',
			'id_role' => 'Id Role',
			'is_deleted' => 'Is Deleted',
			'created_date' => 'Created Date',
			'created_by' => 'Created By',
			'updated_date' => 'Updated Date',
			'updated_by' => 'Updated By',
			'phone' => 'Phone',
			'address' => 'Address',
			'date_of_birth' => 'Date Of Birth',
			'code' => 'Code',
			'picture' => 'Picture',
			'id_institution' => 'Id Institution',
			'id_sub_category' => 'Id Sub Category',
			'id_semester' => 'Id Semester',
			'id_stase' => 'Id Stase',
			'id_client' => 'Id Client',
			'gender' => 'Gender',
			'id_year' => 'Id Year',
			'status' => 'Status',
			'inisial_code' => 'Inisial Code',
			'is_show' => 'Is Show',
			'deleted_at' => 'Deleted At',
			'inactive_at' => 'Inactive At',
			'inactive_notes' => 'Inactive Notes',
			'reactivate_date' => 'Reactivate Date',
		);
	}

}
