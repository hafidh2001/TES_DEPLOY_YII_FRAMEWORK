<?php

class MActionType extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action_type';
	}

	public function rules()
	{
		return array(
			array('name, id_client', 'required'),
			array('id_client', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'mActions' => array(self::HAS_MANY, 'MAction', 'id_type'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'id_client' => 'Id Client',
		);
	}

}
