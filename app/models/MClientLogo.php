<?php

class MClientLogo extends ActiveRecord
{

	public function tableName()
	{
		return 'm_client_logo';
	}

	public function rules()
	{
		return array(
			array('id_client, file', 'required'),
			array('id_client', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_client' => 'Id Client',
			'file' => 'File',
		);
	}

}
