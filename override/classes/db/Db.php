<?php
/*override/db/Db.php*/
abstract class Db extends DbCore {

        /**
         * ExecuteS return the result of $sql as array
         *
         * @param string $sql query to execute
         * @param boolean $array return an array instead of a mysql_result object (deprecated since 1.5.0, use query method instead)
         * @param bool $use_cache if query has been already executed, use its result
         * @return array or result object
         */
        public function executeS($sql, $array = true, $use_cache = true)
        {
                if ($sql instanceof DbQuery)
                        $sql = $sql->build();

                // This method must be used only with queries which display results
                if (!preg_match('#^\s*\(?\s*(select|show|explain|describe|desc)\s#i', $sql))
                {
                        if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_)
                                throw new PrestaShopDatabaseException('Db->executeS() must be used only with select, show, explain or describe queries');
                        return $this->execute($sql, $use_cache);
                }

                $this->result = false;
                $this->last_query = $sql;
                // Ça chie ici: $result = Cache::getInstance()->get(md5($sql)) -> Pareil si pas de requête en cache ou resultat vide
                $result = Cache::getInstance()->get(md5($sql));
                //if ($use_cache && $this->is_cache_enabled && $array && ($result = Cache::getInstance()->get(md5($sql))))
                if ($use_cache && $this->is_cache_enabled && $array && is_array($result))
                {
                        $this->last_cached = true;
                        #error_log('DEBUG: SQL ExecuteS '.md5($sql)." en cache (taille: ".count($result).") \n",3,"/tmp/debug.log");
                        return $result;
                }
                        #error_log('DEBUG: SQL ExecuteS '.md5($sql)." PAS en cache ===".print_r ($result,true)."(taille :".count($result).")\n",3,"/tmp/debug.log");


                $this->result = $this->query($sql);
                if (!$this->result) {
                        #error_log('DEBUG: SQL ExecuteS '.md5($sql)." pas de result > return false \n",3,"/tmp/debug.log");
                        #error_log('DEBUG: SQL ExecuteS SQL='.$sql." --> vide ?\n",3,"/tmp/debug.log");
                        return false;
                }

                $this->last_cached = false;
                if (!$array) {
                        #error_log('DEBUG: SQL ExecuteS '.md5($sql)." return result sans stocker car !$array\n",3,"/tmp/debug.log");
                        return $this->result;
                }

                $result_array = array();
                while ($row = $this->nextRow($this->result))
                        $result_array[] = $row;

                if ($use_cache && $this->is_cache_enabled) {
                        #error_log('DEBUG: SQL ExecuteS '.md5($sql)." on stocke dans le cache (taille:".count($result_array).")\n",3,"/tmp/debug.log");
                        Cache::getInstance()->setQuery($sql, $result_array);
                }
                return $result_array;
        }

	/**
	 * Execute a query and get result ressource
	 *
	 * @param string $sql
	 * @return mixed
	 */
	public function query($sql)
	{
		$before=microtime(true);
		if ($sql instanceof DbQuery)
			$sql = $sql->build();

		$this->result = $this->_query($sql);

		$after=microtime(true);
		$duration=$after-$before;

                /*
                 * Toutes les requêtes dont le temps d’exécution est supérieur à 0.2 secondes
                 * seront stoquée dans le fichier log
                 */
		if($duration>1)
			$this->writeLog($sql,$duration);

		if (_PS_DEBUG_SQL_)
			$this->displayError($sql);
		return $this->result;
	}

	function writeLog($query,$duration) {
		$logfile=_PS_ROOT_DIR_."/log/query_logs.txt";
		$fp=fopen($logfile, "a");
		fwrite($fp,">>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		fwrite($fp, "\n".$query."\n".$_SERVER['REQUEST_URI']."   [".$_SERVER["REMOTE_ADDR"]."]\n".$duration."\n");
		fwrite($fp,"<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<");
		fclose ($fp);
	}
}

?>
