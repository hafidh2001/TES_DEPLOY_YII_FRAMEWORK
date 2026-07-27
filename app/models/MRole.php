<?php

class MRole extends ActiveRecord
{

	public function tableName()
	{
		return 'm_role';
	}

	public function rules()
	{
		return array(
			array('name, created_date, created_by, id_client', 'required'),
			array('created_by, updated_by, id_client', 'numerical', 'integerOnly'=>true),
			array('updated_date', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'mUsers' => array(self::HAS_MANY, 'MUser', 'id_role'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'created_date' => 'Created Date',
			'created_by' => 'Created By',
			'updated_date' => 'Updated Date',
			'updated_by' => 'Updated By',
			'id_client' => 'Id Client',
		);
	}

}
