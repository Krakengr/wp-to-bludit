<?php
			
class pluginWPToBludit extends Plugin {
	
	private $categories;		// Stored categories
	private $comments;			// Stored Comments
	private $posts;				// Stored posts
	private $tags;				// Stored tags
	private $attachments;		// Stored attachments
	private $page_pos;			// Current post Position
	private $com_id;			// Coment ID
	private $site_url;			// Site URL
	private $empty_title;		// Empty Title ID
	private $doc;				// Disqus Comments

	public function init() 
	{
		//ignore_user_abort(true);
		set_time_limit (0);
		
		define('PATH_COMMENTS',	PATH_CONTENT . 'databases' . DS . 'comments' . DS);
		
		ini_set('memory_limit', '750M');
		
		require ( $this->phpPath() . 'php' . DS . 'urlify' . DS . 'URLify.php' );
		
		require ( $this->phpPath() . 'php' . DS . 'image.class.php' );
	
		$this->dbFields = array(
			'disqus_id'=>'',
			'xmlfile'=>'',
			'url'=>'',
			'comments'=>'disable',
			'multilang'=>'',
			'merge'=>0,
			'delete'=>0,
			'copy'=>0,
			'embed'=>0
		);
		
		$this->categories = array();
		$this->comments = array();
		$this->tags = array();
		$this->posts = array();
		$this->attachments = array();
		$this->page_pos = 0;
		$this->empty_title = 0;
		$this->com_id = 0;
		$this->doc = '';				
		$this->site_url = $this->site_url(); // Site URL with a trailing slash
	}	
	
	/**
	 * Saves the DB
	 *
	 * @access public
	 */
	public function post()
	{
		if ( isset( $_POST['convert'] ) ) {
			
			self::convertXML();
			
		}

		else 
		{
	
			// Build the database
			$this->db['embed'] = (!empty($_POST['embed'])) ? 1 : 0;
			$this->db['delete'] = (!empty($_POST['delete'])) ? 1 : 0;
			$this->db['copy'] = (!empty($_POST['copy'])) ? 1 : 0;
			$this->db['merge'] = (!empty($_POST['merge'])) ? 1 : 0;
			$this->db['multilang'] = Sanitize::html($_POST['multilang']);
			$this->db['xmlfile'] = Sanitize::html($_POST['xmlfile']);
			$this->db['comments'] = Sanitize::html($_POST['comments']);
			$this->db['url'] = Sanitize::html($_POST['url']);
			$this->db['disqus_id'] = (!empty($_POST['disqus_id'])) ? Sanitize::html($_POST['disqus_id']) : '';

			// Save the database
			return $this->save();
		}
		
		return false;
	}

