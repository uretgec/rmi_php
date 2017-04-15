<?php
namespace Rmi\Library;

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

	// Storage Types
	const RMI_STORAGE_INT 						= 1;
	const RMI_STORAGE_STRING 					= 2;
	const RMI_STORAGE_BOOL 						= 3;
	const RMI_STORAGE_JSON_ARRAY 			= 4;
	const RMI_STORAGE_SIMPLE_ARRAY 		= 5;
	const RMI_STORAGE_DATE_OBJECT 		= 6;
	const RMI_STORAGE_DATE_TIMESTAMP 	= 7;
	const RMI_STORAGE_INCR 						= 8;
	const RMI_STORAGE_DECR 						= 9;
	const RMI_STORAGE_EXPIRE 					= 10;

	private $redis = null;
	private $redisConfig = null;
	private $redisKey = null;
	private $redisIndexKey = null;
	private $redisDeleteIndexKey = null;
	protected $handleData = null;

	public function __construct($config, $handleData = null)
	{
		$this->redisConfig = $config;

		// TODO: Redis Connect Method Change
		$this->redis = $this->connect();

		// Handle Request
		$this->handleRequest($handleData);
		$this->prepareKeys();
	}

	protected function connect($configId = 0)
	{
		if(!isset($this->redis[$configId])) {
			throw new RmiException(self::RMI_ERROR_000, array($configId));
		}

		list($host, $port, $auth, $db) = $this->redis[$configId];
		$redis = new \Redis();
		$redis->setOption(\Redis::OPT_PREFIX, self::REDIS_PREFIX);
		if(!$redis->pconnect($host, $port)) {
			throw new RmiException(self::RMI_ERROR_001, array($host, $port));
		}
		if($auth !== null && !$redis->auth($auth)) {
			throw new RmiException(self::RMI_ERROR_002, array($host, $port));
		}

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
			'patterns' => array(
				self::REDIS_KEY_LIMIT => '[id]',
				self::REDIS_KEY_CACHE => '[paged][perpage]',
				self::REDIS_KEY_STORAGE => '[key][id]'
			),

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
			throw new RmiException();
		}

		// RmiLimit Redis Keys
		if(isset($patterns[self::REDIS_KEY_LIMIT])) {
			// rmi:[type]
			$this->redisKey[self::REDIS_KEY_LIMIT] = $this->generateKey(
				$this->getHandleDataValue('type')
			);

			// Default Pattern: [pattern]
			$this->redisIndexKey[self::REDIS_KEY_LIMIT] = $this->generatePatternKey($patterns[self::REDIS_KEY_LIMIT]);
		}

		// RmiCache Redis Keys
		if(isset($patterns[self::REDIS_KEY_CACHE])) {
			// rmi:[type]:cache
			$this->redisKey[self::REDIS_KEY_CACHE] = $this->generateKey(array(
				$this->getHandleDataValue('type'),
				'cache'
			));

			// Default Pattern: [paged][perpage]
			$this->redisIndexKey[self::REDIS_KEY_CACHE] = $this->generatePatternKey($patterns[self::REDIS_KEY_CACHE]);
		}

		// RmiStorage Redis Keys
		if(isset($patterns[self::REDIS_KEY_STORAGE])) {
			// rmi:[type]:storage
			$this->redisKey[self::REDIS_KEY_STORAGE] = $this->generateKey(array(
				$this->getHandleDataValue('type'),
				self::REDIS_KEY_STORAGE
			));

			// Default Pattern: [paged][perpage]
			$this->redisIndexKey[self::REDIS_KEY_STORAGE] = $this->generateHashKey($patterns[self::REDIS_KEY_CACHE]);
		}

	}

	protected function getRedisKey($type)
	{
		if(!isset($this->redisKey[$type])) {
			throw new RmiException();
		}

		return $this->redisKey[$type];
	}

	protected function getRedisIndexKey($type)
	{
		if(!isset($this->redisIndexKey[$type])) {
			throw new RmiException();
		}

		return $this->redisIndexKey[$type];
	}

	// RmiLimit Functions

	public function findByLimit($offset = 0, $limit = 10)
	{
		$indexData = array();
		$indexList = $this->redis->zRevRangeByScore($this->redisKey, $this->redisIndexKey, $this->redisIndexKey, array('withscores' => false, 'limit' => array($offset, $limit)));
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
			$this->redis->zAdd($this->redisKey, $this->redisIndexKey, $this->getMicroTime() . ':' . json_encode($data));
			$this->deleteByLimit();
		}
	}

	public function deleteByLimit()
	{
		// Always delete first (max) data
		if($this->getHandleDataValue('id') !== null && $this->countByLimit() > $this->getHandleDataValue('max')) {
			$deletedIndex = $this->redis->zRangeByScore($this->redisKey, $this->redisIndexKey, $this->redisIndexKey, array('withscores' => false, 'limit' => array(0, 1)));
			if(count($deletedIndex) > 0) {
				$this->redis->zRem($this->redisKey, current($deletedIndex));
			}
		}
	}

	public function countByLimit()
	{
		return $this->redis->zCount($this->redisKey, $this->redisIndexKey, $this->redisIndexKey);
	}
	// RmiLimit Functions

	// RmiCache Functions
	public function findByCache()
	{
		$cacheData = $this->redis->hGet($this->redisKey, $this->redisIndexKey);
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
			$this->redis->hSet($this->redisKey, $this->redisIndexKey, (time()+$lifetime) . ':' . json_encode($cacheData));
		}
	}

	public function deleteByCache()
	{
		return $this->redis->hDel($this->redisKey, $this->redisIndexKey);
	}

	public function countByCache()
	{
		return $this->redis->hLen($this->redisKey);
	}

	// RmiCache Functions

	// RmiStorage Functions
	public function findByStorage()
	{
		$storageData = (is_array($this->redisIndexKey))
			? $this->redis->hMGet($this->redisKey, $this->redisIndexKey)
			: $this->redis->hGet($this->redisKey, $this->redisIndexKey)
		;

		return $storageData;
	}

	public function updateByStorage($storageData = null)
	{
		// TODO: hey stop here
	}

	public function deleteByStorage()
	{
		return (is_array($this->redisDeleteIndexKey))
			? call_user_func_array(array($this->redis, 'hDel'), array_merge($this->redisKey, $this->redisDeleteIndexKey))
			: $this->redis->hDel($this->redisKey, $this->redisDeleteIndexKey)
			;
	}

	public function countByStorage()
	{
		return $this->redis->hLen($this->redisKey);
	}
	// RmiStorage Functions






	protected function normalizer($data = null, $stringKeys = null, $intKeys = null, $booleanKeys = null)
	{
		array_walk_recursive($data, function(&$itemValue, &$itemKey) use($stringKeys, $intKeys, $booleanKeys) {
			if(is_array($stringKeys) && count($stringKeys) > 0 && in_array($itemKey, $stringKeys, true)) {
				$itemValue = (string) $itemValue;
			} elseif(is_array($intKeys) && count($intKeys) > 0 && in_array($itemKey, $intKeys, true)) {
				$itemValue = (int) $itemValue;
			}elseif(is_array($booleanKeys) && count($booleanKeys) > 0 && in_array($itemKey, $booleanKeys, true)) {
				$itemValue = (bool) $itemValue;
			}
		});
	}

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

	protected function generateKey($params = null)
	{
		return is_array($params)
			? implode(':', $params)
			: $params
			;
	}

	protected function generateHashKey($pattern = null)
	{
		$keys = $this->getHandleDataValue('keys');
		if($keys === null || empty($keys) || !is_array($keys)) {
			throw new RmiException();
		}

		$hashKeys = null;
		foreach ($keys as $key => $type) {
			$hashKeys[$this->generatePatternKey($pattern, $key)] = $type;
		}

		return $hashKeys;
	}

	protected function generatePatternKey($pattern = null, $hashKey = null)
	{
		preg_match('/(\w+)(?=])/', $pattern, $matchPattern);
		if(count($matchPattern) > 0) {
			$pattern = null;
			foreach ($matchPattern as $item) {
				if($hashKey !== null) {
					$pattern[] = $hashKey;
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
		return preg_match('/^([^:]+)/', $indexItem, $lifeTime);
	}
}