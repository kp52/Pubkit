<?php
$pubKitPath = $modx->config["base_path"] . 'assets/snippets/pubKit/';

require $pubKitPath . 'classes/optionsbuilder.class.inc.php';
require $pubKitPath . 'classes/record.class.inc.php';
require $pubKitPath . 'classes/photo.class.inc.php';

require $pubKitPath . 'pubKit.functions.php';
require $pubKitPath . 'lang/english.inc.php';

$dbg = "";

$fields = $_REQUEST; 

if (isset($fields['gallery'])) {
	$gallery = $fields['gallery'];
} elseif (isset($fields['galleryId'])) {
	$gallery = $fields['galleryId'];
} else {
	$gallery = isset($gallery) ? $gallery : 'photos';
}

$photoId = isset($fields['photoId']) ? $fields['photoId'] : NULL;

if (!isset($action)) {
	if (isset($fields['action'])) {
	  $action = $fields['action'];
	}
    elseif (isset($fields['photoId'])) {
      $action = 'display';
    } else {
      $action = 'index';
	}
}

$page = isset($fields['page']) ? $fields['page'] : 1;

$thumbPrefix = isset($thumbPrefix) ? $thumbPrefix : 'tb-';

$tplSingle = isset($tplSingle) ? $tplSingle : 'gallery.single.tpl';
$tplAdd = isset($tplAdd) ? $tplAdd : 'gallery.add.tpl';
$buttonTextAdd = isset($buttonTextAdd) ? $buttonTextAdd : 'Add';

$tplManageCell = 'gallery.manage.cell.tpl';
$tplManageRow = 'gallery.manage.row.tpl';
$tplManageGrid = 'gallery.manage.container';

$item = new Photo($photoId, $fields, $lang);

// List galleries for quick jump from Add Photo page (photo class fn AddPhoto)
// Create here so TV & placeholder names are easily editable
$item->dropList = new OptionButtons('galleryList', 'galleryId');
$item->dropList->type = 'listbox';
$item->dropList->label = 'Manage gallery: ';

$item->thumb_max_width = 160;
$item->thumb_max_height = 140;
$item->photo_max_width = 600;
$item->photo_max_height = 500;

$item->rows = isset($rows) ? $rows : 100;
$item->cols = isset($cols) ? $cols : 4;

$item->galleryFolder = isset($galleryFolder) ?  $galleryFolder : 'assets/images/gallery/';
$item->galleryPath = $modx->config["base_path"] . $item->galleryFolder;

switch ($action) {
	case "add":
		$output = $item->AddPhoto($thumbPrefix, $tplAdd, $buttonTextAdd);
		break;
	case "display":
		$output = $item->Display($gallery, $fields['photoId'], $tplSingle);
		break;
	case "manage":
		$output = $item->Manage($gallery, $fields, $tplManageCell, $tplManageRow, $tplManageGrid);
		break;
	case "caption": 
		$output = $item->Caption($fields['photoId'], $mgrPage, $tplManageCaptions);
		break;
	case "index":
		$output = $item->Index($gallery);
}
if ($debug == 1) {
	$dbg .= '<pre>' . print_r($fields, 1) . print_r($item, 1) . '</pre>';
}

return $dbg . $output;
?>