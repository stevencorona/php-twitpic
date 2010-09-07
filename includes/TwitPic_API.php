<?php
/*
 * TwitPic API for PHP
 * Copyright 2010 Ryan LeFevre - @meltingice
 * PHP version 5.3.0+
 *
 * Licensed under the New BSD License, more info in LICENSE file
 * included with this software.
 *
 * Source code is hosted at http://github.com/meltingice/TwitPic-API-for-PHP
 */
 
class TwitPic_API {
	private $api, $options;
	private $category = null;
	private $method = null;
	private $format = null;
	
	public function __construct() {		
		$xmlApi = simplexml_load_file(dirname(__FILE__) .'/../api/api.xml');
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
		if($this->api_call()->attributes()->method == 'POST') {
			return $this->executePOST($method_args);
		} else { // assume GET?
			return $this->executeGET($method_args);	
		}
	}
	
	private function upload_photo($args) {
		if(count($args) >= 1) {
			$method_args = array_shift($args);
		} else {
			$method_args = array();
		}
		
		$this->options = $args[0];
		$this->category = 'upload';
		
		if(TwitPic::mode() == TwitPic_Config::MODE_READONLY) {
			throw new TwitPicAPIException("Uploading a photo requires an API key and Twitter credentials");
		}
		
		if(!isset($method_args['message']) || !isset($method_args['media'])) {
			throw new TwitPicAPIException("Missing required parameter for photo upload");
		}
		
		if(!is_file($method_args['media']) || !is_readable($method_args['media'])) {
			throw new TwitPicAPIException("Unable to find or read file");
		}
		
		$this->format = $this->get_format();
		
		$header = $this->build_header();
		$url = "http://api.twitpic.com/2/upload.{$this->format}";
		$r = new Http_Request2($url, Http_Request2::METHOD_POST);
		$r->setHeader('X-Verify-Credentials-Authorization', $header);
		$r->addPostParameter('key', TwitPic_Config::getAPIKey());
		$r->addPostParameter('message', $method_args['message']);
		$r->addUpload('media', $method_args['media']);
		
		$res = $r->send();
		
		if($res->getStatus() == 200) {
			if($this->options['tweet']) {
				return $this->post_to_twitter($res->getBody());
			} else {
				return $this->respond($res->getBody());
			}
			
		} else {
			throw new TwitPicAPIException($res->getBody());
		}
	}
	
	/*
	 * Performs a GET API method.
	 */
	private function executeGET($method_args) {
		$this->validate_args($method_args);

		$this->format = $this->get_format();
		
		$url = $this->get_request_url();
		$args = $this->get_url_args($method_args);
		$url .= "?$args";
		
		$r = new Http_Request2($url, Http_Request2::METHOD_GET);
		$res = $r->send();
		
		if($res->getStatus() == 200) {
			return $this->respond($res->getBody());
		} else {
			throw new TwitPicAPIException($res->getBody());
		}
	}
	
	/*
	 * Performs a POST API method
	 */
	private function executePOST($method_args) {
		$this->validate_args($method_args);
		$this->check_for_authentication();
		$header = $this->build_header();
		
		$url = $this->get_request_url();
		$r = new Http_Request2($url, Http_Request2::METHOD_POST);
		$r->setHeader('X-Verify-Credentials-Authorization', $header);
		$r->addPostParameter('key', TwitPic_Config::getAPIKey());
		foreach($method_args as $arg=>$val) {
			$r->addPostParameter($arg, $val);
		}
		
		$res = $r->send();
		if($res->getStatus() == 200) {
			if(strlen($res->getBody() > 0)) {
				return $this->respond($res->getBody());
			} else {
				return true;
			}
		} else {
			throw new TwitPicAPIException($res->getBody());
		}
	}
	
	/*
	 * Validates all of the arguments given to the API
	 * call and makes sure that required args are present.
	 */
	private function validate_args($args) {
		$api_call = $this->api_call();
		foreach($api_call->param as $param) {
			$attrs = $param->attributes();
			
			if(array_key_exists((string)$attrs->name, $args)) {
				if(!$this->validate_arg((string)$attrs->type, $args[(string)$attrs->name])) {
					throw new TwitPicAPIException("Invalid datatype for {$attrs->name} while calling {$this->category}/{$this->method}");
				}
			}
			
			if(isset($attrs->required)) {
				if($attrs->required == 'true' && !array_key_exists((string)$attrs->name, $args)) {
					throw new TwitPicAPIException("Missing required parameter '{$attrs->name}' for {$this->category}/{$this->method}");
				}
			}
		}
	}
	
