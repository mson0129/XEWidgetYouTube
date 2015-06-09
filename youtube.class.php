<?php
//Copyright (c) 2015 Studio2b
//YouTubeWidget
//Youtube
//Studio2b(www.studio2b.kr)
//Michael Son(mson0129@gmail.com)
class youtube extends WidgetHandler {
	function proc($args) {
		//xFacility2014 - including the part of frameworks
		require_once($this->widget_path."XFCurl.class.php");
		require_once($this->widget_path."XFYoutube.class.php");
		require_once($this->widget_path."XFYoutubeActivities.class.php");
		require_once($this->widget_path."XFYoutubeChannels.class.php");
		require_once($this->widget_path."XFYoutubePlaylistItems.class.php");
		require_once($this->widget_path."XFYoutubePlaylists.class.php");
		require_once($this->widget_path."XFYoutubeVideos.class.php");
		
		//Get settings
		$widgetInfo = new stdClass();

		if(isset($args->api_key)) {
			if(isset($args->mid_id)) {
				$obj->module_srl = $args->mid_id;
				$result = executeQueryArray('widgets.youtube.getModule', $obj);
				if(!is_null($result->data[0]->mid))
					$mid = $result->data[0]->mid;
				unset($result);
				$result = executeQueryArray('widgets.youtube.getModuleValues', $obj);
				foreach($result->data as $val) {
					if($val->name=="api_key")
						$apiKey = $val->value;
					else if($val->name=="playlist_id")
						$playlistId = $val->value;
					else if($val->name=="list_count")
						$items = $val->value;
				}
			}
			
			if(!is_null($args->api_key))
				$apiKey = $args->api_key;
			if(!is_null($args->playlist_id))
				$playlistId = $args->playlist_id;
			if(!is_null($args->items) && is_numeric($args->items))
				$items = $args->items;
			if(!is_null($mid)) {
				$urlBase = getNotEncodedUrl("", "mid", $mid)."&video_id=";
				Context::set("outlink", false);
			} else {
				$urlBase = "//www.youtube.com/watch?v=";
			}
			
			$youtube = New XFYoutube(NULL, trim($apiKey));
			
			//Playlist Default Value Setting
			if(!isset($playlistId))
				$playlistId = "PLmtapKaZsgZt3g_uAPJbsMWdkVsznn_2R"; //실시간 인기 동영상 - 한국
			
			//Get videos
			$videos = $youtube->getPlaylistItems($playlistId, is_numeric($items) ? $items : 20);
			//var_dump($videos);
			unset($videos[totalPages], $videos[totalVideos]);
			
			foreach($videos as $key=>$val) {
				$videos[$key][url] = $urlBase.$val[snippet][resourceId][videoId];
			}
			Context::set("videos", $videos);
		} else
			Context::set("error", "API Key");
		
		$tplPath = sprintf("%sskins/%s/", $this->widget_path, (!is_null($args->skin) &&  $args->skin!="" && is_dir(sprintf("%sskins/%s/", $this->widget_path, $args->skin))) ? $args->skin : "default");
		$tplFile = "browse";
		Context::set("colorset", $args->colorset);
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tplPath, $tplFile);
	}
}
?>