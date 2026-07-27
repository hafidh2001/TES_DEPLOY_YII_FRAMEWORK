<?php

class TLogbookStatus extends ActiveRecord
{

	public function tableName()
	{
		return 't_logbook_status';
	}

	public function rules()
	{
		return array(
			array('id_user, id_logbook, id_action_role, id_client', 'required'),
			array('id_user, id_logbook, id_action_role, id_client', 'numerical', 'integerOnly'=>true),
			array('status, date_time, notes, deleted_at', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idUser' => array(self::BELONGS_TO, 'MUser', 'id_user'),
			'idActionRole' => array(self::BELONGS_TO, 'MActionRole', 'id_action_role'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idLogbook' => array(self::BELONGS_TO, 'TLogbook', 'id_logbook'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_user' => 'Id User',
			'id_logbook' => 'Id Logbook',
			'id_action_role' => 'Id Action Role',
			'status' => 'Status',
			'date_time' => 'Date Time',
			'id_client' => 'Id Client',
			'notes' => 'Notes',
			'deleted_at' => 'Deleted At',
		);
	}

}
