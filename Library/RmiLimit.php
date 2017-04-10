<?php
namespace Rmi\Library;

class RmiLimit extends Rmi
{
	private $redisKey = null;
	private $redisIndexKey = null;

	public function __construct($config = null)
	{
		parent::__construct();

		// TODO: isHandleDataValid function needed

		// rmi:[type]
		$this->redisKey = $this->generateKey($this->getHandleDataValue('type'));

		// Default Pattern: [pattern]
		$this->redisIndexKey = $this->generatePatternKey($this->getHandleDataValue('limit_pattern'));
	}

	public function find($offset = 0, $limit = 10)
	{
		$indexData = array();
		$indexList = $this->redis->zRevRangeByScore($this->redisKey, $this->redisIndexKey, $this->redisIndexKey, array('withscores' => false, 'limit' => array($offset, $limit)));
		if (count($indexList) > 0) {
			foreach ($indexList as $indexItem) {
				$indexData[] = json_decode($this->findPattern($indexItem), true);
			}
		}

		return $indexData;
	}

	/*
	 * ZSET Index Pattern
	 * ==================
	 * zAdd generateKey, generateIndexKey, microtime():json_encode($data)
	 * */
	public function update($data = null)
	{
		if( $this->getHandleDataValue('id') !== null || $data !== null) {
			$this->redis->zAdd($this->redisKey, $this->redisIndexKey, $this->getMicroTime() . ':' . json_encode($data));
			$this->delete();
		}
	}

	public function delete()
	{
		// Always delete first (max) data
		if($this->getHandleDataValue('id') !== null && $this->count() > $this->getHandleDataValue('max')) {
				$deletedIndex = $this->redis->zRangeByScore($this->redisKey, $this->redisIndexKey, $this->redisIndexKey, array('withscores' => false, 'limit' => array(0, 1)));
				if(count($deletedIndex) > 0) {
					$this->redis->zRem($this->redisKey, current($deletedIndex));
				}
		}
	}

	public function count()
	{
		return $this->redis->zCount($this->redisKey, $this->redisIndexKey, $this->redisIndexKey);
	}
}