<?php
//Copyright (c) 2014 Studio2b
//xFacility2014
//xFYoutube
//Studio2b(www.studio2b.kr)
//Michael Son(mson0129@gmail.com)
//02DEC2014(1.0.0.) - Newly added.
//07JUN2015(1.1.0.) - It's ported for XpressEngine. And getPlaylistItems is added.
//08JUN2015(1.2.0.) - getPage is added.
//09JUN2015(1.2.1.) - API Costs(https://developers.google.com/youtube/v3/determine_quota_cost) are optimized.
//08JUL2015(1.3.0.) - All functions for XpressEngine are moved to a XEWidget class.
class XFYoutube {
	var $token;
	var $activities, $channels, $playlists, $playlistItmes;
	
	function XFYoutube($token=null, $apiKey) {
		$this->activities = new XFYoutubeActivities($token, $apiKey);
		$this->channels = new XFYoutubeChannels($token, $apiKey);
		$this->playlists = new XFYoutubePlaylists($token, $apiKey);
		$this->playlistItems = new XFYoutubePlaylistItems($token, $apiKey);
		$this->videos = new XFYoutubeVideos($token, $apiKey);
	}
}
?>