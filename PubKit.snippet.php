<?php
/*  Snippet Name: PubKit
#  Short Desc: Form-based front-end management of news, blogs, events lists as MODX resources,
#  custom database tables including messages (contact forms, guestbooks)
#  Author: Keith Penton (KP52), from original NewsPublisher by Raymond Irving; uses document class by ur001
#
#  Version: E1.0.0
#  October 2011
#  - default tag TV changed to to "noTags" (was pkTags)
#  - form name now parameter (was fixed as pkForm)
#  - showinmenu parameter fixed
#  - &updatePermalinks - renamed, code added to make it work
#  - Multiple tags built in
#  - added &labelPos for option set label position
#  - added uploads, clone command (in .inc files)
#  - set additional TVs and doc fields via parameters
#  - "move" command added
#
######################################################################################
#  Parameters:
#    &class       - type of item (MANDATORY): blog or news post, event, custom record... Default = post
#    &snipFolder  - name of folder containing include files
#    &folder      - ID of folder where posts are stored
#	 &mail		  - set if PHPmailer to be used
#	 &uploads	  - set if forms include uploads
#    &postid      - document ID to load after posting item. Defaults to the page created
#                 - single ID or set of tag => ID pairs `tag1 ID1, tag2 ID2`
#                 - trailing colon plus name of tag TV to set default (otherwise first in list)
#    &prefix      - string prefix for HTML anchors formed from prefix plus doc ID; default 'N'
#    &canpost     - comma delimited web groups that can post comments. leave blank for public posting
#	 &extraAllowedTags  - extend list of HTML tags permitted: =`<tag1><tag2>`
#    &template    - name of template to use for generated resource. Default = system default.
#    &formtpl     - form template (chunk name or @FILE:name of file in pubKit/chunks/chunk.name.html)
#    &formName	  - name of form element; default = pkForm
#    &rtcontent   - name of a richtext content form field
#    &rtsummary   - name of a richtext summary form field
#    &tags        - name of (checkbox/radiobutton) TVs containing tags
#					as semicolon-delimited list,
#					with fieldname and optional format class, thus:
#					tag1,fieldname1,format1;tag2,fieldname2,format2 ...
#	 &labelPos	  - radio|checkbox labels: 'R' (default) for right, 'L' = left, other value for no label
#    &delimiter   - delimiter for tags list; default ||
#	 &tvs         - comma-separated list of text TVs to associate with item
#				    value may be set using double-colon:  tv1,tv2::value2
#	 &docFields	  - extra MODX document fields associated with item
#	 &<item property> - override default value of properties built into item type
#    &showinmenu  - sets whether or not item shows in menus. Defaults to false (0)
#				  - can be overridden by a form field named "show"
#    &permalinks  - create aliases from title? Default = 1;
#    &permaLength - max length of alias (but doc ID will be added in front of it). Default = 35;
#    &updatePermalinks - change permalink when title updated. Default = 0
#				    needs TV called pkUpdatePermalinks, placeholder named [+updateAlias+]
#    &cacheItem   - make resource cacheable or not (e.g. for Ditto/PHx clash). Default = 1
#    &clearcache  - clear the site cache after publishing an article. Default = 1
#    &debug       - 0 = no debug output (default)
#                 - 1 = dump form variables at top of form display ($_GET + $_POST)
#				  - 2 = dump params as well
#				  - 3 = dump whole of $_SESSION vars as well (big list!)
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

if (!empty($uploads)) {
	require_once($snipPath . 'classes/upload.class.inc.php');
}

if (!empty($mail)) {
	require_once('manager/includes/controls/class.phpmailer.php');
}

define('ONE_DAY',86400); //seconds in a day (for calculating unpub dates)

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
// use first tag for multiple &postid navigation if necessary
		if (empty($postNavTag) && empty($tagTvs)) {
			$postNavTag = $tagEls[0];
		}
		$tagTvs[$tagEls[0]]['field'] = isset($tagEls[1]) ? $tagEls[1] : $tagEls[0];
		$tagTvs[$tagEls[0]]['format'] = isset($tagEls[2]) ? $tagEls[2] : NULL;
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

if (isset($rtsummary)) {
  if (substr($rtsummary,0,2) != 'tv' ) {
    $rtsummary = 'tv' . $rtsummary;
  }
} else {
  $rtsummary = 'introtext';
}

// define tags that can be used in rich content
$allowedTags =  '<p><br><a><i><em><b><strong><pre><table><th><td><tr><img>';
$allowedTags .= '<span><div><h1><h2><h3><h4><h5><font><ul><ol><li><dl><dt><dd>';
if (isset($extraAllowedTags)) {
	$allowedTags .= $extraAllowedTags;
}

// get template
$template = isset($template) ? $template : $modx->config['default_template'];

// name of input form (to check for postbacks)
if (!isset($formName)) {
	$formName = 'pkForm';
	$params['formName'] = 'pkForm';
	}

// showinmenu (negated for hidemenu, modifiable by form field named "show")
$showinmenu = isset($showinmenu) ? $showinmenu : 0;
if ($showinmenu < 0 || $showinmenu > 1) {
    $showinmenu = 0;
}

// create aliases from title? default = 1; limit length
$permalinks  = (isset($permalinks)) ? $permalinks : 1;
$permaLength = (isset($permaLength)) ? $permaLength : 35;
//$updatePermalinks = (isset($updatePermalinks)) ? $updatePermalinks : 0;

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
$cmd = $fields['command'];

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