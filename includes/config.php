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
 
class TwitPic_Config {
	# read-only or read-write authentication
	const MODE_READONLY = 1;
	const MODE_READWRITE = 2;
	
	private static $api_key, $oauth_token, $oauth_secret, $consumer_key, $consumer_secret;
	
	public static function mode() {
		if( 
			strlen(trim(self::$api_key)) > 0 && 
			strlen(trim(self::$oauth_token)) > 0 && 
			strlen(trim(self::$oauth_secret)) > 0 &&
			strlen(trim(self::$consumer_key)) > 0 &&
			strlen(trim(self::$consumer_secret)) > 0
		) {
			return self::MODE_READWRITE;
		} else {
			return self::MODE_READONLY;
		}
	}
	
	public static function setAPIKey($key) {
		self::$api_key = $key;
	}
	
	public static function getAPIKey() {
		return trim(self::$api_key);
	}
	
	public static function setOAuth($token, $secret) {
		self::$oauth_token = $token;
		self::$oauth_secret = $secret;
	}
	
	public static function getOAuth() {
		return array('token'=>trim(self::$oauth_token), 'secret'=>trim(self::$oauth_secret));
	}
	
	public static function setConsumer($key, $secret) {
		self::$consumer_key = $key;
		self::$consumer_secret = $secret;
	}
	
	public static function getConsumer() {
		return array('key'=>self::$consumer_key, 'secret'=>self::$consumer_secret);
	}
}
