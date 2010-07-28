<?
include('TwitPic.php');

$twitpic = new TwitPic();
print_r($twitpic->media->show(array('id'=>'291w7u')));
print_r($twitpic->users->show(array('username'=>'meltingice')));
print_r($twitpic->tags->show(array('tag'=>'test')));
?>