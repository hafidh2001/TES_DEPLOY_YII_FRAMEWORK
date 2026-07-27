<?php

class VPpdsScoringFixed extends ActiveRecord
{

	public function tableName()
	{
		return 'v_ppds_scoring_fixed';
	}

	public function rules()
	{
		return array(
			array('id_logbook, id_client', 'numerical', 'integerOnly'=>true),
			array('psikomotor, knowledge, afektif, total', 'numerical'),
			array('ppds, nim, inisial_code, semester, stase, pin, staff, action, date_logbook, title, notes, peran, category, uuid', 'safe'),
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
			'ppds' => 'Ppds',
			'nim' => 'Nim',
			'inisial_code' => 'Inisial Code',
			'semester' => 'Semester',
			'stase' => 'Stase',
			'pin' => 'Pin',
			'staff' => 'Staff',
			'action' => 'Action',
			'date_logbook' => 'Date Logbook',
			'title' => 'Title',
			'notes' => 'Notes',
			'peran' => 'Peran',
			'category' => 'Category',
			'psikomotor' => 'Psikomotor',
			'knowledge' => 'Knowledge',
			'afektif' => 'Afektif',
			'total' => 'Total',
			'id_logbook' => 'Id Logbook',
			'uuid' => 'Uuid',
			'id_client' => 'Id Client',
		);
	}

}
