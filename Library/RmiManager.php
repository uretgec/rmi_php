<?php
namespace Rmi\Library;

use Rmi\Library\Enum\RmiErrorType;

class RmiManager
{
	const REDIS_PREFIX = 'rmi:';

	private $redis = null;
	private $redisConfig = null;

	public function connect($configId = 0)
	{
		if(!isset($this->redis[$configId])) {
			throw new RmiException(RmiErrorType::RMI_000, array($configId));
		}

		list($host, $port, $auth, $db) = $this->redis[$configId];
		$redis = new \Redis();
		$redis->setOption(\Redis::OPT_PREFIX, self::REDIS_PREFIX);
		if(!$redis->pconnect($host, $port)) {
			throw new RmiException(RmiErrorType::RMI_001, array($host, $port));
		}
		if($auth !== null && !$redis->auth($auth)) {
			throw new RmiException(RmiErrorType::RMI_002, array($host, $port));
		}

		$this->redis = $redis;
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
}