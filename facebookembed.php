<?php
/**
 * 
* @copyright Copyright (C) 2012 Stephane Martin. All rights reserved.
* @license GNU/GPL
*
* Version 1.0

*/

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

jimport( 'fb-sdk.src.facebook'); 



/**
* Facebook Album Embedder Content Plugin
*
*/
class plgContentFacebookEmbed extends JPlugin
{
	public $facebook;
	public $fan_id;
	public $app_id;
	public $secret_key;
	public $max_photos;
	public $slideshow;

	/**
	* Constructor
	*
	* @param object $subject The object to observe
	* @param object $params The object that holds the plugin parameters
	*/
	function plgContentFacebookEmbed( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		
		$pluginParams = $this->params;
	
		$this->fan_id=$pluginParams->get('fan_id','');
		$this->app_id=$pluginParams->get('app_id','');
		$this->secret_key=$pluginParams->get('secret_key','');
		$this->max_photos=$pluginParams->get('max_photos','10');
		$this->slideshow=$pluginParams->get('slideshow','popeye');
		
		$this->facebook = new Facebook(array(
		'appId'  => $this->app_id,
		'secret' => $this->secret_key,
		'cookie' => true, // enable optional cookie support
		));
		
	
	}

	
 	/**
	* Example prepare content method in Joomla 1.6/1.7/2.5
	*
	* Method is called by the view
	*
	* @param object The article object. Note $article->text is also available
	* @param object The article params
	*/   
	function onContentPrepare($context, &$row, &$params, $page = 0){
		//jimport('joomla.html.parameter');
		
		//global $mainframe;
		
		//$plugin	=& JPluginHelper::getPlugin('content', 'facebookembed');
		//$pluginParams = $this->params;
	
		//$this->fan_id=$pluginParams->get('fan_id','');
		//$this->app_id=$pluginParams->get('app_id','');
		//$this->secret_key=$pluginParams->get('secret_key','');
	
		//$uri =& JURI::getInstance();
		//$curl = $uri->toString();
	
		//$config =& JFactory::getConfig();
	
		//$lang=&JFactory::getLanguage();
		//$lang_tag=$lang->getTag();
		//$lang_tag=str_replace("-","_",$lang_tag);
		
		//$this->facebook = new Facebook(array(
		//'appId'  => $this->app_id,
		//'secret' => $this->secret_key,
		//'cookie' => true, // enable optional cookie support
		//));
        
 		if ( JString::strpos( $row->text, 'fbembed' ) === false ) {
            return true;
		}
		
		//On passe une chaine {fbembed album_id[,photo1_is,photo2_id...]}
		// expression to search for
        $regex = '/{fbembed\s*.*?}/i';
 
		// find all instances of plugin and put in $matches
		preg_match_all( $regex, $row->text, $matches );

		// Number of plugins
		$count = count( $matches[0] );
				
		// plugin only processes if there are any instances of the plugin in the text
		if ( $count ) {
			$this->_process( $row, $matches, $count, $regex);
		}	
		
		return true;        
        
	}
	
	 protected function _process( &$row, &$matches, $count, $regex )
	{
			for ( $i=0; $i < $count; $i++ )
			{
					$content = str_replace( 'fbembed', '', $matches[0][$i] );
					$content = str_replace( '{', '', $content );
					$content = str_replace( '}', '', $content );
					$content = trim( $content );

					$slideshow = $this->facebookCodeEmbedv2($content);
					$row->text = preg_replace( '{'. $matches[0][$i] .'}', $slideshow, $row->text );
			}
			
			// removes tags without matching module positions
            $row->text = preg_replace( $regex, '', $row->text );
	}
    		
	static		function compareCreatedTime($a, $b) { 
		return strcmp($a['created_time'], $b['created_time']);
	}
	
	public function facebookGraphAPI_getalbuminfo($album_id)
	{
		$album=array();
		
		$album = $this->facebook->api("/".$album_id."?fields=name,description,id");
		
		return $album;
	}
	
