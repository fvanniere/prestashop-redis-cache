<?php
/*
* 2007-2013 PrestaShop
* 2013-2014 Planet-Work
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2013 PrestaShop SA   - 2013 Planet-Work
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * This class require PECL Redis extension
 *
 */
#global $_LANGADM;
#$_LANGADM['AdminPerformance05894ba2c918dd33592bf1bd21c347f6'] = 'RedisCache';


class CacheMemcacheCore extends Cache
{
	/**
	 * @var Redis
	 */
	protected $redis;
        protected $localcache;

	/**
	 * @var bool Connection status
	 */
	protected $_isConnected = false;

	public function __construct()
	{
		$this->connect();

		// Get keys (this code comes from Doctrine 2 project)
                $this->keys = array();
                $this->localcache = array();
       
                if(class_exists('Redis')) {
                   return true;
                }
                return false;
                
        }
      

	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Connect to redis server
	 */
	public function connect()
	{
		if (class_exists('Redis') && extension_loaded('redis'))
			$this->_redis = new Redis();
		else
			return false;
               if (!_PS_CACHE_REDIS_ || !preg_match("/:/",_PS_CACHE_REDIS_))
                        define('_PS_CACHE_REDIS_', '127.0.0.1:6379');
                $params = explode(':',_PS_CACHE_REDIS_);
                try {
    	            $this->_isConnected = $this->_redis->connect($params[0], $params[1]);
                    $this->_redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP); 
                } catch (Exception $e) {
                    $this->_isConnected = false;
                }
	}

	/**
	 * @see Cache::_set()
	 */
	protected function _set($key, $value, $ttl = 0)
	{
		if (!$this->_isConnected)
			return false;
                $this->localcache[$key] = $value;
                if ($ttl > 0)
		    return $this->_redis->setex($key, $ttl, $value);
		return $this->_redis->set($key, $value);
	}

	/**
	 * @see Cache::_get()
	 */
	protected function _get($key)
	{
		if (!$this->_isConnected)
			return false;
                if (isset($this->localcache[$key])) {
                    return $this->localcache[$key];
                }
                $value = $this->_redis->get($key);
                $this->localcache[$key] = $value;
                return $value;
	}

	/**
	 * @see Cache::_exists()
	 */
	protected function _exists($key)
	{
		if (!$this->_isConnected)
			return false;
		return $this->_redis->exists($key);
	}

	/**
	 * @see Cache::_delete()
	 */
	protected function _delete($key)
	{
		if (!$this->_isConnected)
			return false;
		return $this->_redis->delete($key);
	}

	/**
	 * @see Cache::_writeKeys()
	 */
	protected function _writeKeys()
	{
	}

	/**
	 * @see Cache::flush()
	 */
	public function flush()
	{
		if (!$this->_isConnected)
			return false;
                $keys = $this->_redis->keys(_DB_NAME_.'.*');
                $this->_redis->delete(join(' ',$keys));
		return true;
	}

	/**
	 * Close connection to redis server
	 *
	 * @return bool
	 */
	protected function close()
	{
		if (!$this->_isConnected)
			return false;
		return $this->_redis->close();
	}

        public function get($key) 
        {
             return $this->_get(_DB_NAME_.'.'.$key);
        }

        public function set($key, $value, $ttl = 86400)
        { 
             return $this->_set(_DB_NAME_.'.'.$key,$value,$ttl);
        }

        public function exists($key) 
        {
                if (!$this->_isConnected)
                        return false;
                $this->_redis->exists(_DB_NAME_.'.'.$key);
        }

        public function delete($key)
        {
		if (!$this->_isConnected)
			return false;
		$this->_redis->delete(_DB_NAME_.'.'.$key);
        }

        public function setQuery($query, $result)
        {
            if ($this->isBlacklist($query) || strpos($query,'cachemanager') || strpos($query,'_search_'))
                        return true;
            if (!$this->_isConnected)
			return false;
            $md5_query = _DB_NAME_.'.'.md5($query);
            if ($this->_redis->exists($md5_query))
	        return true;
            
	    $key = $this->_set($md5_query, $result); //$key = $md5_query
            if ($tables = $this->getTables($query))
                foreach ($tables as $table) {
                    if (strlen($table) > 0)
                        $this->_redis->hSet(_DB_NAME_.'.'.$table,$md5_query,true);
                }
        } 

        public function deleteQuery($query)
        {
            if (!$this->_isConnected || strpos($query,'cachemanager'))
               return false;
   
            $this->localcache = array();
            $md5_query =  _DB_NAME_.'.'.md5($query);
            $delete_keys = array();
            array_push($delete_keys,$md5_query);

            if ($tables = $this->getTables($query)) {
                foreach ($tables as $table) {
                    if (strlen($table) > 0 && $this->_redis->exists(_DB_NAME_.'.'.$table)) {
                        $keys = $this->_redis->hKeys(_DB_NAME_.'.'.$table);
                        if ($keys) {
   			    foreach ($keys as $redisKey) {
                                if (!in_array($redisKey,$delete_keys)) {
                                     array_push($delete_keys,$redisKey);
                                     array_push($delete_keys,$redisKey.'_nrows');
                                }
                            }
                        }
                        array_push($delete_keys,_DB_NAME_.'.'.$table);
		    }
                }
            }


            $this->_redis->del($delete_keys);
        }

        /**
         * Add a memcache server
         *
         * @param string $ip
         * @param int $port
         * @param int $weight
         */
        public static function addServer($ip, $port, $weight)
        {
                return Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'memcached_servers (ip, port, weight) VALUES(\''.pSQL($ip).'\', '.(int)$port.', '.(int)$weight.')', false);
        }

        /**
         * Get list of memcached servers
         *
         * @return array
         */
        public static function getMemcachedServers()
        {
                return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT * FROM '._DB_PREFIX_.'memcached_servers', true, false);
        }

        /**
         * Delete a memcache server
         *
         * @param int $id_server
         */
        public static function deleteServer($id_server)
        {
                return Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'memcached_servers WHERE id_memcached_server='.(int)$id_server);
        }


}
