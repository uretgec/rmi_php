# RMI TOOLS - Redis Multiple Index Tools
Rmi is basic Redis tool manager for php. Rmi has three tools.

- RmiLimit: Limitation for ZINDEX Data. For example: users last 10 match results, products last 5 purchasers. (LIMIT Option for ZREMRANGEBYSCORE Command)
- RmiCache: Cache for RmiLimit Data with your own pattern and lifetime.
- RmiStorage: Hash based data set and get with your own pattern.

## Options
Required Fields:
* type: Name of Data. matchresult, leaderboard, purchaseitem
* patterns: Pattern settings of Rmi Tools. You can select which one to use or use all of tools.

Use all tools
```
'patterns' => array(
			Rmi::REDIS_KEY_LIMIT => '[id]',
			Rmi::REDIS_KEY_CACHE => '[paged][perpage]',
			Rmi::REDIS_KEY_STORAGE => '[key][id]'
		)
```

You can use only RmiLimit and RmiCache
```
'patterns' => array(
			Rmi::REDIS_KEY_LIMIT => '[id]',
			Rmi::REDIS_KEY_CACHE => '[paged][perpage]'
		)
```

Optional Fields:
* keys: RmiStorage Keys. You can not use into pattern. Rmi use every key in REDIS_KEY_STORAGE pattern ([key][id])
* max: RmiLimit Maximum Data to store per Pattern Key (id)
* paged: Default is 1. Offset data generates this variable.
* perpage: Default is 10. Limit data generates this variable.

Pattern Keys:
* id: the key use in REDIS_KEY_LIMIT pattern ([id]) but you can use own key and variable.

## Basic Usage

1. Generate Redis Configration
```
$config = array(
		// host, port, auth, db
		array('127.0.0.1', 6379, null, 0)
	);
```

2. Generate Handle Data (Options)
```
$handleData = array(
		'type' => 'matchresult',
		'patterns' => array(
			Rmi::REDIS_KEY_LIMIT => '[id]',
			Rmi::REDIS_KEY_CACHE => '[paged][perpage]',
			Rmi::REDIS_KEY_STORAGE => '[key][id]'
		),
		'id' => 123123,
		'keys' => array(
			'goal' => Rmi::RMI_STORAGE_INCR,
			'win' => Rmi::RMI_STORAGE_INCR,
			'user_info' => Rmi::RMI_STORAGE_JSON_ARRAY,
			'due_count' => Rmi::RMI_STORAGE_DECR,
			'matchtime' => Rmi::RMI_STORAGE_TIMESTAMP,
			'blocktime' => Rmi::RMI_STORAGE_EXPIRE
		),
		'max' => 10,
		'paged' => 1,
		'perpage' => 10
	);
```

3. Init RmiManager
```
$rmiManager = new Rmi($config, $handleData);
```

4. Use RmiLimit Update and Find
```
$rmiManager->updateByLimit(array('key'=>'value', 'id' => $i));

$limitData = $rmiManager->findByLimit();
```

5. Use RmiCache Update and Find
```
$cacheData = $rmiManager->findByCache();
if(!$cacheData) {
	$cacheData = $rmiManager->findByLimit();
	$rmiManager->updateByCache($cacheData, 60);
}
```

6. Use RmiStorage Update, Delete and Find
```
$rmiManager->updateByStorage(array(
		'goal' => 4,
		'win' => 1,
		'matchtime' => time(),
		'due_count' => 2,
		'blocktime' => 25,
		'user_info' => array(
			'name' => 'User1',
			'fulname' => 'First Second Name'
		)
));
$hashData = $rmiManager->findByStorage();
$rmiManager->deleteByStorage('due_count');
$rmiManager->deleteByStorage(array('blocktime'));
```

7. Catch Exception
```
try
{

} catch (RmiException $rmiException) {
	echo $rmiException->getRmiMessage();
}
```

