<?php
/*  Snippet Name: PubKit
#  Short Desc: Form-based front-end management of news, blogs, events lists as MODX resources,
#  custom database tables including messages (contact forms, guestbooks)
#  Author: Keith Penton (KP52)
#  From original concepts by Raymond Irving (NewsPublisher), ur001 (document class)
#
#  Version: 1.6.2
#  Aug 2014
######################################################################################
#  Parameters:
#    &class       - type of item: blog, news post, event, custom record... Default = post
#    &snipFolder  - name of folder containing include files
#    &folder      - ID of folder where posts are stored
#    &uploads	  - set if forms include uploads
#    &previewId   - ID for separate preview document
#    &drafts      - ID for container of variant preview documents
#    &postid      - document ID to load after posting item. Defaults to the page created
#                  - single ID or set of tag => ID pairs `tag1 ID1, tag2 ID2`
#                  - trailing colon plus name of tag TV sets default (otherwise first in list)
#    &prefix      - string prefix for HTML anchors formed from prefix plus doc ID; default 'N'
#    &canpost     - comma delimited web groups that can post comments. leave blank for public posting
#    &extraAllowedTags  - extend list of HTML tags permitted in rich text: =`<tag1><tag2>`
#    &template    - name of template to use for generated resource. Default = system default.
#    &formtpl     - form template (chunk name or @FILE:name of file in pubKit/chunks/chunk.name.html)
#    &formName	  - name of form element; default = pkForm
#    &rtcontent   - name of a richtext content form field
#    &tags        - name of (checkbox/radiobutton) TVs containing tags as semicolon-delimited list,
#                   with fieldname and optional format class, thus:
#                   tag1,fieldname1,format1;tag2,fieldname2,format2 ...
#                   NB ***Database-record items use the &tvs parameter in the same format, not &tags***
#    &delimiter   - delimiter for tags list; default ||
#    &labelPos	  - radio|checkbox labels: 'R' (default) for right, 'L' = left, other value for no label
#    &reqTags	  - comma-delimited list of field names of required tags
#    &secureForm  - default empty; set to `1` to require token in hidden field
#    &token       - name of placeholder & session variable created for token; default 'smersh'
#    &tvs         - comma-separated list of text TVs to associate with item
#                 - value may be set using double-colon:  tv1,tv2::value2
#                 NB see not on &tags for record-type items
#    &docFields	  - extra MODX document fields associated with item
#    &<property=`value`> - override default value of (scalar) properties built into item type
#    &validate    - override built-in validation for item type (creates array):
#                   `field1:datatype,Req/Opt,message;field2:...`
#                   main types: string|date|time|token; 'err_' prepended to error for lang file lookup
#    &showinmenu  - sets whether or not item shows in menus. Defaults to false (0)
#                 - can be overridden by a form field named "show"
#    &permalinks  - create aliases from title? Default = 1;
#    &permaLength - max length of alias (but doc ID will be added in front of it). Default = 35;
#    &updatePermalinks - change permalink when title is updated. Default = 1
#    &cacheItem   - make resource cacheable or not (e.g. for Ditto/PHx clash). Default = 1
#    &clearcache  - clear the site cache after publishing an article. Default = 1
#    &debug       - 0 = no debug output (default)
#                 - 1 = dump form variables at top of form display ($_GET + $_POST)
#                 - 2 = dump params as well
#                 - 3 = dump whole of $_SESSION vars as well (big list!)
**************************************************************************************************/

$snipFolder = isset($snipFolder) ? $snipFolder : 'pubKit';
$snipPath = $modx->config['base_path'] . 'assets/snippets/' . $snipFolder.'/';

// location of files defining input forms (if used in place of chunks)
$chunk_place = $snipPath . 'chunks/';

require_once($snipPath . 'classes/document.class.inc.php');
require_once($snipPath . 'classes/resource.class.inc.php');
require_once($snipPath . 'classes/record.class.inc.php');
require_once($snipPath . 'classes/optionsbuilder.class.inc.php');
require_once($snipPath . 'pubKit.functions.php');

define('ONE_DAY',86400); //seconds in a day (for calculating unpub dates)

// For Clipper - snippet parameters no longer passed in $params
$params = $modx->event->params;

// get properties and methods for item type using class definition
$class = (isset($class)) ? $class : 'post';
require_once($snipPath . 'classes/' . $class . '.class.inc.php');

$language = (isset($language)) ? $language : "english";
require_once($snipPath . 'lang/' . $language . '.inc.php');

// get user groups that can post articles
$postgrp = isset($canpost) ? explode(",",$canpost) : array();
$allowAnyPost = count($postgrp)==0 ? true : false;

// cache options; reduce to one or zero
$clearcache  = (isset($clearcache) && $clearcache == 0) ? 0 : 1;
$cacheable   = (isset($cacheItem)  && $cacheItem  == 0) ? 0 : 1;

// get folder id where we should store articles
// else store in current document
$folder = isset($folder) ? intval($folder) : $modx->documentIdentifier;

