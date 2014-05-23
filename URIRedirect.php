<?php
/*
Plugin Name: URI Redirect
Description: Create a list of URIs to 301 redirect to a page
Version: 0.2.1
Author: Nathan Friemel
*/

# get correct id for plugin
$thisfile=basename(__FILE__, '.php');
$uriRedirectFile=GSDATAOTHERPATH .'URIRedirectSettings.xml';
$pluginVersion = '0.2.1';

# register plugin
register_plugin(
	$thisfile, 													# ID of plugin, should be filename minus php
	'URI Redirect',								 			# Title of plugin
	'0.2.1',														# Version of plugin
	'Nathan Friemel',										# Author of plugin
	'http://theonethree.com/',		 			# Author URL
	'Create a list of URIs to 301 redirect to a page', 	# Plugin Description
	'pages', 														# Page type of plugin
	'uri_redirect_show' 								# Function that displays content
);

# hooks
add_action('pages-sidebar','createSideMenu',array($thisfile,'URIs to Redirect'));
add_action('index-pretemplate','do_uri_redirect');

/**
 * Create Plugin Page
 *
 * <p>Handle form submits, display form for adding new page, 
 * and display table of current pages</p>
 */
function uri_redirect_show(){
	global $uriRedirectFile, $success, $error, $SITEURL;

	checkVersion();
	
	if (file_exists($uriRedirectFile)) {
		$xml_settings = simplexml_load_file($uriRedirectFile);
	} else {
		$xml = @new SimpleXMLElement('<uris></uris>');
		$xml->asXML($uriRedirectFile);
		$xml_settings = simplexml_load_file($uriRedirectFile);
	}
	
	// submitted form
	if (isset($_POST['submit'])) {
		// check to see if URI provided
		if ($_POST['incoming_uri'] != '' && $_POST['redirect_page'] != '') {
			$incoming_uri = $_POST['incoming_uri'];
			$redirect_page = explode(':', $_POST['redirect_page']);
			$redirect_page_title = $redirect_page[0];
			$redirect_page_url = $redirect_page[1];
			$redirect_page_parent = $redirect_page[2];
		} else {
			$error .= 'No URI added';
		}
		
		// if there are no errors, save data
		if (!$error) {
			$uri = $xml_settings->addChild('uri');
			$uri->addChild('id', uniqid());
			$uri->addChild('incoming', $incoming_uri);
			$uri->addChild('redirect_page_title', $redirect_page_title);
			$uri->addChild('redirect_page_url', $redirect_page_url);
			$uri->addChild('redirect_page_parent', $redirect_page_parent);
			
			if (! $xml_settings->asXML($uriRedirectFile)) {
				$error = i18n_r('CHMOD_ERROR');
			} else {
				$success = i18n_r('SETTINGS_UPDATED');
			}
		}
	}
	elseif (isset($_POST['delete'])) {
		$id = $_POST['id'];
		if ($id != '') {
			$i = 0;
			foreach ($xml_settings as $a_setting) {
				if ($id == $a_setting->id) {
					unset($xml_settings->uri[$i]);
					break;
				}
				$i++;
			}
			if (!$xml_settings->asXML($uriRedirectFile)) {
				$error = i18n_r('CHMOD_ERROR');
			} else {
				$success = i18n_r('SETTINGS_UPDATED');
			}
		} else {
			$error .= 'ID not found';
		}
	}
	?>
	
	<h3>Add New URI Redirect</h3>
	
	<?php 
	if ($success) { 
		echo '<p style="color:#669933;"><b>'. $success .'</b></p>';
	} 
	if ($error) { 
		echo '<p style="color:#cc0000;"><b>'. $error .'</b></p>';
	}
	?>
	
	<form method="post" action="<?php	echo $_SERVER ['REQUEST_URI']?>">
		<p><label for="incoming_uri" >Incoming URI</label><input id="incoming_uri" name="incoming_uri" class="text" /></p>
		<p><label for="redirect_page" >Redirect Page</label><select id="redirect_page" name="redirect_page" class="select"><?php uri_pages_options(); ?></select></p>
		
		<p><input type="submit" id="submit" class="submit" value="<?php i18n('BTN_SAVESETTINGS'); ?>" name="submit" /></p>
	</form>
	
	<?php if ($xml_settings) { ?>
	<h3>Existing URI Redirects</h3>
	<table class="edittable highlight paginate">
		<tbody>
			<tr>
				<th>Redirect Page</th>
				<th>Incoming URI</th>
				<th style="text-align: center;">Delete</th>
			</tr>
	<?php
		foreach ($xml_settings as $a_setting) {
	?>
			<tr>
				<td><?php echo stripcslashes($a_setting->redirect_page_title); ?></td>
				<td><?php echo $a_setting->incoming; ?></td>
				<td style="text-align: center;"><form method="post" action="<?php	echo $_SERVER ['REQUEST_URI']?>"><input type="hidden" name="id" value="<?php echo $a_setting->id; ?>" /><input style="cursor: pointer;" type="submit" value="X" name="delete" /></form></td>
			</tr>
	<?php
		}
	?>
		</tbody>
	</table>
	<?php
	}
}

