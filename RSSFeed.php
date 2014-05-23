<?php
/*
Plugin Name: RSS Feed
Description: Create and add pages to an RSS feed
Version: 0.1
Author: Nathan Friemel
*/

# get correct id for plugin
$thisfile=basename(__FILE__, ".php");
$rss_file=GSDATAOTHERPATH .'RSSFeedPages.xml';
$feed_file_name = "feed.xml";
$auto_save = false;

# register plugin
register_plugin(
	$thisfile, 													# ID of plugin, should be filename minus php
	'RSS Feed',										 			# Title of plugin
	'0.1', 															# Version of plugin
	'Nathan Friemel',										# Author of plugin
	'http://theonethree.com/',		 			# Author URL
	'Create and add pages to an RSS feed', 	# Plugin Description
	'pages', 														# Page type of plugin
	'rss_feed_creator' 									# Function that displays content
);

# hooks
add_action('pages-sidebar','createSideMenu',array($thisfile,'RSS Feed'));
add_action('changedata-save','add_saved_page_to_feed');

function rss_feed_creator(){
	global $rss_file, $success, $error;
	
	$rss = get_or_create_rss_file();
	
	// submitted form
	if(isset($_POST['submit'])){
		// check to see if all fields provided
		if($_POST['feed_title'] != ""){
			$feed_title = stripcslashes($_POST['feed_title']);
		}
		else{
			$error .= 'No filed title provided. ';
		}
		if($_POST['feed_description'] != ""){
			$feed_description = stripcslashes($_POST['feed_description']);
		}
		else{
			$error .= 'No feed description provided. ';
		}
		if($_POST['feed_link'] != ""){
			$feed_link = stripcslashes($_POST['feed_link']);
		}
		else{
			$error .= 'No feed link provided. ';
		}
		
		// if there are no errors, save data
		if(!$error){
			$rss->feed_data->feed_title = $rss->feed_data->feed_description = $rss->feed_data->feed_link = null;
			$rss->feed_data->feed_title->addCData(htmlentities($feed_title, ENT_QUOTES));
			$rss->feed_data->feed_description->addCData(htmlentities($feed_description, ENT_QUOTES));
			$rss->feed_data->feed_link->addCData($feed_link);
			
			if($_POST['page_to_add'] != ""){
				$p = explode(":", $_POST['page_to_add']);
				$r = $rss->pages->addChild('page');
				$r->addChild('id', uniqid());
				$t = $r->addChild('title');
				$t->addCData(stripcslashes($p[0]));
				$r->addChild('fileName', $p[1]);
				$d = $r->addChild('description');
				$d->addCData(htmlentities(stripcslashes($_POST['page_description']), ENT_QUOTES));
			}
			
			if (! $rss->asXML($rss_file)){
				$error = i18n_r('CHMOD_ERROR');
			}else{
				$success = i18n_r('SETTINGS_UPDATED');
				create_feed_file($rss);
			}
		}
	}
	elseif(isset($_POST['delete'])){
		$id = $_POST['id'];
		if($id != ""){
			$new_xml = @new SimpleXMLExtended('<rss></rss>');
			$fd = $new_xml->addChild('feed_data');
			$f = $fd->addChild('feed_title');
			$f->addCData($rss->feed_data->feed_title);
			$f = $fd->addChild('feed_description');
			$f->addCData($rss->feed_data->feed_description);
			$f = $fd->addChild('feed_link');
			$f->addCData($rss->feed_data->feed_link);
			
			$ps = $new_xml->addChild('pages');
			foreach($rss->pages->page as $page){
				if($id != $page->id){
					$p = $ps->addChild('page');
					$p->addChild('id', $page->id);
					$q = $p->addChild('title');
					$q->addCData($page->title);
					$p->addChild('fileName', $page->fileName);
					$q = $p->addChild('description');
					$q->addCData($page->description);
				}
			}
			if (!$new_xml->asXML($rss_file)){
				$error = i18n_r('CHMOD_ERROR');
			}else{
				$success = i18n_r('SETTINGS_UPDATED');
				$rss = simplexml_load_file($rss_file);
				create_feed_file($rss);
			}
		}
		else{
			$error .= 'Page not found';
		}
	}
?>
	
	<h2>Create RSS Feed</h2>
	
<?php 
	if($success) { 
		echo '<p style="color:#669933;"><b>'. $success .'</b></p>';
	} 
	if($error) { 
		echo '<p style="color:#cc0000;"><b>'. $error .'</b></p>';
	}
?>
	
	<form method="post" action="<?php	echo $_SERVER ['REQUEST_URI']?>">
		<h3>Feed Settings</h3>
		<p>
			<label for="feed_title">Title</label>
			<input id="feed_title" name="feed_title" class="text" value="<?=$rss->feed_data->feed_title?>" />
		</p>
		<p>
			<label for="feed_description">Description</label>
			<input id="feed_description" name="feed_description" class="text" value="<?=$rss->feed_data->feed_description?>" />
		</p>
		<p>
			<label for="feed_link">Link</label>
			<input id="feed_link" name="feed_link" class="text" value="<?=$rss->feed_data->feed_link?>" />
		</p>
		<h3>Add A New Page To The Feed</h3>
		<p>
			<label for="page_to_add" >Page</label>
			<select id="page_to_add" name="page_to_add" class="select"><?php rss_pages_options(); ?></select>
		</p>
		<p>
			<label for="page_description">Description (optional)</label>
			<textarea id="page_description" name="page_description"></textarea>
		</p>
		
		<p><input type="submit" id="submit" class="submit" value="<?php i18n('BTN_SAVESETTINGS'); ?>" name="submit" /></p>
	</form>
	
	<?php if($rss->pages->page){ ?>
	<h3>Existing Feed Pages</h3>
	<table class="edittable highlight paginate">
		<tbody>
			<tr>
				<th>Page</th>
				<th>Description</th>
				<th style="text-align: center;">Delete</th>
			</tr>
	<?php
		foreach($rss->pages->page as $p){
	?>
			<tr>
				<td><?=$p->title?></td>
				<td><?=$p->description?></td>
				<td style="text-align: center;"><form method="post" action="<?php	echo $_SERVER ['REQUEST_URI']?>"><input type="hidden" name="id" value="<?=$p->id?>" /><input style="cursor: pointer;" type="submit" value="X" name="delete" /></form></td>
			</tr>
	<?php
		}
	?>
		</tbody>
	</table>
	<?php
	}
}

