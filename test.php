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

/*
 * Retrieves all images where the user is facetagged
 */
$user = $twitpic->faces->show(array('user'=>'meltingice'));

print_r($user);
?>