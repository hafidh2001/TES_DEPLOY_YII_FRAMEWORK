<?php

class MUserAction extends ActiveRecord
{

	public function tableName()
	{
		return 'm_user_action';
	}

	public function rules()
	{
		return array(
			array('id_action_hidden, id_user, id_client', 'required'),
			array('id_action_hidden, id_user, id_client', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idActionHidden' => array(self::BELONGS_TO, 'MAction', 'id_action_hidden'),
			'idUser' => array(self::BELONGS_TO, 'MUser', 'id_user'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_action_hidden' => 'Id Action Hidden',
			'id_user' => 'Id User',
			'id_client' => 'Id Client',
		);
	}

}
