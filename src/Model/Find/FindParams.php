<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\EntityAppModelTrait;
use Cesys\CakeEntities\Model\GetModelTrait;
use Cesys\CakeEntities\Model\Recursion\Query;

class FindParams
{
	use GetModelTrait {
		getModel as getModelInTrait; // přejmenování metody
	}

	/**
	 * FindParams modelů, jejichž entity se mají k entitě odpovídající FindParams připojit
	 * @var static[]
	 */
	public array $contains = [];

	/**
	 * Uživatelovy params uvedené v contains zvazbených modelů
	 * @var ContainsParams
	 */
	public ContainsParams $containsParams;

	/**
	 * Další conditions, tyto jsou přidávány frameworkem pro vyhledávání entit v relaci
	 * Tyto jsou spojeny s $containsParams před voláním find
	 * @var FindConditions
	 */
	public FindConditions $conditions;

	/**
	 * Třída modelu těchto FindParams
	 * @var string
	 */
	public string $modelClass;

	/**
	 * Koliklrát jsou tyto find params (bez rekurze!) v základních FindParams findu, tj. kolikrát minimálně očekáváme volání find
	 * Při 'průchodu', kde vzniká rekurze, a tedy další (zde na začátku nezapočítané) použití, se uměle (při vstupu do rekurze) navyšuje
	 * @var int
	 */
	public int $willBeUsed = 0;

	/**
	 * Zde se při přípravě FindConditions ukládají indexy, které se týkají příštího volání find
	 * Tj. entity získané voláním find budou indexovány na tyto indexy
	 * @var array
	 */
	private array $nextUsedIndexes = [];

	public function __construct(string $modelClass)
	{
		$this->modelClass = $modelClass;
	}

	public function getConditions(): FindConditions
	{
		return $this->conditions ??= new FindConditions();
	}

	public function setConditions(FindConditions $conditions): void
	{
		$this->conditions = $conditions;
	}


	/*public function getDescendants(): array
	{
		static $query;
		/** @var static[] $pathCache *//*
		static $pathCache;
		if ( ! isset($query)) {
			$query = new Query();
			$pathCache = [];
			$query->onEnd[] = function() use (&$query) {
				$query = null;
			};
		}

		if (isset($pathCache[$this->getId()])) {
			return [];
		}

		$query->start($this->getId());
		$pathCache[$this->getId()] = $this;

		foreach ($this->contains as $containedFindParams) {
			$containedFindParams->getDescendants();
		}

		if ($query->end()) {
			return $pathCache;
		}

		return [];
	}*/


	/**
	 * @param string $modelClass
	 * @return self
	 */
	public function getContainedFindParams(string $modelClass): self
	{
		if ( ! isset($this->contains[$modelClass])) {
			throw new \InvalidArgumentException("FindParams of '$this->modelClass' does not contains '$modelClass'");
		}
		return $this->contains[$modelClass];
	}

	public function hasNextStandardFind(bool $isNextFindInRecursion = false): bool
	{
		if ($isNextFindInRecursion) {
			// Stačí, aby willBeUsed bylo alespoň 1, protože onen "NextFindInRecursion" ještě nenastal
			// => ve chvíli kdy "nastane", bude zvýšen kvůli rekurzi o 1
			return !! $this->willBeUsed;
		}
		return $this->willBeUsed > 1;
	}

	public function skipUse(): void
	{
		$this->willBeUsed--;
		if ($this->willBeUsed < 0) {
			bdump($this, 'COZEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEE XXXXXXXXXXXXXXx - CHYBA');
		}
	}

	public function afterFind(): void
	{
		$this->willBeUsed--;
		if ($this->willBeUsed < 0) {
			bdump($this, 'COZEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEE XXXXXXXXXXXXXXx - CHYBA');
		}
		$this->nextUsedIndexes = [];
	}

	public function setNextUsedIndex(string $index): void
	{
		$this->nextUsedIndexes[$index] = $index;
	}

	public function removeNextUsedIndex(string $index): void
	{
		unset($this->nextUsedIndexes[$index]);
	}

	public function getNextUsedIndexes(): array
	{
		return $this->nextUsedIndexes;
	}

	public function getId(): int
	{
		return spl_object_id($this);
	}

