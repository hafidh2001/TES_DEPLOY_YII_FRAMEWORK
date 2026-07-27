<?php

class MActionSemester extends ActiveRecord
{

	public function tableName()
	{
		return 'm_action_semester';
	}

	public function rules()
	{
		return array(
			array('id_semester, id_action, id_client', 'required'),
			array('id_semester, id_action, id_client', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idSemester' => array(self::BELONGS_TO, 'MSemester', 'id_semester'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_semester' => 'Id Semester',
			'id_action' => 'Id Action',
			'id_client' => 'Id Client',
		);
	}

}
