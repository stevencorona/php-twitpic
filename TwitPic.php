<?
include 'includes/config.php';
include 'includes/API.php';
include 'includes/TwitPicAPIException.php';

class TwitPic {
	private $api_key, $api;
	private $mode = MODE_READONLY;
	
	public function __construct($api_key = "") {
		
		if(is_string($api_key) && strlen($api_key) > 0) {
			$this->api_key == $api_key;
			$this->mode = MODE_READWRITE;
		}
		
		$this->api = new API();
	}
	
	/*
	 * Throw the request over to the API class to handle
	 */
	public function __get($key) {
		return $this->api->{$key};
	}
}