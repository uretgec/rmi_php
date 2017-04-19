<?php
namespace Rmi;

/**
 * Class Rmi
 * @package Rmi
 */
class Rmi
{
	const REDIS_PREFIX = 'rmi:';

	// Redis Key Type
	const REDIS_KEY_LIMIT = 'limit';
	const REDIS_KEY_CACHE = 'cache';
	const REDIS_KEY_STORAGE = 'storage';

	// Error Codes
	const RMI_ERROR_000 = 'Redis config not found: %s';
	const RMI_ERROR_001 = 'Could not connect redis server: %s:%s';
	const RMI_ERROR_002 = 'Could not auth redis server: %s:%s';
	const RMI_ERROR_003 = 'Hash Keys not generated!';
	const RMI_ERROR_004 = 'Patterns not found!';

	// Storage Types
	const RMI_STORAGE_INT 						= 1;
	const RMI_STORAGE_STRING 					= 2;
	const RMI_STORAGE_BOOL 						= 3;
	const RMI_STORAGE_JSON_ARRAY 			= 4;
	const RMI_STORAGE_SIMPLE_ARRAY 		= 5;
	const RMI_STORAGE_TIMESTAMP 			= 6;
	const RMI_STORAGE_INCR 						= 7;
	const RMI_STORAGE_DECR 						= 8;
	const RMI_STORAGE_EXPIRE 					= 9;

	private $redis = null;
	private $redisConfig = null;
	private $redisKey = null;
	private $redisIndexKey = null;
	protected $handleData = null;

	public function __construct($config, $handleData = null)
	{
		$this->redisConfig = $config;
		$this->redis = $this->connect();

		// Handle Request
		$this->handleRequest($handleData);
		$this->prepareKeys();
	}

	protected function connect($configId = 0)
	{
		if(!isset($this->redisConfig[$configId])) {
			throw new RmiException(self::RMI_ERROR_000, array($configId));
		}

		list($host, $port, $auth, $db) = $this->redisConfig[$configId];
		$redis = new \Redis();
		if(!$redis->pconnect($host, $port, 5)) {
			throw new RmiException(self::RMI_ERROR_001, array($host, $port));
		}
		if($auth !== null && !$redis->auth($auth)) {
			throw new RmiException(self::RMI_ERROR_002, array($host, $port));
		}
		$redis->setOption(\Redis::OPT_PREFIX, self::REDIS_PREFIX);

		return $redis;
	}

	public function setRedisConfig($key = null, $value = null)
	{
		if($key === null) {
			$this->redisConfig = $value;
		} else {
			$this->redisConfig[$key] = $value;
		}
	}

	public function getRedisConfig()
	{
		return $this->redisConfig;
	}

	protected function handleRequest($data)
	{
		// All Request Data
		$handleData = array(
			// Req field
			'type' => null,

			// Patterns
			'patterns' => null,

			// Pattern Keys
			'id' => null,
			'keys' => null,

			// RmiLimit Parameters
			'max' => 10,
			 // opt pattern key

			// RmiCache Parameters
			'paged' => 1,
			'perpage' => 10,

			// RmiStorage Parameters

		);

		foreach ($handleData as $handleKey => $handleItem) {
			if( isset($data[$handleKey]) && !empty($data[$handleKey]) ) {
				$handleData[$handleKey] = $data[$handleKey];
			}
		}

		// Filter not empty data
		$this->setHandleData(null, array_filter($handleData, function($value) {
			return ($value !== null || !empty($value));
		}));
	}

	public function setHandleData($key = null, $value = null)
	{
		if($key === null) {
			return $this->handleData = $value;
		}
		return $this->handleData[$key] = $value;
	}

	public function getHandleData()
	{
		return $this->handleData;
	}

	protected function getHandleDataValue($key = null)
	{
		return (isset($this->handleData[$key]) && !empty($this->handleData[$key]))
			? $this->handleData[$key]
			: null;
	}

	public function isHandleDataValid($requiredFields = null)
	{
		if($requiredFields !== null && is_array($requiredFields) && count($requiredFields) > 0 ) {
			foreach ($requiredFields as $requiredField) {
				if($this->getHandleDataValue($requiredField) === null) {
					return false;
				}
			}
		}
		return true;
	}

	protected function prepareKeys()
	{
		$patterns = $this->getHandleDataValue('patterns');
		if($patterns === null) {
			throw new RmiException(self::RMI_ERROR_004);
		}

		$type = $this->getHandleDataValue('type');
		foreach ($patterns as $key => $value) {
			if($value !== null) {
				$this->redisKey[$key] = $this->generateKey(array(
					$type,
					$key
				));
				$this->redisIndexKey[$key] = (self::REDIS_KEY_STORAGE == $key)
					? $this->generateHashKey($value)
					: $this->generatePatternKey($value)
				;
			}
		}
	}

//	protected function getRedisKey($type)
//	{
//		if(!isset($this->redisKey[$type])) {
//			throw new RmiException();
//		}
//
//		return $this->redisKey[$type];
//	}
//
//	protected function getRedisIndexKey($type)
//	{
//		if(!isset($this->redisIndexKey[$type])) {
//			throw new RmiException();
//		}
//
//		return $this->redisIndexKey[$type];
//	}

