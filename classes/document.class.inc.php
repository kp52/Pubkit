<?php
/***************************************************************
Document class 
for PubKit v 1.5  Dec 2012  
To create new document resources, update old ones, clone and delete
Includes get and set for TVs
-----------------
Dec 2012: setTemplate to site default if name not found
***************************************************************/

class Document {
	public $contentTable;
	public $columns; // resource fields list
	public $fields;	// doc fields array
	public $tvs;	// TV array
	public $tvIds;	// TV names array
	public $oldTVs;	// TV values array
	public $isNew;	// true - new doc, false - existing doc

	function __construct($id = 0, $fields = "*") {
		global $modx;

		$this->isNew = ($id == 0);

		$this->contentTable = $modx->getFullTableName('site_content');
		$this->columns = array_keys($modx->db->getTableMetaData($this->contentTable));

		if ($this->isNew) {
			$this->fields = array(
				'pagetitle'	=> $modx->config['site_name'],
				'alias'		=> '',
				'parent'	=> 0, 
				'createdon' => time(),
				'createdby' => '0',
				'editedon' 	=> '0',
				'editedby' 	=> '0',
				'published' => $modx->config['publish_default'],
				'deleted' 	=> '0',
				'hidemenu' 	=> '1',
				'template' 	=> $modx->config['default_template'],
				'content' 	=> ''
			); 
		} else { 
			$this->fields = $modx->getPageInfo($id, 0, $fields);
			$this->TvIds();
			$this->fields['id'] = $id;
		}
	}

	function SaveAs($id) { 
		$this->isNew = false;
		$this->fields['id'] = $id;
		$this->Save();
	} 

	function Save() { 
		global $modx;
		
		if ($this->isNew) { 
			$this->fields['id'] = $modx->db->insert($this->fields, $this->contentTable);
			$this->isNew = false;
		} else {
			$id = $this->fields['id'];
			$modx->db->update($this->fields, $this->contentTable, "id = $id");
		}  

// Save TVs if any
		if (count($this->tvs) > 0) {
			$this->SaveTvs(); 
		}
		
		return $this->fields['id'];
	}

	function SaveTvs() { 
		global $modx;

		$this->oldTVs = $this->PopulateTvs();
		$tvc = $modx->getFullTableName('site_tmplvar_contentvalues');
		$id = $this->fields['id'];
	
		foreach ($this->tvs as $tv => $value) { 
			$tmplvarid = $this->tvIds[$tv];
			$value = $modx->db->escape($value); 
			
			if (!isset($this->oldTVs[$tv])) {
			$sql="INSERT INTO $tvc (tmplvarid,value,contentid) VALUES ($tmplvarid, '$value', $id)";
		} else {
			if ($this->oldTVs[$tv] == $this->tvIds[$tv]) {
				continue;
			}
			$sql="UPDATE $tvc SET value='$value' WHERE tmplvarid = $tmplvarid AND contentid = $id";
		}
		$modx->db->query($sql);
		}
	}

	function Get($field) { 
		if (!in_array($field, $this->columns)) {
			$result = $this->GetTV($field);
		} else {
			$result = isset($this->fields[$field]) ? $this->fields[$field] : null; 
		}
		return $result;
	}

	function Set($field, $value){
/*		if (substr($field, 0, 2) == 'tv') {
			$result = $this->SetTV(substr($field,2), $value);
		}
*/
		if (!in_array($field, $this->columns)) {
			$result = $this->SetTV($field, $value);
		}
		elseif ($field == 'template') {
		 	$result = $this->SetTemplate($value);
		} else {
			$result = $this->fields[$field] = $value;
		}
		return $result;
	}

	/***********************************************
	  Setting TV value
	************************************************/
	function SetTV($tv,$value){ 
		
		if (!is_array($this->tvIds)) {
			$this->tvIds();
		}
		
		if (!is_array($this->tvs)) {
			$this->tvs = array();
		}

		if (array_key_exists($tv, $this->tvIds)) { 
			$this->tvs[$tv] = $value;	
		}   ///// else error - trying to set non-existent TV
	}

