<?php

namespace skycubes\skyentities;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\Player;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\entity\Skin;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\level\ChunkLoadEvent;

use skycubes\skyentities\Definitions;
use skycubes\skyentities\SkinData;
use skycubes\skyentities\Translate;
use \Exception;

class SkyEntities extends PluginBase implements Listener{

	private $definitions;
	private $config;
	protected $translator;

	private $is_inspecting = array();

	public function onLoad(){

		$this->definitions = new Definitions($this);

        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder().$this->definitions->getDef('LANG_PATH'));
        @mkdir($this->getDataFolder().$this->definitions->getDef('ENTITIES_PATH'));
        foreach(array_keys($this->getResources()) as $resource){
			$this->saveResource($resource, false);
		}

    }

	public function onEnable(){

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->config = new Config($this->getDataFolder().'config.yml', Config::YAML);
		$this->entities = new Config($this->getDataFolder()."entities.yml", Config::YAML);

		$this->translator = new Translate($this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case "se":
				
				if(isset($args[0])) switch ($args[0]){

					case 'create':
						if(!($sender instanceof Player)){
							$sender->sendMessage("§c".$this->translator->get('ONLY_PLAYER_CMD'));
							return true;
						}
						if(!isset($args[1]) || !isset($args[2])){
							$sender->sendMessage("§c".$this->translator->get('CMD_CREATE_USAGE'));
							return true;
						}

						$entityName = $args[1];
                        $skin = $args[2];

						if($this->entities->get($entityName)){
							$sender->sendMessage("§c".$this->translator->get('ENTITY_NAME_ALREADY_EXISTS'));
							return true;
						}

						$skinData = new SkinData($this, $skin);

						if($skinData->issetSkin()){
							$entity = $this->spawnEntity($sender, $entityName, $skin);
	                        if($entity !== false){
	                            $sender->sendMessage("§a".$this->translator->get('ENTITY_SUCCESSFULLY_CREATED'));

	                            if($sender->getLevel()->save()){
	                                $this->getLogger()->info("§a".$this->translator->get('WORLD_SAVED_AFTER_ENTITY_CREATION', ["§6".$sender->getLevel()->getName()."§a"]));
	                            }
	                        }else{
	                            $sender->sendMessage("§c".$this->translator->get('ENTITY_CREATION_ERROR'));
	                        }
						}else{
							$sender->sendMessage("§c".$this->translator->get('SKIN_NOT_FOUND'));
						}
                        

                        break;

                    case 'remove':
                    	if(!isset($args[1])){
							$sender->sendMessage("§c".$this->translator->get('CMD_REMOVE_USAGE'));
							return true;
						}
						$entityName = $args[1];
						$entityData = $this->entities->get($entityName);

						if($entityData){

							if($sender->getLevel()->getName() == $entityData['level']){

								$entity = $this->getEntityByName($sender, $entityName);

								if($entity !== false){

									if($this->removeEntity($entity)){
										$sender->sendMessage("§a".$this->translator->get('ENTITY_SUCCESSFULLY_REMOVED'));
									}else{
										$sender->sendMessage("§c".$this->translator->get('CANT_REMOVE_ENTITY'));
									}

								}else{
									$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
								}
								

							}else{
								$sender->sendMessage("§c".$this->translator->get('CANT_CMD_OUTSIDE_LEVEL', ["§6".$entityData['level']."§c"]));
							}

						}else{
							$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
						}

						break;

                    case 'scale':
                    	if(!isset($args[1]) || !isset($args[2]) || !is_numeric($args[2])){
                            $sender->sendMessage("§c".$this->translator->get('CMD_SCALE_USAGE'));
                            return true;
                        }

                        $entityName = $args[1];
                        $entityData = $this->entities->get($entityName);
                        $scale = floatval($args[2]);

                        if($entityData){

                        	if($sender->getLevel()->getName() == $entityData['level']){

                        		$entity = $this->getEntityByName($sender, $entityName);

                        		if($entity instanceof Entity){

                        			if($this->setEntityScale($entity, $scale)){
                        				$sender->sendMessage("§a".$this->translator->get('ENTITY_SUCCESSFULLY_SCALED'));
                        			}else{
                        				$sender->sendMessage("§c".$this->translator->get('ENTITY_SCALING_ERROR'));
                        			}

                        		}else{
                        			$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
                        		}

                        	}else{
                        		$sender->sendMessage("§c".$this->translator->get('CANT_CMD_OUTSIDE_LEVEL', ["§6".$entityData['level']."§c"]));
                        	}

                        }else{
                        	$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
                        }
                    	break;

                   	case 'rotate':
                   		if(!isset($args[1])){
                            $sender->sendMessage("§c".$this->translator->get('CMD_ROTATE_USAGE'));
                            return true;
                        }

                        $entityName = $args[1];
                        $entityData = $this->entities->get($entityName);
                        $angle = (isset($args[2]) && is_numeric($args[2])) ? floatval($args[2]) : NULL;

                        if($entityData){

                        	if($sender->getLevel()->getName() == $entityData['level']){

	                        	$entity = $this->getEntityByName($sender, $entityName);

		                        if($entity instanceof Entity){

		                        	$this->setEntityRotation($sender, $entity, $angle);
		                        	$sender->sendMessage("§a".$this->translator->get('ENTITY_SUCCESSFULLY_ROTATED'));

		                        }else{
		                        	$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
		                        }

	                    	}else{
	                    		$sender->sendMessage("§c".$this->translator->get('CANT_CMD_OUTSIDE_LEVEL', ["§6".$entityData['level']."§c"]));
	                    	}

                        }else{
                        	$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
                        }  
                   		break;

                   	case 'move':
                   		if(!isset($args[1])){
                            $sender->sendMessage("§c".$this->translator->get('CMD_MOVE_USAGE'));
                            return true;
                        }

                        $entityName = $args[1];
                        $entityData = $this->entities->get($entityName);

						if($entityData){

                        	if($sender->getLevel()->getName() == $entityData['level']){

	                        	$entity = $this->getEntityByName($sender, $entityName);
	                        	$entity->setPosition($sender->getPosition());

	                        	$sender->sendMessage("§a".$this->translator->get('ENTITY_MOVED_TO_YOU'));

	                        }else{
	                        	$sender->sendMessage("§c".$this->translator->get('CANT_CMD_OUTSIDE_LEVEL', ["§6".$entityData['level']."§c"]));
	                        }

	                    }else{
	                    	$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
	                    }
	                    break;

	                case 'addcommand':
	                case 'addcmd':
	                	if(!isset($args[1]) || !isset($args[2])){
                            $sender->sendMessage("§c".$this->translator->get('CMD_ADDCMD_USAGE'));
                            return true;
                        }

                        $entityName = $args[1];
                        $entityData = $this->entities->get($entityName);

						if($entityData){
							$command = trim(implode(" ", array_slice($args, 2, count($args))));

							if($this->addEntityCommand($entityName, $command)){
								$sender->sendMessage("§a".$this->translator->get('ENTITY_CMD_ADDED'));
							}
							
						}else{
							$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
						}
	                	break;

	                case 'deletecommand':
	                case 'delcommand':
	                case 'delcmd':
	                	if(!isset($args[1]) || !isset($args[2]) || !is_numeric($args[2])){
                            $sender->sendMessage("§c".$this->translator->get('CMD_DELCMD_USAGE'));
                            return true;
                        }

                        $entityName = $args[1];
                        $entityData = $this->entities->get($entityName);
                        $commandId = $args[2];

						if($entityData){

							if($this->delEntityCommand($entityName, $commandId)){
								$sender->sendMessage("§a".$this->translator->get('ENTITY_CMD_DELETED'));
							}
							
						}else{
							$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
						}
						break;

					case 'setskin':
						if(!isset($args[1]) || !isset($args[2])){
                            $sender->sendMessage("§c".$this->translator->get('CMD_SETSKIN_USAGE'));
                            return true;
                        }

                        $entityName = $args[1];
                        $entityData = $this->entities->get($entityName);

                        $skinName = $args[2];

						if($entityData){

                        	if($sender->getLevel()->getName() == $entityData['level']){

	                        	$entity = $this->getEntityByName($sender, $entityName);

	                        	$skin = new SkinData($this, $skinName);
	                        	if($skin->issetSkin()){

	                        		$this->setEntitySkin($entity, $skin);
	                        		$sender->sendMessage("§a".$this->translator->get('ENTITY_SKIN_SUCCESSFULLY_SET'));

	                        	}else{
	                        		$sender->sendMessage("§c".$this->translator->get('SKIN_NOT_FOUND'));
	                        	}

	                        }else{
	                        	$sender->sendMessage("§c".$this->translator->get('CANT_CMD_OUTSIDE_LEVEL', ["§6".$entityData['level']."§c"]));
	                        }

	                    }else{
	                    	$sender->sendMessage("§c".$this->translator->get('ENTITY_NOT_FOUND'));
	                    }

						break;

                    case 'inspect':
                    	$this->is_inspecting[] = $sender->getName();
                        $sender->addTitle("\n", "§a".$this->translator->get('CLICK_ENTITY'), 20, 20, 20);

                       	break;

                    case 'availables':

                    	$entities = $this->getAvailableEntities();
                    	$sender->sendMessage("§e§l".$this->translator->get('AVAILABLE_ENTITIES').":");
                    	foreach ($entities as $entity){
                    		if(file_exists($this->definitions->getEntitiesPath($entity.'.data'))){
                    			$sender->sendMessage("§f  - §a".$entity);
                    		}else{
                    			$sender->sendMessage("§f  - §c".$entity);
                    		}
                    	}
                    	break;

                    default:
                    	break;
                }

                break;

            default:
                break;
        }

        return true;
    }

    /** 
    * Search and retrieve Human obj if isset IntTag 'SkyEntity' and StringTag 'EntityName' == $entityName
    * @access public
    * @param pocketmine\Player $player
    * @param String $entityName
    * @return pocketmine\entity\Entity|Bool
    */
    public function getEntityByName(Player $player, $entityName){
        $entities = $player->getLevel()->getEntities();
        if($entities){
            foreach ($entities as $entity){
                if($entity->namedtag->hasTag("SkyEntity", IntTag::class)){
                    if($entity->namedtag->getString("EntityName") == $entityName){
                        return $entity;
                    }
                }
            }
            return false;
        }else{
            return false;
        }
    }

    /** 
    * Spawn entity with skin and geometry at player position
    * @access public
    * @param pocketmine\Player $player
    * @param String $entityName
    * @param String $skinName
    * @return Int|Bool
    */
    public function spawnEntity(Player $player, $entityName, $skinName){

    	$level = $player->getLevel();

        $nbt = Entity::createBaseNBT($player, null, $player->getYaw(), 0);
        $nbt->setShort("Health", 20);
        $nbt->setTag(new IntTag("SkyEntity", 1));
        $nbt->setTag(new StringTag("EntityName", $entityName));
        $nbt->setTag(new FloatTag("EntityScale", 1));
        $nbt->setTag(new FloatTag("EntityRotation", $player->getYaw()));
        $nbt->setTag(new StringTag("EntitySkin", $skinName));
        $skinTag = $player->namedtag->getCompoundTag("Skin");
        $nbt->setTag(clone $skinTag);

        $entity = new Human($level, $nbt);
        $entity->setNameTag("");

        $skin = new SkinData($this, $skinName);

        if(!$skin->issetSkin()) return false;

        $entity->setSkin(new Skin($skinName, $skin->getBytes(), "", $skin->getGeometryId(), $skin->getGeometry()));
        $entity->sendSkin();

        $entity->spawnToAll();

        $this->entities->set($entityName, array(
        	"level" => $level->getName(),
            "commands" => array()
        ));
        $this->entities->save();

        return $entity->getId() ?? false;

    }

    /** 
    * Despawn given entity and remove it from entities.yml
    * @access public
    * @param pocketmine\entity\Entity $entity
    * @return void
    */
    public function removeEntity(Entity $entity){
    	$entityName = $entity->namedtag->getString("EntityName");

        $entity->close();
        $this->entities->remove($entityName);
        return $this->entities->save();
    }

    /** 
    * Change given entity scale
    * @access public
    * @param pocketmine\entity\Entity $entity
    * @param Float $scale
    * @return Bool
    */
    public function setEntityScale(Entity $entity, float $scale){
    	if($scale > 0){
    		$entity->setScale($scale);
            $entity->namedtag->setTag(new FloatTag("EntityScale", $scale));

            return true;
    	}else{
    		return false;
    	}
    }

    /** 
    * Change given entity rotation
    * @access public
    * @param pocketmine\Player $player
    * @param pocketmine\entity\Entity $entity
    * @param Float $angle
    * @return void
    */
    public function setEntityRotation(Player $player, Entity $entity, ?float $angle){

    	if($angle == NULL){
    		$x = $player->getX() - $entity->getX();
            $y = $player->getY() - $entity->getY();
            $z = $player->getZ() - $entity->getZ();
            $angle = rad2deg(atan2(-$x,$z));
    	}

    	$entity->setRotation($angle, 0);
		$entity->namedtag->setTag(new FloatTag("EntityRotation", $angle));
    }

    /** 
    * Add command to entity
    * @access public
    * @param String $entityName
    * @param String $command
    * @return Bool
    */
    public function addEntityCommand(string $entityName, string $command){

    	$entityData = $this->entities->get($entityName);

    	if($entityName){

    		$entityData['commands'][] = $command;
    		$this->entities->set($entityName, $entityData);
    		$this->entities->save();

    		return true;

    	}else{
    		return false;
    	}

    }

    /** 
    * Delete command from entity
    * @access public
    * @param String $entityName
    * @param Int $command
    * @return Bool
    */
    public function delEntityCommand(string $entityName, int $commandId){

    	$entityData = $this->entities->get($entityName);

    	if($entityName){

    		unset($entityData['commands'][$commandId]);
    		$this->entities->set($entityName, $entityData);
    		$this->entities->save();

    		return true;

    	}else{
    		return false;
    	}

    }

    public function setEntitySkin(Entity $entity, SkinData $skin){
    	$skinName = $skin->getName();
    	$bytes = $skin->getBytes();
        $geometryId = $skin->getGeometryId();
        $geometry = $skin->getGeometry();

        $newSkin = new Skin($skinName, $bytes, "", $geometryId, $geometry);
        $entity->setSkin($newSkin);
        $entity->sendSkin();
        $entity->namedtag->setTag(new StringTag("EntitySkin", $skinName));
    }

	/** 
    * Display info about given entity
    * @access public
    * @param pocketmine\entity\Entity $entity
    * @return void
    */
    public function inspectEntity(Player $player, Entity $entity){
    	$entityName = $entity->namedtag->getString("EntityName");

    	$player->sendMessage("\n");
        $player->sendMessage("§a-- ".$this->translator->get('ENTITY_INFO', ["§6".$entityName])."§a --");
        $player->sendMessage("§a".$this->translator->get('ENTITY_CURRENT_ID').": §e".$entity->getId());
        $player->sendMessage("§a".$this->translator->get('ENTITY_SCALE').": §e".$entity->namedtag->getFloat("EntityScale"));
        $player->sendMessage("§a".$this->translator->get('ENTITY_ROTATION').": §e".$entity->namedtag->getFloat("EntityRotation"));
        $player->sendMessage("§a".$this->translator->get('ENTITY_SKIN_NAME').": §e".$entity->namedtag->getString("EntitySkin"));
        $player->sendMessage("\n");

        $entities = $this->entities->get($entityName);
        $commands = $entities['commands'];
        $commandId = 0;
        if(count($commands)){
            $player->sendMessage("§a".$this->translator->get('ENTITY_COMMANDS').":");
            foreach($commands as $command){
                $player->sendMessage("§a- [§2".$commandId."§a]: §e".$command);
                $commandId++;
            }
        }else{
            $player->sendMessage("§a".$this->translator->get('ENTITY_COMMANDS').": §c".$this->translator->get('ENTITY_NO_CONFIGURED_CMD'));
        }
    }

    /** 
    * Returns a list of available entities present in entities directory
    * @access public
    * @return String
    */
    public function getAvailableEntities(){
    	$files = array_diff(scandir($this->definitions->getEntitiesPath()), array('.', '..'));
    	$entities = array();
    	foreach($files as $file){
    		$fileName = preg_replace('/(.png|.json|.data)$/', '', $file);
    		$entities[] = $fileName;
    	}

    	$entities = array_unique($entities);
    	return $entities;
    }


    /** 
    * Event pocketmine\event\entity\EntityDamageByEntityEvent (when a entity get hit from another entity)
    * @access public
    * @param pocketmine\event\entity\EntityDamageByEntityEvent $event
    * @return Bool
    */
    public function onDamage(EntityDamageByEntityEvent $event){
    	$player = $event->getDamager();
    	$entity = $event->getEntity();

        if(!$entity->namedtag->hasTag("SkyEntity", IntTag::class)) return true;

    	$event->setCancelled(true);
    	$entityName = $entity->namedtag->getString("EntityName");

        if(in_array($player->getName(), $this->is_inspecting)){

        	$this->inspectEntity($player, $entity);

            $key = array_search($player->getName(), $this->is_inspecting);
            array_splice($this->is_inspecting, $key, 1);

        }else{

            $entities = $this->entities->get($entityName);
            $commands = $entities['commands'];
            if(count($commands)){
                foreach($commands as $command){
                    $player->getServer()->dispatchCommand($player, ltrim($command, '/'));
                }
            }
        }

        return true;
    }

    /** 
    * Event pocketmine\event\level\ChunkLoadEvent (when a chunk is loaded)
    * Re-scale and re-rotate entity
    * @access public
    * @param pocketmine\event\level\ChunkLoadEvent $event
    * @return void
    */
    public function onChunkLoad(ChunkLoadEvent $event){
        $level = $event->getLevel();

        foreach($level->getEntities() as $entity){
            $nbt = $entity->namedtag;
            if($nbt->hasTag("SkyEntity", IntTag::class) &&
                $nbt->hasTag("EntityScale", FloatTag::class) &&
                $nbt->hasTag("EntityRotation", FloatTag::class))
            {

                $scale = $entity->namedtag->getFloat("EntityScale");
                $entity->setScale($scale);
            
                $angle = $entity->namedtag->getFloat("EntityRotation");
                $entity->setRotation($angle, 0);
            }
        }
    }

    /** 
    * Returns selected language in config.yml
    * @access public
    * @return String
    */
    public function getLanguage(){
    	return $this->config->get('Language');
    }


}

?>