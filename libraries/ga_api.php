<?php

/* Name        : gap_api.php
*  Description : Codeigniter Library Class for Google Analytics API
*  Creation	   : 01/05/2011
*  Version	   : 0,2
*  Author	   : Nithin Meppurathu
*/

class Ga_api {

	private	$auth;
	private	$accounts;
	private $ci;
	
	//compte
	var $profile_id		= '';
	var $email 			= '';
	var $password		= '';
	var $data_home 		= 'https://www.google.com/analytics/feeds/data?';
	var $accounts_home	= 'https://www.google.com/analytics/feeds/accounts/default';
	var $login_home		= 'https://www.google.com/accounts/ClientLogin';
	var $cache_ext		= 'xml';
	var $prettyprint	= false;
	var $dimension		= ''; 
	var $metric 		= '';
	var $segment		= '';
	var $dsegment		= '';
	var $sort_by 		= false; 
	var $start			= false; 
	var $stop 			= false;
	var $max_results 	= '';
	var $start_index 	= 1;
	var $filters 		= false;
	var $debug 			= true;
	var $summary		= true;
	var $data_file		= '';
	var $cache_folder 	= '';
	var $query_string 	= '';
	var $cache_data		= false;
	var $cache_accounts	= false;
	var $acc_vars		= '';
	var $source_name	= 'Codeigniter';

	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->config('ga_api');

		$this->profile_id 	= $this->ci->config->item('profile_id');
		$this->email 		= $this->ci->config->item('email');
		$this->password 	= $this->ci->config->item('password');

		$this->cache_data 	= $this->ci->config->item('cache_data');
		$this->cache_folder = $this->ci->config->item('cache_folder');
		$this->clear_cache 	= $this->ci->config->item('clear_cache');
			
