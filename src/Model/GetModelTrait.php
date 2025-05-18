<?php

namespace Cesys\CakeEntities\Model;

use Cesys\Utils\Reflection;

trait GetModelTrait
{
    /**
     * Vylepšená getModel
     * Ověřuje, že třída modelu $modelClass existuje (a že je \AppModel), tj. nelze ji již použít na dynamicky vytvářený model
     * Model do property přiřazuje jen, pokud je property stejného jména definovaná, a buď nemá typ (historicky máme hodně), nebo má správně typ $modelClass
     * POZOR při nahrazování ve starých modelech/controllerech -> vlastnost, že se už dynamicky nevytváří property,
     * pokud není definována, může dělat problémy, protože někdy se takto v kódu postupovalo (např. volání getModel v konstruktoru, aby se uložil do property)
     * @template T of \AppModel
     * @param class-string<T> $modelClass
     * @return T
     * @throws \InvalidArgumentException Poud $modelClass není string se třídou existující instance \AppModel
     */
    public function getModel($modelClass)
    {
        if ( ! is_string($modelClass) || empty($modelClass)) {
            throw new \InvalidArgumentException('Parameter $modelClass must be string and not empty.');
        }
        if ( ! class_exists($modelClass, false) && ! \App::import('Model', $modelClass)) {
            // Soubor není ani načten, ani ho nelze najít mezi modely
            throw new \InvalidArgumentException("Model $modelClass does not exist.");
        }

        if ( ! is_a($modelClass, \AppModel::class, true)) {
            // Není to instanceof AppModel
            throw new \InvalidArgumentException("Model $modelClass is not instance of AppModel.");
        }

        if (isset($this->{$modelClass}) && $this->{$modelClass} instanceof $modelClass) {
            // Existuje property stejného jména jako $modelName a je v ní instance $modelName
            // Nemusíme dělat nic a vracíme
            return $this->{$modelClass};
        }

        if (\ClassRegistry::isKeySet($modelClass)) {
            // Model už je vytvořen v ClassRegistry
            $model = \ClassRegistry::getObject($modelClass);
        } else {
            // Model se vytvoří pomocí ClassRegistry
            $model = \ClassRegistry::init($modelClass);
        }

        if ($property = Reflection::getReflectionPropertiesOfClassByName(static::class)[$modelClass] ?? null) {
            // Existuje property stejného jména jako $modelName
            if ($property->getType() instanceof \ReflectionNamedType && $property->getType()->getName() === $modelClass) {
                // Má jeden typ a ten je shodný s $modelName
                $this->{$modelClass} = $model;
            } elseif ($property->getType() === null) {
                // Nemá typ -> taky přiřadíme, je to historicky u spousty modelů...
                $this->{$modelClass} = $model;
            }
        }

        return $model;
    }
}