<?php

class TAuditTrails extends ActiveRecord
{

	public function tableName()
	{
		return 't_audit_trails';
	}

	public function rules()
	{
		return array(
			array('activity, id_user, timestamp, id_client', 'required'),
			array('id_user, id_client', 'numerical', 'integerOnly'=>true),
			array('ip_user, type, meta', 'safe'),
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
			'id' => 'ID',
			'activity' => 'Activity',
			'ip_user' => 'Ip User',
			'id_user' => 'Id User',
			'timestamp' => 'Timestamp',
			'type' => 'Type',
			'meta' => 'Meta',
			'id_client' => 'Id Client',
		);
	}

}
