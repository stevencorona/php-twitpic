<?
class API {
	private $api;
	private $category = null;
	
	public function __construct() {
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
		$method_args = array_shift($args);
		if($this->api[$this->category][$this->method]->method == 'POST') {
			return $this->executePOST($method_args, $args);
		} else { // assume GET?
			return $this->executeGET($method_args, $args);	
		}
	}
	
	/*
	 * Performs a GET API method
	 */
	private function executeGET($method_args, $args) {
		$this->validate_args($method_args);
	}
	
	/*
	 * Performs a POST API method
	 */
	private function executePOST($method_args, $args) {
		$this->validate_args($method_args);
	}
	
	private function validate_args($args) {
		$api_call = $this->api[$this->category][$this->method];
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