// detect single ID postId or list of tag => ID pairs by presence of space
if (isset($postid) && strpos($postid, chr(32)) > 0) {
// Oct 2011: tag for navigation follows spec, separated by colon
// set to first tag if no nav tag specified (below)
	$postNavPlusTag = explode(':', $postid);
	$postNavTag = trim($postNavPlusTag[1]);
	$postPairs = explode(',', $postNavPlusTag[0]);
	$postid = array();
	  foreach ($postPairs as $postPair) {
		$pp = explode(chr(32), $postPair);
	    $postid[$pp[0]] = $pp[1];
	  }
}

// prefix for anchors
$prefix = isset($prefix) ? $prefix : 'N';

// TVs containing tags (checkbox, radio buttons or selection list)
$tagTvs = array();

if (isset($tags)) {
	$tagArray = explode(';', $tags);
	foreach ($tagArray as $tagItem) {
		$tagEls = explode(',', $tagItem);
		$tagTvs[$tagEls[0]]['field'] = isset($tagEls[1]) ? $tagEls[1] : $tagEls[0];
		$tagTvs[$tagEls[0]]['format'] = isset($tagEls[2]) ? $tagEls[2] : NULL;
	}

	if (empty($postNavTag)) {
		$postNavTag = $tagTvs[0]['field'];
	} else {
		$postNavTag = $tagTvs[$postNavTag]['field'];
	}
}

// delimiter for tags list. Default is || (as when no widget used)
$delimiter = isset($delimiter) ? $delimiter : '||';
// record-type classes need access to this variable
$params['delimiter'] = $delimiter;

// set rich text fields (add "tv" prefix if required)
if (isset($rtcontent)) {
  if (substr($rtcontent,0,2) != 'tv' ) {
    $rtcontent = 'tv' . $rtcontent;
  }
} else {
  $rtcontent = 'content';
}

// define tags that can be used in rich content
$allowedTags =  '<p><br><a><i><em><b><strong><pre><table><th><td><tr><img>';
$allowedTags .= '<span><div><h1><h2><h3><h4><h5><font><ul><ol><li><dl><dt><dd>';
if (isset($extraAllowedTags)) {
	$allowedTags .= $extraAllowedTags;
}

// turn &validate into array with corrected delimiter
if (isset($validate)) {
	$newValidate = array();
	$vRules = explode(';', $validate);

	foreach ($vRules as $vRule) {
		$vField = explode(':', $vRule);
		$vElements = explode(',', $vField[1]);
		$newValidate[$vField[0]] = implode('||', $vElements);
	}

	$params['validate'] = $newValidate;
}

// get template
$template = isset($template) ? $template : $modx->config['default_template'];


// name of input form (to check for postbacks)
if (!isset($formName)) {
	$formName = 'pkForm';
	$params['formName'] = 'pkForm';
	}

// set up security token for secure form. Highly recommended!
// call to snippet setSecurityToken creates placeholder named as &token
if (!empty($secureForm)) {
	$secureForm = 1;
	$token = (!empty($token)) ? $token : 'smersh';
}

// showinmenu (negated for hidemenu, modifiable by form field named "show")
$showinmenu = isset($showinmenu) ? $showinmenu : 0;
if ($showinmenu < 0 || $showinmenu > 1) {
    $showinmenu = 0;
}

// create aliases from title? default = 1; limit length
$permalinks  = (isset($permalinks)) ? $permalinks : 1;
$permaLength = (isset($permaLength)) ? $permaLength : 35;
$updatePermalinks = (isset($updatePermalinks)) ? $updatePermalinks : 1;

$debug = isset($debug) ? $debug : 0;

// allow for older standard deletion chunk
$confirmDeletionChunk = $modx->getChunk('pk.ConfirmDeletion.tpl');
if (empty ($confirmDeletionChunk)) {
	$confirmDeletionChunk = $modx->getChunk('pkConfirmDeletion');
}

/******* CRUD logic for all items (resource or record) begins here ************/

// copy input variables to array so they can be updated
$fields = array_merge($_GET, $_POST);
$fields['params'] = $params;

// If docID is set, you are editing existing item
$docId = isset($fields['docId']) ? $fields['docId'] : '';

// operation - create, delete, edit etc.
$cmd = strtolower($fields['command']);

// list variables if debug is enabled; at last a useful aspect of PHP switch behaviour!
switch ($debug) {
	case '3':
		$dbg .= "<pre>Session variables:\n" . print_r($_SESSION, true)  . '</pre>';
	case '2':
		$dbg .= "<pre>Parameters:\n" . print_r($params, true)  . '</pre>';
	case '1':
		$dbg .= "<pre>POST variables:\n" . print_r($_POST, true)  . '</pre>';
		$dbg .= "<pre>GET variables:\n" . print_r($_GET, true)  . '</pre>';
		break;
	default:
		$dbg = NULL;
}

// get properties and methods for relevant type of item
// select include needed for resource or record processing
$item = new $class($docId, $fields, $lang);

if (! $item->recordType == 'record') {
	require_once($snipPath . 'pubKit.inc.php');
} else {
	require_once($snipPath . 'pubKit.record.inc.php');
}

return $pubKit;
?>