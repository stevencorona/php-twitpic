<?
include('TwitPic.php');

/*
 * All of this info is needed only for API calls that
 * require authentication. However, if the API call doesn't
 * require authentication and you provide the information anyways,
 * it won't make a difference.
 */
$api_key = ""; // your TwitPic API key (http://dev.twitpic.com/apps)
$consumer_key = ""; // your Twitter application consumer key
$consumer_secret = ""; // your Twitter application consumer secret
$oauth_token = ""; // the user's OAuth token (retrieved after they log into Twitter and auth there)
$oauth_secret = ""; // the user's OAuth secret (also from Twitter login)

/*
 * The TwitPic() constructor can be left blank if only
 * doing non-auth'd API calls
 */
$twitpic = new TwitPic($api_key, $consumer_key, $consumer_secret, $oauth_token, $oauth_secret);

try {

	/*
	 * Retrieves all images where the user is facetagged
	 */
	$user = $twitpic->faces->show(array('user'=>'meltingice'));
	print_r($user->images);
	
	$media = $twitpic->media->show(array('id'=>1234));
	echo $media->message;
	
	$user = $twitpic->users->show(array('username'=>'meltingice'), array('process'=>false, 'format'=>'xml'));
	echo $user; // raw XML response data
	
	/*
	 * Uploads an image to TwitPic
	 */
	 $resp = $twitpic->upload(array('media'=>'path/to/file.jpg', 'message'=>'This is an example'));
	 print_r($resp);
	 
	 /*
	  * Uploads an image to TwitPic AND posts a tweet
	  * to Twitter.
	  *
	  * NOTE: this still uses v2 of the TwitPic API. This means that the code makes 2 separate
	  * requests: one to TwitPic for the image, and one to Twitter for the tweet. Because of this,
	  * understand this call may take a bit longer than simply uploading the image.
	  */
	  $resp = $twitpic->uploadAndPost(array('media'=>'path/to/file.jpg', 'message'=>'Another example'));
	  print_r($resp);
	
} catch (TwitPicAPIException $e) {

	echo $e->getMessage();
	
}
?>