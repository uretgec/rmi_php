<?php
namespace Rmi\Library\Adapter;

class RmiCache extends Rmi
{
	private $redisKey = null;
	private $redisIndexKey = null;

	public function __construct()
	{
		parent::__construct();

		// TODO: isHandleDataValid function needed

		// rmi:[type]:cached
		$this->redisKey = $this->generateKey(array(
			$this->getHandleDataValue('type'),
			'cache'
		));

		// Default Pattern: [paged][perpage]
		$this->redisIndexKey = $this->generatePatternKey($this->getHandleDataValue('cache_pattern'));
	}

	public function find()
	{
		$cacheData = $this->redis->hGet($this->redisKey, $this->redisIndexKey);
		if ($cacheData) {
			// Check Cache Lifetime finished
			if ($this->findLifetime($cacheData) < time()) {
				$this->delete();
			}

			// Decode Data
			$cacheData = json_decode($this->findData($cacheData), true);
		}

		return $cacheData;
	}

	public function update($cacheData = null, $lifetime = 360)
	{
		if($cacheData !== null && is_array($cacheData) && count($cacheData) > 0 && $lifetime > 0) {
			$this->redis->hSet($this->redisKey, $this->redisIndexKey, (time()+$lifetime) . ':' . json_encode($cacheData));
		}
	}

	public function delete()
	{
		return $this->redis->hDel($this->redisKey, $this->redisIndexKey);
	}

	public function count()
	{
		return $this->redis->hLen($this->redisKey);
	}
}