	// RmiLimit Functions

	public function findByLimit($offset = 0, $limit = 10)
	{
		$indexData = array();
		$indexList = $this->redis->zRevRangeByScore(
			$this->redisKey[self::REDIS_KEY_LIMIT],
			$this->redisIndexKey[self::REDIS_KEY_LIMIT],
			$this->redisIndexKey[self::REDIS_KEY_LIMIT],
			array('withscores' => false, 'limit' => array($offset, $limit))
		);
		if (count($indexList) > 0) {
			foreach ($indexList as $indexItem) {
				$indexData[] = json_decode($this->findData($indexItem), true);
			}
		}

		return $indexData;
	}

	/*
	 * ZSET Index Pattern
	 * ==================
	 * zAdd generateKey, generateIndexKey, microtime():json_encode($data)
	 * */

	public function updateByLimit($data = null)
	{
		if( $this->getHandleDataValue('id') !== null || $data !== null) {
			$this->redis->zAdd(
				$this->redisKey[self::REDIS_KEY_LIMIT],
				$this->redisIndexKey[self::REDIS_KEY_LIMIT],
				$this->getMicroTime() . ':' . json_encode($data)
			);
			$this->deleteByLimit();
		}
	}

	protected function deleteByLimit()
	{
		// Always delete first (max) data
		if($this->getHandleDataValue('id') !== null && $this->countByLimit() > $this->getHandleDataValue('max')) {
			$deletedIndex = $this->redis->zRangeByScore(
				$this->redisKey[self::REDIS_KEY_LIMIT],
				$this->redisIndexKey[self::REDIS_KEY_LIMIT],
				$this->redisIndexKey[self::REDIS_KEY_LIMIT],
				array('withscores' => false, 'limit' => array(0, 1))
			);
			if(count($deletedIndex) > 0) {
				$this->redis->zRem(
					$this->redisKey[self::REDIS_KEY_LIMIT],
					current($deletedIndex)
				);
			}
		}
	}

	public function countByLimit()
	{
		return $this->redis->zCount(
			$this->redisKey[self::REDIS_KEY_LIMIT],
			$this->redisIndexKey[self::REDIS_KEY_LIMIT],
			$this->redisIndexKey[self::REDIS_KEY_LIMIT]
		);
	}
	// RmiLimit Functions

	// RmiCache Functions
	public function findByCache()
	{
		$cacheData = $this->redis->hGet(
			$this->redisKey[self::REDIS_KEY_CACHE],
			$this->redisIndexKey[self::REDIS_KEY_CACHE]
		);
		if ($cacheData) {
			// Check Cache Lifetime finished
			if ($this->findLifetime($cacheData) < time()) {
				$this->deleteByCache();
			}

			// Decode Data
			$cacheData = json_decode($this->findData($cacheData), true);
		}

		return $cacheData;
	}

	public function updateByCache($cacheData = null, $lifetime = 360)
	{
		if($cacheData !== null && is_array($cacheData) && count($cacheData) > 0 && $lifetime > 0) {
			$this->redis->hSet(
				$this->redisKey[self::REDIS_KEY_CACHE],
				$this->redisIndexKey[self::REDIS_KEY_CACHE],
				(time()+$lifetime) . ':' . json_encode($cacheData)
			);
		}
	}

	protected function deleteByCache()
	{
		return $this->redis->hDel(
			$this->redisKey[self::REDIS_KEY_CACHE],
			$this->redisIndexKey[self::REDIS_KEY_CACHE]
		);
	}

	public function countByCache()
	{
		return $this->redis->hLen(
			$this->redisKey[self::REDIS_KEY_CACHE]
		);
	}
	// RmiCache Functions

	// RmiStorage Functions
	public function findByStorage()
	{
		$storageData =  $this->redis->hMGet($this->redisKey[self::REDIS_KEY_STORAGE], array_values($this->redisIndexKey[self::REDIS_KEY_STORAGE]));

		return $this->_convertStorage($storageData, true);
	}

	public function updateByStorage($storageData = null, $reverse = false)
	{
		$hashKeys = $this->_convertStorage($storageData);
		if($hashKeys !== null && is_array($hashKeys) && count($hashKeys) > 0) {
			$this->redis->hMset($this->redisKey[self::REDIS_KEY_STORAGE], $hashKeys);
		}
	}

	public function deleteByStorage($storageKeys = null)
	{
		$hashKeys = null;
		if ($storageKeys !== null) {
			$indexKeys = $this->redisIndexKey[self::REDIS_KEY_STORAGE];
			$storageKeys = is_array($storageKeys) ? $storageKeys : (array) $storageKeys;
			foreach ($storageKeys as $storageKey) {
				if(isset($indexKeys[$storageKey])){
					$hashKeys[] = $indexKeys[$storageKey];
				}
			}
		}
		return ($hashKeys !== null)
			? call_user_func_array(array($this->redis, 'hDel'), array_merge( (array) $this->redisKey[self::REDIS_KEY_STORAGE], $hashKeys))
			: false
		;
	}

