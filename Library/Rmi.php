<?php
namespace Rmi\Library;

class Rmi
{
	// Constants
	const REDIS_PREFIX = 'rmi:';

	public $redis = null;
	protected $handleData = null;

	public function __construct($handleData = null)
	{
		// Redis
		$this->redis = $this->connect();

		// Handle Data
		$this->handleRequest($handleData);
	}

	protected function setHandleData($key = null, $value = null)
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

	public function getHandleDataValue($key = null)
	{
		return (isset($this->handleData[$key]) && !empty($this->handleData[$key]))
			? $this->handleData[$key]
			: null;
	}

	private function handleRequest($data)
	{
		// All Request Data
		$handleData = array(
			// RmiLimit Parameters
			'limit_pattern' => '[id]',
			'type' => null,
			'max' => 10,
			'id' => null,

			// RmiCache Parameters
			'cache_pattern' => '[paged][perpage]',
			'paged' => 1,
			'perpage' => 10,

			// RmiStorage Parameters
			'storage_pattern' => '[key][id]',
			'keys' => array()
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

	/*
	 * Redis Client Library
	 * https://github.com/phpredis/phpredis
	 * */
	protected function connect()
	{
		$redis = new \Redis();
		$redis->setOption(\Redis::OPT_PREFIX, self::REDIS_PREFIX);
		$redis->pconnect('127.0.0.1', '6379');

		return $redis;
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

	// TODO: not ready to use
	protected function generateHashKey($params = null)
	{
		if ($params !== null && is_array($params) || count($params) > 0) {
			return implode(':', $params);
		}

		return $params;
	}

	protected function generatePatternKey($pattern = null)
	{
		preg_match('/(\w+)(?=])/', $pattern, $matchPattern);
		if(count($matchPattern) > 0) {
			$pattern = null;
			foreach ($matchPattern as $item) {
				if($this->getHandleDataValue($item) !== null) {
					$pattern[] = $this->getHandleDataValue($item);
				}
			}
		}

		return $this->generateKey($pattern);
	}

	protected function findPattern($indexItem = null)
	{
		return preg_replace('/^([^:]+):/', '', $indexItem);
	}

	public function normalizer($data = null, $stringKeys = null, $intKeys = null, $booleanKeys = null)
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
}