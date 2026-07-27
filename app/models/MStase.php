<?php

class MStase extends ActiveRecord
{

	public function tableName()
	{
		return 'm_stase';
	}

	public function rules()
	{
		return array(
			array('name, id_stage, id_client, sequence', 'required'),
			array('id_stage, id_client, sequence', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_stase'),
			'mUsers' => array(self::HAS_MANY, 'MUser', 'id_stase'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idStage' => array(self::BELONGS_TO, 'MStage', 'id_stage'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'id_stage' => 'Id Stage',
			'id_client' => 'Id Client',
			'sequence' => 'Sequence',
		);
	}

}
