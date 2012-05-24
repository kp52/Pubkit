<?php
class PTA extends Record
{
	public $name = 'PTA
	';
	public $table = 'pta';
	public $columns = array('id','member','section','role','rank','updated','published');
	public $sortOrder = 'section,rank';

	public $validate = array(
		'member'=>'string||Req||playerName',
		'office'=>'string||Req||sectionSelect'
		);

	public $delMsg;

function __construct($pid=0, $fields=array(), $lang) {
	parent::__construct($pid, $fields, $lang);
	$this->delMsg = $lang['del_ctee'] . $this->member .'?';
}


function Save($pid=NULL, $fields=array()) {
// transfer values from "tvs" to DB fields
	$fields[$this->tags] = $fields['tags'];
	if (!isset($this->rank) && empty($fields['rank'])) {
		$fields['rank'] = 1;
	}
	parent::Save($pid, $fields);
}

function CustomFields($fields) {
// adjust for differences between field names and retrieved TV names for edit command
// set required format for dates and options sets (no widgets applied)
	global $modx;

	$customFields = array();
	foreach($this->columns as $column) {
		$customFields[$column] = $this->$column;
	}
	if (isset($this->tvs)) {
		foreach ($this->tvs as $tv) {
			if ($tv['field'] != $tv['column']) {
				$customFields[$tv['field']] = $this->$tv['column'];
				unset ($customFields[$tv['column']]);
			}
		}
	}
	return $customFields;
}

}
?>