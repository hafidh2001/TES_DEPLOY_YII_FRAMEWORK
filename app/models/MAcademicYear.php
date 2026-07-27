<?php

class MAcademicYear extends ActiveRecord
{

	public function tableName()
	{
		return 'm_academic_year';
	}

	public function rules()
	{
		return array(
			array('name, is_active, created_by, created_date, id_institution', 'required'),
			array('created_by, updated_by, id_institution, id_client', 'numerical', 'integerOnly'=>true),
			array('updated_date, start_time, end_time', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_academic_year'),
			'mUsers' => array(self::HAS_MANY, 'MUser', 'id_year'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'is_active' => 'Is Active',
			'created_by' => 'Created By',
			'created_date' => 'Created Date',
			'updated_by' => 'Updated By',
			'updated_date' => 'Updated Date',
			'id_institution' => 'Id Institution',
			'start_time' => 'Start Time',
			'end_time' => 'End Time',
			'id_client' => 'Id Client',
		);
	}

}