	public function form()
	{
		global $L;
		
		//if ( !empty( $this->getValue( 'xmlfile' ) ) && file_exists(PATH_UPLOADS . $this->getValue('xmlfile')))
			//$disabled = 'disabled';
		//else
			$disabled = '';
		
		$html = '<div>';
		$html .= '<label>'.$L->get('embed').'</label>';
		$html .= '<input type="checkbox" id="jsembed" name="embed" '.($this->getValue('embed')==1?'checked="checked"':'').' value="1" ' . $disabled . '/>';
		$html .= '<span class="tip"><small>'.$L->get('embed-info').'</small></span>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$L->get('copy').'</label>';
		$html .= '<input type="checkbox" id="jscopy" name="copy" '.($this->getValue('copy')==1?'checked="checked"':'').' value="1" ' . $disabled . '/>';
		$html .= '<span class="tip">'.$L->get('copy-info').'</small></span>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$L->get('merge').'</label>';
		$html .= '<input type="checkbox" id="jsmerge" name="merge" '.($this->getValue('merge')==1?'checked="checked"':'').' value="1" ' . $disabled . '/>';
		$html .= '<span class="tip">'.$L->get('merge-info').'</small></span>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$L->get('delete-xml-file').'</label>';
		$html .= '<input type="checkbox" id="jsdelete" name="delete" '.($this->getValue('delete')==1?'checked="checked"':'').' value="1" ' . $disabled . '/>';
		$html .= '<span class="tip">'.$L->get('delete-info').'</small></span>';
		$html .= '</div>';
		/*
		$html .= '<div>';
		$html .= '<label>'.$L->get('multilang').'</label>';
		$html .= '<input name="multilang" id="jsmultilang" type="text" value="'.$this->getValue('multilang').'" disabled>';
		$html .= '<span class="tip"><small>'.$L->get('multilang-info').'</small></span>';
		$html .= '</div>';
		*/
		$html .= '<div>';
		$html .= '<label>'.$L->get('url').'</label>';
		$html .= '<input name="url" id="jsurl" type="text" value="'.$this->getValue('url').'" ' . $disabled . '>';
		$html .= '<span class="tip"><small>'.$L->get('url-info').'</small></span>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$L->get('xml-name').'</label>';
		$html .= '<input name="xmlfile" id="jsxmlfile" type="text" value="'.$this->getValue('xmlfile').'" ' . $disabled . '>';
		$html .= '<span class="tip"><small>'.$L->get('xml-file').'</small></span>';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$L->get('copy-comments').'</label>';
		$html .= '<select name="comments">';
		$html .= '<option value="disable" '.($this->getValue('comments') == 'disable'?'selected':'').'>'.$L->get('disable-comments').'</option>';
		$html .= '<option value="internal" '.($this->getValue('comments') == 'internal'?'selected':'').'>'.$L->get('internal-comments').'</option>';
		$html .= '<option value="disqus" '.($this->getValue('comments') == 'disqus'?'selected':'').'>'.$L->get('disqus-comments').'</option>';
		$html .= '</select>';
		$html .= '</div>';
				
		$html .= '<div>';
		$html .= '<label>'.$L->get('disqus-id').'</label>';
		$html .= '<input name="disqus_id" id="jsdisqusID" type="text" value="'.$this->getValue('disqus_id').'" ' . $disabled . '>';
		$html .= '<span class="tip"><small>'.$L->get('disqus-empty').'</small></span>';
		$html .= '</div>';
		
		
		if ( !empty( $this->getValue( 'xmlfile' ) ) && file_exists(PATH_UPLOADS . $this->getValue('xmlfile'))) {
		
			$html .= '<div class="unit-100">';
			$html .= '<button class="uk-button uk-button-primary" value="true" type="submit" name="convert"><i class="uk-icon-life-ring"></i> ' .$L->get("convert-xml"). '</button>';
			$html .= '</div>';
			//$html .= '<br /><small>'.$L->get('locked').'</small>';
			$html .= '<style type="text/css" scoped>.uk-form-row button, .uk-form-row a {display:none};</style>';
			
		}
		
		return $html;
		
	}

