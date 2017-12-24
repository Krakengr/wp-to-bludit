<?php
			
class pluginWPToBludit extends Plugin {

	public function init() {
		
		set_time_limit (0);
	
		define('DB_ROOT', PATH_ROOT.'bl-content'.DS.'databases'.DS);
		define('PAGES_ROOT', PATH_ROOT.'bl-content'.DS.'pages'.DS);
		define('UPLOADS_ROOT', PATH_ROOT.'bl-content'.DS.'uploads'.DS);
		define('THUMBS_ROOT', PATH_ROOT.'bl-content'.DS.'uploads'.DS.'thumbnails'.DS);
		define('PROFILES_ROOT', PATH_ROOT.'bl-content'.DS.'uploads'.DS.'profiles');
		define('DB_CATS', PATH_ROOT.'bl-content'.DS.'databases'.DS.'categories.php');
	
		$this->dbFields = array(
			'merge'=>0,
			'embed'=>0,
			'disqus_id'=>'',
			'xmlfile'=>''
		);
	}	

	public function form()
	{
		global $Language;
		
		if ( Text::isNotEmpty( $this->getValue( 'xmlfile' ) ) && file_exists(UPLOADS_ROOT . $this->getDbField('xmlfile')))
			$disabled = 'disabled';
		else
			$disabled = '';
		
		$html = '<div>';
		$html .= '<label>'.$Language->get('embed').'</label>';
		$html .= '<input type="checkbox" id="jsembed" name="embed" '.($this->getValue('embed')==1?'checked="checked"':'').' value="1" ' . $disabled . '/>';
		$html .= '<br /><small>'.$Language->get('embed-info').'</small>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$Language->get('merge').'</label>';
		$html .= '<input type="checkbox" id="jsmerge" name="merge" '.($this->getValue('merge')==1?'checked="checked"':'').' value="1" ' . $disabled . '/>';
		$html .= '<br /><small>'.$Language->get('merge-info').'</small>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$Language->get('xml-name').'</label>';
		$html .= '<input name="xmlfile" id="jsxmlfile" type="text" value="'.$this->getDbField('xmlfile').'" ' . $disabled . '>';
		$html .= '<br /><small>'.$Language->get('xml-file').'</small>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$Language->get('disqus-id').'</label>';
		$html .= '<input name="disqus_id" id="jsdisqusID" type="text" value="'.$this->getDbField('disqus_id').'" ' . $disabled . '>';
		$html .= '<br /><small>'.$Language->get('disqus-empty').'</small>';
		$html .= '</div>';
		
		
		if ( Text::isNotEmpty( $this->getValue( 'xmlfile' ) ) && file_exists(UPLOADS_ROOT . $this->getDbField('xmlfile'))) {
		
			$html .= '<div class="unit-100">';
			$html .= '<button class="uk-button uk-button-primary" type="submit" name="convert"><i class="uk-icon-life-ring"></i> ' .$Language->get("convert-xml"). '</button>';
			$html .= '</div>';
			$html .= '<br /><small>'.$Language->get('locked').'</small>';
			$html .= '<style type="text/css" scoped>.uk-form-row button, .uk-form-row a {display:none};</style>';
			
			if (isset($_POST['convert'])) {
				pluginWPToBludit::convertXML();
			}
		}
		
		return $html;
		
	}
	
