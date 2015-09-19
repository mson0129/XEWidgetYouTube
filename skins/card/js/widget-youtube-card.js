function YoutubePlayer(videoId, playlistId) {
	this.url = "https://www.youtube.com/embed/" + videoId + "?list=" + playlistId;
	this.player = null;
	this.open = function() {
		if(!this.isOpened()) {
			this.player = window.open(this.url, "youtubePlayer", "width=640,height=360,toolbar=0,directories=0,status=0,menubar=0,scrollbars=0,resizable=0");
			if(!this.player.opener) this.player.opener = self;
		}
	}
	this.isOpened = function() {
		if(!this.player || this.player.closed)
			return false;
	    return true;
	}
}

function openYoutubePlayer(videoId, playlistId) {
	var youtubePlayer = new YoutubePlayer(videoId, playlistId);
	youtubePlayer.open();
}