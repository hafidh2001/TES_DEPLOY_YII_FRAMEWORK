<?php

class MActionStatus extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action_status';
	}

	public function rules()
	{
		return array(
			array('status, id_action, id_client', 'required'),
			array('id_action, id_client', 'numerical', 'integerOnly'=>true),
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
			'status' => 'Status',
			'id_action' => 'Id Action',
			'id_client' => 'Id Client',
		);
	}

}