	private function convertXML() 
	{
		global $L;
				
		//This is to continue the conversion. Doesn't work well yet...
		$this->loadDB();
		
		$xml = $this->loadPosts();
		
		$html = '';
		
		//Load the attachments into an array
		$this->attachments( $xml );

		//Let's begin the conversion...
		foreach($xml->channel->item as $item) 
		{
			$type = $item->xpath('wp:post_type');
			$type = (string) $type['0'];
			
			//Skip any item that is not post or page
			if( ($type == 'post') || ($type == 'page') )
			{

				$status = $item->xpath('wp:status');
				$status = $status['0'];
								
				$title = (string) $item->title;
						
				$date = $item->xpath('wp:post_date');
				$date = (string) $date['0'];
				
				//Keep the original post sef.
				$sef = $item->xpath('wp:post_name');
				$sef = (string) $sef['0'];
								
				//To avoid empty titles
				if ( $title === '' )
				{
					$this->empty_title++;
					$title = 'Empty Title - ' . $this->empty_title;
				}
				
				//Just to make sure that we have the proper SEF. We don't want orphan content files
				$sef = $this->sef ($sef, $title);
				
				//Save us time and memory and skip any post that exists...
				if ( ( $this->getValue('merge') == 1 ) && isset( $this->posts[$sef] ) )
				{
					continue;
				}
				
				//Before anything else, we want the name of the folder
				$p_dir = PATH_PAGES . $sef . DS;
				
				//We want the name of the file also
				$f_name = $p_dir . 'index.txt';

				//Count this as a position
				$this->page_pos++;

				$p_id = $item->xpath('wp:post_id');
				$p_id = (int) $p_id['0'];
		
				$comm = $item->xpath('wp:comment_status');
				$comm = $comm['0'];
				
				//Let's set the post's status
				$post_status = ( ($status == 'draft') ? 'draft' : ( ( strtotime($date) > time() ) ? 'scheduled' : 'published' ) );
				
				//...and the type of the post
				$post_type = ( ($type == 'post') ? 'post' : 'page' );
				
				//Set the comment status of the post
				$comment_status = ( ($comm == 'open') ? 'true' : 'false' );
				
				//Does this post has any comment?
				$comment = $item->xpath('wp:comment');
				
				//Let's create the uniqid of the post, based on the SEF, if we have it
				$uuid = ( !empty($sef) ? md5($sef) : md5(uniqid()) ); //md5(uniqid());
				
				//Find the category of the post
				$category = $this->category ( $item, $sef );
				
				//Load the content of the post
				$content = $this->content ( $item );
				
				//We can't continue if the folder doesn't exists...
				$this->makeDir( $p_dir );
				
				//Let's do some modifications to the content...
				$content = $this->replaceImage($content, $uuid);

				//Create (a new) file with the content
				@file_put_contents($f_name, $content);
								
				//Set the checksum of the file
				$checksum = md5_file($f_name);

				//Set Post Status
				$lstatus = ( ($status == 'draft') ? 'draft' : ( ($type == 'post') ? 'published' : 'static' ) );
				
				//We can't have any special chars in the DB
				$title = htmlspecialchars( $title ) ;
				
				//Database
				$this->posts[$sef] = array
				(
					'title' => mb_convert_encoding($title, "UTF-8"),
					'description' => mb_convert_encoding( $this->descr ( $content ) , "UTF-8"),
					'username' => 'admin',
					'tags' => $this->tags ( $item, $sef ),
					'type' => $lstatus,
					'date' => $date,
					'dateModified' => "",
					'allowComments' => $comment_status,
					'position' => $this->page_pos,
					'coverImage' => $this->getThumb ( $item, $uuid ),
					'md5file' => $checksum,
					'category' => ( (!empty($category) && ($type == 'post') ) ? $category : "" ),
					'uuid' => $uuid,
					'parent' => "",
					'template' => "",
					'noindex' => false,
					'nofollow' => false,
					'noarchive' => false
				);
					
				if ( $comment )
					$this->comments( $item, $uuid, $sef );
					
				echo '<strong>Post</strong>: "' . $title . '" converted successfully<br />';
				
			}

		}
		
		//Let's backup the data...
		if ($this->saveDB())
		{
			echo '<p><strong>' . $L->get("success") . '</strong></p>';//Alert::set($L->get("success"));
		
			//Delete the uploades XML WP file...
			if ( file_exists( PATH_UPLOADS . $this->getValue('xmlfile' ) ) && ( $this->getValue('delete') == 1 ) )
				@unlink ( PATH_UPLOADS . $this->getValue( 'xmlfile' ) );
				
			//We're ready...
			sleep ( 2 );
		
			echo "<script>window.location.replace('" . $this->site_url() . "admin/plugins');</script>";//Redirect::page('plugins');
		}
		
		echo "<script>window.location.replace('" . $this->site_url() . "admin/configure-plugin/pluginWPToBludit');</script>";

	}
	
	public function loadFile( $file )
	{
		return json_decode(file_get_contents($file, NULL, NULL, 50), TRUE);
	}
	
	public function attachments ( $xml ) 
	{
		$img_num = 0;
		
		//Keep all the nessecary attachments in an array. We need them for later...
		foreach($xml->channel->item as $item) 
		{
			$img_num++;
			
			$type = $item->xpath('wp:post_type');
			$type = $type['0'];
						
			if ($type == 'attachment') 
			{
				$thumb_id = 0;
								
				$p_id = $item->xpath('wp:post_id');
				$p_id = (int) $p_id['0'];
				
				$parent_id = $item->xpath('wp:post_parent');
				$parent_id = (int) $parent_id['0'];
				
				$attachment_url = $item->xpath('wp:attachment_url');
				$attachment_url = (string) $attachment_url['0'];
					
				$info = pathinfo($attachment_url);
				$attachment_name =  $info['basename'];//basename($attachment_url,'.'.$info['extension']);
				
				if (!empty($info['extension']) && strpos( $attachment_name, $info['extension'] ) === false )
					$attachment_name = $info['filename'] . '.' . $info['extension'];
								
				//Put every attachment in an array, we will need them later...
				$this->attachments[$p_id] = array 
				(
						//'id' => $p_id, 
						'parent' => $parent_id, 
						'name' => $attachment_name, 
						'url' => $attachment_url 
				);
								
			}
		}
		
	}
	
