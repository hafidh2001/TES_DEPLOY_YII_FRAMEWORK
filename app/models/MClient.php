<?php

class MClient extends ActiveRecord
{

	public function tableName()
	{
		return 'm_client';
	}

	public function rules()
	{
		return array(
			array('name', 'required'),
		);
	}

	public function relations()
	{
		return array(
			'mAcademicYears' => array(self::HAS_MANY, 'MAcademicYear', 'id_client'),
			'mActions' => array(self::HAS_MANY, 'MAction', 'id_client'),
			'mActionAnotherRoles' => array(self::HAS_MANY, 'MActionAnotherRole', 'id_client'),
			'mClientLogos' => array(self::HAS_MANY, 'MClientLogo', 'id_client'),
			'mHospitals' => array(self::HAS_MANY, 'MHospital', 'id_client'),
			'tAuditTrails' => array(self::HAS_MANY, 'TAuditTrails', 'id_client'),
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_client'),
			'mSemesters' => array(self::HAS_MANY, 'MSemester', 'id_client'),
			'mUsers' => array(self::HAS_MANY, 'MUser', 'id_institution'),
			'mRoles' => array(self::HAS_MANY, 'MRole', 'id_client'),
			'tLogbookEmrs' => array(self::HAS_MANY, 'TLogbookEmr', 'id_client'),
			'tLogbookStatuses' => array(self::HAS_MANY, 'TLogbookStatus', 'id_client'),
			'tMenus' => array(self::HAS_MANY, 'TMenu', 'id_client'),
			'tLogbookAsms' => array(self::HAS_MANY, 'TLogbookAsm', 'id_client'),
			'mAnotherRoles' => array(self::HAS_MANY, 'MAnotherRole', 'id_client'),
			'mActionTypes' => array(self::HAS_MANY, 'MActionType', 'id_client'),
			'mActionCategories' => array(self::HAS_MANY, 'MActionCategory', 'id_client'),
			'mUserActions' => array(self::HAS_MANY, 'MUserAction', 'id_client'),
			'mActionRolemaps' => array(self::HAS_MANY, 'MActionRolemap', 'id_client'),
			'mActionRoles' => array(self::HAS_MANY, 'MActionRole', 'id_client'),
			'mAsmActions' => array(self::HAS_MANY, 'MAsmAction', 'id_client'),
			'mAsmParams' => array(self::HAS_MANY, 'MAsmParam', 'id_client'),
			'mScoreOptions' => array(self::HAS_MANY, 'MScoreOption', 'id_client'),
			'mStages' => array(self::HAS_MANY, 'MStage', 'id_client'),
			'mSessions' => array(self::HAS_MANY, 'MSession', 'id_client'),
			'mStases' => array(self::HAS_MANY, 'MStase', 'id_client'),
			'tLogbookAttachments' => array(self::HAS_MANY, 'TLogbookAttachment', 'id_client'),
			'tNotifs' => array(self::HAS_MANY, 'TNotif', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
		);
	}

}
