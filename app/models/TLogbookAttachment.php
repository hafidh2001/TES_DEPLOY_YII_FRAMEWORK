<?php

class TLogbookAttachment extends ActiveRecord
{

	public function tableName()
	{
		return 't_logbook_attachment';
	}

	public function rules()
	{
		return array(
			array('id_logbook, id_client', 'required'),
			array('id_logbook, id_client', 'numerical', 'integerOnly'=>true),
			array('url_file, name, created_date, deleted_at', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idLogbook' => array(self::BELONGS_TO, 'TLogbook', 'id_logbook'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_logbook' => 'Id Logbook',
			'url_file' => 'Url File',
			'name' => 'Name',
			'id_client' => 'Id Client',
			'created_date' => 'Created Date',
			'deleted_at' => 'Deleted At',
		);
	}

}
