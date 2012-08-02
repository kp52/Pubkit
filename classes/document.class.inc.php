<?php
/***************************************************************
  Name: Docmanager
  Description: Class for editing/creating/duplicating/deleting documents
  Version 0.5.3
  Author: ur001
  e-mail: ur001@mail.ru

  Example of use:
	require_once('assets/libs/docmanager/document.class.inc.php');
	$doc = new Document();
	$doc->Set('parent',$folder);
	$doc->Set('alias','post'.time());
	$doc->Set('content','document content');
	$doc->Set('template','GuestBookComments');
	$doc->Set('tvComment','post to comment');
	$doc->Save();

  Area of use:
	guestbooks, blogs, forums, frontend manager modules

  TODO:
	* document_groups

  Important:
	2) Not to be used just for receiving TV values or deleting docs. Use  
	   $modx->getTemplateVars(); and $modx->db->delete(); instead.

***************************************************************/
class Document{
	public $fields;	// doc fields array
	public $tvs;		// TV array
	
	public $tvNames;	// TV names array
	public $oldTVs;	// TV values array
	public $isNew;		// true - new doc, false - existing doc

	/***********************************************
	  Initializing class
	  $id   - existing doc id or 0 for new doc
	  $fields - comma delimited field list
	************************************************/	
	function Document($id=0,$fields="*"){
		global $modx;
		$this->isNew = $id==0;
		if(!$this->isNew){
			$this->fields = $modx->getPageInfo($id,0,$fields);
			$this->fields['id']=$id;
		}
		else
			$this->fields = array(
				'pagetitle'	=> 'New document',
				'alias'		=> '',
				'parent'	=> 0, 
				'createdon' => time(),
				'createdby' => '0',
				'editedon' 	=> '0',
				'editedby' 	=> '0',
				'published' => '1',
				'deleted' 	=> '0',
				'hidemenu' 	=> '1',
				'template' 	=> '0',
				'content' 	=> ''
			);
	}
	
	/***********************************************
	  Saving/Updating document
	************************************************/	
	function Save(){
		global $modx;
		$tablename=$modx->getFullTableName('site_content');
		if($this->isNew){
			$this->fields['id']=$modx->db->insert($this->fields, $tablename);
			$this->isNew = false;
		} else {
			$id=$this->fields['id'];
			$modx->db->update($this->fields, $tablename, "id=$id");
		}
		if(is_array($this->tvs)) $this->saveTVs();
	}


	/***********************************************
	  Receiving doc values ot TV
	  $field - doc value or TV with 'tv' prefix
	  Result: doc value, TV or null
	************************************************/	
	function Get($field){ 
		switch(1){
			case substr($field,0,2)=='tv': return $this->GetTV(substr($field,2));
			default: return isset($this->fields[$field]) ? $this->fields[$field] : null; 
		}
	}
	
	/***********************************************
	  Setting doc or TV value
	  $field - doc or TV (with prefix 'tv') name
	  $value - value
	  Result: true or false
	************************************************/	
	function Set($field, $value){
		switch(1){
			case substr($field,0,2)=='tv':		return $this->SetTV(substr($field,2), $value);
			case $field=='template':		return $this->SetTemplate($value);
			default: $this->fields[$field]=$value;	return true;
		}
	}
	
	
	/***********************************************
	  Receiving TV 
	  $name - TV name
	************************************************/
	function GetTV($tv){
		if(!is_array($this->tvs)){
			if($this->isNew) return null;
			$this->tvs=array();
		}
		// Look in the values created by Set() function
		if(isset($this->tvs[$tv])) return $this->tvs[$tv];
		// Look in the TVs already defined for the document
		// Call fillOldTVValues() if not yet retrieved
		if(!is_array($this->oldTVs)){
			if($this->isNew) return null;
			$this->oldTVs=$this->fillOldTVValues();
		}
		if(isset($this->oldTVs[$tv])) return $this->oldTVs[$tv];
		return null;
	}
	
	/***********************************************
	  Setting TV value
	************************************************/
	function SetTV($tv,$value){
		if(!is_array($this->tvs)) $this->tvs=array();
		$this->tvs[$tv]=$value;
	}

