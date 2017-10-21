<?php

require_once("lt_entities.php");

class Scraper {
	
	function searchGoogle($newsId) {
		$newsId = (int) $newsId;
		$news = News::get($newsId);
		if ($news) {
			$news['keywords']
			$keywords = [];
			if ($news['keywords']) {
				foreach($news['keywords'] as $item) {
					$keywords[] = strtolower($item['name']);
				}
			}
		} else {
			return null;
		}
	}
	
	function searchYandex($newsId) {
		$newsId = (int) $newsId;
		$news = News::get($newsId);
		if ($news) {
			
		} else {
			return null;
		}
	}
	
	function searchRIA($newsId) {
		$newsId = (int) $newsId;
		$news = News::get($newsId);
		if ($news) {
			
		} else {
			return null;
		}
	}
	
	function searchLenta($newsId) {
		$newsId = (int) $newsId;
		$news = News::get($newsId);
		if ($news) {
			
		} else {
			return null;
		}
	}
	
	function searchRBC($newsId) {
		$newsId = (int) $newsId;
		$news = News::get($newsId);
		if ($news) {
			
		} else {
			return null;
		}
	}
		
}


?>