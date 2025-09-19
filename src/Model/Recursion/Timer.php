<?php

namespace Cesys\CakeEntities\Model\Recursion;

class Timer
{
	private static array $timers = [];

	private static array $passed = [];

	private static array $results = [];

	public static function start(string $name)
	{
		self::$timers[$name] = hrtime(true);
	}

	public static function stop()
	{
		$name = array_key_last(self::$timers);
		$delta = hrtime(true) - self::$timers[$name];
		unset(self::$timers[$name]);
		foreach (self::$timers as $key => $timer) {
			self::$timers[$key] = $timer + $delta;
		}

		$delta = $delta / 1e6;
		self::$passed[$name] = $delta;
	}

	public static function getResults(): array
	{
		$total = 0;
		foreach (self::$passed as $passed) {
			$total += $passed;
		}
		$result = self::$passed;
		$result['$$total$$'] = $total;
		self::$results[] = $result;
		self::$passed = [];
		//self::$timers = [];
		return $result;
	}


	public static function getFullResults(): array
	{
		$total = 0;
		$results = self::$results;
		foreach (self::$results as $key => $result) {
			$total += $result['$$total$$'];
			$results['$$total - ' . ($key + 1) . '$$'] = $results[$key]['$$total$$'];
			unset($results[$key]['$$total$$']);
		}
		$results ['$$$$total$$$$'] = $total;
		return $results;
	}
}