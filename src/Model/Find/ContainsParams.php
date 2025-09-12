<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\Utils\Reflection;


class ContainsParams
{
	private ?Conditions $conditions = null;

	// public int $recursive = -1; Není, je vždy -1

	// public array $fields = []; Je vždy defaultní od frameworku

	/**
	 * null a [] mají stejný efekt (žádný), takže rovnou []
	 * @var array
	 */
	private array $order = [];

	// public array $joins = []; Toto možná nějak v budoucnu

	// public array $group = []; Toto by se u entit asi těžko použilo

	/**
	 * V Cake 0 a null mají stejný výsledek => žádný limit, takže null by bylo zbytečné udržovat
	 * @var int
	 */
	private int $limit = 0;

	/**
	 * Cake vždy změní hodnotu na 1, pokud je 1< nebo null, takže rovnou na defaultu 1
	 * Efekt má pouze, pokud je $limit > 0
	 * @var int
	 */
	private int $page = 1;

	/**
	 * Efekt má pouze, pokud je $limit > 0
	 * Pokud je $page > 1, Cake vždy offset dopočítá, takže v takovém případě nemá smysl a je přepsán
	 * Takže efekt má jen, pokud je $limit > 0 a $page = 1
	 * Pokud je null, nebo 0, nemá efekt, null tedy nemá smysl udržovat
	 * @var int
	 */
	private int $offset = 0;

	/**
	 * @var bool|string true, false, 'before', 'after'
	 */
	private $callbacks = true;

	public static function create(array $params = [], array $defaultParams = []): self
	{
		$instance = new static();

		if ( ! empty($params['conditions'])) {
			$instance->setConditions($params['conditions']);
		} elseif ( ! empty($defaultParams['conditions'])) {
			$instance->setConditions($defaultParams['conditions']);
		}
		if (isset($params['order'])) {
			$instance->setOrder($params['order']);
		} elseif ( ! empty($defaultParams['order'])) {
			$instance->setOrder($defaultParams['order']);
		}
		if (isset($params['limit'])) {
			$instance->setLimit($params['limit']);
		} elseif ( ! empty($defaultParams['limit'])) {
			$instance->setLimit($defaultParams['limit']);
		}
		if (isset($params['page'])) {
			$instance->setPage($params['page']);
		} elseif ( ! empty($defaultParams['page'])) {
			$instance->setPage($defaultParams['page']);
		}
		if (isset($params['offset'])) {
			$instance->setOffset($params['offset']);
		} elseif ( ! empty($defaultParams['offset'])) {
			$instance->setOffset($defaultParams['offset']);
		}
		if (isset($params['callbacks'])) {
			$instance->setCallbacks($params['callbacks']);
		} elseif ( ! empty($defaultParams['callbacks'])) {
			$instance->setCallbacks($defaultParams['callbacks']);
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
			->setOrder()
			->setLimit()
			->setPage()
			->setOffset()
			->setCallbacks();
	}

	/**
	 *
	 * @param ContainsParams $containsParams
	 * @return bool
	 * @internal Je to podivuhodný compare
	 */
	public function isEqualTo(self $containsParams): bool
	{
		if ($this === $containsParams) {
			return true;
		}
		if ($this->callbacks !== $containsParams->callbacks) {
			return false;
		}
		if ($this->limit !== $containsParams->limit) {
			return false;
		}
		// Přesně podle logiky popsané u properties
		if ($this->limit) {
			if ($this->page !== $containsParams->page) {
				return false;
			}
			if ($this->page === 1) {
				if ($this->offset !== $containsParams->offset) {
					return false;
				}
			}
		}
		// Pokud jsou ekvivalentní order napsány jinak, tak toto nezachytíme => tak ať je píšou stejně :D
		if (count($this->order) !== count($containsParams->order)) {
			return false;
		}
		$order = [];
		foreach ($this->order as $key => $item) {
			if ( ! is_int($key)) {
				$item = $key . ' ' . $item;
			}
			$order[] = strtolower($item);
		}
		$containsParamsOrder = [];
		foreach ($containsParams->order as $key => $item) {
			if ( ! is_int($key)) {
				$item = $key . ' ' . $item;
			}
			$containsParamsOrder[] = strtolower($item);
		}
		if (array_diff($order, $containsParamsOrder)) {
			return false;
		}

		$conditionsEmpty = $this->conditions === null || $this->conditions->isEmpty();
		$containsParamsConditionsEmpty = $containsParams->conditions === null || $containsParams->conditions->isEmpty();
		if ($conditionsEmpty !== $containsParamsConditionsEmpty) {
			// Jedno je prázdné a druhé ne
			return false;
		} elseif ($conditionsEmpty) {
			// Obě jsou prázdné
			return true;
		}
		// Obě nejsou prázdné -> musí se porovnat
		return Conditions::isEqual($this->getConditions(), $containsParams->getConditions());
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