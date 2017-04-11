<?php
namespace Rmi\Library\Enum;

class RmiErrorType
{
	const RMI_000 = 'Redis config not found: %s';
	const RMI_001 = 'Could not connect redis server: %s:%s';
	const RMI_002 = 'Could not auth redis server: %s:%s';
}