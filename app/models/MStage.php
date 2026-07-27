<?php

class MStage extends ActiveRecord
{

	public function tableName()
	{
		return 'm_stage';
	}

	public function rules()
	{
		return array(
			array('name, label_color, id_institution, created_date, created_by, id_client', 'required'),
			array('id_institution, created_by, updated_by, id_client', 'numerical', 'integerOnly'=>true),
			array('code', 'length', 'max'=>20),
			array('updated_date', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'mSemesters' => array(self::HAS_MANY, 'MSemester', 'id_stage'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'mStases' => array(self::HAS_MANY, 'MStase', 'id_stage'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'label_color' => 'Label Color',
			'id_institution' => 'Id Institution',
			'created_date' => 'Created Date',
			'created_by' => 'Created By',
			'updated_date' => 'Updated Date',
			'updated_by' => 'Updated By',
			'code' => 'Code',
			'id_client' => 'Id Client',
		);
	}

}
