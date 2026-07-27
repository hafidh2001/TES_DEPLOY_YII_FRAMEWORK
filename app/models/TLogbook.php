<?php

class TLogbook extends ActiveRecord
{

	public function tableName()
	{
		return 't_logbook';
	}

	public function rules()
	{
		return array(
			array('date, id_action, created_by, created_date, id_client', 'required'),
			array('id_hospital, id_category, id_action, created_by, updated_by, id_user, id_client, id_another_role, id_semester, id_stase, id_academic_year', 'numerical', 'integerOnly'=>true),
			array('title, notes, location, is_presentation, updated_date, verified, operation_code, schedule_status, exam_result, verified_status, deleted_at, is_retake', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idAcademicYear' => array(self::BELONGS_TO, 'MAcademicYear', 'id_academic_year'),
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idCategory' => array(self::BELONGS_TO, 'MActionCategory', 'id_category'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idHospital' => array(self::BELONGS_TO, 'MHospital', 'id_hospital'),
			'idAnotherRole' => array(self::BELONGS_TO, 'MAnotherRole', 'id_another_role'),
			'idSemester' => array(self::BELONGS_TO, 'MSemester', 'id_semester'),
			'idStase' => array(self::BELONGS_TO, 'MStase', 'id_stase'),
			'idUser' => array(self::BELONGS_TO, 'MUser', 'id_user'),
			'tLogbookEmrs' => array(self::HAS_MANY, 'TLogbookEmr', 'id_logbook'),
			'tLogbookStatuses' => array(self::HAS_MANY, 'TLogbookStatus', 'id_logbook'),
			'tLogbookAsms' => array(self::HAS_MANY, 'TLogbookAsm', 'id_logbook'),
			'tLogbookAttachments' => array(self::HAS_MANY, 'TLogbookAttachment', 'id_logbook'),
			'tNotifs' => array(self::HAS_MANY, 'TNotif', 'id_logbook'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'title' => 'Title',
			'notes' => 'Notes',
			'date' => 'Date',
			'location' => 'Location',
			'id_hospital' => 'Id Hospital',
			'id_category' => 'Id Category',
			'id_action' => 'Id Action',
			'is_presentation' => 'Is Presentation',
			'created_by' => 'Created By',
			'created_date' => 'Created Date',
			'updated_by' => 'Updated By',
			'updated_date' => 'Updated Date',
			'id_user' => 'Id User',
			'id_client' => 'Id Client',
			'verified' => 'Verified',
			'operation_code' => 'Operation Code',
			'id_another_role' => 'Id Another Role',
			'id_semester' => 'Id Semester',
			'id_stase' => 'Id Stase',
			'schedule_status' => 'Schedule Status',
			'exam_result' => 'Exam Result',
			'id_academic_year' => 'Id Academic Year',
			'verified_status' => 'Verified Status',
			'deleted_at' => 'Deleted At',
			'is_retake' => 'Is Retake',
		);
	}

}