	/*
	 * Validates the datatype of all arguments based on
	 * the type defined in the API. Returns true if value
	 * is valid, false otherwise.
	 */
	private function validate_arg($type, $value) {
		switch($type) {
			case 'integer':
				return is_integer($value);
			case 'string':
				return is_string($value);
			case 'short_id':
				return (bool) !preg_match('/([^A-Za-z0-9]+)/', $value);
			case 'username':
				if(mb_strlen($value, 'UTF-8') > 15 || mb_strlen($value, 'UTF-8') == 0) {
					return false;
				}
				
				return (bool) !preg_match('/([^A-Za-z0-9_]+)/', $value);
			case 'hashtag':
				return (bool) !preg_match('/([^A-Za-z0-9_])+/', $value);
		}
	}
	
	/*
	 * Checks to see if this API call requires authentication,
	 * and if we have all the required info.
	 */
	private function check_for_authentication() {
		/* if auth_required is not set, assume false */
		if(!isset($this->api_call()->attributes()->auth_required)){ return; }
		
		if($this->api_call()->attributes()->auth_required == 'true' && TwitPic::mode() == TwitPic_Config::MODE_READONLY){
			throw new TwitPicAPIException("API call {$this->category}/{$this->method} requires an API key and OAuth credentials");
		}
	}
	
	/*
	 * When making an authenticated API call, we need
	 * to build the header that is sent with the authorization
	 * information.
	 */
	private function build_header($tweet=false) {
		$consumer = TwitPic_Config::getConsumer();
		$oauth = TwitPic_Config::getOAuth();
		
		$signature = HTTP_OAuth_Signature::factory('HMAC_SHA1');
		$timestamp = gmdate('U');
		$nonce = uniqid();
		$version = '1.0';
		
		if(is_string($tweet)) {
			$params = array( 'oauth_consumer_key' => $consumer['key'] ,
				'oauth_signature_method' => 'HMAC-SHA1' ,
				'oauth_token' =>  $oauth['token'],
				'oauth_timestamp' => $timestamp ,
				'oauth_nonce' => $nonce ,
				'oauth_version' => $version,
				'status' => $tweet);
	
			$sig_text = $signature->build( 'POST',"http://api.twitter.com/1/statuses/update.{$this->format}", $params, $consumer['secret'], $oauth['secret'] );
	
			$params['oauth_signature'] = $sig_text;
		} else {
			$params = array( 'oauth_consumer_key' => $consumer['key'] ,
				'oauth_signature_method' => 'HMAC-SHA1' ,
				'oauth_token' =>  $oauth['token'],
				'oauth_timestamp' => $timestamp ,
				'oauth_nonce' => $nonce ,
				'oauth_version' => $version );
	
			$sig_text = $signature->build( 'GET','https://api.twitter.com/1/account/verify_credentials.json', $params, $consumer['secret'], $oauth['secret'] );
	
			$params['oauth_signature'] = $sig_text;
		}
		

		$realm = 'http://api.twitter.com/';
		$header = 'OAuth realm="' . $realm . '"';
		foreach ($params as $name => $value) {
			$header .= ", " . HTTP_OAuth::urlencode($name) . '="' . HTTP_OAuth::urlencode($value) . '"';
		}

		return $header;
	}
	
	/*
	 * Builds the TwitPic API request URL
	 */
	private function get_request_url() {
		$this->format = $this->get_format();
		return "http://api.twitpic.com/2/{$this->category}/{$this->method}.{$this->format}";
	}
	
	/*
	 * Gets the response format, defaults to json
	 * if none is given.
	 */
	private function get_format() {
		if(!isset($this->api_call()->formats) || $this->category == 'upload') {
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
		$data = htmlspecialchars_decode($data, ENT_QUOTES);
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
	
	private function post_to_twitter($resp_data) {
		if($this->format == 'json') {
			$data = json_decode($resp_data);
		} elseif($this->format == 'xml') {
			$data = simplexml_load_string($resp_data);
		}
		
		$tweet = $this->truncate((string)$data->text) . ' ' . (string)$data->url;
		
		$header = $this->build_header($tweet);
		$url = "http://api.twitter.com/1/statuses/update.{$this->format}";
		
		$r = new Http_Request2($url, Http_Request2::METHOD_POST);
		$r->setHeader('Authorization', $header);
		$r->addPostParameter('status', $tweet);
		$res = $r->send();
		
		if($res->getStatus() == 200) {
			return $this->respond($resp_data);
		} else {
			throw new TwitPicAPIException("Image uploaded, but error posting to Twitter");
		}
	}
	
	private function truncate($msg) {
		return mb_substr($msg, 0, 114, 'UTF-8');
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
		return isset($this->api[$this->category][$this->method]) ? $this->api[$this->category][$this->method] : null;
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
		if($method == 'upload') { // need to make an exception for upload
			return $this->upload_photo($args);
		} elseif(is_null($this->category)) {
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
