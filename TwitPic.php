<?
include 'includes/config.php';
include 'includes/API.php';
include 'includes/TwitPicAPIException.php';

require_once 'HTTP/Request2.php';
require_once 'HTTP/OAuth/Consumer.php';

class TwitPic {
	private $api, $api_key;
	private static $mode = TwitPic_Config::MODE_READONLY;
	
	public function __construct($api_key = "") {
		
		if(is_string($api_key) && strlen($api_key) > 0) {
			$this->$api_key == $api_key;
			self::$mode = TwitPic_Config::MODE_READWRITE;
		}
		
		$this->api = new API($this->api_key);
	}
	
	public static function mode() {
		return self::$mode;
	}
	
	/*
	 * Throw the request over to the API class to handle
	 */
	public function __get($key) {
		return $this->api->{$key};
	}
}