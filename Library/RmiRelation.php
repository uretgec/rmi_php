<?php
namespace Rmi\Library;

class RmiRelation extends Rmi
{
	private $redisKey = null;
	private $redisIndexKey = null;

	public function __construct()
	{
		parent::__construct();

		// rmi:[type]:cached
		$this->redisKey = $this->generateKey(array(
			$this->getHandleDataValue('type'),
			'relation'
		));
	}

	public function find()
	{

	}

	public function update()
	{

	}

	public function delete()
	{

	}

	public function count()
	{

	}
}