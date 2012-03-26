<?
/*
	Custom BigTree integration:
		
		Copyright (c) 2012 Ben Plum, MIT license
		http://www.benjaminplum.com
		
		Requires: BTXCachableModule
*/
	
	class BTXTwitterAPI extends BTXCachableModule {
	    
	    var $version = "0.1";
	    
	    /*
			Construct
		*/
	    public function __construct($debug = false) {
	    	global $cms;
	        
	        $this->max_cache_age = 60 * 5; // 5 mins
	        $this->cache_prefix = "btx-twitter-api";
	        
	        parent::__construct($debug);
	        
	        $this->active_username = $cms->getSetting("btx-twitter-active-username");
	        
			$this->SECOND = 1;
			$this->MINUTE = 60 * $this->SECOND;
			$this->HOUR = 60 * $this->MINUTE;
			$this->DAY = 24 * $this->HOUR;
			$this->MONTH = 30 * $this->DAY;
	    }
	    
	    /*
			BigTree Specific Functions
		*/
		public function setActiveUsername($username) {
	    	global $admin; 
			
			if (!$username) {
				return false;
			}
			
			sqlquery("DELETE FROM bigtree_settings WHERE id = 'btx-twitter-active-username'");
			$setting = array(
				"id" => "btx-twitter-active-username",
				"title" => "BTX Twitter Active Username",
				"type" => "text",
				"encrypted" => "",
				"system" => "on"
			);
			$admin->createSetting($setting);
			$admin->updateSettingValue("btx-twitter-active-username", $username);
			
			$this->clearCache();
			return true;
	    }
	    
	    public function clearActiveUsername($username) {
			sqlquery("DELETE FROM bigtree_settings WHERE id = 'btx-twitter-active-username'");
			$this->clearCache();
	    }
		
		public function user($username = false) {
	    	if (!$username) {
	    		if (!$this->active_username) {
	    			return false;
	    		}
	    		$username = $this->active_username;
	    	}
	    	$curl_url = "http://api.twitter.com/1/users/lookup.json?screen_name=" . $username;
			if ($count) {
				$curl_url .= "&count=" . $count;
			}
			$cache_file = $this->cache_base . "-" . $username . ".btc";
			$user = $this->cacheCurl($curl_url, $cache_file);
			return $user[0];
	    }
		
	    public function timeline($username = false, $count = false) {
	    	if (!$username) {
	    		if (!$this->active_username) {
	    			return false;
	    		}
	    		$username = $this->active_username;
	    	}
	    	$curl_url = "https://api.twitter.com/1/statuses/user_timeline.json?screen_name=" . $username . "&include_rts=true&trim_user=1";
			if ($count) {
				$curl_url .= "&count=" . $count;
			}
			$cache_file = $this->cache_base . "-" . $username . "-timeline.btc";
			$timeline = $this->cacheCurl($curl_url, $cache_file, "parseTimeline");
			return $timeline;
	    }
	    
	    public function search($query = false, $count = false) {
	    	if (!$query) {
	    		return false;
	    	}
	    	$curl_url = "https://search.twitter.com/search.json?q=" . urlencode($query);
			if ($count) {
				$curl_url .= "&count=" . $count;
			}
			$cache_file = $this->cache_base . "-search-" . md5($query) . ".btc";
			$results = $this->cacheCurl($curl_url, $cache_file, "parseTimeline");
			return $results;
	    }
	    
	    public function parseTimeline($timeline) {
	    	if (!is_array($timeline)) {
	    		$timeline = json_decode($timeline, true);
	    	}
	    	if ($timeline == NULL) {
	    		return false;
	    	}
	    	$tweets = $timeline;
	    	if ($tweets["query"]) {
	    		$tweets = $tweets["results"];
	    	}
	    	$return = array();
			for ($i = 0, $count = count($tweets); $i < $count; $i++) {
				$tweet = $tweets[$i];
				$parsed = array();
				
				$parsed["id"] = $tweet["id"];
				if ($tweet["retweeted_status"]) {
					$parsed["text"] = $this->replaceLinks("RT @" . $tweet["retweeted_status"]["user"]["screen_name"] . ": " . $tweet["retweeted_status"]["text"]);
				} else {
					$parsed["text"] = $this->replaceLinks($tweet["text"]);
				}
				$parsed["created"] = $this->relativeTime($tweet["created_at"]);
				$parsed["source"] = $tweet["source"];
				if ($tweet["from_user"]) {
					$parsed["user"] = $tweet["from_user"];
					$parsed["user_image"] = $tweet["profile_image_url"];
				}
				$parsed["original"] = $tweet;
				
				$return[] = $parsed;
			}
			if ($timeline["query"]) {
				$timeline["results"] = $return;
			} else {
				$timeline = $return;
			}
			return $timeline;
	    }
	    
	    public function replaceLinks($text)
	    {
	    	// RANDOM LINKS
	    	$text = preg_replace("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@", '<a href="\0" target="_blank">\0</a>', $text);
	    	// USERS
			$text = preg_replace('/(^|\s)@(\w+)/','\1<a href="http://www.twitter.com/\2" target="_blank">@\2</a>', $text);
			// HASHTAGS
			$text = preg_replace('/(^|\s)#(\w+)/','\1<a href="http://search.twitter.com/search?q=%23\2" target="_blank">#\2</a>', $text);
			return $text;
	    }
	    
		public function relativeTime($time)
		{
			$delta = strtotime(date('r')) - strtotime($time);
			
			if ($delta < 2 * $this->MINUTE) {
				return "1 min ago";
			} else if ($delta < 45 * $this->MINUTE) {
				return floor($delta / $this->MINUTE) . " min ago";
			} else if ($delta < 90 * $this->MINUTE) {
				return "1 hour ago";
			} else if ($delta < 24 * $this->HOUR) {
				return floor($delta / $this->HOUR) . " hours ago";
			} else if ($delta < 48 * $this->HOUR) {
				return "yesterday";
			} else if ($delta < 30 * $this->DAY) {
				return floor($delta / $this->DAY) . " days ago";
			} else if ($delta < 12 * $this->MONTH) {
				$months = floor($delta / $this->DAY / 30);
				return $months <= 1 ? "1 month ago" : $months . " months ago";
			} else {
				$years = floor($delta / $this->DAY / 365);
				return $years <= 1 ? "1 year ago" : $years . " years ago";
			}
		}
	}
?>