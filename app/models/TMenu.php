<?php

class TMenu extends ActiveRecord
{

	public function tableName()
	{
		return 't_menu';
	}

	public function rules()
	{
		return array(
			array('id_user, created_by, id_action, id_client', 'required'),
			array('created_by, updated_by, id_action, id_client', 'numerical', 'integerOnly'=>true),
			array('updated_date', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'createdBy' => array(self::BELONGS_TO, 'MUser', 'created_by'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'updatedBy' => array(self::BELONGS_TO, 'MUser', 'updated_by'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_user' => 'Id User',
			'created_by' => 'Created By',
			'updated_date' => 'Updated Date',
			'updated_by' => 'Updated By',
			'id_action' => 'Id Action',
			'id_client' => 'Id Client',
		);
	}

}
