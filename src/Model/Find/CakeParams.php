<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\Utils\Reflection;


class CakeParams
{
	public ?Conditions $conditions = null;

	/**
	 * Ať to nemusíme nikde řešit, framework vždy používá zásadně recursive -1
	 * Ani uživ. volání, vždy je přepsáno na -1
	 * Joinovat je možno pouze 'ručně' pomocí $joins
	 * @var int
	 */
	public int $recursive = -1;

	/**
	 * V cake má null a [] stejný efekt (žádný), takže []
	 * @var array
	 */
	public array $fields = [];

	/**
	 * null a [] mají stejný efekt (žádný), takže rovnou []
	 * @var array
	 */
	public array $order = [];

	/**
	 * I v cake je default []
	 * @var array
	 */
	public array $joins = [];

	/**
	 * null a [] mají stejný efekt -> žádný
	 * @var array
	 */
	public array $group = [];

	/**
	 * V Cake 0 a null mají stejný výsledek => žádný limit, takže null by bylo zbytečné udržovat
	 * @var int
	 */
	public int $limit = 0;

	/**
	 * Cake vždy změní hodnotu na 1, pokud je 1< nebo null, takže rovnou na defaultu 1
	 * Efekt má pouze, pokud je $limit > 0
	 * @var int
	 */
	public int $page = 1;

	/**
	 * Efekt má pouze, pokud je $limit > 0
	 * Pokud je $page > 1, Cake vždy offset dopočítá, takže v takovém případě nemá smysl a je přepsán
	 * Takže efekt má jen, pokud je $limit > 0 a $page = 1
	 * Pokud je null, nebo 0, nemá efekt, null tedy nemá smysl udržovat
	 * @var int
	 */
	public int $offset = 0;

	/**
	 * @var bool|string true, false, 'before', 'after'
	 */
	public $callbacks = true;

	public static function create(array $params = []): self
	{
		$instance = new static();

		if ( ! empty($params['conditions'])) {
			$instance->setConditions($params['conditions']);
		}
		if (isset($params['recursive'])) {
			$instance->setRecursive($params['recursive']);
		}
		if (isset($params['fields'])) {
			$instance->setFields($params['fields']);
		}
		if (isset($params['order'])) {
			$instance->setOrder($params['order']);
		}
		if (isset($params['joins'])) {
			$instance->setJoins($params['joins']);
		}
		if (isset($params['group'])) {
			$instance->setGroup($params['group']);
		}
		if (isset($params['limit'])) {
			$instance->setLimit($params['limit']);
		}
		if (isset($params['page'])) {
			$instance->setPage($params['page']);
		}
		if (isset($params['offset'])) {
			$instance->setOffset($params['offset']);
		}
		if (isset($params['callbacks'])) {
			$instance->setCallbacks($params['callbacks']);
		}

		return $instance;
	}

	public function toArray(): array
	{
		$params = [];
		foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
			$value = $property->getValue($this);
			if ($value instanceof Conditions) {
				$value = $value->toArray();
			}
			$params[$property->getName()] = $value;
		}
		return $params;
	}

	public function clear(): void
	{
		$this->setConditions()
			->setRecursive()
			->setFields()
			->setOrder()
			->setJoins()
			->setGroup()
			->setLimit()
			->setPage()
			->setOffset()
			->setCallbacks();
	}

	/**
	 *
	 * @param CakeParams $params
	 * @return bool
	 * @throws \ReflectionException
	 * @internal Je to podivuhodný compare
	 */
	public function isEqualTo(self $params): bool
	{
		foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
			// todo @fixme conditions v contains
			if ($property->getName() === 'conditions') {
				continue;
			}
			if ($property->getName() === 'fields') {
				// Dělá problémy, fields defaultne nastavene takze ok // todo @internal
				continue;
			}
			// todo @internal 'order', 'joins', 'group' => porovnání polí
			if ($property->getValue($this) !== $property->getValue($params)) {
				return false;
			}
		}

		return true;
	}


	public function getConditions(): Conditions
	{
		return $this->conditions ??= new Conditions();
	}


	/**
	 * @param Conditions|array $conditions
	 * @return static
	 */
	public function setConditions($conditions = []): self
	{
		if ( ! empty($conditions)) {
			if (is_array($conditions)) {
				$this->conditions = Conditions::create($conditions);
			} elseif ($conditions instanceof Conditions) {
				$this->conditions = $conditions;
			} else {
				$className = static::class;
				throw new \InvalidArgumentException("Conditions must be an 'array' or instance of '$className'.");
			}
		} else {
			$this->conditions = null;
		}

		return $this;
	}


	public function setRecursive(int $recursive = -1): self
	{
		$this->recursive = $recursive;
		return $this;
	}


	public function setFields(array $fields = []): self
	{
		$this->fields = $fields;
		return $this;
	}


	/**
	 * @param string|array $order
	 * @return static
	 */
	public function setOrder($order = []): self
	{
		if (is_string($order)) {
			$order = [$order];
		} elseif ( ! is_array($order)) {
			throw new \InvalidArgumentException('Order must be an array or string.');
		}
		$this->order = $order;
		return $this;
	}


	public function setJoins(array $joins = []): self
	{
		$this->joins = $joins;
		return $this;
	}


	public function setGroup(array $group = []): self
	{
		$this->group = $group;
		return $this;
	}


	public function setLimit(int $limit = 0): self
	{
		if ($limit < 0) {
			throw new \InvalidArgumentException('Limit must be greater or equal 0.');
		}
		$this->limit = $limit;
		return $this;
	}


	public function setPage(int $page = 1): self
	{
		if ($page < 1) {
			throw new \InvalidArgumentException('Page must be greater or equal 1.');
		}
		$this->page = $page;
		return $this;
	}


	public function setOffset(int $offset = 0): self
	{
		if ($offset < 0) {
			throw new \InvalidArgumentException('Offset must be greater or equal 0.');
		}
		$this->offset = $offset;
		return $this;
	}


	/**
	 * @param bool|string $callbacks true, false, 'before', 'after'
	 * @return void
	 */
	public function setCallbacks($callbacks = true): self
	{
		if ( ! in_array($callbacks, [true, false, 'before', 'after'], true)) {
			throw new \InvalidArgumentException('Callbacks must be true, false, \'before\' or \'after\'.');
		}
		$this->callbacks = $callbacks;
		return $this;
	}


}