function add_saved_page_to_feed(){
	global $rss_file, $auto_save;
	
	if($auto_save === true){
		$rss = get_or_create_rss_file();

		if ($_POST['post-id']){ 
			$postId = $_POST['post-id'];
		}
		elseif($_POST['post-title'])	{ 
			$postId = $_POST['post-title'];
			$postId = to7bit($postId, "UTF-8");
			$postId = clean_url($postId);
		}

		if($postId != "index"){
			$exists = false;
			foreach($rss->pages->page as $page){
				if($postId . ".xml" == $page->fileName){
					$exists = true;
					break;
				}
			}
			if($exists == false){
				$r = $rss->pages->addChild('page');
				$r->addChild('id', uniqid());
				$t = $r->addChild('title');
				$t->addCData(htmlentities(stripcslashes($_POST['post-title']), ENT_QUOTES));
				$r->addChild('fileName', $postId . ".xml");
				$d = $r->addChild('description');
				$rss->asXML($rss_file);
			}
			create_feed_file($rss, $postId);
		}
	}
}

function create_feed_file($rss, $newFile=null){
	global $feed_file_name, $success, $error;

	$feed = @new SimpleXMLExtended("<rss xmlns:content=\"http://purl.org/rss/1.0/modules/content/\"></rss>");
	$feed->addAttribute('version', '2.0');
	
	$channel = $feed->addChild('channel');
	$channel->addChild('title', $rss->feed_data->feed_title);
	$channel->addChild('description', $rss->feed_data->feed_description);
	$channel->addChild('link', $rss->feed_data->feed_link);
	
	$pages = $rss->pages->page;
	for($i = count($pages)-1; $i >= 0; $i--){
		$page = $pages[$i];
		
		if(file_exists(GSDATAPAGESPATH . $page->fileName) && $newFile . ".xml" != $page->fileName){
			$fileData = getXML(GSDATAPAGESPATH . $page->fileName);
			$item = $channel->addChild('item');
			$item->addChild('title', stripcslashes($fileData->title));
			
			$d = $item->addChild('description');
			if($page->description != "")
				$d->addCData(html_entity_decode($page->description, ENT_QUOTES));
			else
				$d->addCData(strip_tags(html_entity_decode(stripcslashes($fileData->content))));
			
			$c = $item->addChild("encoded", "", "http://purl.org/rss/1.0/modules/content/");
			$c->addCData(html_entity_decode(stripcslashes($fileData->content)));
			
			$item->addChild('link', find_url($fileData->url, $fileData->parent));
			$item->addChild('pubDate', $fileData->pubDate);
		}
		elseif($newFile . ".xml" == $page->fileName){
			$item = $channel->addChild('item');
			$item->addChild('title', htmlentities(stripcslashes($_POST['post-title']), ENT_QUOTES));
			
			$d = $item->addChild('description');
			$d->addCData(strip_tags(html_entity_decode(stripcslashes($_POST['post-content']))));
			
			$c = $item->addChild("encoded", "", "http://purl.org/rss/1.0/modules/content/");
			$c->addCData(html_entity_decode(stripcslashes($_POST['post-content'])));
			
			$item->addChild('link', find_url($newFile, $_POST['post-parent']));
			$item->addChild('pubDate', date("D, j M Y H:i:s O"));
		}
	}
	
	if (!$feed->asXML(GSROOTPATH . $feed_file_name)){
		$error .= ". Unable to create feed file. ";
	}else{
		$success .= ". Feed file created. ";
	}
}

