<?php

class TNotif extends ActiveRecord
{

	public function tableName()
	{
		return 't_notif';
	}

	public function rules()
	{
		return array(
			array('message, date, type, id_user, url, id_role, id_client', 'required'),
			array('id_user, id_role, id_client, id_logbook', 'numerical', 'integerOnly'=>true),
			array('read, deleted_at', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idLogbook' => array(self::BELONGS_TO, 'TLogbook', 'id_logbook'),
			'idUser' => array(self::BELONGS_TO, 'MUser', 'id_user'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'message' => 'Message',
			'date' => 'Date',
			'type' => 'Type',
			'id_user' => 'Id User',
			'url' => 'Url',
			'id_role' => 'Id Role',
			'read' => 'Read',
			'id_client' => 'Id Client',
			'id_logbook' => 'Id Logbook',
			'deleted_at' => 'Deleted At',
		);
	}

}
