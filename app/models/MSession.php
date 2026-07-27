<?php

class MSession extends ActiveRecord
{

	public function tableName()
	{
		return 'm_session';
	}

	public function rules()
	{
		return array(
			array('session_id, id_client', 'required'),
			array('id_user, id_client', 'numerical', 'integerOnly'=>true),
			array('created_at, updated_at', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idUser' => array(self::BELONGS_TO, 'MUser', 'id_user'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id_user' => 'Id User',
			'session_id' => 'Session',
			'created_at' => 'Created At',
			'updated_at' => 'Updated At',
			'id_client' => 'Id Client',
		);
	}

}