	private function convertXML() {
		
		global $Language;
		global $Site;
		
		//If the convert button is pressed but the file is not there, don't continue...		
		if ( Text::isNotEmpty( $this->getValue( 'xmlfile' ) ) && !file_exists(UPLOADS_ROOT . $this->getDbField('xmlfile'))) {
			Alert::set($Language->get("no-file-found"));
			Redirect::page('configure-plugin/pluginWPToBludit');
		}
		
		//Load the XML data, before we delete everything
		$xml = simplexml_load_string(file_get_contents(UPLOADS_ROOT . $this->getDbField('xmlfile')));
		
		//Is this a valid xml data?
		if ($xml === false) {
			Alert::set($Language->get("file-error"));
			Redirect::page('configure-plugin/pluginWPToBludit');
		}
		
		//The Arrays
		$categories = array();
		$tags = array();
		$comments = array();
		$posts = array();
		$attachments = array();

		$page_pos = 0;
		$com_id = 0;
		
		//This is to continue the conversion. Doesn't work well yet...
		if ( $this->getValue('embed') == 1 ) {
			$file = fopen($this->workspace().'lock', 'w') or die("can't open file");
			fclose($file);
		}
		
		//This is for testing purposes right now...
		if (!file_exists($this->workspace().'lock')) {
			
			//Remove posts dir
			pluginWPToBludit::rrmdir(PAGES_ROOT);
			
			//And uploads too...
			pluginWPToBludit::rrmdir(UPLOADS_ROOT);
				
			//and create them again...
			if (!is_dir(PAGES_ROOT))
				mkdir(PAGES_ROOT, 0755, true) or die ('Could not create folder ' . PAGES_ROOT);
			
			if (!is_dir(UPLOADS_ROOT))
				mkdir(UPLOADS_ROOT, 0755, true) or die ('Could not create folder ' . UPLOADS_ROOT);
			
			if (!is_dir(THUMBS_ROOT))
				mkdir(THUMBS_ROOT, 0755, true) or die ('Could not create folder ' . THUMBS_ROOT);
			
			if (!is_dir(PROFILES_ROOT))
				mkdir(PROFILES_ROOT, 0755, true) or die ('Could not create folder ' . PROFILES_ROOT);
			
		} else { //It's OK, continue or merge the data...
			
			//Load the data in arrays
			if ( file_exists( DB_CATS ) )
				$categories = json_decode(file_get_contents(DB_CATS, NULL, NULL, 50), TRUE);
			
			if ( file_exists( DB_PAGES ) ) {
				$posts = json_decode(file_get_contents(DB_PAGES, NULL, NULL, 50), TRUE);
				
				//For this, we need the last position to continue
				uasort( $posts, function($a, $b) { return $a['position'] - $b['position']; } );
				$last = end($posts);
				$page_pos = $last['position'];
			}
			
			if ( file_exists( DB_TAGS ) )
				$tags = json_decode(file_get_contents(DB_TAGS, NULL, NULL, 50), TRUE);
		}
		
		//This is for disqus file. Don't bother with it...
		if ( Text::isNotEmpty( $this->getValue( 'disqus_id' ) ) ) {

			//Comments XML File
			$doc = new DOMDocument('1.0', 'UTF-8');
							
			// Friendly XML code
			$doc->formatOutput = true;
							
			//create "RSS" element
			$rss = $doc->createElement("rss");
			$rss_node = $doc->appendChild($rss); //add RSS element to XML node
			$rss_node->setAttribute("version","2.0"); //set RSS version

			//set attributes
			$rss_node->setAttribute("xmlns:content","http://purl.org/rss/1.0/modules/content/");
			$rss_node->setAttribute("xmlns:dsq","http://www.disqus.com/");
			$rss_node->setAttribute("xmlns:dc","http://purl.org/dc/elements/1.1/");
			$rss_node->setAttribute("xmlns:wp","http://wordpress.org/export/1.0/");
							
			//create "channel" element under "RSS" element
			$channel = $doc->createElement("channel");  
			$channel_node = $rss_node->appendChild($channel);
		}
		
		//This is to continue the conversion. Doesn't work well yet...
		if (!file_exists($this->workspace().'lock')) {
			$file = fopen($this->workspace().'lock', 'w') or die("can't open file");
			fclose($file);
		}
		
		//Keep the attachments in an array...
		foreach($xml->channel->item as $item) {
			$type = $item->xpath('wp:post_type');
			$type = $type['0'];
			
			if ($type == 'attachment') {
				
				$p_id = $item->xpath('wp:post_id');
				$p_id = (int) $p_id['0'];
				
				$parent_id = $item->xpath('wp:post_parent');
				$parent_id = (int) $parent_id['0'];
				
				$attachment_url = $item->xpath('wp:attachment_url');
				$attachment_url = (string) $attachment_url['0'];
					
				$info = pathinfo($attachment_url);
				$attachment_name =  basename($attachment_url,'.'.$info['extension']);
				
				//We don't need every image, only the header images
				if (!empty($parent_id)) {
					$attachments[$parent_id] = array ('id' => $p_id, 'parent' => $parent_id, 'name' => $attachment_name, 'url' => $attachment_url );
				}
				
			}
		}
		
		//Let's begin
		foreach($xml->channel->item as $item) {
			$status = $item->xpath('wp:status');
			$status = $status['0'];
			
			$title = (string) $item->title;
		
			$date = $item->xpath('wp:post_date');
			$date = (string) $date['0'];
			
			//Keep the original post name. We need this if we want to redirect to the new url...
			$seo = $item->xpath('wp:post_name');
			$seo = (string) $seo['0'];
			
			//If the name is non-latin, create a new one
			if ( preg_match( '/[^\\p{Common}\\p{Latin}]/u', $seo ) ) {
				$seo = urldecode ( $seo );
				$seo = pluginWPToBludit::seo ( $seo );
			}
			
			//Do we still have problems?
			if ( ( strpos( $seo, '%' ) !== false ) || empty( $seo ) ) {
				//Keep the name the same each time we run this method, to avoid convert everything again...
				$seo = substr( md5( $title ), 0, 15 );
			}
	
			$p_id = $item->xpath('wp:post_id');
			$p_id = (int) $p_id['0'];
	
			$comm = $item->xpath('wp:comment_status');
			$comm = $comm['0'];
		
			$type = $item->xpath('wp:post_type');
			$type = $type['0'];
		
			if ($status == 'trash')
				continue;
			
			$post_status = ($status == 'draft') ? 'draft' : 'published';
			
			if ($type == 'post')
				$post_type = 'post';
			elseif ($type == 'page')
				$post_type = 'page';
	
			if (strtotime($date) > time())
				$post_status = 'scheduled';
	
			$comment_status = ($comm == 'open') ? 'true' : 'false';
	
			$comment = $item->xpath('wp:comment');
		
			$content = $item->xpath('content:encoded');
			$content = (string) $content['0'];
	
			if ( strpos($content, '<!--more-->') )
			{
				$descr = explode ('<!--more-->', $content);
				$descr = strip_tags($descr ['0']);
				$descr = pluginWPToBludit::removeCaption ( $descr );
			} else {
				$descr = pluginWPToBludit::removeCaption ( $content );
				$descr = pluginWPToBludit::shorten( $descr, 160 ) ;
			}

			$content = str_replace( array('<!--more-->', '<!--nextpage-->'), array('<!-- pagebreak -->', ''), $content);
			
			if ( $this->getValue('embed') == 1 )
				$content = pluginWPToBludit::url2embed ( $content );
			
			$content = pluginWPToBludit::replaceImage ( $content );
			
			//Convert the posts and pages only
			if( ($type == 'post') || ($type == 'page') ) {
				$page_pos++;
				$p_tags = '';
				$p_tags = array();
				$cat_pos = 0;
				
				$image_name = '';
				
				if ( isset($attachments[$p_id]) ) {
					$image_url = $attachments[$p_id]['url'];
					$image_name_temp = $attachments[$p_id]['name'];
					
					$image_name = pluginWPToBludit::create_image($image_url, UPLOADS_ROOT, $image_name_temp, $thumb = '');
					
					if (!empty($image_name))
						pluginWPToBludit::create_image($image_url, THUMBS_ROOT, $image_name_temp, $thumb = true);
				}
				
				foreach ($item->category as $c) 
				{
					$att = $c->attributes();

					if ($att['domain'] == 'post_tag') 
					{
						$tag_name = (string) $c;
						$tag_name_seo = urldecode ( $tag_name );
						$tag_name_seo = pluginWPToBludit::seo ( $tag_name_seo );
						
						if( !isset($tags[$tag_name_seo]) )
							$tags[$tag_name_seo] = array('name' => $tag_name, 'list' => array( $seo ) );
						else
							array_push($tags[$tag_name_seo]['list'], $seo);
						
						if( !in_array( $tag_name_seo , $p_tags ) )
							$p_tags[$tag_name_seo] = $tag_name;
						
					}
					
					if ($att['domain'] == 'category') 
					{
						//We need only one category...
						if ($cat_pos == 1)
							continue;
						
						$cat_pos++;
						$cat_name = (string) $c;
						$cat_name_seo = urldecode ( $cat_name );
						$cat_name_seo = pluginWPToBludit::seo ( $cat_name_seo );
						
						if( !isset($categories[$cat_name_seo]) )
							$categories[$cat_name_seo] = array('name' => $cat_name, 'list' => array( $seo ) );
						else
							array_push($categories[$cat_name_seo]['list'], $seo);
					}
				}
				
				$p_dir = PAGES_ROOT . $seo . '/';
				$f_name = $p_dir . 'index.txt';
				
				//We can't continue if the folder can't be created...
				if (!is_dir($p_dir))
					mkdir($p_dir, 0755, true) or die ('Could not create folder ' . $seo . ' in ' . PAGES_ROOT);
					
				$p = 'Title: ' . $title . PHP_EOL;
				$p .= 'Content:' . PHP_EOL;
				$p .= $content;
				
				$uuid = sha1($p);
				
				//Check if the post is already there...
				if ( file_exists( $f_name ) ) {
					echo 'Post: ' . $title . ' already exists<br />';
					continue;
				}
				
				//Create file	
				file_put_contents($f_name, $p);
				
				$checksum = md5_file($f_name);
				
				if ($type == 'post')
					$lstatus = $post_status;
				
				else {
					
					if ($status == 'draft')
						$lstatus = 'draft';
					else
						$lstatus = 'static';
				}
				
				//Database
				$posts[$seo] = array(
					'description' => mb_convert_encoding($descr, "UTF-8"),
					'username' => 'admin',
					"tags" => $p_tags,
					'status' => $lstatus,
					'type' => ($type == 'post') ? "post" : "page",
					'date' => $date,
					'dateModified' => '',
					'position' => $page_pos,
					'coverImage' => $image_name,
					'category' => (!empty($cat_name_seo) && ($type == 'post') ) ? $cat_name_seo : '',
					'md5file' => $checksum,
					'uuid' => $uuid,
					'allowComments' => $comment_status,
					'parent' => '',
					'slug' => $seo
				);
				
				//Do we need the comments?
				if ( $comment && ( Text::isNotEmpty( $this->getValue( 'disqus_id' ) ) ) )
				{			
					foreach ($item->xpath('wp:comment') as $co) 
					{
						$com_id++;
						
						$com_author = $co->xpath('wp:comment_author');
						$com_author = (string) $com_author['0'];
						
						$com_email = $co->xpath('wp:comment_author_email');
						$com_email = (string) $com_email['0'];
						
						$com_url = $co->xpath('wp:comment_author_url');
						$com_url = (string) $com_url['0'];
						
						$com_IP = $co->xpath('wp:comment_author_IP');
						$com_IP = (string) $com_IP['0'];
						
						$com_date = $co->xpath('wp:comment_date');
						$com_date = (string) $com_date['0'];
						
						$com_content = $co->xpath('wp:comment_content');
						$com_content = (string) $com_content['0'];
						
						$com_approved = $co->xpath('wp:comment_approved');
						$com_approved = (string) $com_approved['0'];
						
						$item_node = $channel_node->appendChild($doc->createElement("item")); //create a new node called "item"
						$title_node = $item_node->appendChild($doc->createElement( "title", htmlentities($title) )); //Add Title under "item"
						
						$seo_node = $item_node->appendChild($doc->createElement("link", $Site->url() . $seo)); //Add link under "item"
						
						//create "description" node under "item"
						$description_node = $item_node->appendChild($doc->createElement("content:encoded"));  
						 
						//fill description node with CDATA content
						$description_contents = $doc->createCDATASection(htmlentities($descr));  
						$description_node->appendChild($description_contents);
						  
						$dsq_node = $item_node->appendChild($doc->createElement("dsq:thread_identifier", $seo)); //add dsq node under "item"
						$date_node = $item_node->appendChild($doc->createElement("wp:post_date_gmt", $date)); //add date node under "item"
						$comment_node = $item_node->appendChild($doc->createElement("wp:comment_status", $comment_status)); //add comment node under "item"
						 
						 //Comment node
						$commend_node = $item_node->appendChild($doc->createElement("wp:comment")); //create a new node called "comment"
						
						$dsq_remote = $commend_node->appendChild($doc->createElement("dsq:remote", '0')); //Add dsq:remote under "comment"
						
						$dsq_id = $dsq_remote->appendChild($doc->createElement("dsq:id", '0')); //Add dsq:id under "dsq:remote"
						
						$dsq_avatar = $dsq_remote->appendChild($doc->createElement("dsq:avatar", '0')); //Add dsq:avatar under "dsq:remote"
						
						$comment_id = $commend_node->appendChild($doc->createElement("wp:comment_id", $com_id)); //Add comment_id under "comment"
						
						$comment_author = $commend_node->appendChild($doc->createElement("wp:comment_author", $com_author)); //Add Title under "item"
						
						$comment_author_email = $commend_node->appendChild($doc->createElement("wp:comment_author_email", $com_email)); //Add Title under "item"
						
						$comment_author_url = $commend_node->appendChild($doc->createElement("wp:comment_author_url", $com_url)); //Add Title under "item"
						
						$comment_author_IP = $commend_node->appendChild($doc->createElement("wp:comment_author_IP", $com_IP)); //Add Title under "item"
						
						$comment_date_gmt = $commend_node->appendChild($doc->createElement("wp:comment_date_gmt", $com_date)); //Add Title under "item"
						
						$comment_content = $commend_node->appendChild($doc->createElement("wp:comment_content", $com_content)); //Add Title under "item"	
						
						$comment_approved = $commend_node->appendChild($doc->createElement("wp:comment_approved", $com_approved)); //Add Title under "item"
						
						$comment_parent = $commend_node->appendChild($doc->createElement("wp:comment_parent", '0')); //Add Title under "item"
											
					}

				}
			
				echo 'Post: ' . $title . ' converted successfully<br />';
			}

		}
		
		//Let's backup the data...
		if ( count( $posts ) > 0)
		{
			uasort( $posts, function($a, $b) { return $b['position'] - $a['position']; } );
			$posts = json_encode($posts, JSON_PRETTY_PRINT);
			$posts_dt = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>" . PHP_EOL . $posts;
			file_put_contents(DB_PAGES, $posts_dt);	
		}
		
		if ( count( $tags ) > 0)
		{
			$tags = json_encode($tags, JSON_PRETTY_PRINT);
			$tags_dt = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>" . PHP_EOL . $tags;
			file_put_contents(DB_TAGS, $tags_dt);	
				
		}
		
		if ( count( $categories ) > 0)
		{
			$categories = json_encode($categories, JSON_PRETTY_PRINT);
			$categories_dt = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>" . PHP_EOL . $categories;
			file_put_contents(DB_CATS, $categories_dt);	
				
		}
		
		//Create the disqus file, if we need it...
		if ( Text::isNotEmpty( $this->getValue( 'disqus_id' ) ) )
			file_put_contents(UPLOADS_ROOT . '/comments.xml', $doc->saveXML());
		
		//Delete the XML file...
		if ( file_exists( UPLOADS_ROOT . $this->getDbField('xmlfile' ) ) )
			unlink ( UPLOADS_ROOT . $this->getDbField( 'xmlfile' ) );
		
		//We're done...
		Alert::set($Language->get("success"));
		sleep ( 3 );
		Redirect::page('plugins');
		
	}
	
