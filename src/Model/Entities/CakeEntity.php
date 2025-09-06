<?php

namespace Cesys\CakeEntities\Model\Entities;

use Cesys\Utils\Reflection;

abstract class CakeEntity
{
    /**
     * @var string[][]
     */
    protected static array $modelClasses = [];

    /**
     * @var array[]
     */
	protected static array $excludedFormDbArrays = [];

    /**
     * @return int|string|null
     */
    public function getPrimary()
    {
        if ( ! $this->getPrimaryColumnProperty()->property->isInitialized($this)) {
            return null;
        }

        return $this->getPrimaryColumnProperty()->property->getValue($this);
    }


    /**
     * @param int|string $value
     * @return void
     */
    public function setPrimary($value)
    {
		$this->getPrimaryColumnProperty()->property->setValue($this, $value);
    }


	/**
	 * @return ColumnProperty
	 * @throws void
	 */
	private function getPrimaryColumnProperty(): ColumnProperty
	{
		$primaryPropertyName = static::getPrimaryPropertyName();
		$primaryColumnProperty = EntityHelper::getColumnProperties(static::class)[$primaryPropertyName] ?? null;
		if ( ! $primaryColumnProperty) {
			throw new \LogicException("Primary column property for property '$primaryPropertyName' not found.");
		}
		return $primaryColumnProperty;
	}


    public function toDbArray(): array
    {
		return EntityHelper::toDbArray($this);
    }

	public function appendFromDbArray(array $data)
	{
		EntityHelper::appendFromDbArray($this, $data);
	}


    /**
     * @param array $data
     * @return static
     */
    public static function createFromDbArray(array $data)
    {
        $entity = new static();
        EntityHelper::appendFromDbArray($entity, $data);
        return $entity;
    }


    /**
     * @return string[]
     */
    public static function getExcludedFromDbArray(): array
    {
        return static::$excludedFormDbArrays[static::class] ??= ['created', 'modified', 'createdBy', 'modifiedBy'];
    }


    /**
     * @param array $excludedFromDbArray
     * @return string[] původní $excludedFromDbArray
     */
    public static function setExcludedFromDbArray(array $excludedFromDbArray): array
    {
        $originalExcludedFromDbArray = static::getExcludedFromDbArray();
        static::$excludedFormDbArrays[static::class] = $excludedFromDbArray;
        return $originalExcludedFromDbArray;
    }


    /**
     * @return string
     */
    public static function getModelClass(): string
    {
        return static::$modelClasses[static::class] ??= Reflection::getReflectionClass(static::class)->getShortName();
    }


    /**
     * @param string $modelClass
     * @return string původní $modelClass
	 * @deprecated
     */
    public static function setModelClass(string $modelClass): string
    {
        $originalClass = static::getModelClass();
        static::$modelClasses[static::class] = $modelClass;
        return $originalClass;
    }


    public static function getExcludedFromProperties(): array
    {
        return [];
    }


    public static function getPrimaryPropertyName(): string
    {
        return 'id';
    }

}