	public function sef ($string, $title)
	{
		//If the name is non-latin, create a new one
		if ( preg_match( '/[^\\p{Common}\\p{Latin}]/u', $string ) ) {
			$string = URLify::filter ( $title );
		}
				
		//Do we still have problems?
		if ( ( strpos( $string, '%' ) !== false ) || ( $string === '' ) ) 
		{
			$string = URLify::filter ( $title );
		}
		
		return $string;
	}
	
	public function descr ( $content )
	{
		if ( strpos($content, '<!-- pagebreak -->') )
		{
			$descr = explode ('<!-- pagebreak -->', $content);
			$descr = strip_tags($descr ['0']);
			$descr = $this->removeCaption ( $descr );
		} else {
			$descr = $this->removeCaption ( $content );
			$descr = $this->shorten( $descr, 160 ) ;
		}
		
		return $descr;
	}
	
	public function tags ( $item, $sef )
	{
		
		$p_tags = array();
		
		foreach ($item->category as $c) 
		{
			$att = $c->attributes();

			if ($att['domain'] == 'post_tag') 
			{
				$tag_name = (string) $c;
				
				$tag_name_seo = urldecode ( $tag_name );
				
				$tag_name_seo = URLify::filter ( $tag_name_seo );
							
				if( !isset($this->tags[$tag_name_seo]) )
					$this->tags[$tag_name_seo] = array('name' => $tag_name, 'list' => array( $sef ) );
				else
					array_push($this->tags[$tag_name_seo]['list'], $sef);
							
				if( !in_array( $tag_name_seo , $p_tags ) )
					$p_tags[$tag_name_seo] = $tag_name;
				
			}
			
		}
		
		return $p_tags;
		
	}
	
	public function category ( $item, $sef )
	{
		
		$cat_name_seo = '';
		
		$cat_pos = 0;
		
		foreach ($item->category as $c) 
		{
			$att = $c->attributes();

			if ($att['domain'] == 'category') 
			{
				//We need only one category...
				if ($cat_pos == 1)
					break;
				
				$cat_pos++;
				
				$cat_name = (string) $c;
							
				$cat_name_seo = urldecode ( $cat_name );
				$cat_name_seo = URLify::filter ( $cat_name_seo );
							
				if( !isset( $this->categories[$cat_name_seo] ) )
					$this->categories[$cat_name_seo] = array('name' => $cat_name, 'list' => array( $sef ) );
				else
					array_push($this->categories[$cat_name_seo]['list'], $sef);
			}

		}
		
		return $cat_name_seo;
		
	}
	
	public function replaceURL ( $content ) 
	{
		if ( !empty ( $this->getValue( 'url' ) ) )
		{
			$exURL = $this->getValue( 'url' );
						
			$last = $exURL[strlen($exURL)-1];
						
			if ($last != '/')
				$exURL = $exURL . '/';

			$exURL = str_replace (array("/", "."), array ("\/", "\."), $exURL );
						
			$content = preg_replace('/' . $exURL . '([0-9]{4}\/)?([0-9]{2}\/)?([0-9]{2}\/)?(([^_]+)\/)?([^_]+)\//', $this->site_url . "$6",  $content);

		}
		
		return $content;
	}
	
	public function content ( $item ) 
	{
		$content = $item->xpath('content:encoded');
		$content = (string) $content['0'];
		
		$content = str_replace( array('<!--more-->', '<!--nextpage-->'), array('<!-- pagebreak -->', ''), $content);
					
		if ( $this->getValue('embed') == 1 ) 
			$content = $this->url2embed ( $content );
		
		//Replace the inpost URLs if any...
		$content = $this->replaceURL ( $content );
		
		return $content;
	}
	
	public function makeDir( $dir )
	{
		if (!is_dir($dir))
			mkdir( $dir, 0755, true ) or die ( 'Could not create folder ' . $dir );
	}
	
