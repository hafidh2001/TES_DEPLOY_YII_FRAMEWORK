<?php

class MActionAnotherRole extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action_another_role';
	}

	public function rules()
	{
		return array(
			array('id_action, id_another_role, required_asm, id_client', 'required'),
			array('id_action, id_another_role, id_client', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idAnotherRole' => array(self::BELONGS_TO, 'MAnotherRole', 'id_another_role'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_action' => 'Id Action',
			'id_another_role' => 'Id Another Role',
			'required_asm' => 'Required Asm',
			'id_client' => 'Id Client',
		);
	}

}
