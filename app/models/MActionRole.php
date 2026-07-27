<?php

class MActionRole extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action_role';
	}

	public function rules()
	{
		return array(
			array('role, id_client', 'required'),
			array('id_client', 'numerical', 'integerOnly'=>true),
			array('identifier', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'tLogbookStatuses' => array(self::HAS_MANY, 'TLogbookStatus', 'id_action_role'),
			'mActionRolemaps' => array(self::HAS_MANY, 'MActionRolemap', 'id_action_role'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'role' => 'Role',
			'id_client' => 'Id Client',
			'identifier' => 'Identifier',
		);
	}

}
