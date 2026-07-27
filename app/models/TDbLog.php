<?php

class TDbLog extends ActiveRecord
{

	public function tableName()
	{
		return 't_db_log';
	}

	public function rules()
	{
		return array(
			array('id, at, data, ref, ip', 'required'),
			array('id_user', 'numerical', 'integerOnly'=>true),
			array('mlsid', 'safe'),
		);
	}

	public function relations()
	{
		return array(
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'at' => 'At',
			'data' => 'Data',
			'ref' => 'Ref',
			'ip' => 'Ip',
			'mlsid' => 'Mlsid',
			'id_user' => 'Id User',
		);
	}

}
