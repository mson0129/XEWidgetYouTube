<?php
//Copyright (c) 2015 Studio2b
//YouTubeWidget
//Youtube
//Studio2b(www.studio2b.kr)
//Michael Son(mson0129@gmail.com)
//10JUN2015(1.0.0.) - This file is newly created.
//08JUL2015(1.1.0.) - The way to read module infomation is changed From XE Query -> XEModuleModule->getModuleInfoByModuleSrl();. And Some functions written for XpressEngine are move from XFYoutube.class to here.
//09JUL2015(1.1.1.) - XFCurl collision with Instagram Widget is fixed.
//10JUL2015(1.2.0.) - Cache function and Multi Channel/PlaylistID supporting are updated.
class youtube extends WidgetHandler {
	var $apiKey, $playlistIds;
	var $startTime, $timer=60; //MakeThisStop
	
	//View(Main)
	function proc($args) {
		$this->startTime = microtime(true);
		
		//xFacility2014 - including the part of frameworks
		if(class_exists("XFCurl")===false)
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
				$this->apiKey = trim($module_info->api_key);
				if(is_array(json_decode($module_info->playlist_id, true))) {
					$playlistIds = json_decode($module_info->playlist_id, true);
				} else {
					$playlistIds[0] = $module_info->playlist_id;
				}
				$list_count = $module_info->list_count;
			}
		}
		
		if(!empty($args->api_key))
			$this->apiKey = trim($args->api_key);
		if(!empty($args->playlist_id)) {
			unset($playlistIds);
			$playlistIds[0] = $args->playlist_id;
		}
		if(!is_null($args->items) && is_numeric($args->items))
			$list_count = $args->items;
		if(!empty($module_info) && $module_info->module=="youtube") {
			$urlBase = getNotEncodedUrl("", "mid", $module_info->mid, "video_id", "temp");
			Context::set("outlink", false);
		} else {
			$urlBase = "//www.youtube.com/watch?v=";
		}
		//updateCache
		foreach($playlistIds as $key=>$val) {
			$playlistIds[$key] = $this->getRealPlaylistId($this->apiKey, $val);
			if($_SERVER['HTTP_X_REQUESTED_WITH']!="XMLHttpRequest") {
				$updater = $this->updateCache($apiKey, $playlistIds[$key], $cacheTime);
			} else {
				break;
			}
		}
		
		if($args->shuffle=="Y") {
			$temp = $this->getShuffledCaches($playlistIds, $list_count);
		} else {
			$temp = $this->getCaches($playlistIds, $list_count);
		}
		foreach($temp as $key=>$val) {
			$videos[] = json_decode($val->item, true);
		}
		foreach($videos as $key=>$val) {
			$videos[$key][url] = substr($urlBase, 0, -4).$val[snippet][resourceId][videoId];
			if(is_array($playlistIds))
				$videos[$key][url] .= "&category=".array_search($val[snippet][playlistId], $playlistIds);
		}
		Context::set("playlistIds", $playlistIds);
		Context::set("videos", $videos);
		$tplPath = sprintf("%sskins/%s/", $this->widget_path, (!is_null($args->skin) &&  $args->skin!="" && is_dir(sprintf("%sskins/%s/", $this->widget_path, $args->skin))) ? $args->skin : "default");
		$tplFile = "browse";
		Context::set("colorset", $args->colorset);
		Context::set("popup", $args->popup);
		$oTemplate = &TemplateHandler::getInstance();
		//var_dump(sprintf("%.9fs", microtime(true)-$this->startTime)); //ProcessingTime
		return $oTemplate->compile($tplPath, $tplFile);
	}
	
	//Model
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
	
	protected function getCacheTime($id, $items=null, $page=null) {
		$args = new stdClass();
		$args->id = $id;
		if(is_numeric($items) && is_numeric($page)) {
			$args->start = $items*($page-1);
			$args->end = $items*$page;
			$args->items = $items;
			$result = executeQuery("widgets.youtube.browseSomeCacheTime", $args);
		} else {
			$result = executeQuery("widgets.youtube.browseCacheTime", $args);
		}
		return ($result->toBool()===false) ? false : $result->data->timestamp;
	}
	
	protected function setCache($playlistId, $no, $item) {
		$args = new stdClass();
		$args->id = $playlistId;
		$args->no = $no;
		$result = executeQuery("widgets.youtube.peruseCache", $args);
	
		$args->title = $item[snippet][title];
		$args->description = $item[snippet][description];
		$args->channel = $item[snippet][channelTitle];
		$args->utc = $item[snippet][utc]; //utc + 32400 = utc + 9h * 60m * 60s = kst
		$args->item = json_encode($item);
		$args->timestamp = time();
		if(empty($result->data)) {
			$return = executeQuery("widgets.youtube.insertCache", $args);
		} else {
			$return = executeQuery("widgets.youtube.updateCache", $args);
		}
	
		return true;
	}
	
	protected function setPlaylistInfo($playlistId, $totalVideos) {
		if(!is_null($id)) {
			$args = new stdClass();
			$args->id = $playlistId;
			$result = executeQuery("widgets.youtube.perusePlaylistInfo", $args);
				
			$args->totalVideos = $totalVideos;
			$args->timestamp = time();
			if(empty($result->data)) {
				$return = executeQuery("widgets.youtube.insertPlaylistInfo", $args);
			} else {
				$return = executeQuery("widgets.youtube.updatePlaylistInfo", $args);
			}
			unset($args);
		} else {
			$return = false;
		}
		return $return;
	}
	
	protected function getPlaylistInfo($playlistId) {
		if(!is_null($playlistId)) {
			$args = new stdClass();
			$args->id = $playlistId;
			$result = executeQuery("widgets.youtube.perusePlaylistInfo", $args);
			if(empty($result->data)) {
				$return = false;
			} else {
				$return = $result->data;
			}
		} else {
			$return = false;
		}
		return $return;
	}
	
	protected function getCaches($playlistIds=NULL, $items=20, $page=1, $asc=true) {
		$args = new stdClass();
		$args->id = !empty($playlistIds) ? $playlistIds : "PLmtapKaZsgZt3g_uAPJbsMWdkVsznn_2R"; //실시간 인기 동영상 - 한국
		$args->index = "no";
		$args->listCount = is_numeric($items) ? $items : 20;
		$args->page = is_numeric($page) ? $page : 1;
		$args->order = $asc===false ? "desc" : "asc";
		$output = executeQuery("widgets.youtube.browseCaches", $args);
		return ($output->toBool()===false) ? false : $output->data;
	}
	
	protected function getShuffledCaches($playlistIds=NULL, $items=20, $page=1) {
		$args = new stdClass();
		$args->id = !empty($playlistIds) ? $playlistIds : "PLmtapKaZsgZt3g_uAPJbsMWdkVsznn_2R"; //실시간 인기 동영상 - 한국
		for($i=0; $i<50; $i++)
			$temp[] = $i;
		$arr = array_rand($temp, $items);
		$args->no = $arr;
		$args->listCount = is_numeric($items) ? $items : 20;
		$args->page = is_numeric($page) ? $page : 1;
		$args->order = $asc===false ? "desc" : "asc";
		$output = executeQuery("widgets.youtube.browseShuffledCaches", $args);
		shuffle($output->data);
		return ($output->toBool()===false) ? false : $output->data;
	}
	
	//Controller
	protected function updateCache($apiKey, $playlistId, $cacheTime=10, $items=20, $page=1, $asc=true) {
		$apiKey = !empty($apiKey) ? $apiKey : $this->apiKey;
		$playlistId = !empty($playlistId) ? $playlistId : "PLmtapKaZsgZt3g_uAPJbsMWdkVsznn_2R"; //실시간 인기 동영상 - 한국
		$cacheTime = is_numeric($cacheTime) ? $cacheTime : 10;
		$items = is_numeric($items) ? $items : null;
		$items = is_null($items) ? Context::get("items") : $items;
		$page = is_numeric($page) ? $page : null;
		$page = is_null($page) ? Context::get("page") : $page;
		$asc = is_null($asc) ? Context::get("asc") : $asc;
		$asc = $asc===false ? false : true;
	
		$start = microtime(true);
		$return = new stdClass();
	
		if(!is_null($apiKey)) {
			$youtube = new XFYoutube(null, $apiKey);
			if(time() - $this->getCacheTime($playlistId, $items, $page) > $cacheTime*60) {
				$return->loop = 0;
				$return->updatedVideos = 0;
				while(true) {
					$result = $youtube->playlistItems->browse("snippet", null, $playlistId, 50, $result[nextPageToken]);
					foreach($result[items] as $key=>$val) {
						$this->setCache($playlistId, $return->loop*50+$key, $val);
						$return->updatedVideos++;
					}
					if(is_numeric($items) && is_numeric($page) && $asc && ($loop*50+count($result[items])>=($page)*$items || is_null($result[nextPageToken]))) {
						$return->message = "UPDATE_PART_OF_CACHE_ASC";
						$return->bool = true;
						if(is_null($result[nextPageToken])) {
							//END_OF_LIST
							$return->totalVideos = $return->updatedVideos;
						} else {
							$return->totalVideos = $result[pageInfo][totalResults];
						}
						break;
					} else if(is_numeric($items) && is_numeric($page) && $asc===false && $loop*50+count($result[items])>=$result[pageInfo][totalResults]-($page-1)*$items) {
						$return->message = "UPDATE_PART_OF_CACHE_DESC";
						$return->bool = true;
						$return->totalVideos = $result[pageInfo][totalResults];
						break;
					} else if(is_null($result[nextPageToken])) {
						$return->message = "END_OF_LIST";
						$return->bool = true;
						$return->totalVideos = $return->updatedVideos;
						break;
					} else $return->loop++;
					
					if($this->startTime+$this->timer<time()) break;
				}
				$this->setPlaylistInfo($playlistId, $return->totalVideos);
			} else {
				$temp = $this->getPlaylistInfo($playlistId);
				$return->totalVideos = $temp->total_videos;
				$return->message = "NO_NEED_TO_UPDATE";
				$return->bool = false;
			}
		} else {
			$return->message = "NO_APIKEY";
			$return->bool = false;
		}
		$return->time = sprintf("%.9fs", microtime(true)-$start);
		if($_SERVER['HTTP_X_REQUESTED_WITH']=="XMLHttpRequest") {
			echo json_encode($return);
			exit;
		} else {
			return $return;
		}
	}
}
?>