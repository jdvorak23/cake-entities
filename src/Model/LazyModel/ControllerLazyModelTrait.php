<?php

namespace Cesys\CakeEntities\Model\LazyModel;

/**
 * Použít na \AppController, kde nemáme přepsaný konstruktor
 * V opačném případě použít rovnou LazyModelTrait a v konstruktoru volat LazyModelTrait::lazyModelTraitConstructor
 */
trait ControllerLazyModelTrait
{
    use LazyModelTrait;

    /**
     * Viz LazyModelTrait::lazyModelTraitConstructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->lazyModelTraitConstructor();
    }
}