	function GetTV($tv) { 
		if (!is_array($this->tvs)) {
			$this->tvs = array();
			$result = null;
		}

		if (isset($this->tvs[$tv])) {
			$result = $this->tvs[$tv];
		} else {
			$this->oldTVs = $this->PopulateTvs();
			if (isset($this->oldTVs[$tv])) {
				$result = $this->oldTVs[$tv];
			}	
		} 
		return $result;
	}
	
	/***********************************************
	  Setting doc template
	  $tpl - template name or id
	************************************************/		
	function SetTemplate($tpl){	
		global $modx;
		// Retrieve id of template if name is given
		if(!is_numeric($tpl)) {
			$tablename = $modx->getFullTableName('site_templates');
			
			$tpl = $modx->db->getValue("SELECT id FROM $tablename WHERE templatename='$tpl' LIMIT 1");
			
			if(empty($tpl)) {
//				$tpl = 0;
				$tpl = $modx->config['default_template'];
			}
		} 

		$this->fields['template']=$tpl; 

		return $tpl;
	}

	/************************************************************
	  Deleting doc with TVs
	*************************************************************/
	function Delete(){
		global $modx;

		$id = $this->fields['id'];
		$modx->db->delete($this->contentTable, "id = $id");
		$modx->db->delete($modx->getFullTableName('site_tmplvar_contentvalues'), "contentid = $id");
		$this->isNew = true;
	}
	
	/************************************************************
	  Duplicate doc with TVs
	*************************************************************/
	function Duplicate($dupTvs = true, $menuInc = 1) {
		global $modx;
		
		foreach ($this->fields as $key=>$field) {
			$this->fields[$key]  = $modx->db->escape($field);
		}
		$this->fields['alias'] = '';
		$this->fields['createdon'] = time();
		$this->fields['createdby'] = 0;
		$this->fields['editedon'] = 0;
		$this->fields['editedby'] = 0;
		$this->fields['menuindex'] += $menuInc;

		$all_tvs = $this->PopulateTvs();

// set TVs to default values if required
		if ($dupTvs) { 
			foreach ($all_tvs as $tv=>$value) {
				if (!isset($this->tvs[$tv])) {
					$this->tvs[$tv] = $value; 
				}
			}
		} else {
			
		}
		$this->oldTVs = array();
		
		$this->isNew = true;
		unset($this->fields['id']);  
	}
	
	function PopulateTvs($defaults = false) {
		global $modx;
		
		$tvars = $modx->getFullTableName('site_tmplvars');
		$tvc = $modx->getFullTableName('site_tmplvar_contentvalues');
		$tvt = $modx->getFullTableName('site_tmplvar_templates');
		
		if (!$defaults && !$this->isNew) {
			$sql = "SELECT tvs.name as name, tvc.value as value
			FROM $tvc tvc INNER JOIN $tvars tvs 
			ON tvs.id = tvc.tmplvarid 
			WHERE tvc.contentid = " . $this->fields['id'];
		} else {
			$sql = "SELECT tvs.name AS name, tvs.default_text AS value
			FROM $tvt AS tvt INNER JOIN $tvars AS tvs 
			ON tvs.id = tvt.tmplvarid 
			WHERE tvt.`templateid` = " . $this->fields['template'];
		}
		
		$result = $modx->db->query($sql);
		
		while ($row = $modx->db->getRow($result)) {
			$tvs[$row['name']] = $row['value'];
		}
		return $tvs;		
	}

	function TvIds() {
		global $modx;
		
		$this->tvIds = array();
		
		$result = $modx->db->select('id, name', $modx->getFullTableName('site_tmplvars'));
		
		while ($row = $modx->db->getRow($result)) {
			$this->tvIds[$row['name']] = $row['id'];
		}
	}
	
	function TvDefaultValues() {
		
	}
}
?>