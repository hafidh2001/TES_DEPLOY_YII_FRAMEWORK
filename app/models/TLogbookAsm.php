<?php

class TLogbookAsm extends ActiveRecord
{

	public function tableName()
	{
		return 't_logbook_asm';
	}

	public function rules()
	{
		return array(
			array('id_logbook, id_asm_param, score, created_by, created_date, id_client', 'required'),
			array('id_logbook, id_asm_param, created_date, updated_by, id_client', 'numerical', 'integerOnly'=>true),
			array('score', 'numerical'),
			array('updated_date, deleted_at', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idAsmParam' => array(self::BELONGS_TO, 'MAsmParam', 'id_asm_param'),
			'idLogbook' => array(self::BELONGS_TO, 'TLogbook', 'id_logbook'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_logbook' => 'Id Logbook',
			'id_asm_param' => 'Id Asm Param',
			'score' => 'Score',
			'created_by' => 'Created By',
			'created_date' => 'Created Date',
			'updated_date' => 'Updated Date',
			'updated_by' => 'Updated By',
			'id_client' => 'Id Client',
			'deleted_at' => 'Deleted At',
		);
	}

}