	/***********************************************
	  Setting doc template
	  $tpl - template name or id
	************************************************/		
	function SetTemplate($tpl){	
		global $modx;
		// Retrieve id of template if name is given
		if(!is_numeric($tpl)) {
			$tablename=$modx->getFullTableName('site_templates');
			$tpl = $modx->db->getValue("SELECT id FROM $tablename WHERE templatename='$tpl' LIMIT 1");
			if(empty($tpl)) return false;
		}
		
		$this->fields['template']=$tpl; 
		return true;
	}

	/************************************************************
	  Deleting doc with TVs
	*************************************************************/
	function Delete(){
		if($this->isNew) return;
		global $modx;
		$id=$this->fields['id'];
		$modx->db->delete($modx->getFullTableName('site_content'),"id=$id");
		$modx->db->delete($modx->getFullTableName('site_tmplvar_contentvalues'),"contentid=$id");
		$this->isNew=true;
	}
	
	/************************************************************
	  Duplicating doc with TVs
	*************************************************************/
	function Duplicate(){
		if($this->isNew) return;
	// escape text fields (2 Aug 2012)
		foreach ($this->fields as $key=>$field) {
			$this->fields[$key]	= mysql_real_escape_string($field);
		}
		$all_tvs=$this->fillOldTVValues();
		foreach($all_tvs as $tv=>$value)
			if(!isset($this->tvs[$tv])) {
				$this->tvs[$tv]= mysql_real_escape_string($value);
			}
		$this->oldTVs=array();
		$this->isNew=true;
		unset($this->fields['id']);
	}
	
	/************************************************************
	  Saving TV values, maintenance function. Only $tvNames values are saved, 
        If a TV exists in oldTVs, then updating, else inserting
	*************************************************************/
	function saveTVs(){
		global $modx;
		if(!is_array($this->tvNames))$this->fillTVNames();
		if(!is_array($this->oldTVs) && !$this->isNew)
			$this->oldTVs=$this->fillOldTVValues();
		else 
			$this->oldTVs = array();
			
		$tvc = $modx->getFullTableName('site_tmplvar_contentvalues');
		$id=$this->fields['id'];
		foreach($this->tvs as $tv=>$value)
		if(isset($this->tvNames[$tv])){
			$tmplvarid=$this->tvNames[$tv];		
			if(isset($this->oldTVs[$tv])){
				if($this->oldTVs[$tv]==$this->tvNames[$tv]) continue;
				$sql="UPDATE $tvc SET value='$value' WHERE tmplvarid=$tmplvarid AND contentid=$id";
			}
			else
				$sql="INSERT INTO $tvc (tmplvarid,value,contentid) VALUES ($tmplvarid,'$value',$id)";
			$modx->db->query($sql);
		}
	}
	
	/************************************************************
	  Filling TV array ($oldTVs), maintenance function. 
	  Differs from $modx->getTemplateVars
	*************************************************************/
	function fillOldTVValues(){
		global $modx;
		$tvc = $modx->getFullTableName('site_tmplvar_contentvalues');
		$tvs = $modx->getFullTableName('site_tmplvars');
		$sql = 'SELECT tvs.name as name, tvc.value as value '.
		       "FROM $tvc tvc INNER JOIN $tvs tvs ".
			   'ON tvs.id=tvc.tmplvarid WHERE tvc.contentid ='.$this->fields['id'];
		$result = $modx->db->query($sql);
		$TVs = array();
		while ($row = mysql_fetch_assoc($result)) $TVs[$row['name']] = $row['value'];
		return $TVs;
	}
	
	/************************************************************
	  Fillin TV names array ($tvNames)), maintenance function. 
	*************************************************************/	
	function fillTVNames(){
		global $modx;
		$this->tvNames = array();
		$result = $modx->db->select('id, name', $modx->getFullTableName('site_tmplvars'));
		while ($row = mysql_fetch_assoc($result)) $this->tvNames[$row['name']] = $row['id'];
	}
}
?>