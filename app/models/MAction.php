<?php

class MAction extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action';
	}

	public function rules()
	{
		return array(
			array('id_type, name, has_notes, has_attachment, has_category, is_milestone, show_on_milestone, multiple_verification, has_score, has_presentation, has_location, has_emr, has_another_role, has_title, id_client', 'required'),
			array('id_type, id_client, max_entry_per_day', 'numerical', 'integerOnly'=>true),
			array('has_status, show_on_menu, has_hospital, attachment_name, has_score_option, is_schedule, identifier, is_grouped_by_category, has_operation_code, is_exam', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idType' => array(self::BELONGS_TO, 'MActionType', 'id_type'),
			'mActionAnotherRoles' => array(self::HAS_MANY, 'MActionAnotherRole', 'id_action'),
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_action'),
			'mAnotherRoles' => array(self::HAS_MANY, 'MAnotherRole', 'id_action'),
			'mActionSemesters' => array(self::HAS_MANY, 'MActionSemester', 'id_action'),
			'mActionCategories' => array(self::HAS_MANY, 'MActionCategory', 'id_action'),
			'mUserActions' => array(self::HAS_MANY, 'MUserAction', 'id_action_hidden'),
			'mActionRolemaps' => array(self::HAS_MANY, 'MActionRolemap', 'id_action'),
			'mAsmActions' => array(self::HAS_MANY, 'MAsmAction', 'id_action'),
			'mScoreOptions' => array(self::HAS_MANY, 'MScoreOption', 'id_action'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_type' => 'Id Type',
			'name' => 'Name',
			'has_notes' => 'Has Notes',
			'has_attachment' => 'Has Attachment',
			'has_category' => 'Has Category',
			'is_milestone' => 'Is Milestone',
			'show_on_milestone' => 'Show On Milestone',
			'multiple_verification' => 'Multiple Verification',
			'has_score' => 'Has Score',
			'has_presentation' => 'Has Presentation',
			'has_location' => 'Has Location',
			'has_emr' => 'Has Emr',
			'has_another_role' => 'Has Another Role',
			'has_title' => 'Has Title',
			'id_client' => 'Id Client',
			'has_status' => 'Has Status',
			'show_on_menu' => 'Show On Menu',
			'has_hospital' => 'Has Hospital',
			'attachment_name' => 'Attachment Name',
			'has_score_option' => 'Has Score Option',
			'is_schedule' => 'Is Schedule',
			'max_entry_per_day' => 'Max Entry Per Day',
			'identifier' => 'Identifier',
			'is_grouped_by_category' => 'Is Grouped By Category',
			'has_operation_code' => 'Has Operation Code',
			'is_exam' => 'Is Exam',
		);
	}

}
