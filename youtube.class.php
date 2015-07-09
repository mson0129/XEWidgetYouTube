<?php
//Copyright (c) 2015 Studio2b
//YouTubeWidget
//Youtube
//Studio2b(www.studio2b.kr)
//Michael Son(mson0129@gmail.com)
//10JUN2015(1.0.0.) - This file is newly created.
//08JUL2015(1.1.0.) - The way to read module infomation is changed From XE Query -> XEModuleModule->getModuleInfoByModuleSrl();. And Some functions written for XpressEngine are move from XFYoutube.class to here.
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
		
		if(isset($args->mid_id)) {
			$oModuleModel = getModel("module");
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->mid_id);
			if($module_info->module=="youtube") {
				$apiKey = $module_info->api_key;
				if(is_array(json_decode($module_info->playlist_id, true))) {
					$temp = json_decode($module_info->playlist_id, true);
					$playlistId = $temp[0];
				} else {
					$playlistId = $module_info->playlist_id;
				}
				$items = $module_info->list_count;
			}
		}
		
		if(!is_null($args->api_key) && trim($args->api_key)=="")
			$apiKey = $args->api_key;
		if(!is_null($args->playlist_id) && trim($args->playlist_id)=="")
			$playlistId = $args->playlist_id;
		if(!is_null($args->items) && is_numeric($args->items))
			$items = $args->items;
		if(!is_null($mid)) {
			$urlBase = getNotEncodedUrl("", "mid", $mid)."&video_id=";
			Context::set("outlink", false);
		} else {
			$urlBase = "//www.youtube.com/watch?v=";
		}
		
		$playlistId = $this->getRealPlaylistId($apiKey, $playlistId);
		
		//Legacy
		$youtube = New XFYoutube(NULL, trim($apiKey));
		
		//Get videos
		$videos = $this->getPlaylistItems($apiKey, $playlistId, is_numeric($items) ? $items : 20);
		unset($videos[totalPages], $videos[totalVideos]);
		
		foreach($videos as $key=>$val) {
			$videos[$key][url] = $urlBase.$val[snippet][resourceId][videoId];
		}
		Context::set("videos", $videos);
		
		$tplPath = sprintf("%sskins/%s/", $this->widget_path, (!is_null($args->skin) &&  $args->skin!="" && is_dir(sprintf("%sskins/%s/", $this->widget_path, $args->skin))) ? $args->skin : "default");
		$tplFile = "browse";
		Context::set("colorset", $args->colorset);
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tplPath, $tplFile);
	}
	
	//XEModuleYouTubeModel
	protected function getRealPlaylistId($apiKey, $playlistId) {
		$youtube = new XFYoutube(null, $apiKey);
		if(!is_null($apiKey)) {
			$return = empty($playlistId) ? "PLmtapKaZsgZt3g_uAPJbsMWdkVsznn_2R" : $playlistId;
			$result = $youtube->playlistItems->browse("id", null, $return, 0);
			if($result===false) {
				$channel = $youtube->channels->browse("contentDetails", null, $return);
				if($channel===false || is_null($channel[items][0][contentDetails][relatedPlaylists][uploads])) {
					$channel = $youtube->channels->browse("contentDetails", null, null, $return);
					if($channel===false || is_null($channel[items][0][contentDetails][relatedPlaylists][uploads])) {
						$return = false;
						break;
					} else {
						$return = $channel[items][0][contentDetails][relatedPlaylists][uploads];
					}
				} else {
					$return = $channel[items][0][contentDetails][relatedPlaylists][uploads];
				}
			}
			unset($result, $channel);
		} else {
			$return = false; //실시간 인기 동영상 - 한국
		}
		return $return;
	}
	
	protected function getPlaylistItems($apiKey, $playlistId, $items=20, $page=1, $reverse=false) {
		$youtube = new XFYoutube(null, $apiKey);
		
		//Default values
		if(!is_numeric($items))
			$items = 20;
		if(!is_numeric($page))
			$page = 1;
		
		if($reverse==false) {
			$loop = ceil($items*$page/50);
			for($i=0;$i<$loop;$i++) {
				if($i==$loop-1) {
					 if($items*$page-($loop-1)*50<$items) {
					 	$pageItems[$i] = $items;
					 	if($i>0) {
					 		$pageItems[$i-1] -= $items-($items*$page-($loop-1)*50);
					 	}
					 } else {
					 	$pageItems[$i] = $items*$page-($loop-1)*50;
					 }
				} else {
					$pageItems[$i] = 50;
				}
			}
		}
		
		if(!is_null($playlistId)) {
			for($i=0; $i<$loop; $i++) {
				//$nowItems = $i==$loop-1 ? $lastPageItems : 50;
				$result = $youtube->playlistItems->browse("snippet", NULL, $playlistId, $pageItems[$i], $result[nextPageToken]);
				//var_dump($result);
				if($result===false) {
					//If it is not a playlist ID, it may be a channel name.
					$channel = $youtube->channels->browse("contentDetails", NULL, $playlistId);
					$playlistId = $channel[items][0][contentDetails][relatedPlaylists][uploads];
					$result = $youtube->playlistItems->browse("snippet", NULL, $playlistId, $pageItems[$i], $result[nextPageToken]);
					if($result===false) {
						$return = false;
						break;
					}
				}
			}
			
			for($j=0; $j<min($items, count($result[items])); $j++) {
				$return[] = $result[items][$pageItems[$loop-1]-$items+$j];
			}
			$return[totalPages] = ceil($result[pageInfo][totalResults]/$items);
			$return[totalVideos] = $result[pageInfo][totalResults];
		} else {
			$return = false;
		}
		
		return $return;
	}
}
?>