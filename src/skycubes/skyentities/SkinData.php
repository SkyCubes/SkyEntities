<?php

namespace skycubes\skyentities;
use skycubes\skyentities\Translate;

use \Exception;

class SkinData{
	protected $plugin;
	protected $translator;
	protected $definitions;

	private $skin;

	public function __construct(SkyEntities $plugin, $skin){
		$this->plugin = $plugin;
		$this->translator = new Translate($plugin);

		$this->definitions = new Definitions($this->plugin);

		$this->skin = $skin;
	}

	/** 
    * Returns skin texture in bytes and saves it on .data file if doesnt exists
    * @access public 
    * @param String $skinPath
    * @return String
    */
	public function getBytes(){

		$skinPath = $this->definitions->getEntitiesPath($this->skin.'.png');
		$dataPath = $this->definitions->getEntitiesPath($this->skin.'.data');

		if(file_exists($dataPath)){
			return file_get_contents($dataPath);
		}

		if(file_exists($skinPath)){

	        $img = @imagecreatefrompng($skinPath);
	        $bytes = '';
	        $size = 0;
	        $l = (int) @getimagesize($skinPath)[1];
	        for ($y = 0; $y < $l; $y++) {
	            for ($x = 0; $x < @getimagesize($skinPath)[0]; $x++) {
	                $rgb = @imagecolorat($img, $x, $y);
	                $a = (127 - (($rgb >> 24) & 0x7F)) * 2;
	                $r = ($rgb >> 16) & 0xff;
	                $g = ($rgb >> 8) & 0xff;
	                $b = $rgb & 0xff;
	                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
	                $size++;
	            }
	        }
	        @imagedestroy($img);

	        if($size == (64 * 32 * 4) || $size == (64 * 64 * 4) || $size == (128 * 128 * 4)){
	        	@file_put_contents($this->definitions->getEntitiesPath($this->skin.'.data'), $bytes);
	        	return $bytes;
	        }else{
	        	throw new Exception($this->translator->get('SKIN_SIZE_NOT_SUPPORTED'));
	        }

		}else{
			throw new Exception($this->translator->get('SKIN_NOT_FOUND'));
		}

	}

	/** 
    * Returns geometry id defined into geometry .json file
    * @access public
    * @return String
    */
	public function getGeometryId(){
		$geometryPath = $this->definitions->getEntitiesPath($this->skin.'.json');

		if(file_exists($geometryPath)){
	        $geometryData = json_decode(file_get_contents($geometryPath), true);

	        if(isset($geometryData['minecraft:geometry'][0]['description']['identifier'])){

	        	return $geometryData['minecraft:geometry'][0]['description']['identifier'];

	        }else{
	        	throw new Exception($this->translator->get('GEOMETRY_ID_NOT_FOUND'));
	        }
    	}else{
    		throw new Exception($this->translator->get('GEOMETRY_FILE_NOT_FOUND'));
    	}
	}

	/** 
    * Returns content of geometry .json file
    * @access public
    * @return String
    */
	public function getGeometry(){
		$geometryPath = $this->definitions->getEntitiesPath($this->skin.'.json');

		if(file_exists($geometryPath)){

			$geometry = file_get_contents($geometryPath);

        	return $geometry;
        	
		}else{
			throw new Exception($this->translator->get('GEOMETRY_FILE_NOT_FOUND'));
		}
        
	}

	/** 
    * Search skin files and return true if they exists
    * @access public
    * @param String $skin
    * @return Bool
    */
	public function issetSkin($skin=NULL){
		$skin = $skin ?? $this->skin;
		$skinPath = $this->definitions->getEntitiesPath($skin.'.png');
		$geometryPath = $this->definitions->getEntitiesPath($skin.'.json');

		return (file_exists($skinPath) && file_exists($geometryPath));
	}

	/** 
    * Retrieve skin name
    * @access public
    * @return String
    */
	public function getName(){
		return $this->skin;
	}



}

?>