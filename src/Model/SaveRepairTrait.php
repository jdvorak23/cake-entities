<?php

namespace Cesys\CakeEntities\Model;

trait SaveRepairTrait
{
    /**
     * Pro zapamatování, kterého id se uložené Model::__exists vztahuje, oprava Cake
     * @var mixed
     */
    protected $existsId;

    /**
     * Zde je uloženo to, co vrátilo poslední volání save()
     * @var array|bool
	 * @deprecated
     */
    protected $lastSaveResult;


    /**
     * Přepisuje původní metodu, pouze navíc uloží do $this->existsId idčko záznamu, pro který se existence vlastně zjišťovala + oprava empty
     * To se použije na správné dovyresetování $this->__exists v přepsaném save()
     * @param $reset
     * @return bool
     */
    function exists($reset = false)
    {
        if (is_array($reset)) {
            extract($reset, EXTR_OVERWRITE);
        }
        $id = $this->getID();
        if ($id === false || $this->useTable === false) {
            return false;
        }
        if ($this->__exists !== null && $reset !== true) { // PREPSANO
            return $this->__exists;
        }
        $conditions = array($this->alias . '.' . $this->primaryKey => $id);
        $query = array('conditions' => $conditions, 'recursive' => -1, 'callbacks' => false);

        if (is_array($reset)) {
            $query = array_merge($query, $reset);
        }
        $this->existsId = $id;
        return $this->__exists = ($this->find('count', $query) > 0);
    }


    /**
     * Opravuje původní save -> chybu v exists, takže už posílá správně UPDATE / INSERT
     * Odstraňuje created a modified, to se do save nemá posílat (Ano, takto ho není možné uložit s vlastní hodnotou)
     * Pokud je vraceno pole s uloženým záznamem, a byl to INSERT bez primárního klíče, automaticky doplní hodnotu primary do výsledku
     * @param $data
     * @param $validate
     * @param $fieldList
     * @return array|bool
     */
    public function save($data = null, $validate = true, $fieldList = array())
    {
		$this->data = false;
        $this->set($data); // Stejně to je přiřazeno v parent::save(), tím se doplní do $this->data i s alias, pokud nebyl
        // Navíc zde již finálně víme id -> pokud bylo v $data, přepsalo / nastavilo hodnotu v $this->id, nebo se bere dříve nastavená, nebo není

        // Vyřeší omylem ponechané klíče v $data, tyto nemá smysl posílat do save, protože se mají generovat automaticky
        unset($this->data[$this->alias]['created']);
        unset($this->data[$this->alias]['modified']);
        if (empty($this->data[$this->alias])) {
            // Teoreticky jsme tím mohli odebrat všechny klíče z pole s klíčem '$this->alias', takže ten musíme odebrat, Cake si s tím neporadí
            unset($this->data[$this->alias]);
        }

        // Chyba v logice Model::save() -> pokud v $this->data->id něco bylo, a volal se Model::exists(), a následně se $this->id = null,
        // nebo $this->id = $jinyId, stále v interním záznamu zůstává původní hodnota exists. Volání Model::create() to sice vyresetuje,
        // ale to zase vytváří nechtěné defaulty do $this->data
        if (isset($this->__exists, $this->existsId)) {
            $existsId = is_int($this->existsId) ? (string) $this->existsId : $this->existsId;
            $id = is_int($this->id) ? (string) $this->id : $this->id;
            if ($existsId !== $id) {
                $this->__exists = null;
            }
        }

		// Vložíme vyfiltrovaná data, ne původní
        return parent::save($this->data, $validate, $fieldList);
    }


	/**
	 * Toto opravuje debilitu na konci Model::save -> mergování $this->data do $result
	 * To je hovadina, protože pokud v afterSave se volají další save do toho samotného modelu, začnou se nám míchat nesouvisející data
	 * V přetížených metodách volat parent::afterSave až ve chvíli, kdy si načteme z $this->data co potřebujeme, většinou klidně jako poslední řádek
	 * @param $created
	 * @return void
	 */
	public function afterSave($created)
	{
		parent::afterSave($created);
		$this->data = false;
	}


	/**
	 * Někdy je potřeba logiku v static::save "zkopírovat" a rozšířit (viz UCA EFolderAppModel)
	 * Pak by volání static::save proběhl pouze redundantní kód, takže dáme možnost "přeskočit", ale stále nám zůstane přepsané exists / afterSave
	 * @param $data
	 * @param $validate
	 * @param $fieldList
	 * @return mixed
	 */
	public function originalSave($data = null, $validate = true, $fieldList = array())
	{
		return parent::save($data, $validate, $fieldList);
	}
}