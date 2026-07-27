<?php

class MAsmParam extends ActiveRecord
{

	public function tableName()
	{
		return 'm_asm_param';
	}

	public function rules()
	{
		return array(
			array('name, min_score, max_score, id_client', 'required'),
			array('id_client', 'numerical', 'integerOnly'=>true),
			array('min_score, max_score', 'numerical'),
		);
	}

	public function relations()
	{
		return array(
			'tLogbookAsms' => array(self::HAS_MANY, 'TLogbookAsm', 'id_asm_param'),
			'mAsmActions' => array(self::HAS_MANY, 'MAsmAction', 'id_asm_param'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'min_score' => 'Min Score',
			'max_score' => 'Max Score',
			'id_client' => 'Id Client',
		);
	}

}
