<?php

class MActionCategory extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action_category';
	}

	public function rules()
	{
		return array(
			array('name, id_action, id_client', 'required'),
			array('id_action, id_client', 'numerical', 'integerOnly'=>true),
			array('required_asm', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_category'),
			'mUsers' => array(self::HAS_MANY, 'MUser', 'id_sub_category'),
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'id_action' => 'Id Action',
			'id_client' => 'Id Client',
			'required_asm' => 'Required Asm',
		);
	}

}