	public function loadDB()
	{
		if ( $this->getValue('merge') == 1 )
		{
			if ( file_exists( DB_CATEGORIES ) )
				$this->categories = $this->loadFile( DB_CATEGORIES );
			
			if ( file_exists( DB_PAGES ) ) 
			{
				$this->posts = $this->loadFile( DB_PAGES );
				
				//For this, we need the last position to continue
				uasort( $this->posts, function($a, $b) { return $a['position'] - $b['position']; } );
				$last = end($this->posts);
				$this->page_pos = $last['position'];
			}
			
			if ( file_exists( DB_TAGS ) )
				$this->tags = $this->loadFile( DB_TAGS );
		}
	
	}
	
	public function comments( $item, $uuid, $sef )
	{
		
		//This is for disqus file. Don't bother with it...
		if ( ( $this->getValue('comments') == 'disqus' ) && !empty($this->getValue( 'disqus_id' ) ) )
		{
			
			//Comments XML File		
			$this->doc = new DOMDocument('1.0', 'UTF-8');
			// Friendly XML code
			$this->doc->formatOutput = true;
							
			//create "RSS" element
			$rss = $this->doc->createElement("rss");
			$rss_node = $this->doc->appendChild($rss); //add RSS element to XML node
			$rss_node->setAttribute("version","2.0"); //set RSS version

			//set attributes
			$rss_node->setAttribute("xmlns:content","http://purl.org/rss/1.0/modules/content/");
			$rss_node->setAttribute("xmlns:dsq","http://www.disqus.com/");
			$rss_node->setAttribute("xmlns:dc","http://purl.org/dc/elements/1.1/");
			$rss_node->setAttribute("xmlns:wp","http://wordpress.org/export/1.0/");
							
			//create "channel" element under "RSS" element
			$channel = $this->doc->createElement("channel");  
			$channel_node = $rss_node->appendChild($channel);
		}
		
		if ( $this->getValue('comments') == 'internal' )
			$this->makeDir( PATH_COMMENTS );
		
		//Do we need the comments?
		if ( !empty($this->getValue( 'disqus_id' ) ) || ( $this->getValue('comments') == 'internal' ) )
		{

			$comm = '';
						
			$comm = array();
						
			$comm_folder = PATH_COMMENTS . $uuid . DS;
						
			$this->makeDir( $comm_folder );
						
			$comm_file = $comm_folder . 'index.php';
						
			foreach ($item->xpath('wp:comment') as $co) 
			{
				$this->com_id++;
							
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
							
				if ($this->getValue('comments') == 'internal' )
				{
					//Commnets Database
					$comm[] = array
					(
						'comment_author' => htmlspecialchars( $com_author, ENT_QUOTES ),
						'comment_author_email' => $com_email,
						'comment_author_url' => $com_url,
						'comment_author_IP' => $com_IP,
						'comment_date' => strtotime( $com_date ),
						'comment_content' => htmlspecialchars( $com_content, ENT_QUOTES ),
						'comment_approved' => $com_approved
					);
								
				}
				
				elseif (!empty($this->getValue( 'disqus_id' ) ) )
				{
					$item_node = $channel_node->appendChild($doc->createElement("item")); //create a new node called "item"
					$title_node = $item_node->appendChild($doc->createElement( "title", htmlspecialchars($title, ENT_QUOTES) )); //Add Title under "item"
								
					$seo_node = $item_node->appendChild($doc->createElement("link", $this->site_url . $sef)); //Add link under "item"
								
					//create "description" node under "item"
					$description_node = $item_node->appendChild($doc->createElement("content:encoded"));  
								 
					//fill description node with CDATA content
					$description_contents = $doc->createCDATASection(htmlspecialchars($descr , ENT_QUOTES));  
					$description_node->appendChild($description_contents);
								  
					$dsq_node = $item_node->appendChild($doc->createElement("dsq:thread_identifier", $uuid)); //add dsq node under "item"
					$date_node = $item_node->appendChild($doc->createElement("wp:post_date_gmt", $date)); //add date node under "item"
					
					$comment_node = $item_node->appendChild($doc->createElement("wp:comment_status", $comment_status)); //add comment node under "item"
								 
					//Comment node
					$commend_node = $item_node->appendChild($doc->createElement("wp:comment")); //create a new node called "comment"
								
					//$dsq_remote = $commend_node->appendChild($doc->createElement("dsq:remote", '0')); //Add dsq:remote under "comment"
								
					//$dsq_id = $dsq_remote->appendChild($doc->createElement("dsq:id", '0')); //Add dsq:id under "dsq:remote"
								
					//$dsq_avatar = $dsq_remote->appendChild($doc->createElement("dsq:avatar", '0')); //Add dsq:avatar under "dsq:remote"
								
					$comment_id = $commend_node->appendChild($doc->createElement("wp:comment_id", $this->com_id)); //Add comment_id under "comment"
								
					$comment_author = $commend_node->appendChild($doc->createElement("wp:comment_author", $com_author)); //Add Title under "item"
								
					$comment_author_email = $commend_node->appendChild($doc->createElement("wp:comment_author_email", $com_email)); //Add Title under "item"
								
					$comment_author_url = $commend_node->appendChild($doc->createElement("wp:comment_author_url", $com_url)); //Add Title under "item"
								
					$comment_author_IP = $commend_node->appendChild($doc->createElement("wp:comment_author_IP", $com_IP)); //Add Title under "item"
								
					$comment_date_gmt = $commend_node->appendChild($doc->createElement("wp:comment_date_gmt", $com_date)); //Add Title under "item"
								
					$comment_content = $commend_node->appendChild($doc->createElement("wp:comment_content", htmlspecialchars($com_content , ENT_QUOTES)) ); 
								
					$comment_approved = $commend_node->appendChild($doc->createElement("wp:comment_approved", $com_approved)); //Add Title under "item"
								
					$comment_parent = $commend_node->appendChild($doc->createElement("wp:comment_parent", '0')); //Add Title under "item"
				}
			}
			
			if ( ( $this->getValue('comments') == 'internal' ) && !empty( $comm ) )
			{
				uasort( $comm, function($a, $b) { return $a['comment_date'] < $b['comment_date']; } );
				$comm = json_encode($comm, JSON_PRETTY_PRINT);
				$comm_dt = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>" . PHP_EOL . $comm;
				file_put_contents($comm_file, $comm_dt, LOCK_EX);	
			}
		}		
	}
	
