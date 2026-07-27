<?php

class MAnotherRole extends ActiveRecord
{

	public function tableName()
	{
		return 'm_another_role';
	}

	public function rules()
	{
		return array(
			array('role_name, id_client', 'required'),
			array('id_client, id_action', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'mActionAnotherRoles' => array(self::HAS_MANY, 'MActionAnotherRole', 'id_another_role'),
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_another_role'),
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'role_name' => 'Role Name',
			'id_client' => 'Id Client',
			'id_action' => 'Id Action',
		);
	}

}
