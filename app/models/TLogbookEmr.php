<?php

class TLogbookEmr extends ActiveRecord
{

	public function tableName()
	{
		return 't_logbook_emr';
	}

	public function rules()
	{
		return array(
			array('id_logbook', 'required'),
			array('id_logbook, age, id_client, month', 'numerical', 'integerOnly'=>true),
			array('emr_number, diagnosis, treatment, gender, patient_name, deleted_at', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'idLogbook' => array(self::BELONGS_TO, 'TLogbook', 'id_logbook'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'id_logbook' => 'Id Logbook',
			'emr_number' => 'Emr Number',
			'diagnosis' => 'Diagnosis',
			'treatment' => 'Treatment',
			'age' => 'Age',
			'gender' => 'Gender',
			'id_client' => 'Id Client',
			'month' => 'Month',
			'patient_name' => 'Patient Name',
			'deleted_at' => 'Deleted At',
		);
	}

}
