<?php

class MScoreOption extends ActiveRecord
{

	public function tableName()
	{
		return 'm_score_option';
	}

	public function rules()
	{
		return array(
			array('score, id_client', 'required'),
			array('id_action, id_client', 'numerical', 'integerOnly'=>true),
			array('score', 'numerical'),
		);
	}

	public function relations()
	{
		return array(
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'score' => 'Score',
			'id_action' => 'Id Action',
			'id_client' => 'Id Client',
		);
	}

}
