<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\Interfaces;

interface IBankTransaction
{
	/**
	 * Typy enum `status`
	 * Jsou nějaké staré něco, pro e-folder se nepoužívají
	 */
	const StatusMatched = 'matched';
	const StatusUnused = 'unused';


}