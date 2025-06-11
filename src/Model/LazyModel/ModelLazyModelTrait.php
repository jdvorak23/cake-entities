<?php

namespace Cesys\CakeEntities\Model\LazyModel;

/**
 * Použít na \AppModel, kde nemáme přepsaný konstruktor
 * V opačném případě použít rovnou LazyModelTrait a v konstruktoru volat LazyModelTrait::lazyModelTraitConstructor
 */
trait ModelLazyModelTrait
{
    use LazyModelTrait;

    /**
     * Viz LazyModelTrait::lazyModelTraitConstructor
     * @param $id
     * @param $table
     * @param $ds
     */
    public function __construct($id = false, $table = null, $ds = null)
    {
		$this->lazyModelTraitConstructor();
        parent::__construct($id, $table, $ds);
    }
}