<?php

class MHospital extends ActiveRecord
{

	public function tableName()
	{
		return 'm_hospital';
	}

	public function rules()
	{
		return array(
			array('name, created_by, created_date, id_client', 'required'),
			array('created_by, updated_by, id_client', 'numerical', 'integerOnly'=>true),
			array('address, notes, longitude, latitude, updated_date, code', 'safe'),
		);
	}

	public function relations()
	{
		return array(
			'createdBy' => array(self::BELONGS_TO, 'MUser', 'created_by'),
			'idClient' => array(self::BELONGS_TO, 'MClient', 'id_client'),
			'updatedBy' => array(self::BELONGS_TO, 'MUser', 'updated_by'),
			'tLogbooks' => array(self::HAS_MANY, 'TLogbook', 'id_hospital'),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'address' => 'Address',
			'notes' => 'Notes',
			'longitude' => 'Longitude',
			'latitude' => 'Latitude',
			'created_by' => 'Created By',
			'created_date' => 'Created Date',
			'updated_date' => 'Updated Date',
			'updated_by' => 'Updated By',
			'id_client' => 'Id Client',
			'code' => 'Code',
		);
	}

}