		$this->debug 		= $this->ci->config->item('debug');
	}
	
	// --------------------------------------------------------------------
	/**
	 * GA_Api function.
	 * Contructor
	 * 
	 * @access public
	 * @param array $config. (default: array())
	 * @return void
	 */
	function init($config = array())
	{	
		if (count($config) > 0) $this->initialize($config);
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * Initialize the user preferences
	 * Accepts an associative array as input, containing display preferences
	 *
	 * @access	public
	 * @param	array	config preferences
	 * @return	void
	 */	
	function initialize($config = array())
	{		
		foreach ($config as $key => $val)
		{
			if (isset($this->$key)) $this->$key = $val;
		}
		if (isset($this->ci->config->clear_cache)) $this->clear_cache($this->clear_cache);
	}

	//---------------------------------------------------------------------------------------------
	/**
	 * Logs into the Google Analytics API and sets $this->auth to the authorisation token returned
	 *
	 * @param string $email The email address of your Google Analytics account
	 * @param string $password Password for the account
	 * @return boolean True if the login succeeded, false if not
	 */
	function login($email = false, $password = false) 
	{
		if ($email) $this->email = $email;
		if ($password) $this->password = $password;
		
		$this->ci->load->library('session');
		$this->auth = $this->ci->session->userdata('ga_auth');
		
		if (! $this->auth)
		{
			$data = array(
				'accountType' => 'GOOGLE',
				'Email'		=> $this->email,
				'Passwd'	=> $this->password,
				'service' 	=> 'analytics',
				'source' 	=> $this->source_name
			);

			$ch = $this->curl_init($this->login_home);
			curl_setopt($ch, CURLOPT_POST, true);			
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
			
			if($info['http_code'] == 200) {
				preg_match('/Auth=(.*)/', $output, $matches);
				if(isset($matches[1])) {
					$this->auth = $matches[1];
				}
			}
			$this->ci->session->set_userdata('ga_auth', $this->auth);
			$this->auth = $this->ci->session->userdata('ga_auth');
		}

		if (! $this->auth) return false;
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * get_accounts function.
	 * Récupère la liste des profils et quelques informations supplémentaires
	 * Récupère également les segments par défaut et les segements définis par 
	 * le propriétaire du compte
	 * 
	 * @access public
	 */
	function get_accounts($object = true) 
	{		
		$this->acc_file = $this->call($this->accounts_home);
		$dom = new DOMDocument();
		$dom->loadXML($this->acc_file);
		
		//segments
		$segments =  $dom->getElementsByTagName('segment');
		foreach ($segments as $segment)
		{
			$index = $segment->getAttribute('id');
			
			$this->accounts['segments'][$index] = $segment->getAttribute('name');
		}
		
		// web-sites
		$entries = $dom->getElementsByTagName('entry');
		foreach($entries as $entry) 
		{
			$titles = $entry->getElementsByTagName('title');
			$title = $titles->item(0)->nodeValue;
			$this->accounts[$title] = array('title' => $title);
			
			$tableIds = $entry->getElementsByTagName('tableId');
			$this->accounts[$title]['tableId'] = $tableIds->item(0)->nodeValue;
			
			$properties = $entry->getElementsByTagName('property');
			
			foreach($properties as $property) 
			{
				switch($property->getAttribute('name')) 
				{
					case 'ga:accountId':
						$this->accounts[$title]['accountId'] = $property->getAttribute('value');
					break;
					case 'ga:accountName':
						$this->accounts[$title]['accountName'] = $property->getAttribute('value');
					break;
					case 'ga:webPropertyId':
						$this->accounts[$title]['webPropertyId'] = $property->getAttribute('value');
					break;
					case 'ga:profileId':
						$this->accounts[$title]['profileId'] = $property->getAttribute('value');
					break;
				}
			}
		}
		
		if ($object) 
			return (array) $this->_array_to_object($this->accounts);
			
		return $this->accounts;
	}

	// --------------------------------------------------------------------
	/**
	 * filter function.
	 * 
	 * @access public
	 * @param mixed $dimension_or_metric
	 * @param mixed $filter_comparison
	 * @param mixed $filter_value
	 */
	function filter($dim_or_met, $filter_comparison, $filter_value)
	{
		$this->filters = $this->_ga_prefix($dim_or_met) . urlencode($filter_comparison.$filter_value);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * and_filter function.
	 * 
	 * @access public
	 * @param mixed $dimension_or_metric
	 * @param mixed $filter_comparison
	 * @param mixed $filter_value
	 */
	function and_filter($dim_or_met, $filter_comparison, $filter_value) 
	{
		$this->filters .= ';' . $this->_ga_prefix($dim_or_met) . urlencode($filter_comparison.$filter_value);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * or_filter function.
	 * 
	 * @access public
	 * @param mixed $dimension_or_metric
	 * @param mixed $filter_comparison
	 * @param mixed $filter_value
	 */
	function or_filter($dim_or_met, $filter_comparison, $filter_value) 
	{
		$this->filters .= ',' . $this->_ga_prefix($dim_or_met) . urlencode($filter_comparison.$filter_value);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * when function.
	 * Les temps d'entrée peuvent être soit un timestamp, soit un 
	 * string compatible avec la fonction strtotime. si on passe false 
	 * en second parametre ($end) alors cette valeur vaut la première
	 * 
	 * @access public
	 * @param string $start. (default: '1 month ago')
	 * @param string $end. (default: 'yesterday')
	 */
	function when($start = '2 day ago', $end = 'yesterday')
	{
		$this->start = $this->_parse_time($start);
		$this->stop = $this->_parse_time($end);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * sort_by function.
	 * On passe les paramêtres (dimension ou metric) qui doivent servir à trier les résultats.
	 * Si la variable $inv existe, le tri se fait dans l'ordre inverse.
	 * 
	 * @access public
	 * @param mixed $sort
	 */
	function sort_by($arr_or_str, $inv = false)
	{
		$this->sort_by= $this->_values_converter($arr_or_str);
		
		if (! $inv) $this->sort_by= '-'.$this->sort_by;
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * limit function.
	 * Spécification du nombre de résultats et de l'index
	 * de départ
	 *
	 * @access public
	 * @param int $results. (default: 31)
	 */
	function limit($results = 31, $offset = null)
	{
		$this->max_results = $results;
		
		if ($offset) $this->offset($offset);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * offset function.
	 * 
	 * @access public
	 * @param int $index. (default: 10)
	 */
	function offset($index = 10)
	{
		$this->start_index = $index;
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * dimension function.
	 * 
	 * @access public
	 * @param mixed $arr_or_str
	 */
	function dimension($arr_or_str)
	{
		$this->dimension = $this->_values_converter($arr_or_str);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * metric function.
	 * 
	 * @access public
	 * @param mixed $arr_or_str
	 */
	function metric($arr_or_str)
	{
		$this->metric = $this->_values_converter($arr_or_str);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * segment function.
	 * 
	 * @access public
	 * @param mixed $int
	 */
	function segment($int)
	{
		$this->segment = $int;
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * segment function.
	 * segment dynamique. Doit être écrit comme conformement à la syntaxe google
	 * 
	 * @access public
	 */
	function dsegment($dim_or_met, $filter_comparison, $filter_value)
	{
		$this->dsegment = $this->_ga_prefix($dim_or_met) . urlencode($filter_comparison.$filter_value);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * and_dsegment function.
	 * 
	 * @access public
	 * @param mixed $dimension_or_metric
	 * @param mixed $filter_comparison
	 * @param mixed $filter_value
	 */
	function and_dsegment($dim_or_met, $filter_comparison, $filter_value) 
	{
		$this->dsegment .= ';' . $this->_ga_prefix($dim_or_met) . urlencode($filter_comparison.$filter_value);
		
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * or_dsegment function.
	 * 
	 * @access public
	 * @param mixed $dimension_or_metric
	 * @param mixed $filter_comparison
	 * @param mixed $filter_value
	 */
	function or_dsegment($dim_or_met, $filter_comparison, $filter_value) 
	{
		$this->dsegment .= ',' . $this->_ga_prefix($dim_or_met) . urlencode($filter_comparison.$filter_value);
		
		return $this;
	}

	
	// --------------------------------------------------------------------
	/**
	 * get function.
	 * La fonction permet de récupérer les infos de GA et de les afficher
	 * directement telles quelles au format XML.
	 * 
	 * @access public
	 * @param Bool $file. (default: false)
	 */
	function get($file = false) 
	{	
		if (! $file)
		{
			$url = $this->_build_url();
			
			if ($this->debug) 
			{
			    if (PHP_SAPI == 'cli') $this->ci->ouput->set_output("$url\n");
			    else $this->ci->output->set_output("<p>" . htmlentities($url) . "</p>\n");
			}
			if (file_exists($this->cache_folder.md5($this->query_string).'.'.$this->cache_ext) && $this->cache_data == true)
			{
			    $this->data_file = file_get_contents($this->cache_folder.md5($this->query_string).'.'.$this->cache_ext);
			}
			else 
			{			 
				if (! $this->auth) $this->login();
				   			    
			    $this->data_file = $this->call($url);
			
			    if (! $this->data_file) return false;
			    
			    if ($this->cache_data == true) $this->_store_data($this->data_file);
			}
		}
		
		else if (! $this->data_file = file_get_contents($file)) return false;
		
		return $this->data_file;
	}
	
	// --------------------------------------------------------------------
	/**
	 * get_array function.
	 * Retourne les données sous forme d'array
	 * 
	 * @access public
	 * @param mixed $config
	 */
	function get_array($file = false)
	{
		if (! $file) $xml =  $this->get();
		
		if (! $xml = $this->get($file)) return false;
		
		return $this->_xml_to_array($xml);
	}
	
	// --------------------------------------------------------------------
	/**
	 * get_object function.
	 * Retourne les données sous forme d'object
	 * 
	 * @access public
	 * @param mixed $config
	 */
	function get_object($file = false)
	{
		if (! $file) $xml = $this->get();
		
		if (! $xml = $this->get($file)) return false;
		
		$object = $this->_array_to_object($this->_xml_to_array($xml));
		
		return (array) $object;
	}
	
	// --------------------------------------------------------------------
	/**
	 * _parse_time function.
	 * Permet l'utilisation de certains mots clés pour les dates.
	 * Quelque chose d'humainement lisible du style 'today', 'yesterday'...etc
	 * On utilise simplement la fonction strtotime si on découvre un string
	 * 
	 * @access private
	 */
	function _parse_time($time)
	{
		if (! is_numeric($time)) //on suppose que le format est compatible strtotime
		{ 
			if ($time === 'today') return date('Y-m-d');
			else return date('Y-m-d', strtotime($time));
		}
		else return  date('Y-m-d', $time);	
	}
	
	// --------------------------------------------------------------------
	/**
	 * _ga_prefix function.
	 * Ajoute un préfix 'ga:'à la chaine si celui-ci n'est pas trouvé
	 * 
	 * @access private 
	 */
	function _ga_prefix($string)
	{
		if ($string[2] != ':')  return 'ga:'.$string;
		
		return $string;
	}
	
	// --------------------------------------------------------------------
	/**
	 * _values_converter function.
	 * traite un array ou un string (séparé par une virgule) pour ajouter
	 * le prefix ga: avant de l'assigner à l'objet.
	 * 
	 * @access private
	 */
	function _values_converter($arr_or_str)
	{
		if (is_string($arr_or_str) && strpos($arr_or_str, ','))
		{
			$arr_or_str = explode(',', $arr_or_str);
		}
		
		if (is_array($arr_or_str))
		{
			foreach ($arr_or_str as $key => $string)
			{
				$arr_or_str[$key] = $this->_ga_prefix(trim($string));
			}
			$output = implode(',', $arr_or_str);
		}
		
		else $output = $this->_ga_prefix(trim($arr_or_str));
		
		return $output;
	}
	
	// --------------------------------------------------------------------
	/**
	 * _array_to_object function.
	 * 
	 * @access private
	 * @param mixed $array
	 */
	function _array_to_object($array) 
	{
		foreach ($array as $key => $value) 
		{
			$object->$key = is_array($value) ? $this->_array_to_object($value) : $value;
		}
		return  $object;
	}
	
	// --------------------------------------------------------------------
	/**
	 * _xml_to_array function.
	 * La fonction permet de convertir les données du fichier XML en données
	 * de type php
	 * 
	 * @access private
	 * @param mixed $xml
	 */
	function _xml_to_array($xml)
	{
		if (! $xml) return false;
		
		$this->api_error = false;
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		
		//résumé
		if ($this->summary === true) 
		{
			$sum['accountName'] = $dom->getElementsByTagName('tableName')->item(0)->nodeValue;
			$sum['startDate'] 	= $dom->getElementsByTagName('startDate')->item(0)->nodeValue;
			$sum['endDate'] 	= $dom->getElementsByTagName('endDate')->item(0)->nodeValue;
			$sum['totalResults']= $dom->getElementsByTagName('totalResults')->item(0)->nodeValue;
			$sum['startIndex']  = $dom->getElementsByTagName('startIndex')->item(0)->nodeValue;
			$sum['itemsPerPage']= $dom->getElementsByTagName('itemsPerPage')->item(0)->nodeValue;
			if ($this->segment) $sum['segment']= $dom->getElementsByTagName('segment')->item(0)->getAttribute('name');
			
			$aggregates = $dom->getElementsByTagName('aggregates');
			
			foreach ($aggregates as $aggregate)
			{
				foreach($aggregate->getElementsByTagName('metric') as $metric) {
					$sum['metrics'][substr($metric->getAttribute('name'), 3)] = $metric->getAttribute('value');
				}
			}
			$data['summary'] = $sum;
		}
				
		$entries = $dom->getElementsByTagName('entry');

		foreach($entries as $entry) 
		{
			$index = array();
			foreach($entry->getElementsByTagName('dimension') as $mydimension) 
			{
				$index[] = $mydimension->getAttribute('value');
			}
			switch(count($index)) 
			{
				case 0:
					foreach($entry->getElementsByTagName('metric') as $metric) {
						$data[substr($metric->getAttribute('name'), 3)] = $metric->getAttribute('value');
					}
				break;
				
				case 1:
					foreach($entry->getElementsByTagName('metric') as $metric) {
						$data[$index[0]][substr($metric->getAttribute('name'), 3)] = $metric->getAttribute('value');
					}
				break;
			
				case 2:
					foreach($entry->getElementsByTagName('metric') as $metric) {
						$data[$index[0]][$index[1]][substr($metric->getAttribute('name'), 3)] = $metric->getAttribute('value');
					}
				break;
			
				case 3:
					foreach($entry->getElementsByTagName('metric') as $metric) {
						$data[$index[0]][$index[1]][$index[2]][substr($metric->getAttribute('name'), 3)] = $metric->getAttribute('value');
					}
				break;		
			}
		}
		return $data;
	}
	
	// --------------------------------------------------------------------
	/**
	 * _build_url function.
	 * La fonction contruit le string de l'url pour appeler l'api à 
	 * partie des paramêtres passés au modèle
	 * 
	 * @access private
	 */
	function _build_url()
	{
		if (! $this->start) $this->start = $this->_parse_time('1 month ago');
		if (! $this->stop) $this->stop = $this->_parse_time('yesterday');
		if (! $this->sort_by) $this->sort_by= '-'.$this->metric;
						
		$url  = "ids=ga:".$this->profile_id;
		if ($this->dimension) $url .= "&dimensions=".$this->dimension;
		if ($this->metric) $url .= "&metrics=".$this->metric;
		if ($this->segment) $url .= "&segment=gaid::".$this->segment;
		else if ($this->dsegment) $url .= "&segment=dynamic::".$this->dsegment;
		$url .= "&sort=".$this->sort_by;
		$url .= "&start-date=".$this->start;
		$url .= "&end-date=".$this->stop;
		$url .= "&start-index=".$this->start_index;
		if ($this->max_results) $url .= "&max-results=".$this->max_results;
		if ($this->filters) $url .= "&filters=".$this->filters;
		$url.'&prettyprint='.$this->prettyprint;
		
		$this->query_string = $url;
		return $this->data_home.$url;
	}

	//---------------------------------------------------------------------------------------------	
	/**
	 * Calls an API function using the url passed in and returns either the XML returned from the
	 * call or false on failure
	 *
	 * @param string $url 
	 * @return string or boolean false
	 */
	function call($url) {

		$headers = array(
			"Authorization: GoogleLogin auth=$this->auth",
			"GData-Version: 2"
		);

		$ch = $this->curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		// set return value to a default of false; it will be changed to the return string on success
		$return = false;
		
		if ($info['http_code'] == 200) $return = $output;
		
		else show_error('<b>Google Analytics Api Library:</b> '.$output, $info['http_code']);
				
		return $return;
	}

	//---------------------------------------------------------------------
	/**
	* Returns an instance from curl_init with all the commonly needed properties set.
	* 
	* @param $url string The $url to open
	*/
	protected function curl_init($url) {
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if($this->auth) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: GoogleLogin auth=$this->auth"));
		}

		// Check for safe mode, CURLOPT_FOLLOWLOCATION will throw an error if safe mode is active
		if( ini_get('safe_mode') != FALSE ) {

			// the following thanks to Kyle from www.e-strategy.net
			// i didn't need these settings myself on a Linux box but he seemed to need them on a Windows one
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    		}
		
		return $ch;
	}
	
	// --------------------------------------------------------------------
	/**
	 * _store_data function.
	 * Sauvegarde du XML récupéré (uniqment fichier pour le moment) 
	 * 
	 * @access public
	 */
	function _store_data()
	{
		if (file_exists($this->cache_folder) && is_dir($this->cache_folder))
		{	
			if ($this->cache_data) 
			{
				$file = fopen($this->cache_folder.md5($this->query_string).'.'.$this->cache_ext, 'w');
				fwrite($file, $this->data_file);
			}
			return true;
		}
		return false;
	}
	
	// --------------------------------------------------------------------
	/**
	 * clear function.
	 * Réinitialise les objets. Il est possible de spécifier des valeurs 
	 * à nettoyer
	 * 
	 * @access public
	 */
	function clear($objects = false)
	{	
		if (is_string($objects)) 
		{
			if (strpos($objects, ',')) $objects = explode(',', $objects);
			else $objects = array($objects);
		}
		
		if (is_array($objects))
		{
			foreach ($objects as $key)
			{
				$key = trim($key);
				
				if ($key == 'or_filter' || $key == 'and_filter') $this->filters = false;
				
				if ($key == 'or_dsegment' || $key == 'and_dsegment') $this->segment = false;

				else if ($key == 'when') { $this->start = ''; $this->stop = ''; }
				
				else if ($key == 'sort_by') $this->sort_by= '';
				
				else if ($key == 'limit') $this->max_results = 31;
				
				else if ($key == 'login') $this->clear_login_data();
								
				else if (isset($this->$key)) $this->$key = '';
			}
		}
		else 
		{
			$this->dimension	= ''; 
			$this->metric 		= '';
			$this->sort_by		= false;
			$this->start		= false; 
			$this->stop 		= false;
			$this->max_results  = 31;
			$this->start_index  = 1;
			$this->filters 		= false;
			$this->segdment		= false;
			$this->clear_login_data();			
		}
		return $this;
	}
	
	// --------------------------------------------------------------------
	/**
	 * clear_login_data function.
	 * Supprime les infos de connexion de la session
	 * 
	 * @access private
	 */
	function clear_login_data()
	{
		$this->ci =& get_instance();
		$this->ci->load->library('session');
		
		$this->ci->session->unset_userdata('ga_auth');
		$this->auth = false;
	}
	
	// --------------------------------------------------------------------
	/**
	 * clear_cache function.
	 * Supprime les fichiers du cache
	 * $params[0]: size ou date
	 * $params[1]: taille total en ko, date en timestamp ou compatible strtotime
	 *
	 * @access public
	 * @param array $params. (default: array())
	 */
	function clear_cache($params = array())
	{
		$this->ci->load->helper('file');
				
		$cache_files = get_dir_file_info($this->cache_folder);
		
		if (! $cache_files) return false;

		if (! $params) $params = array('date', 9999999999999);
						
		if ($params[0] == 'size') 
		{
			$total_size = 0;
			foreach ($cache_files as $key => $value) 
			{
				$total_size = $total_size + $value['size'];
			}
			if ($total_size > $params[1]) $params[1] = 9999999999999;
		}
		
		else if ($params[0] == 'size' || $params[0] == 'date')
		{
			if (! is_numeric($params[1])) $params[1] = strtotime($params[1]);
			foreach ($cache_files as $key => $value) 
			{
				if ($value['date'] < $params[1]) unlink($value['relative_path'].$key);
			}
		}
		
		else return false;
		
		return true;
	}
}
