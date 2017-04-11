<?php
namespace Rmi\Library\Adapter;

// TODO: not finished.
class RmiStorage extends Rmi
{
	private $redisKey = null;
	private $redisIndexKey = null;
	private $redisDeleteIndexKey = null;

	public function __construct()
	{
		parent::__construct();

		// TODO: isHandleDataValid function needed

		// rmi:[type]:cached
		$this->redisKey = $this->generateKey(array(
			$this->getHandleDataValue('type'),
			'storage'
		));

		// Default Pattern: [key][id]
		$this->redisIndexKey = $this->generateHashKey($this->getHandleDataValue('storage_pattern'));
	}

	public function find()
	{
		$storageData = (is_array($this->redisIndexKey))
			? $this->redis->hMGet($this->redisKey, $this->redisIndexKey)
			: $this->redis->hGet($this->redisKey, $this->redisIndexKey)
		;

		return $storageData;
	}

	public function update($storageData = null)
	{
		// TODO: hey stop here
	}

	public function delete()
	{
		return (is_array($this->redisDeleteIndexKey))
			? call_user_func_array(array($this->redis, 'hDel'), array_merge($this->redisKey, $this->redisDeleteIndexKey))
			: $this->redis->hDel($this->redisKey, $this->redisDeleteIndexKey)
		;
	}

	public function count()
	{
		return $this->redis->hLen($this->redisKey);
	}
}