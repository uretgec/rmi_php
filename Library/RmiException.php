<?php
namespace Rmi\Library;

use Throwable;

/**
 * Class RmiException
 * @package Rmi\Library
 */
class RmiException extends \Exception
{
	/**
	 * @var null
	 */
	private $data = null;

	/**
	 * RmiException constructor.
	 * @param string $message
	 * @param null $data
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct($message = "", $data = null, $code = 0, Throwable $previous = null) {
		parent::__construct($message, $code, $previous);

		$this->data = $data;
	}

	/**
	 * @return mixed|string
	 */
	public function getRmiMessage()
	{
		return is_array($this->getData())
			? call_user_func('sprintf', array_merge(array($this->getMessage()), $this->getData()))
			: $this->getMessage()
		;
	}

	/**
	 * @return null
	 */
	public function getData()
	{
		return $this->data;
	}
}