	public function countByStorage()
	{
		return $this->redis->hLen(
			$this->redisKey[self::REDIS_KEY_STORAGE]
		);
	}

	protected function _convertStorage($storageData = null, $reverse = false)
	{
		$hashKeys = null;
		$indexKeys = $this->redisIndexKey[self::REDIS_KEY_STORAGE];
		$reverseIndexKeys = ($reverse) ? array_flip($indexKeys) : $indexKeys;
		$keys = $this->getHandleDataValue('keys');
		foreach ($storageData as $key => $value) {
			$key = ($reverse) ? $reverseIndexKeys[$key] : $key ;
			if(isset($keys[$key]) && !empty($keys[$key])) {
				switch ($keys[$key])
				{
					case self::RMI_STORAGE_INT:
						$hashKeys[$indexKeys[$key]] = (int) $value;
						break;
					case self::RMI_STORAGE_STRING:
						$hashKeys[$indexKeys[$key]] = (string) $value;
						break;
					case self::RMI_STORAGE_BOOL:
						$hashKeys[$indexKeys[$key]] = (bool) $value;
						break;
					case self::RMI_STORAGE_JSON_ARRAY:
						$hashKeys[$indexKeys[$key]] = ($reverse) ? json_decode($value, true) : json_encode($value);
						break;
					case self::RMI_STORAGE_SIMPLE_ARRAY:
						$hashKeys[$indexKeys[$key]] = ($reverse) ? json_decode($value, true) : json_encode(array_values($value));
						break;
					case self::RMI_STORAGE_TIMESTAMP:
						$hashKeys[$indexKeys[$key]] = (int) $value;
						break;
					case self::RMI_STORAGE_INCR:
						if($reverse) {
							$hashKeys[$indexKeys[$key]] = (int) $value;
						} else {
							$this->redis->hIncrBy($this->redisKey[self::REDIS_KEY_STORAGE], $indexKeys[$key], (int) $value);
						}
						break;
					case self::RMI_STORAGE_DECR:
						if($reverse) {
							$hashKeys[$indexKeys[$key]] = (int) $value;
						} else {
							$this->redis->hIncrBy($this->redisKey[self::REDIS_KEY_STORAGE], $indexKeys[$key], -((int) $value));
						}
						break;
					case self::RMI_STORAGE_EXPIRE:
						$hashKeys[$indexKeys[$key]] = ($reverse) ? (int) $value : time() + (int) $value;
						break;
				}
			}
		}

		return $hashKeys;
	}
	// RmiStorage Functions

//	protected function normalizer($data = null, $stringKeys = null, $intKeys = null, $booleanKeys = null)
//	{
//		array_walk_recursive($data, function(&$itemValue, &$itemKey) use($stringKeys, $intKeys, $booleanKeys) {
//			if(is_array($stringKeys) && count($stringKeys) > 0 && in_array($itemKey, $stringKeys, true)) {
//				$itemValue = (string) $itemValue;
//			} elseif(is_array($intKeys) && count($intKeys) > 0 && in_array($itemKey, $intKeys, true)) {
//				$itemValue = (int) $itemValue;
//			}elseif(is_array($booleanKeys) && count($booleanKeys) > 0 && in_array($itemKey, $booleanKeys, true)) {
//				$itemValue = (bool) $itemValue;
//			}
//		});
//	}

	protected function getMicroTime()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	protected function paginate($count = 1, $page = 1, $perpage = 10)
	{
		$pages = ceil($count / $perpage);

		return array(
			'current' => max(1, $page),
			'total' => $pages,
			'count' => $count
		);
	}

	protected function generateKey($params = null, $glue = ':')
	{
		return is_array($params)
			? implode(':', $params)
			: $params
			;
	}

	protected function generateHashKey($pattern = null, $keys = null)
	{
		$keys = ($keys !== null) ? $keys : $this->getHandleDataValue('keys');
		if($keys === null || empty($keys) || !is_array($keys)) {
			throw new RmiException(self::RMI_ERROR_003);
		}
		$hashKeys = null;
		foreach ($keys as $key => $type) {
			$hashKeys[$key] = $this->generatePatternKey($pattern, $key);
		}

		return $hashKeys;
	}

	protected function generatePatternKey($pattern = null, $key = null)
	{
		preg_match_all('/(\w+)(?=])/', $pattern, $matchPattern);

		if(count($matchPattern) > 0) {
			$pattern = null;
			foreach (current($matchPattern) as $item) {
				if($key !== null && $item == 'key') {
					$pattern[] = $key;
				} elseif ($this->getHandleDataValue($item) !== null) {
					$pattern[] = $this->getHandleDataValue($item);
				}
			}
		}

		return $this->generateKey($pattern);
	}

	protected function findData($indexItem = null)
	{
		return preg_replace('/^([^:]+):/', '', $indexItem);
	}

	protected function findLifetime($indexItem = null)
	{
		preg_match('/^([^:]+)/', $indexItem, $lifeTime);

		return current($lifeTime);
	}

	public function debug()
	{
		var_dump($this);
	}
}