/**
 * Generate Page Option List
 *
 * <p>Cycle through all the page xml files and build an options 
 * list to be inserted in a select tag</p>
 */
function uri_pages_options(){
	$path = GSDATAPAGESPATH;
	$dir_handle = @opendir($path) or die('Unable to open ' . $path);
	$filenames = array();
	while ($filename = readdir($dir_handle)) {
		$filenames[] = $filename;
	}
	closedir($dir_handle);

	$pagesArray = array();
	$count = 0;
	if (count($filenames) != 0) {
		foreach ($filenames as $file) {
			if ($file == '.' || $file == '..' || is_dir($path . $file) || $file == '.htaccess') {
				// not a page data file
			} else {
				$data = getXML($path . $file);
				if ($data->private != 'Y') {
					$pagesArray[$count]['parent'] = $data->parent;
					$pagesArray[$count]['title'] = stripcslashes($data->title);
					$pagesArray[$count]['url'] = $data->url;
					$count++;
				}
			}
		}
	}
	$pagesSorted = subval_sort($pagesArray,'title');
	
	$options = '<option value="">-Select Page-</option>';
	foreach ($pagesSorted as $page) {
		$options .= '<option value="' . $page[title] . ':' . $page[url] . ':' . $page[parent] . '">';
		if ($page['parent'] != '') {
			$options .= $page[parent] . ' - ';
		}
		$options .= $page[title] . '</option>';
	}
	echo $options;
}

/**
 * Do Redirect
 *
 * <p>Fired before a page is loaded to check if the current URI 
 * is in the uri_redirect_file and if it is it will redirect 
 * to the provided page</p>
 */
function do_uri_redirect(){
	global $uriRedirectFile, $SITEURL;

	if (file_exists($uriRedirectFile)) {
		$xml_settings = simplexml_load_file($uriRedirectFile);

		$subFolder = parse_url($SITEURL, PHP_URL_PATH);
		$subFolder = rtrim($subFolder, '/');
	
		$requestURI = rtrim($_SERVER['REQUEST_URI'], '/');
			
		if ($_SERVER['SERVER_PROTOCOL'] == 'HTTP/1.1') {
			$protocol = 'http://';
		} else {
			$protocol = 'https://';
		}
		
		foreach ($xml_settings as $a_setting) {
			$incoming = rtrim($a_setting->incoming, '/');
			$incoming = $subFolder . $incoming;

			if ($incoming == $requestURI) {
				$link = $subFolder . '/';
				if ($a_setting->redirect_page_parent != '') {
					$link .= $a_setting->redirect_page_parent . '/';
				}
				$link .= $a_setting->redirect_page_url . '/';

				header ('HTTP/1.1 301 Moved Permanently');
	      header ('Location: ' . $protocol . $_SERVER['SERVER_NAME'] . $link);
  	    die();
			}
		}
	}
}

/**
 * Check For New Plugin Version
 *
 * <p>Checks the GetSimple extend_api and compares versions  
 * and if the versions are different displays a note to the user</p>
 */
function checkVersion(){
	try { // Check if curl installed
		$v = curl_version();
	} catch (Exception $e) {
		return;
	}

	global $pluginVersion;
	
	$c = curl_init();
	curl_setopt($c, CURLOPT_URL, 'http://get-simple.info/api/extend/?id=150');
	curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
	$checkVersion = json_decode(curl_exec($c), true);
	curl_close($c);

	if ($pluginVersion < $checkVersion['version']) {
		echo '<p style="background: #F7F7C3; border: 1px solid #F9CF51; color:#669933; padding: 5px 10px;"><b>Version '. $checkVersion['version'] .' is now available, <a href="' . $checkVersion['file'] . '">download</a> the new file.</b></p>';
	}
}
?>
