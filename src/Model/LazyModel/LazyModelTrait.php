<?php

namespace Cesys\CakeEntities\Model\LazyModel;

use Cesys\CakeEntities\Model\GetModelTrait;
use Cesys\Utils\Reflection;

trait LazyModelTrait
{
    use GetModelTrait;

    /**
     * Property s modelem NESMÍ být private;
     * Proiteruje všechny properties ve třídě. Pokud má property 1 typ, a ten typ je třída a není načtena ( ! class_exists()),
     * pokusí se ji načíst přes App::import jako model. Pokud načtena je (ať už přes ten App::import, nebo už byla),
     * zjistí se, jestli to je instance AppModel. Pokud ano, je tato property, pokud je prázdná (= pravděpodobně neinicializovaná)
     * UNSETována -> jedině po unsetu "spadne" volání na tuto property do magického __get, kde se načte přes getModel a nahraje do property
     * Výhody:
     * 1) Stačí definovat property modelu a o nic se nemusíme starat, bude načteno automaticky při prvním použití =>
     * 2) Model se vytvoří, jenom je-li volán, pokud volán není, nic se nevytváří
     * 3) Všechny třídy modelů jsou již v konstruktory načteny přes App::import, takže se nemusíme starat při použití const / static
     * 4) Vidíme závislosti
     */
    public function lazyModelTraitConstructor()
    {
        foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
            if (! $property->getType() instanceof \ReflectionNamedType) {
                continue;
            }
            $type = $property->getType()->getName();
            $notInterested = ['array', 'bool', 'float', 'int', 'object', 'string', 'false', 'iterable', 'mixed', 'true', 'parent', 'self', 'static'];
            if (strpos($type, '\\') !== false || in_array($type, $notInterested, true)) {
                continue;
            }
            if ( ! class_exists($type, false) && ! \App::import('Model', $type)) {
                continue;
            }

            if (is_a($type, \AppModel::class, true)) {
                if ( ! isset($this->{$property->getName()})) {
                    if ($property->isPrivate()) {
                        $staticClass = static::class;
                        throw new \LogicException("Property '$staticClass::{$property->getName()}' can't be private.");
                    }
                    unset($this->{$property->getName()});
                }
            }
        }
    }


    /**
     * Magický __get slouží k dynamickému vytváření instance modelu, který je definován jako property ve třídě
     * Viz __construct()
     * Pokud to sem spadne a nejedná se o tuto funkcionalitu, bude simulováno přesné chování tak, jako by __get nebylo implementováno
     * @param string $name
     * @return null
     */
    public function __get(string $name)
    {
        $staticClass = static::class;
        if ( ! $property = Reflection::getReflectionPropertiesOfClassByName(static::class)[$name] ?? null) {
            // Volání nedefinované property, vrací null a emituje warning
            trigger_error("Undefined property: $staticClass::\$$name", E_USER_WARNING);
            return null;
        }
        if ( ! $property->getType()) {
            // Volání definované property, která nemá typ => vrací se null
            return null;
        }
        if ($property->getType() instanceof \ReflectionNamedType && is_a($property->getType()->getName(), \AppModel::class, true)) {
            $model = $this->getModel($property->getType()->getName());
            $this->{$name} = $model;
            return $model;
        }

        // Volání typované definované property, která nemá hodnotu => Error
        throw new \Error("Typed property $staticClass::\$$name must not be accessed before initialization");
    }


}