	public function getThumb ( $item, $uuid ) 
	{
		$thumbnail_id = 0;
		
		$image_name_temp = '';
		
		$image = false;
		
		foreach ($item->xpath('wp:postmeta') as $meta)
		{
			$meta_key = $meta->xpath('wp:meta_key');
			$meta_key = (string) $meta_key['0'];
						
			if ($meta_key == '_thumbnail_id')
			{

				$thumbnail_id = $meta->xpath('wp:meta_value');
				$thumbnail_id = (int) $thumbnail_id['0'];

			}

		}

		if ( !empty($thumbnail_id) && isset($this->attachments[$thumbnail_id]) ) 
		{
			$image_url = $this->attachments[$thumbnail_id]['url'];

			$image_url = $this->returnImgUrl($image_url);
						
			$info = pathinfo($image_url);
						
			$image_name_temp = $info['basename'];
						
			$upload_folder = PATH_UPLOADS_PAGES . $uuid . DS;
			
			if (!empty($info['extension']) && strpos( $image_name_temp, $info['extension'] ) === false )
				$image_name_temp = $info['filename'] . '.' . $info['extension'];//$image_name_temp = $info['filename'] . '.' . $info['extension'];

			$image = $this->create_image($image_url, $upload_folder, $image_name_temp);

			if ( $image ) 
			{

				$thumbs_folder = $upload_folder . 'thumbnails' . DS;
							
				$this->makeDir( $thumbs_folder ) ;
							
				$this->create_image($image_url, $thumbs_folder, $image_name_temp, 50, true);
							
			}
			
		}

		return $image_name_temp;
	}
	
	public function saveDB()
	{
		//Posts and pages
		if ( !empty( $this->posts ) )
		{
			uasort( $this->posts, function($a, $b) { return $a['date'] < $b['date']; } );
			$posts = json_encode($this->posts, JSON_PRETTY_PRINT);
			$posts_dt = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>" . PHP_EOL . $posts;
			file_put_contents(DB_PAGES, $posts_dt, LOCK_EX);	
		}
		
		//Tags
		if ( !empty( $this->tags ) )
		{
			$tags = json_encode($this->tags, JSON_PRETTY_PRINT);
			$tags_dt = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>" . PHP_EOL . $tags;
			file_put_contents(DB_TAGS, $tags_dt, LOCK_EX);	
				
		}
		
		//Categories
		if ( !empty( $this->categories ) )
		{
			$categories = json_encode($this->categories, JSON_PRETTY_PRINT);
			$categories_dt = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>" . PHP_EOL . $categories;
			file_put_contents(DB_CATEGORIES, $categories_dt, LOCK_EX);	
				
		}
				
		//Create the disqus file if we want it...
		if ( !empty( $this->getValue( 'disqus_id' ) ) )
			file_put_contents(PATH_UPLOADS . '/comments.xml', $this->doc->saveXML(), LOCK_EX);
		
		return true;
	}
	