	public function rrmdir($dir) {
	  if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
		  if ($object != "." && $object != "..") {
			if (filetype($dir."/".$object) == "dir") 
			   pluginWPToBludit::rrmdir($dir."/".$object); 
			else unlink   ($dir."/".$object);
		  }
		}
		reset($objects);
		rmdir($dir);
	  }
	}
	
	public function searcharray($value, $key, $array) {
		foreach ($array as $k => $val) {
			if ($val[$key] == $value)
				return $k;
		}
		return null;
	}
	
	public function date_compare($a, $b) {
		$t1 = strtotime($a['date']);
		$t2 = strtotime($b['date']);
		return $t2 - $t1;
	}  
	
	
	public function seo( $slug )
	{
		if (function_exists('transliterator_transliterate')) {
			
			// Using transliterator to get rid of accents and convert non-Latin to Latin
			$slug = transliterator_transliterate("Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();", $slug);
		
		} elseif (function_exists('iconv')) {
			
			// Transliterate accented characters to Latin (ascify)
			$slug = iconv('UTF-8', 'ASCII//TRANSLIT', utf8_encode( $slug ));
		
		} else {
			
			// Convert special Latin letters and other characters to HTML entities.
			$slug = htmlentities($slug, ENT_NOQUOTES, "UTF-8");

			// With those HTML entities, either convert them back to a normal letter, or remove them.
			$slug = preg_replace(array("/&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);/i", "/&[^;]{2,6};/"), array("$1", " "), $slug);
		}

			// Now replace non-alphanumeric characters with a hyphen, and remove multiple hyphens.
			$slug = strtolower(trim(preg_replace(array("/[^0-9a-z]/i", "/-+/"), "-", $slug), "-"));

			return substr($slug, 0, 40);
	
	
	}
	
	public function shorten($string, $length) {
		// By default, an ellipsis will be appended to the end of the text.
		$suffix = '...';

		// Strip the HTML tags, and convert all tabs and line-break characters to single spaces.
		$short_desc = trim(str_replace(array("\r", "\n", '"', "\t"), ' ', strip_tags($string)));

		// Cut the string to the requested length, and strip any extraneous spaces
		// from the beginning and end.
		$desc = trim(substr($short_desc, 0, $length));

		// Find out what the last displayed character is in the shortened string
		$lastchar = substr($desc, -1, 1);

		// If the last character is a period, an exclamation point, or a question
		// mark, clear out the appended text.
		if ($lastchar == '.' || $lastchar == '!' || $lastchar == '?')
			$suffix = '';

		// Append the text.
		$desc .= $suffix;
		//$desc .= '... // '.$item->get_date('j M Y | g:i a T');

		// Send the new description back to the page.
		return $desc;
	}
	
	public function generate_key($length) {
		return substr(str_shuffle('qwertyuiopasdfghjklmnbvcxz'), 0, $length);
	}
	
	//This function converts url links to embed codes
	public function url2embed($text) {
		
		$text = preg_replace(
        "/\s*[a-zA-Z\/\/:\.]*youtu(be.com\/watch\?v=|.be\/)([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i",
        "<iframe src=\"https://www.youtube.com/embed/$2\" height=\"404\" width=\"600\" allowfullscreen></iframe>",
        $text);
		
		$text = preg_replace('#https?://(www\.)?vimeo\.com/(\d+)#', '<iframe src="//player.vimeo.com/video/$2" frameborder="0" width="600" height="404" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>',  $text);
		
		$text = preg_replace('/.+dailymotion.com\/(video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/', "<iframe frameborder=\"0\" width=\"600\" height=\"404\" src=\"//www.dailymotion.com/embed/video/$2\" allowfullscreen></iframe>",  $text);
		
		$text = preg_replace('/(?:https?:\/\/)?v\.youku\.com\/v_show\/id_([^_&]+).html/', "<iframe frameborder=\"0\" allowfullscreen type=\"text/html\" width=\"600\" height=\"405\" src='//player.youku.com/embed/$1' ></iframe>",  $text);
		
		if (preg_match('#https?://(www\.)?twitter\.com/(.*?)(/status)?(\d+)#i', $text, $matches) )
		{
					
			//getting the file content
			$json = @file_get_contents('https://api.twitter.com/1/statuses/oembed.json?id=' . $matches[4] . '&omit_script=true', true); 
			
			if ($json !== false) {
				$decode = json_decode($json, true); //getting the file content as array
				$text = preg_replace('#https?://(www\.)?twitter\.com/(.*?)(/status)?(\d+)#i', $decode['html'],  $text);	
				unset( $json, $decode, $matches );
			}
			
		}
		
		if (preg_match("#https?://(www\.)?facebook\.com/(.*?)(/videos)?(\d+)#i", $text, $matches)) {
			$text = preg_replace("#https?://(www\.)?facebook\.com/(.*?)(/videos)?(\d+)#i", '<iframe src="https://www.facebook.com/plugins/video.php?href='.urlencode($matches['0']).'&show_text=0&width=600" width="600" height="404" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowTransparency="true" allowFullScreen="true"></iframe>', $text);
		}
		
		return $text;
	}
	
	private function removeCaption($content)
	{
		return preg_replace ('/\[caption[^\]]*\](.*)\[\/caption\]/', '', $content);
	}
	
	// Replace the <img> and [caption]tag with bludit's Img Link
	private function replaceImage($content)
	{
		
		//First let's find if there are any images with caption...
		preg_match_all('%(\\[caption.*])(.*)<img.+src=[\'"]([^\'"]+)[\'"].*>(.*)(\\[/caption\\])%', $content, $matches);

		if (count($matches[3] > 0)) {
			
			foreach ($matches[3] as $key => $value) {
				$alt = htmlspecialchars( trim ( strip_tags ( $matches[4][$key] ) ), ENT_COMPAT );
				
				if ( strpos( $value, '?' ) !== false ) {
				
					$img = explode('?', $value);
					$img = $img['0'];
				} else
					$img = $value;
			
				$bluditImg = '!['.$alt.']('.$img.')';
			
				$content = str_replace($matches[0][$key], $bluditImg, $content);
				
			}
		}
		
		//Let's now check if there are any images
		$pattern = '/<img\s*(?:class\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|src\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|alt\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|width\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|height\s*\=\s*[\'\"](.*?)[\'\"].*?\s*)+.*?>/si';
		
		preg_match_all($pattern, $content, $matches);

		if (count($matches[2] > 0)) {
			foreach ($matches[2] as $key => $value) {
				
				$alt = htmlspecialchars( trim ( $matches[3][$key] ), ENT_COMPAT );
				
				if ( strpos( $value, '?' ) !== false ) {
					
					$img = explode('?', $value);
					$img = $img['0'];
				} else
					$img = $value;
				
				$bluditImg = '!['.$alt.']('.$img.')';
				
				$content = str_replace($matches[0][$key], $bluditImg, $content);
				
			}
		}
		
		return $content;
	}
	
	//This function copies the image
	public function create_image($img_link, $upload_path, $name, $thumb = '')
	{
		
		$ext = pathinfo($img_link, PATHINFO_EXTENSION);
		$name = $name . '.' . $ext;
		
		if (is_file($upload_path . $name)) {
			echo 'File "' . $name . '" already exists...<br />';
			return false;
		}

		// try copying it... if it fails, go to backup method.
		if (!@copy($img_link, $upload_path . $name)) {
			//	create a new image
			list($img_width, $img_height, $img_type, $img_attr) = @getimagesize($img_link);

			$image = '';

			switch ($img_type) {
				case 1:
					//GIF
					$image = imagecreatefromgif($img_link);
					$ext = ".gif";
					break;
				case 2:
					//JPG
					$image = imagecreatefromjpeg($img_link);
					$ext = ".jpg";
					break;
				case 3:
					//PNG

					$image = imagecreatefrompng($img_link);
					$ext = ".png";
					break;
			}
			
			if (!empty($thumb))
			{
				$newwidth = 400;
				$newheight = 400;
			} else
			{
				$newwidth = $img_width;
				$newheightt = $img_height;
			}

			$resource = @imagecreatetruecolor($newwidth, $newheight);
			if (function_exists('imageantialias')) {
				@imageantialias($resource, true);
			}
			
			@imagecopyresampled($resource, $image, 0, 0, 0, 0, $newwidth, $newheight, $img_width,
				$img_height);

			@imagedestroy($image);
		

			switch ($img_type) {
				default:
				case 1:
					//GIF
					@imagegif($resource, $upload_path . $name);
					break;
				case 2:
					//JPG
					@imagejpeg($resource, $upload_path . $name);
					break;
				case 3:
					//PNG
					@imagepng($resource, $upload_path . $name);
					break;
			}
		

			if ($resource === '')
				return false;

		}

		return $name;
	}
	
}
