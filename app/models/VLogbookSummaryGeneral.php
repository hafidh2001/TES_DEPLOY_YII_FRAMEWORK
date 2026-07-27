<?php

class VLogbookSummaryGeneral extends ActiveRecord
{

	public function tableName()
	{
		return 'v_logbook_summary_general';
	}

	public function rules()
	{
		return array(
			array('id, id_client', 'numerical', 'integerOnly'=>true),
			array('date, ppds, nim, action, semester, stase, pin, peran, category, staff, status, attachment, emr_number, diagnosis, treatment, patient, title', 'safe'),
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
			'date' => 'Date',
			'ppds' => 'Ppds',
			'nim' => 'Nim',
			'action' => 'Action',
			'semester' => 'Semester',
			'stase' => 'Stase',
			'pin' => 'Pin',
			'peran' => 'Peran',
			'category' => 'Category',
			'staff' => 'Staff',
			'status' => 'Status',
			'attachment' => 'Attachment',
			'emr_number' => 'Emr Number',
			'diagnosis' => 'Diagnosis',
			'treatment' => 'Treatment',
			'patient' => 'Patient',
			'title' => 'Title',
			'id' => 'ID',
			'id_client' => 'Id Client',
		);
	}

}
