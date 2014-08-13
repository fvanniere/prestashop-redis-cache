<?php

if (!defined('_PS_VERSION_'))
	exit;



class Rediscache extends Module
{
	public function __construct()
	{
		$this->name = 'rediscache';
		$this->tab = 'front_office_features';
		$this->version = '1.0';
		$this->author = 'Frédéric VANNIÈRE - Planet-Work';

		parent::__construct();

		$this->displayName = $this->l('Redis Cache');
		$this->description = $this->l('Replace Memcached with Redis as reliable and fast cache storage');
	}




}

