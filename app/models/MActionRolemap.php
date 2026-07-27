<?php

class MActionRolemap extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action_rolemap';
	}

	public function rules()
	{
		return array(
			array('id_action_role, id_action, id_client', 'required'),
			array('id_action_role, id_action, id_client', 'numerical', 'integerOnly'=>true),
			array('type, is_required', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idActionRole' => array(self::BELONGS_TO, 'MActionRole', 'id_action_role'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_action_role' => 'Id Action Role',
			'id_action' => 'Id Action',
			'id_client' => 'Id Client',
			'type' => 'Type',
			'is_required' => 'Is Required',
		);
	}

}
