<?
class API {
	private $api, $api_key, $options;
	private $category = null;
	private $method = null;
	
	public function __construct($api_key) {
		$this->api_key = $api_key;
		
		$xmlApi = simplexml_load_file('api/api.xml');
		foreach ($xmlApi->category as $category) {
			$catName = (string)$category['name'];
			$this->api[$catName] = array();
			foreach ($category->endpoint as $endpoint) {
				$this->api[$catName][(string)$endpoint['name']] = $endpoint;
			}
		}
	}
	
	/*
	 * Executes an API call only after the category and method
	 * have been validated.
	 */
	private function execute($args) {
		if(count($args) >= 1) {
			$method_args = array_shift($args);
		} else {
			$method_args = array();
		}
		
		$this->options = $args[0];
		if($this->api[$this->category][$this->method]->method == 'POST') {
			return $this->executePOST($method_args);
		} else { // assume GET?
			return $this->executeGET($method_args);	
		}
	}
	
	/*
	 * Performs a GET API method. This simply uses file_get_contents() to
	 * perform the API call.
	 */
	private function executeGET($method_args) {
		$this->validate_args($method_args);
		$this->check_for_authentication();

		$this->format = $this->get_format();
		
		$url = "http://api.twitpic.com/2/{$this->category}/{$this->method}.{$this->format}";
		$args = $this->get_url_args($method_args);
		$url .= "?$args";
		
		$data = file_get_contents($url);
		
		return $this->respond($data);
	}
	
	/*
	 * Performs a POST API method
	 */
	private function executePOST($method_args, $args) {
		$this->validate_args($method_args);
		$this->check_for_authentication();
	}
	
	/*
	 * Validates all of the arguments given to the API
	 * call and makes sure that required args are present.
	 */
	private function validate_args($args) {
		$api_call = $this->api_call();
		foreach($api_call->param as $param) {
			$attrs = $param->attributes();
			if(isset($attrs->required)) {
				if($attrs->required == 'true' && !array_key_exists((string)$attrs->name, $args)) {
					throw new TwitPicAPIException("Missing required parameter '{$attrs->name}' for {$this->category}/{$this->method}");
				}
			}
		}
	}
	
	/*
	 * Checks to see if this API call requires authentication,
	 * and if we have all the required info.
	 * ### UNFINISHED ###
	 */
	private function check_for_authentication() {
		/* if auth_required is not set, assume false */
		if(!isset($this->api_call()->attributes()->auth_required)){ return; }
		
		if($this->api_call()->attributes()->auth_required == 'true' && TwitPic::mode() == MODE_READONLY){
			throw new TwitPicAPIException("API call {$this->category}/{$this->method} requires an API key");
		}
	}
	
	/*
	 * Gets the response format, defaults to json
	 * if none is given.
	 */
	private function get_format() {
		if(!isset($this->api_call()->formats)) {
			$allowed = array('xml','json');
		} else {
			$allowed = explode(',', $this->api_call()->formats);
			foreach($allowed as $i=>$var){
				$allowed[$i] = trim($var);
			}
		}
		
		if(isset($this->options['format'])) {
			if(!in_array($this->options['format'], $allowed)) {
				throw new TwitPicAPIException("Invalid response format requested for {$this->category}/{$this->method}");
			}
			
			return $this->options['format'];
		}
		
		if(in_array('json', $allowed)) {
			return 'json';
		}
		
		return $allowed[0];
	}
	
	/*
	 * Builds the query part of the URL and
	 * escapes any data necessary.
	 */
	private function get_url_args($args) {
		$pairs = array();
		foreach($args as $i=>$val) {
			$pairs[] = $i ."=". urlencode($val);
		}
		
		return implode("&", $pairs);
	}
	
	/*
	 * Processes the API response and converts it to
	 * an object if requested (default). The response
	 * can be forced to be returned in its raw format using
	 * the 'process' argument.
	 */
	private function respond($data) {
		if((isset($this->options['process']) && $this->options['process'] == true) || !isset($this->options['process'])) {
			if($this->format == 'json') {
				$data = json_decode($data);
			} elseif($this->format == 'xml') {
				$data = simplexml_load_string($data);
			}
		}
		
		$this->reset_settings();
		
		return $data;
	}
	
	/*
	 * Resets the variables in this class to avoid
	 * any conflicts if multiple API calls are made.
	 */
	 private function reset_settings() {
	 	$this->category = null;
	 	$this->method = null;
	 	$this->format = null;
	 	$this->options = null;
	 }
	
	/*
	 * Gets the current API call
	 */
	private function api_call() {
		return $this->api[$this->category][$this->method];
	}
	
	/*
	 * Checks to make sure the API category in use
	 * is valid and returns $this.
	 */
	private function api_category($category) {
		if(!isset($this->api[$category])) {
			throw new TwitPicAPIException('API category not found');
		}
		
		$this->category = $category;
		
		return $this;
	}
	
	/*
	 * Checks to make sure the API method for this category
	 * is defined and begins the execution of the API call.
	 */
	private function api_method($method, $args) {
		if(is_null($this->category)) {
			throw new TwitPicAPIException('WTF is goin on yo?');
		} elseif(!isset($this->api[$this->category][$method])) {
			throw new TwitPicAPIException("API method not found for category {$this->category}");
		}
		
		$this->method = $method;
		
		return $this->execute($args);
	}
	
	/*
	 * This function handles the API category that is
	 * being called and if valid, returns $this so that
	 * the __call() function can be called next.
	 */
	public function __get($key) {
		return $this->api_category($key);
	}
	
	/*
	 * The __call() function handles accessing the API
	 * method.
	 */
	public function __call($method, $args) {
		return $this->api_method($method, $args);
	}
	
}