<?php
class Photo extends Record
{
	public $name = 'Photo';

	public $table = 'gallery';
	public $columns = array('rank', 'id', 'filename', 'galleryId', 'updated',
		'caption', 'subcap', 'alt_text');

	public $nsId;  // non-sequential ID, no autoincrement in DB. Affects Save
/*	public $id;
	public $galleryId;
	public $filename;
	public $caption;
	public $subcap;
	public $alt_text;
	public $updated;
*/
	public $sortOrder = 'galleryId, rank';
	public $delForm = 'photo.delete.tpl';

	public 	$prevPtr = '&laquo;';
	public 	$nextPtr = '&raquo;';
	public 	$divider = '|';

	public $validate = array(
			'caption'=>'string||Req||photo_caption',
			'alt_text'=>'string||Req||photo_alt'
			);


function __construct($pid=0, $fields=array(), $lang) {
	if (empty($pid)) {
		$this->nsId = "N" . (strtotime('now') - strtotime('01 jan 2008'));
	} else {
		$this->nsId = $pid;
	}
	$this->fields = $fields;
	parent::__construct($pid, $fields, $lang);
}

function getGalleryRange($gallery) {
// return rank of first & last pictures in given gallery
	global $modx;

	$firstRank= $modx->db->getValue(
		$modx->db->select('MIN(rank)', $this->table, "galleryId = '$gallery'")
	);

	$lastRank= $modx->db->getValue(
		$modx->db->select('MAX(rank)', $this->table, "galleryId = '$gallery'")
	);

	$prevId = $modx->db->getValue(
		$modx->db->select('id', $this->table, 'rank = ' . ($this->rank - 1))
	);

	$nextId= $modx->db->getValue(
		$modx->db->select('id', $this->table, 'rank = ' . ($this->rank + 1))
	);

	return array(
		'first'=>$firstRank,
		'last'=>$lastRank,
		'prev'=>$prevId,
		'next'=>$nextId,
		'count'=>(++$lastRank - $firstRank)
	);
}

function Caption($id, $managerPage, $tpl) {
// update captions for individual photo
	$item = new Photo($id, NULL, $lang);
	$gallery = $item->galleryId;

	if ($_POST['submit'] == 'Update') {
		$updates = array(
			'caption'  =>$_POST['caption'],
			'subcap'   =>$_POST['subcap'],
			'alt_text' =>$_POST['altText']
		);
		$item->Save($id, $updates);
	}

	return $this->Display($gallery, $id, $tpl, $managerPage);
}

function Display($gallery, $id, $tpl, $mgrIndex=NULL) {
// display a single photo, with alt text, links etc.
// also used by Caption - then $mgrIndex is ID of gallery management page
	global $modx;
	$output = "";

	$result = $modx->db->select('*', $this->table, "id='$id'");

	if ($info = $modx->db->getRow($result)) {
		$imgInfo = getimagesize($this->galleryPath . $info['filename']);
		$dimensions = $imgInfo[3];
		$ph['id']    = $id;
		$ph['gallery'] = $info['galleryId'];
		$ph['caption']    = $info['caption'];
		$ph['subCaption'] = $info['subcap'];
		$ph['altText']    = $info['alt_text'];
		$ph['photoFile'] = $this->galleryFolder . $info['filename'];
		$ph['dimensions'] = $imgInfo[3];

		$nav = $this->getGalleryRange($gallery); 
		$page= ceil(($info['rank'] - $nav['first'] + 1) / ($this->rows * $this->cols));

		if (empty($mgrIndex)) {
			$linkBase = '[~[*id*]~]';
			$ph['indexPage'] = $linkBase . '?p=' . $page;
		} else {
			$ph['indexPage'] = '[~' . $mgrIndex . '~]?gallery=' . $info['galleryId'];
			$linkBase = '[~[*id*]~]';
		}

		$ph['prevId'] = $nav['prev'];
		$ph['nextId'] = $nav['next'];

		if (isset ($nav['prev']) AND $info['rank'] != $nav['first']) {
			$data = array($linkBase, $nav['prev'], $this->lang['photo_prev']);
			$ph['prevPhoto'] = $this->linkString('photoPrevNextLive', $data);
		} else {
			$data = array($this->lang['photo_prev_nil']);
			$ph['prevPhoto'] = $this->linkString('photoPrevNextDead', $data);
			$ph['prevDisabled'] = ' disabled="disabled"';
		}

		if (isset ($nav['next']) AND $info['rank'] != $nav['last']) {
			$data = array($linkBase, $nav['next'], $this->lang['photo_next']);
			$ph['nextPhoto'] = $this->linkString('photoPrevNextLive', $data);
		} else {
			$data = array($this->lang['photo_next_nil']);
			$ph['nextPhoto'] = $this->linkString('photoPrevNextDead', $data);
			$ph['nextDisabled'] = ' disabled="disabled"';
		}

		$output .= $modx->parseChunk($tpl, $ph, '[+', '+]');
	} else {
		$output = 'Picture is missing';
	}

	return $output;
}

function Index($gallery) { 
	global $modx;
	$ph = array();

	$thumbsUrl = $this->galleryFolder . 'thumbs/';
	$pixDir = $modx->config['base_path'] . $this->galleryFolder;

	$perPage = $this->rows * $this->cols;
	$nav = $this->getGalleryRange($gallery);
	$totalPics = $nav['count'];
	$totalPages = floor(($totalPics - 1) / $perPage) + 1;

	if (isset($this->fields['p'])) {
		$start = --$this->fields['p'] * $perPage + 1;
	} elseif (isset($this->fields['start'])) {
		$start = $this->fields['start'];
	} else {
		$start = 1;
	}
	if ($start < 1) {
		$start = 1;
	}
	if ($start > $totalPics) {
		$start = $totalPics;
	}

// adjust $start for page boundary, counting from zero
	$page = floor(--$start / $perPage);
	$start = $page * $perPage;

	$queryWhere = "galleryId='$gallery' ";
	$thumbs = $modx->db->select(
		'*', $this->table, $queryWhere,
		'rank ASC', "$start, $perPage"
		);

	$rowSet = "";
	for ($j=0 ; $j<$this->rows; $j++) {

		$thumbSet = "";

		for ($k=0; $k<$this->cols; $k++) {
			if ($picInfo = $modx->db->getRow($thumbs)) {
				$picFile = $picInfo['filename'];
				$title = $picInfo['caption'] . '; ' . $picInfo['subcap'];
				$thumb = $thumbsUrl . 'tb-' . $picFile;
				$tb = array(
					'page'=>$page + 1,
					'picFile'=>$picFile,
					'dir'=>$pixDir,
					'folder'=>$this->galleryFolder,
					'id'=>$picInfo['id'],
					'caption'=>$picInfo['caption'],
					'title'=>$title,
					'thumb'=>$thumb,
/* As yet unimplemented (= "what was this for?")
					'pictureUrl'=>$picAsUrl,
					'pictureRef'=>$picAsName
*/
				);
				$thumbSet .= $modx->parseChunk('gallery.cell.tpl', $tb, '[+', '+]');
			}
		}

		if (!empty($thumbSet)) {
			$ph = array('row'=>$thumbSet);
			$rowSet .= $modx->parseChunk('gallery.row.tpl', $ph, '[+', '+]');
		} else {
			break;
		}
	}

	$ph['gallery.index.thumbs'] = $rowSet;

	if ($totalPages > 1) { 
    	if ($page > 0) {
    		$data = array('Previous', $page, $this->prevPtr, $this->divider);
    		$ph['prevPage'] = $this->linkString('galPrevNextPageLive', $data);
    	} else {
    		$data = array($this->prevPtr, $this->divider);
    		$ph['prevPage'] = $this->linkString('galPrevNextPageDead', $data, $divider);
    	}

    	if ($page < ($totalPages - 1)) {
    		$data = array('Next', $page + 2, $this->nextPtr, NULL);
    		$ph['nextPage'] = $this->linkString('galPrevNextPageLive', $data);
    	} else {
    		$data = array($this->nextPtr, NULL);
    		$ph['nextPage'] = $this->linkString('galPrevNextPageDead', $data);
    	}

		$ph['pageLinks'] = "";
		for ($j=1; $j <= $totalPages; $j++) {
			if ($j != ($page + 1)) {
				$data = array($j, $j, $this->divider);
				$ph['pageLinks'] .= $this->linkString('galPageNumLinkLive', $data);
			} else {
				$data = array($j, $this->divider);
				$ph['pageLinks'] .= $this->linkString('galPageNumLinkDead', $data);
			}
		}
	$ph['cols'] = $this->cols;
	}

	$output = $modx->parseChunk('gallery.index.container', $ph, '[+', '+]');

	return $output;
}

private function linkString($tpl, $data) {
// format strings for live/disabled links without tangles
	$templates = array(
	'photoPrevNextLive'=>'<a href=%s?photoId=%s>%s</a>',
	'photoPrevNextDead'=>'<span class="greyed">%s</span>',
	'galPrevNextPageLive'=>'<a title="%s page" class=pagelist href="[~[*id*]~]?p=%s">%s</a>%s',
	'galPrevNextPageDead'=>'<span class=pagelist>%s</span>%s',
	'galPageNumLinkLive'=>'<a class="linkpage" href="[~[*id*]~]?p=%s">%s</a>%s',
	'galPageNumLinkDead'=>'<span class="linkpage">%s</span>%s'
	);
	$formatted = vsprintf($templates[$tpl], $data);
	return $formatted;
}

function AddPhoto($thumbPrefix, $tpl, $buttonText) {
	global $modx;
	$basePath = $modx->config['base_path'];
	$output = "";

	$sections = new OptionButtons('galleryList', 'galleryId');
	$setBaseName = $sections->setName;

	$command = $this->fields['command'];

	$stockLoc = $this->galleryFolder . 'stock/';
	$filelist = glob($stockLoc . '{*.jpg,*.JPG}', GLOB_BRACE);

	if ($command === 'upload') {
		$result = Upload('upl_file', $stockLoc, 'JPG');
		if (!is_numeric($result)) {
			$uploaded = basename($result);
			$uploadPath = $stockLoc . $uploaded;

			if (substr($uploaded, 0, strlen($thumbPrefix)) == $thumbPrefix) {
				rename($basePath . $result,
				$basePath . $stockLoc . 'stock-thumbs/' .$uploaded);
			} else {
// 6 sep 11: use max picture size to resize if set
				if (isset($this->photo_max_width) && isset($this->photo_max_height)) 				{
					$this->Resize($uploadPath, $uploadPath, $this->photo_max_width, $this->photo_max_height);
				}
			}

		} else {
			$modx->setPlaceholder('errors', $this->lang['upload_err_' . $output]);
			$output = "";
		}
	}

	if ($command === 'insert') {
// which item was selected? Names are index prefixed by one character
		$selected = array_keys($this->fields,$buttonText);
		$selected = intval(substr($selected[0],1));

		$this->fields['caption'] = $this->fields['f_caption'][$selected];
		$this->fields['subcap'] = $this->fields['f_subcap'][$selected];
		$this->fields['alt_text'] = $this->fields['f_alt_text'][$selected];

// set single identifier for "gallery selected"
		$this->fields['gallery_sel'] = $this->fields['galleryId' . $selected];

// check for mandatory fields (e.g. Caption, Alt text)
		$errors = $this->CheckFields($this->fields);
	 	$message = NULL;

		if (!empty($errors)) {
			foreach ($errors as $error) {
				$message .= "$error <br /> \n";
			}
	        $message = '<div id="inputErrors">' . $message . '</div>';
			$modx->setPlaceholder('errors', $message);
		} else {
// Move item to the gallery
// move the files first (limits disaster if things go wrong)
			$this->fields['filename'] = basename($filelist[$selected]);
			$this->fields['galleryId'] = $this->fields['galleryId' . (string)$selected];
			$galleryLoc = MODX_BASE_PATH . $this->galleryFolder;
			$galleryThumbs = $galleryLoc . 'thumbs/';

			$thumb = MODX_BASE_PATH . $stockLoc . 'stock-thumbs/'
			. $thumbPrefix . $this->fields['filename'];

// avoid spaces, make names lowercase
			$fn = str_replace(chr(32), '_', strtolower($this->fields['filename']));

// avoid duplicates - add first unique (2,3,..) to filename
			$unique = 2;
			$ufn = $fn;
			$fnElements = explode('.', $fn);
			$ext = array_pop($fnElements);

			while (file_exists($galleryLoc . $ufn)) {
				$ufn = implode('.',$fnElements) . '(' . $unique++ . ').' . $ext;
			}

			rename($filelist[$selected], $galleryLoc . $ufn);
			rename($thumb, $galleryThumbs . $thumbPrefix . $ufn);

			$galleryRange = $this->getGalleryRange($this->fields['galleryId']);
//			$this->fields['rank'] = $galleryRange['last'] + 1;
			$this->fields['rank'] = 0;

			$this->fields['filename'] = $ufn; // save new name in database
			$this->Save('', $this->fields);

			resetFormRank($this->table, 1, $order=$this->sortOrder);

			array_splice($this->fields['f_caption'], $selected,1);
			array_splice($this->fields['f_subcap'], $selected,1);
			array_splice($this->fields['f_alt_text'], $selected,1);
		}
	}

// Offer navigation to gallery management pages (dropList created in snippet)
// Add destination-free prompt, so any selection triggers "change" event
// Bookmark it for jScript activator
	$zilch = 'No selection';
	$modx->setPlaceholder('zilch', $zilch);  // used in jQuery jump code
	$selectPrompt = array('-- Select --'=>$zilch);
	$this->dropList->options = array_merge($selectPrompt,$this->dropList->options);
	$output .= $this->dropList->label . $this->dropList->BuildSet($zilch);

//  refresh file list
	$filelist = glob($stockLoc . '{*.jpg,*.JPG}', GLOB_BRACE);

	$outputList = array();

	foreach ($filelist as $index=>$filePath) {
		$outputList['index'] = $index;
		$outputList['buttonText'] = $buttonText;
		$outputList['filename'] = basename($filePath);
		$thumbFile = $stockLoc . 'stock-thumbs/tb-' . $outputList['filename'];
		if (!file_exists($thumbFile)) {
	// create thumbnail if none uploaded
			$this->Resize($filePath, $thumbFile, $this->thumb_max_width, $this->thumb_max_height);
		}

		$outputList['thumb'] = $stockLoc . '/stock-thumbs/tb-' . $outputList['filename'];

		$outputList['caption'] = $this->fields['f_caption'][$index];
		$outputList['subcap'] = $this->fields['f_subcap'][$index];
		$outputList['alt_text'] = $this->fields['f_alt_text'][$index];
		$sections->setName = $setBaseName . $index;

		if ($index >= $selected) {
			$optionValue = $setBaseName . ($index + 1);
		} else {
			$optionValue = $setBaseName . ($index);
		}

		$optionValue = array($this->fields[$optionValue]);

		$outputList['section'] = $sections->BuildSet($optionValue);

		$output .= $modx->ParseChunk($tpl, $outputList, '[+','+]');
	}
	return $output;
}

function Manage($gallery, $fields, $thumbTpl, $rowTpl, $gridContainer) {
	global $modx;

	$id = $fields['docId'];

	if (stripos('Archive==archive', $this->dropList->definition[elements]) === false){
		$this->dropList->options[Archive] = 'archive';
	}

	$modx->setPlaceholder('selectGallery', $this->dropList->BuildSet($gallery));

	switch ($fields['command']) {
		case 'Go':
		case NULL:
			$cmd = 'edit'; break;
		case 'Move item':
			$cmd = 'move'; break;
		case 'Transfer':
			$cmd = 'moveGallery'; break;
		default:
			$cmd = $fields['command'];
	}

	if ($cmd == 'move') {
		$item = new Photo($id, NULL, $lang);
		$oldPosition = $item->rank;
		$newPosition = $fields['to'] + $fields['offset'];
		if ($newPosition != $oldPosition) {
			$adj = ($newPosition < $oldPosition) ? -1 : +1;
			$rank = setFormRank($this->table, $newPosition, 2, $adj);
			$item->Save($id, array('rank'=>$rank));
			resetFormRank($this->table, 1, $item->sortOrder);
		}
		$cmd = 'edit';
	}

	elseif ($cmd == 'moveGallery') {
		$item = new Photo($id, NULL, $lang);
		$oldGallery = $item->galleryId;
		$destination = $fields['toGallery'];
		if ($destination != $oldGallery) {
			$update = array();
			$update['galleryId'] = $destination;
			$update['rank'] = 999999; //insert at end of new home
			$item->Save($id, $update);
			resetFormRank($this->table, 1, $item->sortOrder);
			$landing = $modx->documentObject['alias'];
			$landing .= '?gallery=' . $destination;
			$modx->sendRedirect($landing);
		} else {
			$cmd = 'edit';
		}
	}
	elseif ($cmd == 'delete') {
		$item = new Photo($id, NULL, $lang);

		$info = array(
			'gallery'  => $item->galleryId,
			'filename' => $item->filename,
			'caption'  => $item->caption,
			'docId'    => $item->id
		);
		$output = $modx->parseChunk($item->delForm, $info, '[+', '+]');
	}

	elseif ($cmd == 'remove') {
		if ($fields['Confirm'] == 'Remove') {
			$item = new Photo($id, NULL, $lang);
			$filename = $item->filename;

			switch ($fields['removeTo']) {
				case 'archive':
					$oldGallery = $item->galleryId;
					$update = array();
					$update['galleryId'] = 'archive';
					$update['rank'] = 999999;
					$item->Save($id, $update);
					resetFormRank($this->table, 1, $item->sortOrder);
					$landing = $modx->documentObject['alias'];
					$landing .= '?gallery=' . $oldGallery;
					$modx->sendRedirect($landing);
					break;

				case 'stock':
					rename($this->galleryPath . $filename,
						$this->galleryPath . 'stock/' . $filename);
					rename($this->galleryPath . 'thumbs/tb-' . $filename,
						$this->galleryPath .  'stock/thumbs/tb-' . $filename);
						$item->Delete($fields['docId']);
					break;

				case 'kill' :
					unlink($this->galleryPath . $filename);
					unlink($this->galleryPath . 'thumbs/tb-' . $filename);
					$item->Delete($fields['docId']);
			}
		}
		$cmd = 'edit';
	}

	if ($cmd == 'edit') { 
	  
	// get the thumbnails
		$thumbs = $modx->db->select('id', $this->table, "galleryId = '$gallery' ", 'rank ASC');
        $nav = $this->getGalleryRange($gallery); 
        $start = $nav['first'];
// redundant        $finish = $nav['last'];
		
        $offset = --$start;

		// set Move and Transfer dropdowns. Source restricted to current gallery
		$params = array(
			'action'=>'move',
			'table'=>'gallery',
			'where'=>"galleryId = '$gallery' ",
			'name'=>'to',
		);
		$moveItemList = $modx->runSnippet('itemDropList', $params);

		// set up destinations for transfer to another gallery
		$destList = new OptionButtons('galleryList', 'toGallery');
		$destList->type = 'listbox';
		$destination = $destList->BuildSet(array($gallery));

		$params['sourceOnly'] = 1;
		$moveGalleryList = $modx->runSnippet('itemDropList', $params);

		$moveControls = array(
        'galleryId' => $gallery,
		'movePhoto' => $moveItemList,
		'destination' => $destination,
		'moveToGallery' => $moveGalleryList,
		'offset'=> $offset
		);

		$controls .= $modx->parseChunk('gallery.controls.tpl', $moveControls, '[+', '+]');
		$modx->setPlaceholder('controls', $controls);

// create picture grid
		$rowSet = "";
		do {
			$thumbSet = "";

			for ($k=0; $k<$this->cols; $k++) { 
				if ($id = $modx->db->getValue($thumbs)) {
					$item = new Photo($id, NULL, $lang); 
					$info = get_object_vars($item);
					$info['index'] = $item->rank - $start;
					$thumbSet .= $modx->parseChunk($thumbTpl, $info, '[+', '+]');
				} else {
					break;
				}
			}

			if (!empty($thumbSet)) { 
				$ph = array('row'=>$thumbSet);
				$rowSet .= $modx->parseChunk($rowTpl, $ph, '[+', '+]');
			} else {
				break;
			}
		} while (!empty($thumbSet));

		$ph['thumbs'] = $rowSet;
		$output = $modx->parseChunk($gridContainer, $ph, '[+', '+]');
	}

	return $output;
}

function Resize($srcFile, $destFile, $new_w, $new_h, $quality=90) {
/* 	$srcFile	Original filename
	$destFile	Filename of the resized image
	$new_w		width of resized image
	$new_h		height of resized image
*/
	$srcFile = MODX_BASE_PATH . $srcFile;
	$destFile = MODX_BASE_PATH . $destFile;
	$finfo = pathinfo($srcFile);

	$result = false;

	if (preg_match("/jpg|JPG/",$finfo['extension'])){
		$src_img = imagecreatefromjpeg($srcFile);
		$old_w = imageSX($src_img);
		$old_h = imageSY($src_img);
		if ($old_w > $old_h) {
			$dest_w = $new_w;
			$dest_h = $old_h*($new_w/$old_w);
			}
		if ($old_w < $old_h) {
			$dest_h  =$new_h;
			$dest_w = $old_w*($new_h/$old_h);
			}
		if ($old_w == $old_h) {
			$dest_w = $new_w;
			$dest_h = $new_h;
			}
		$dst_img=ImageCreateTrueColor($dest_w,$dest_h);
		imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $dest_w, $dest_h, $old_w, $old_h);
		imagedestroy($src_img);
//		Create JPG at 90% quality
		$result = imagejpeg($dst_img, $destFile, $quality);
		imagedestroy($dst_img);
	}

	return $result;
}

function CustomFields($fields) {
// adjust for differences between field names and retrieved TV names for edit command
// set required format for dates and options sets (no widgets applied by docmanager)
	global $modx;

	$customFields = array();
	foreach($this->columns as $column) {
		$customFields[$column] = $this->$column;
	}

	$this->delMsg = $this->lang['del_gallery'] . chr(32) . $this->caption;

	return $customFields;
}

}
?>