	public function facebookFQL_getalbuminfo($album_id) {
		
		$fql    =   "SELECT aid, object_id, name, description FROM album WHERE object_id = '".$album_id."'";
		$param  =   array(

		 'method'    => 'fql.query',
		 'query'     => $fql,
		 'callback'  => ''

		);
		$fqlResult   =   $this->facebook->api($param);
		return $fqlResult;
	}
	
	public function facebookGraphAPI_getalbumphotos($album_id)
	{
		$photos=array();
		
		$photos = $this->facebook->api("/".$album_id."/photos", "get");
		
		return $photos;
	}
    
 	/**
	* Function to insert the facebook gallery
	*
	* Method is called by the onContentPrepare or onPrepareContent
	*
	* @param string The text string to find and replace
	*/       
	public function facebookCodeEmbedv2( $content )
	{
		//On demonte la chaine album_id[,photo1_is,photo2_id....]
		$items = array();
		$items = explode(',', $content);
		
		//L'album id est en premier
		$album_id = $items[0];
			
		/* Lorsque le id album est de 1..12 : c'est un index depuis le dernier créé!*/
		if ($album_id<=12) 
		{
			$albums = $this->facebook->api('/'.$this->fan_id.'/albums?fields=id,type,created_time');
			usort($albums['data'], array($this, 'compareCreatedTime'));
			
			$index=1;
			foreach($albums['data'] as $album)
			{      
				if ($album['type']=='normal') {
					if ($index==$album_id) {
						$album_id = $album['id'];
						break;
					}
					$index++;
				}
			}
			//Pas trouvé ?
			if ($album_id<=12) return 'Album '.$album_id.' non disponible';      
		}
	
		//Album details
		$album = $this->facebookGraphAPI_getalbuminfo($album_id);
				
		//Photos de l'album
		$photos_id_list=array();
		if (count($items)>1) {
			$photos_id_list = array_slice($items, 1);
		}
		//On demande les photos a facebook...
		$photos = $this->facebookGraphAPI_getalbumphotos($album_id);
				
		//Construction du slideshow
		$slideshow = "<div class='facebook_slider'><ul class='ppy-imglist'>";
		
		$photos_counter=0;
		foreach( $photos['data'] as $keys => $values ){

			//On verifie le nombre max de photos
			if ($photos_counter<$this->max_photos) {
			
				//On check l'id dans la liste
				if ((count($photos_id_list)==0) || (in_array($values['id'] , $photos_id_list)))
				{
					$caption='';
					if (isset($values['name'])) {
						if( $values['name'] == '' ){
							$caption = "";
						}else{
							$caption = $values['name'];
						} 
					}
					
					$slideshow .= "<li><a href=\"" . $values['source'] . "\" >";
					$slideshow .= "<img src='" . $values['images'][4]['source'] . "' alt=\"" . $caption . "\" />";
				
					$slideshow .= "</a></li>";
					$photos_counter++;
				}
			}
			else break;
			
			
		}

		$slideshow .= "</ul>";
		$slideshow .= "<div class=\"ppy-outer\">
						<div class=\"ppy-stage\">
							<div class=\"ppy-nav\">
								<a class=\"ppy-prev\" title=\"Photo précédente\">Previous image</a>
								<a class=\"ppy-switch-enlarge\" title=\"Enlarge\">Enlarge</a>
                        <a class=\"ppy-switch-compact\" title=\"Close\">Close</a>
								<a class=\"ppy-next\" title=\"Photo suivante\">Next image</a>
							</div>
						</div>
					</div>
					<div class=\"ppy-caption\">
					   
						<span class=\"ppy-text\"></span>
					</div>";
		$slideshow .= "</div>";
		
		//Titre de l'album
		$slideshow .= "<div class=\"facebook_slider_name\">".$album['name']."</div>";
		$slideshow .= "<div class=\"facebook_slider_description\">".$album['description']."</div>";
		
		return $slideshow;
	}
}
