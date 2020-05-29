<?php

namespace skycubes\skyentities;

use \Exception;

class Definitions{

	protected const LANG_PATH = 'lang';
	protected const ENTITIES_PATH = 'entities';

	private $plugin;

	public function __construct(SkyEntities $plugin){

		$this->plugin = $plugin;

	}


	public function getLangPath(string $language){

		return $this->plugin->getDataFolder().self::LANG_PATH.'/'.$language.'.json';

	}

	public function getEntitiesPath($file = ''){

		return $this->plugin->getDataFolder().self::ENTITIES_PATH.'/'.$file;

	}

	public function getDef($def){

		return constant('self::'.$def);

	}



}

?>