	public function loadPosts()
	{
		global $L;
		
		//If the convert button is pressed but the file is not there, don't continue...		
		if ( !empty( $this->getValue( 'xmlfile' ) ) && !file_exists(PATH_UPLOADS . $this->getValue('xmlfile'))) {
			Alert::set($L->get("no-file-found"));
			Redirect::page('configure-plugin/pluginWPToBludit');
		}
		
		//Load the XML data, before we delete everything
		$xml = simplexml_load_string($this->stripInvalidXml(file_get_contents(PATH_UPLOADS . $this->getValue('xmlfile'))));
		
		//Is this a valid xml data?
		if ($xml === false) {
			Alert::set($L->get("file-error"));
			Redirect::page('configure-plugin/pluginWPToBludit');
		}
		
		return $xml;		
	}
		
	public function rrmdir($dir) {
	  if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
		  if ($object != "." && $object != "..") {
			if (filetype($dir."/".$object) == "dir") 
			   $this->rrmdir($dir."/".$object); 
			else unlink   ($dir."/".$object);
		  }
		}
		reset($objects);
		rmdir($dir);
	  }
	}
	
	public function site_url() 
	{
		global $site;
		
		$site_url = $site->url();
						
		$last = $site_url[strlen($site_url)-1];
						
		if ($last != '/')
			$site_url = $site_url . '/';
		
		return $site_url;
		
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
	
	private function returnImgUrl($img)
	{
		if ( strpos( $img, '?' ) !== false ) 
		{
			$img = explode('?', $img);
			$img = $img['0'];
		}
		
		return $img;
	}
	
	/**
	 * Returns all the images found in a text
	 *
	 * @access public
	 * @param string $content
	 * @return array
	 */
	private function getImages($content)
	{
		$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/ii', $content, $matches);
			
		if (!empty($matches[1]))
			return $matches[1];

		return false;
	}
	
	/**
	 * Replaces the caption tag into html and copies the image
	 *
	 * @access public
	 * @param string $content
	 * @param string $uuid
	 * @return string
	 */
	private function replaceImage($content, $uuid)
	{
		
		//First let's find if there are any images with caption...
		preg_match_all('%(\\[caption.*])(.*)<img.+src=[\'"]([^\'"]+)[\'"].*>(.*)(\\[/caption\\])%', $content, $matches);

		if (count($matches[3] > 0)) {
			
			foreach ($matches[3] as $key => $value) 
			{
				
				$alt = trim ( $matches[4][$key] );
				
				if ( strpos( $value, '?' ) !== false ) 
				{
				
					$img = explode('?', $value);
					$img = $img['0'];
				} 
				
				else
					$img = $value;
				
				$imgf = explode('/', $img);
				
				$info = pathinfo($img);
				
				if ( $this->getValue('copy') == 1 ) 
				{
					$img_temp = $info['basename'];
					
					if (!empty($info['extension']) && strpos( $img_temp, $info['extension'] ) === false )
						$img_temp = $info['filename'].'.'.$info['extension'];
					
					$upload_folder = PATH_UPLOADS_PAGES . $uuid . DS;
					
					$this->makeDir( $upload_folder );
										
					$img_copy = $this->create_image($value, $upload_folder, $img_temp);
					
					if (!empty($img_copy)) 
					{
						
						$thumbs_folder = $upload_folder . 'thumbnails' . DS;
						
						$this->makeDir( $thumbs_folder );
												
						$this->create_image($value, $thumbs_folder, $img_temp, 50, true);
						
						$img = $img_temp;
					}
				}
							
				$img_final_url = $this->site_url() . 'bl-content/uploads/pages/' . $uuid . '/' . $img;
				
				$bluditImg = '<figure style="width: 640px" class="wp-caption aligncenter"><img src="' . $img_final_url . '" alt="" class="size-full wp-image" align="middle"><figcaption class="wp-caption-text">' . $alt . '</figcaption></figure>';
			
				$content = str_replace($matches[0][$key], $bluditImg, $content);
				
			}
		}
		
		//Let's now check if there are any images without caption	
		$pattern = '/<img\s*(?:class\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|src\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|alt\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|width\s*\=\s*[\'\"](.*?)[\'\"].*?\s*|height\s*\=\s*[\'\"](.*?)[\'\"].*?\s*)+.*?>/si';
				
		preg_match_all($pattern, $content, $matches);
			
		if (count($matches[2] > 0)) 
		{
			foreach ($matches[2] as $key => $value) 
			{
									
				if ( strpos( $value, '?' ) !== false ) 
				{
						
					$img = explode('?', $value);
					$img = $img['0'];
				} 
				
				else
					$img = $value;
					
				$imgf = explode('/', $img);
									
				$info = pathinfo($img);
					
				if ( $this->getValue('copy') == 1 ) 
				{
					$img_temp = $info['basename'];
						
					$upload_folder = PATH_UPLOADS_PAGES . $uuid . DS;
						
					$this->makeDir( $upload_folder );
					
					if (!empty($info['extension']) && strpos( $img_temp, $info['extension'] ) === false )
						$img_temp = $info['filename'] . '.' . $info['extension'];
						
					$img_copy = $this->create_image($value, $upload_folder, $img_temp);
						
					if (!empty($img_copy)) 
					{
						$thumbs_folder = $upload_folder . 'thumbnails' . DS;
							
						$this->makeDir( $thumbs_folder );
						
						$this->create_image($value, $thumbs_folder, $img_temp, 50, true);

					}
				}
					
				$bluditImg = '<img src="' . $this->site_url() . 'bl-content/uploads/pages/' . $uuid . '/' . $img_temp . '" alt="" class="size-full wp-image aligncenter" />';
									
				$content = str_replace($matches[0][$key], $bluditImg, $content);

			}
		}
		
		return $content;
	}
	
	/**
	 * Replaces the caption tag into html
	 *
	 * @access public
	 * @param string $content
	 * @return string
	 */
	 /*
	public function captionHtml ( $content )
	{
		
		$pat = '/\[caption[^\]\]](.*)?<img.+src=[\'"]([^\'"]+)[\'"].*>(<\/a>)?(.*)\[\/caption\]/';
				
		$code = '<figure style="width: 640px" class="wp-caption aligncenter"><img src="$2" alt="" class="size-full wp-image" align="middle"><figcaption class="wp-caption-text">' . htmlspecialchars( "$3" ) . '</figcaption></figure>';

			
		$text = preg_replace($pat, $code, $text );
		
		return $text;
		
	}
	*/
	
	/**
	 * Copies the image from the URL given
	 *
	 * @access public
	 * @param string $img_link
	 * @param string $upload_path
	 * @param string $name
	 * @param int $scale
	 * @param boolean $thumb
	 * @return bool
	 */
	public function create_image($img_link, $upload_path, $name, $scale = 75, $thumb = false) 
	{
		if ( file_exists( $upload_path . $name ) ) 
		{
			echo '<strong>File</strong> "' . $name . '" already exists...<br />';
			
			return true;
		}
		
		if (@copy($img_link, $upload_path . $name)) 
		{
				
			$image = new SimpleImage( $upload_path . $name );
			
			if ( $thumb ) 
			{
				$image->resizeToWidth(400);
			}
				
			$image->scale( $scale );
				
			$image->save( $upload_path . $name );
			
			return true;
		}
		else
			return false;
	}
	
	/**
	 * Removes invalid XML
	 *
	 * @access public
	 * @param string $value
	 * @return string
	 */
	public function stripInvalidXml($value)
	{
		$ret = "";
		$current;
		if (empty($value)) 
		{
			return $ret;
		}

		$length = strlen($value);
		for ($i=0; $i < $length; $i++)
		{
			$current = ord($value{$i});
			if (($current == 0x9) ||
				($current == 0xA) ||
				($current == 0xD) ||
				(($current >= 0x20) && ($current <= 0xD7FF)) ||
				(($current >= 0xE000) && ($current <= 0xFFFD)) ||
				(($current >= 0x10000) && ($current <= 0x10FFFF)))
			{
				$ret .= chr($current);
			}
			else
			{
				$ret .= " ";
			}
		}
		return $ret;
	}
	
}
