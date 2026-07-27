<?php

class MSemester extends ActiveRecord
{

	public function tableName()
	{
		return 'm_semester';
	}

	public function rules()
	{
		return array(
			array('name, id_stage, id_client', 'required'),
			array('id_stage, id_client', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_semester'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idStage' => array(self::BELONGS_TO, 'MStage', 'id_stage'),
			'mUsers' => array(self::HAS_MANY, 'MUser', 'id_semester'),
			'mActionSemesters' => array(self::HAS_MANY, 'MActionSemester', 'id_semester'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'id_stage' => 'Id Stage',
			'id_client' => 'Id Client',
		);
	}

}