function get_or_create_rss_file(){
	global $rss_file;

	if(file_exists($rss_file)){
		return getXML($rss_file);
	}
	else{
		$xml = @new SimpleXMLElement('<rss><feed_data><feed_title></feed_title><feed_description></feed_description><feed_link></feed_link></feed_data><pages></pages></rss>');
		$xml->asXML($rss_file);
		return getXML($rss_file);
	}
}

function rss_pages_options(){
	$path = GSDATAPAGESPATH;
	$dir_handle = @opendir($path) or die("Unable to open $path");
	$filenames = array();
	while ($filename = readdir($dir_handle)){
		$filenames[] = $filename;
	}
	closedir($dir_handle);

	$pagesArray = array();
	$count = 0;
	if (count($filenames) != 0){
		foreach ($filenames as $file){
			if ($file == "." || $file == ".." || is_dir($path . $file) || $file == ".htaccess"  ) {
				// not a page data file
			}
			else{
				$data = getXML($path . $file);
				if ($data->private != 'Y'){
					$pagesArray[$count]["parent"] = $data->parent;
					$pagesArray[$count]["title"] = stripcslashes($data->title);
					$pagesArray[$count]["fileName"] = $file;
					$count++;
				}
			}
		}
	}
	$pagesSorted = subval_sort($pagesArray,'title');
	
	$options = "<option value=\"\">-Select Page-</option>";
	foreach ($pagesSorted as $page) {
		$options .= "<option value=\"$page[title]:$page[fileName]\">";
		if($page["parent"] != "")
			$options .= "$page[parent] - ";
		$options .= "$page[title]</option>";
	}
	echo $options;
}
?>
