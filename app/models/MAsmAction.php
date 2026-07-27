<?php

class MAsmAction extends ActiveRecord
{

	public function tableName()
	{
		return 'm_asm_action';
	}

	public function rules()
	{
		return array(
			array('id_action, id_asm_param, id_client', 'required'),
			array('id_action, id_asm_param, id_client', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idAction' => array(self::BELONGS_TO, 'MAction', 'id_action'),
			'idAsmParam' => array(self::BELONGS_TO, 'MAsmParam', 'id_asm_param'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_action' => 'Id Action',
			'id_asm_param' => 'Id Asm Param',
			'id_client' => 'Id Client',
		);
	}

}
