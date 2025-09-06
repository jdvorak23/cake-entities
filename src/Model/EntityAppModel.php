<?php

namespace Cesys\CakeEntities\Model;

/**
 * Základní (entity) AppModel pro všechny Cake projekty, z tohoto dědit modely
 * @template E
 */
abstract class EntityAppModel extends \AppModel
{
	/**
	 * @use EntityAppModelTrait<E>
	 */
	use EntityAppModelTrait;


}