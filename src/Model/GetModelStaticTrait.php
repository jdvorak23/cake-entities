<?php

namespace Cesys\CakeEntities\Model;

trait GetModelStaticTrait
{
	/**
	 * @template T of \AppModel
	 * @param class-string<T> $modelClass
	 * @return T
	 * @throws \InvalidArgumentException Pokud $modelClass není string se třídou potomka \AppModel
	 */
	protected static function getModelStatic(string $modelClass)
	{
		if ( ! class_exists($modelClass, false) && ! \App::import('Model', $modelClass)) {
			// Soubor není ani načten, ani ho nelze najít mezi modely
			throw new \InvalidArgumentException("Model '$modelClass' does not exists.");
		}
		if ( ! is_a($modelClass, \AppModel::class, true)) {
			// Není to instanceof AppModel
			throw new \InvalidArgumentException("Model '$modelClass' is not instance of AppModel.");
		}
		if (\ClassRegistry::isKeySet($modelClass)) {
			// Model už je vytvořen v ClassRegistry
			return \ClassRegistry::getObject($modelClass);
		}

		// Model se vytvoří pomocí ClassRegistry
		return \ClassRegistry::init($modelClass);
	}
}