	public static function create(array $contains, bool $useCache = false): self
	{
		if (count($contains) !== 1) {
			throw new \Exception('Contains can only have one element');
		}

		$modelClass = array_key_first($contains);

		static $query;
		/** @var static[] $pathCache */
		static $pathCache;
		if ( ! isset($query)) {
			$query = new Query();
			$pathCache = [];
			$query->onEnd[] = function() use (&$query) {
				$query = null;
			};
		}
		$query->start($modelClass, function () use (&$pathCache) {
			array_pop($pathCache);
		});

		if ($contains[$modelClass]['contains'] === null) {
			$activePath = $query->activePath;
			array_pop($activePath);
			// null znamená, že jsme v 'rekurzi', tj. v přímé cestě 'nahoru' už je stejný contains
			// FindParams budou stejné, pokud budou stejné i ContainsParams
			$index = array_search($modelClass, $activePath, true);
			$similarFindParams = $pathCache[$index];
		}

		$containsParams = ContainsParams::create($contains[$modelClass]);

		if (isset($similarFindParams)) {
			// Jsme v 'null'
			if ($similarFindParams->containsParams->isEqualTo($containsParams)) {
				// ContainsParams jsou stejné -> rekurze, přiřadíme nalezený
				$pathCache[] = 'none'; // Smaže se voláním end(), do $pathCache jsme ještě nepřidali
				$query->end(); // Zde query určitě není nikdy na konci (vždy false)
				return $similarFindParams;
			}
			// Jsou rozdílné ContainsParams => jiné FindParams
		}

		$instance = new self($modelClass);
		$pathCache[] = $instance;
		$instance->containsParams = $containsParams;
		$instance->conditions = FindConditions::create();

		if (isset($similarFindParams)) {
			// Musíme přiřadit až na konci, jinak by mohlo být nekompletní
			$query->onEnd[] = function () use ($instance, $similarFindParams) {
				$instance->contains = $similarFindParams->contains;
			};
			$query->end();  // Zde query určitě není nikdy na konci (vždy false)
			return $instance;
		}

		foreach ($contains[$modelClass]['contains'] as $containedModelClass => $modelContains) {
			$instance->contains[$containedModelClass] = self::create([$containedModelClass => $modelContains]);
		}

		if ($query->end()) {
			// Není v callbacku, musí být až jako poslední věc před return, ostatní volání end() nemohou být poslední
			$instance = static::replaceIdentical($instance, $useCache);
		}
		return $instance;
	}


	/**
	 * Jestli EntityCache těchto 2 FindParams mohou být sdílené
	 * 1) Musí mít ekvivalentní ContainsParams
	 * 2) contained FindParams pro stejné modely musí mít ekvivalentní ContainsParams
	 * Pokud jedny mají contained nějaké FindParams a druhé pro ten model nemají, nevadí to
	 * Pokud mají oba, musí být ContainsParams ekvivalentní
	 * @param FindParams $findParams
	 * @return bool
	 */
	public function isCacheCompatibleWith(self $findParams): bool
	{
		static $query;
		static $pathCache;
		if ( ! isset($query)) {
			$query = new Query();
			$query->onEnd[] = function () use (&$query) {
				$query = null;
			};
			$pathCache = [];
		}
		$query->start($this->modelClass);
		if (isset($pathCache[$this->getId()])) {
			$query->end();
			return true;
		}
		$pathCache[$this->getId()] = true;

		if ($this === $findParams) {
			// Sem by se stejné params dostat neměly, ale kdoví jak se to použije v budoucnu, ano, pokud jsou stejná instance, tak určitě jsou
			$query->end();
			return true;
		}
		if ($this->modelClass !== $findParams->modelClass) {
			$query->end();
			return false;
		}
		if ( ! $this->containsParams->isEqualTo($findParams->containsParams)) {
			$query->end();
			return false;
		}
		foreach (array_intersect_key($this->contains, $findParams->contains) as $modelClass => $containedFindParams) {
			if ( ! $containedFindParams->isCacheCompatibleWith($findParams->contains[$modelClass])) {
				$query->end();
				return false;
			}
		}
		$query->end();
		return true;
	}


	public function isCacheShareableFrom(self $findParams): bool
	{
		if ($this->getId() === $findParams->getId()) {
			// Sem by se stejné params dostat neměly, ale kdoví jak se to použije v budoucnu, ano, pokud jsou stejná instance, tak určitě jsou
			return true;
		}
		if ($this->modelClass !== $findParams->modelClass) {
			return false;
		}
		if (array_diff_key($this->contains, $findParams->contains)) {
			// Použít cache z jiných FindParams lze pouze, pokud tyto FindParams mají všechny contains modely našich FindParams
			return false;
		}
		if ( ! $this->containsParams->isEqualTo($findParams->containsParams)) {
			return false;
		}

		foreach (array_intersect_key($this->contains, $findParams->contains) as $modelClass => $containedFindParams) {
			if ( ! $containedFindParams->containsParams->isEqualTo($findParams->contains[$modelClass]->containsParams)) {
				return false;
			}
		}
		return true;
	}


	/**
	 * Musí být zcela stejné
	 * @param FindParams $findParams
	 * @return bool
	 */
	public function isEqualTo(self $findParams): bool
	{
		//bdump($this);
		//bdump($contains);
		static $query;
		static $pathCache;
		if ( ! isset($query)) {
			$query = new Query();
			$query->onEnd[] = function () use (&$query) {
				$query = null;
			};
			$pathCache = [];
		}
		$query->start($findParams->modelClass);

		if (isset($pathCache[$findParams->getId()])) {
			$query->end();
			return true;
		}
		$pathCache[$findParams->getId()] = true;

		if ($this->modelClass !== $findParams->modelClass) {
			$query->end();
			return false;
		}

		if (
			count($this->contains) !== count($findParams->contains)
			|| array_diff_key($this->contains, $findParams->contains)
		) {
			//bdump(count($this->contains), "chcip");
			//bdump(count($contains->contains));
			$query->end();
			return false;
		}

		if ( ! $this->containsParams->isEqualTo($findParams->containsParams)) {
			$query->end();
			return false;
		}

		foreach ($this->contains as $modelClass => $modelFindParams) {
			if ( ! $modelFindParams->isEqualTo($findParams->contains[$modelClass])) {
				$query->end();
				return false;
			}
		}

		$query->end();
		return true;
	}


	public function isRecursiveToSelfEndlessCacheCompatible(): bool
	{
		static $query;
		static $pathCache;
		if ( ! isset($query)) {
			$query = new Query();
			$query->onEnd[] = function () use (&$query) {
				$query = null;
			};
			$pathCache = [];
		}
		$query->start($this->modelClass);

		if (isset($pathCache[$this->getId()])) {
			$query->end();
			return true;
		}
		$pathCache[$this->getId()] = true;

		if ( ! $childFindParams = $this->contains[$this->modelClass] ?? null) {
			$query->end();
			return false;
		}

		if ( ! $this->isCacheCompatibleWith($childFindParams)) {
			$query->end();
			return false;
		}

		$return = $childFindParams->isRecursiveToSelfEndlessCacheCompatible();
		$query->end();
		return $return;
	}



	/**
	 * @return EntityAppModelTrait&\AppModel
	 */
	public function getModel()
	{
		return $this->getModelInTrait($this->modelClass);
	}

	private static function replaceIdentical(self $originalFindParams, bool $useCache): self
	{
		//bdump($originalFindParams, 'UNREPLACED FindParams');
		$cache = [];
		$queue = new \SplQueue();
		$queue[] = $originalFindParams;
		$containsToAppend = [];
		/*if ($useCache) {
			foreach ($originalFindParams->getModel()->getModelCache()->getModelCache($originalFindParams->modelClass) as $entityCache) {
				$findParams = $entityCache->findParams;
				if ($findParams->isEqualTo($originalFindParams)) {
					return $findParams;
				}
			}
		}*/
		foreach ($queue as $findParams) {
			if (isset($cache[$findParams->modelClass][$findParams->getId()])) {
				// Stejný node, který už jsme prošli / procházíme, na jiném místě ve stromě
				continue;
			}

			/** @var static $sameModelContains */
			foreach ($cache[$findParams->modelClass] ?? [] as $sameModelContains) {
				if ($sameModelContains->isEqualTo($findParams)) {
					if ($containsToAppend[$findParams->getId()] !== $sameModelContains) {
						// + node jenom když už to není rekurze
						$sameModelContains->willBeUsed++;
					}
					$containsToAppend[$findParams->getId()]->contains[$findParams->modelClass] = $sameModelContains;
					continue 2;
				}
			}

			$cache[$findParams->modelClass][$findParams->getId()] = $findParams;
			$findParams->willBeUsed++;

			foreach ($findParams->contains as $modelContains) {
				$containsToAppend[$modelContains->getId()] = $findParams;
				$queue[] = $modelContains;
			}
		}
		/*if ($useCache) {
			exit;
		}*/
		return $originalFindParams;
	}

}