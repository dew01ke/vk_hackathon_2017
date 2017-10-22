<?php

require_once("lt_core.php");
require_once("lt_vk.php");
require_once("morphy/common.php");

$stopWords = [];
$tmp = file($root."/var/stopwords.txt");
foreach($tmp as $item) $stopWords[trim($item)] = 1;

const 	PIPELINE_CREATE = 0,
		PIPELINE_ADVANCE = 1,
		PIPELINE_REVERT = 2,
		PIPELINE_TRASH = 3,
		PIPELINE_COMMENT = 4,
		PIPELINE_APPROVE = 5,
		PIPELINE_FILE = 6,
		PIPELINE_ALERT = 7,
		PIPELINE_FEEDBACK = 8,
		PIPELINE_FLAG = 9,
		PIPELINE_REWARD = 10;

const	NOTIFICATION_INBOX = 0,
		NOTIFICATION_FEEDBACK = 1,
		NOTIFICATION_ALERT = 2,
		NOTIFICATION_MESSAGE = 3,
		NOTIFICATION_PUBLISH = 4;

		
// -----------------------------------------------------------------------


$morphyConfig = [
	"dir" => $root."/var",
	"lang" => "ru_RU"
];

// -----------------------------------------------------------------------

		
class Flags {
	
	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		$fields = [	"name",
					"is_tag",
					"is_keyword",
					"priority" ];
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		if (!$prepared['is_tag'] and !$prepared['is_keyword']) $prepared['is_tag'] = 1;
		if (!$prepared['name']) $proceed = false;
		if ($proceed) {
			$isKeyword = 0;
			if ($prepared['is_tag']) $isKeyword = 1;
			if ($prepared['is_keyword']) $isKeyword = 2;
			$existingFlag = self::getByName($prepared['name'], $isKeyword);
			if (!$existingFlag) {
				$prepared['create_time'] = time();
				DB::insert("l_flags", $prepared);
				$iid = DB::insertId();
			} else {
				$iid = $existingFlag['id'];
			}
			return $iid;
		} else {
			return 0;
		}
	}
	
	public static function delete($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		DB::query("DELETE FROM l_flags WHERE id=$id");
		DB::query("DELETE FROM l_news_flags WHERE flag_id=$id");
	}

	public static function get($id) {
		$id = (int) $id;
		$data = DB::getSingle("SELECT * FROM l_flags WHERE id=$id AND is_deleted=0");
		return $data;
	}

	public static function getByName($name, $isKeyword) {
		$name = DB::escape($name);
		$where = "";
		if ($isKeyword == 1) $where = " AND is_tag = 1";
		if ($isKeyword == 2) $where = " AND is_keyword = 1";
		$data = DB::getSingle("SELECT * FROM l_flags WHERE name=$name $where AND is_deleted=0");
		return $data;
	}

	public static function getList($data) {
		$where = "is_deleted=0";
		if ($data['ids'] and is_string($data['ids'])) {
			$data['ids'] = explode(",", $data['ids']);
		}
		if (is_array($data['ids'])) {
			$tmp = [];
			foreach($data['ids'] as $tmpId) $tmp[] = (int) $tmpId;
			$where .= " AND id IN (".implode(",",$tmp).")";
		}
		$onPage = 500;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
		$limit = $startFrom.",".$onPage;
		$data = DB::query("SELECT * FROM l_flags WHERE $where LIMIT $limit");
		foreach($data as $key=>$item) {
			$data[$key] = $item;
		}
		return $data;
	}
	
}


// -----------------------------------------------------------------------


class News {
	
	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		// Для добавления внешних пользователей одним шагом с добавлением новости
		// ext_user
		//	channel: "vk"
		//	id: 18273824
		//  profile_data: {}
		$fields = [	"title",
					"synopsis",
					"text",
					"stage_id",
					"source_id",
					"source_url",
					"origin_user",
					"origin_data",
					"is_anonymous",
					"priority",
					"trust_level",
					"truth_level",
					"dup_level" ];
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		if (!$data['source_id'] and $data['source_url']) {
			$url = $data['source_url'];
			$tmp = explode("//", $url);
			if ($tmp[1]) {
				$tmp = $tmp[1];
				$tmp = explode("/", $tmp);
				$tmp = strtolower(trim($tmp[0]));
				if ($tmp and substr_count($tmp,".") >= 1) {
					$existingSource = Sources::getByDomain($tmp);
					if ($existingSource) {
						$sourceId = $existingSource['id'];
					} else {
						$sourceData = [ "url" ];
						$sourceId = Sources::create([ "url" => $tmp ], $userId);
					}
					$prepared['source_id'] = $sourceId;
				}
			}
		}
		if ($data['ext_user']) {
			$extUser = $data['ext_user'];
			$userData = [ "role_id" => 1 ];
			if ($extUser['channel']) {
				$extUser['channel'] = trim(strtolower($extUser['channel']));
				$userData['origin_channel'] = $extUser['channel'];
			}
			if ($extUser['id']) $userData['origin_id'] = $extUser['id'];
			if ($extUser['handle']) $userData['origin_handle'] = $extUser['handle'];
			$existingExtUser = Users::getByOrigin($extUser['channel'], $extUser['id'], $extUser['handle']);
			if (!$existingExtUser) {
				if ($extUser['first_name']) $userData['first_name'] = $extUser['first_name'];
				if ($extUser['last_name']) $userData['last_name'] = $extUser['last_name'];
				if ($extUser['mid_name']) $userData['mid_name'] = $extUser['mid_name'];
				if ($extUser['profile']) {
					$userData['profile_data'] = $extUser['profile'];
					if ($extUser['channel'] == "vk") {
						if ($extUser['profile']['first_name']) $userData['first_name'] = $extUser['profile']['first_name'];
						if ($extUser['profile']['last_name']) $userData['last_name'] = $extUser['profile']['last_name'];
						if ($extUser['profile']['nickname']) $userData['origin_handle'] = $extUser['profile']['nickname'];
					}
				}
				$originUserId = Users::create($userData, $userId);
				$prepared['origin_user'] = $originUserId;
			} else {
				if ($extUser['first_name'] and $extUser['first_name'] != $existingExtUser['first_name']) {
					DB::query("UPDATE l_users SET first_name=".DB::escape($extUser['first_name'])." WHERE id=$existingExtUser[id]");
				}
				if ($extUser['last_name'] and $extUser['last_name'] != $existingExtUser['last_name']) {
					DB::query("UPDATE l_users SET last_name=".DB::escape($extUser['last_name'])." WHERE id=$existingExtUser[id]");
				}
				$originUserId = $existingExtUser['id'];
				$prepared['origin_user'] = $originUserId;
			}
		}
		$stageId = self::getInitialStage($data);
		if (!isset($prepared['stage_id'])) {
			$prepared['stage_id'] = $stageId;
		}
		if (!$prepared['source_url'] and !$prepared['synopsis'] and !$prepared['text'] and !$prepared['title']) $proceed = false; 
		// if (!$prepared['source_url'] and !$prepared['synopsis'] and !$prepared['text']) $proceed = false; 
		if ($proceed) {
			$prepared['create_time'] = time();
			$prepared['create_date'] = date("Y-m-d");
			DB::insert("l_news", $prepared);
			$iid = DB::insertId();
			if ($iid) {
				$pipelineId = self::advancePipeline($iid, [ "news_id" => $iid, "stage_id" => $stageId, "type" => PIPELINE_CREATE, "data" => $data ], $userData);
				if ($pipelineId and $data['files']) {
					foreach($data['files'] as $key=>$file) {
						if ($file['tmp_name']) {
							$hash = md5(json_encode($file));
							$newPath = $root."/upload/".substr($hash,0,2);
							if (!file_exists($newPath)) mkdir($root."/upload", 0777, true);
							move_uploaded_file($file['tmp_name'], $newPath."/".$hash);
							$fileData = [ "type" => 0, "name" => $file['name'], "hash" => $hash, "origin_name" => $file['name'], "mime" => $file['type'], "size" => $file['size'], "origin_key" => $key ];
							$fileId = Files::create($fileData);
							if ($fileId) {
								self::addFile($pipelineId, $fileId, $userId);
							}
						} else {
							$path = $file['path'];
							if (file_exists($path)) {
								$fileName = array_pop(explode("/", $path));
								if (!$file['title']) $file['title'] = $fileName;
								$fileData = [ "type" => 0, "name" => $file['title'], "origin_name" => $fileName, "mime" => $file['mime'], "size" => filesize($file['path']) ];
								$fileId = Files::create($fileData);
								if ($fileId) {
									self::addFile($pipelineId, $fileId, $userId);
								}
							}
						}
					}
				}
			}
			return $iid;
		} else {
			return 0;
		}
	}
		
	public static function delete($id, $userId) {
		$userId = (int) $userId;
		$id = (int) $id;
		DB::query("UPDATE l_news SET is_deleted=1 WHERE id=$id");
		self::touch($id, $userId);
	}
	
	public static function touch($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		DB::query("UPDATE l_news SET touch_time=".time().", touched_by=$userId WHERE id=$id");
	}

	public static function rate($id, $rating, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		$rating = (int) $rating;
		$news = self::get($id);
		if ($news) {
			if ($rating != -1 and $rating != 1) $rating = 0;
			if ($rating != 0) {
				DB::insert("l_news_rating", [ "news_id" => $id, "user_id" => $userId, "rating" => $rating, "touch_time" => time() ], [ "rating" => $rating, "touch_time" => time() ]);
			} else {
				DB::query("DELETE FROM l_news_rating WHERE news_id=$id AND user_id=$userId");
			}
			$ratingList = DB::query("SELECT rating, SUM(rating) AS s FROM l_news_rating WHERE news_id=$id GROUP BY rating");
			$totalRating = 0;
			$upRating = 0;
			$downRating = 0;
			foreach($ratingList as $item) {
				$totalRating += $item['s'];
				if ($item['rating'] < 0) $downRating += $item['s'];
				if ($item['rating'] > 0) $upRating += $item['s'];
			}
			DB::query("UPDATE l_news SET rating=$totalRating, rating_up=$upRating, rating_down=$downRating WHERE id=$id");
			self::touch($id, $userId);
			return $totalRating;
		} else {
			return 0;
		}
	}

	public static function get($id) {
		$id = (int) $id;
		$data = DB::getSingle("SELECT * FROM l_news WHERE id=$id AND is_deleted=0");
		if ($data) {
			$pipeline = self::getPipeline($id);
			$flags = self::getFlags($id);
			if ($pipeline) $data['pipeline'] = $pipeline;
			if ($data['touched_by']) {
				$touchUser = Users::get($data['touched_by']);
				if ($touchUser) {
					$data['touched_by'] = [
						"id" => $touchUser['id'],
						"first_name" => $touchUser['first_name'],
						"last_name" => $touchUser['last_name'],
						"mid_name" => $touchUser['mid_name'],
						"origin_channel" => $touchUser['origin_channel'],
						"origin_id" => $touchUser['origin_id'],
						"origin_handle" => $touchUser['origin_handle'],
						"is_blacklisted" => $touchUser['is_blacklisted']
					];
				} else {
					unset($data['touched_by']);
				}
			} else {
				unset($data['touched_by']);
			}
			if ($data['origin_user']) {
				$originUser = Users::get($data['origin_user']);
				if ($originUser) {
					$data['origin_user'] = [
						"id" => $originUser['id'],
						"first_name" => $originUser['first_name'],
						"last_name" => $originUser['last_name'],
						"mid_name" => $originUser['mid_name'],
						"origin_channel" => $originUser['origin_channel'],
						"origin_id" => $originUser['origin_id'],
						"origin_handle" => $originUser['origin_handle'],
						"is_blacklisted" => $originUser['is_blacklisted']
					];
				} else {
					unset($data['origin_user']);
				}
			} else {
				unset($data['origin_user']);
			}
			foreach($flags as $item) {
				if ($item['is_tag']) {
					$data['tags'][] = $item;
				} else {
					$data['keywords'][] = $item;
				}
			}
			$ratingList = DB::query("SELECT * FROM l_news_rating WHERE news_id=$id");
			foreach($ratingList as $item) {
				$data['rating_list'][$item['user_id']] = $item['rating'];
			}
		}
		return $data;
	}
	
	public static function getFlags($id, $ifKeywords) {
		// ifKeywords
		// 0 - все
		// 1 - только теги
		// 2 - только ключевые слова
		$id = (int) $id;
		$where = "";
		if ($ifKeywords == 1) $where = " AND is_tag = 1";
		if ($ifKeywords == 2) $where = " AND is_keyword = 1";
		$list = DB::query("SELECT l_flags.* FROM l_news_flags LEFT JOIN l_flags ON l_news_flags.flag_id=l_flags.id WHERE news_id=$id $where AND l_flags.is_deleted=0");
		return $list;
	}

	public static function getPipeline($id) {
		$id = (int) $id;
		$list = DB::query("SELECT * FROM l_news_pipeline WHERE news_id=$id AND is_deleted=0 ORDER BY id DESC");
		$pipelineFiles = [];
		$pipelineUsers = [];
		foreach($list as $item) {
			$pipelineFiles[$item['id']] = [];
			$pipelineUsers[$item['user_id']] = [];
		}
		if (count($pipelineIds)) {
			$fileList = DB::query("SELECT l_news_files.pipeline_id, l_files.* FROM l_news_files LEFT JOIN l_files ON l_news_files.file_id=l_files.id WHERE pipeline_id IN (".implode(",",array_keys($pipelineIds)).") AND is_deleted=0");
			foreach($fileList as $item) {
				$pipelineId = $item['pipeline_id'];
				unset($item['pipeline_id'], $item['is_deleted'], $item['path']);
				$item['thumbnails'] = json_decode($item['thumbnails'], true);
				if (!$item['thumbnails']) $item['thumbnails'] = [];
				$pipelineFiles[$pipelineId][] = $item;
			}
			foreach($list as $key=>$item) {
				if ($pipelineFiles[$item['id']]) $item['files'] = $pipelineFiles[$item['id']];
				$list[$key] = $item;
			}
		}
		if (count($pipelineUsers)) {
			$userList = DB::query("SELECT id, first_name, last_name, mid_name, origin_channel, origin_id, origin_handle, is_blacklisted FROM l_users WHERE id IN (".implode(",",array_keys($pipelineUsers)).") AND is_deleted=0");
			foreach($userList as $item) {
				$pipelineUsers[$item['id']] = $item;
			}
			foreach($list as $key=>$item) {
				if ($pipelineUsers[$item['user_id']]) $item['user'] = $pipelineUsers[$item['user_id']];
				$list[$key] = $item;
			}
		}
		return $list;
	}
	
	public static function getList($data) {
		$where = "l_news.is_deleted=0";
		if (array_key_exists('order', $data)) {
		  $orderItems = [];
		  foreach ($data['order'] as $i => $o) {
		    if (in_array($i, ["rating", "touch_time", 'create_time', "publish_time"])
			&& in_array($o, ['ASC', 'DESC', 'RAND()']))
		      $orderItems[] = "$i $o";
		  }
		  $order = implode(',', $orderItems);
		} else {
		  $order = "touch_time ASC";
		}
		$onPage = 50;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
		if ($data['ids'] and is_string($data['ids'])) {
			$data['ids'] = explode(",", $data['ids']);
		}
		if (is_array($data['ids'])) {
			$tmp = [];
			foreach($data['ids'] as $tmpId) $tmp[] = (int) $tmpId;
			$where .= " AND id IN (".implode(",",$tmp).")";
		}
		if ($data['stage_id']) $where .= " AND stage_id=".((int) $data['stage_id']);
		if ($data['added_after']) {
			$t = (int) $data['added_after'];
			$where .= " AND create_date>=".DB::escape(date("Y-m-d",$t))." AND create_time>$t";
		}
		if ($data['added_before']) {
			$t = (int) $data['added_before'];
			$where .= " AND create_date<=".DB::escape(date("Y-m-d",$t))." AND create_time<$t";
		}
		if ($data['published_before']) {
			$t = (int) $data['published_before'];
			$where .= " AND publish_date>=".DB::escape(date("Y-m-d",$t))." AND publish_time>$t";
		}
		if ($data['published_before']) {
			$t = (int) $data['published_before'];
			$where .= " AND publish_date<=".DB::escape(date("Y-m-d",$t))." AND publish_time<$t";
		}
		if ($data['source_id']) $where .= " AND source_id=".((int) $data['source_id']);
		if ($data['origin_user']) $where .= " AND origin_user=".((int) $data['origin_user']);
		$limit = $startFrom.",".$onPage;
		if (!$data['flag_id']) {
			$data = DB::query("SELECT l_news.* FROM l_news WHERE $where ORDER BY $order LIMIT $limit");
			//echo "SELECT l_news.* FROM l_news WHERE $where ORDER BY $order LIMIT $limit";
		} else {
			$data = DB::query("SELECT l_news.* FROM l_news_flags LEFT JOIN l_news ON l_news_flags.news_id=l_news.id WHERE $where ORDER BY $order LIMIT $limit");
			//echo "SELECT l_news.* FROM l_news_flags LEFT JOIN l_news ON l_news_flags.news_id=l_news.id WHERE $where ORDER BY $order LIMIT $limit";
		}
		$newsFlags = [];
		$newsActions = [];
		$relatedUsers = [];
		$allRatings = [];
		foreach($data as $key=>$item) {
			$newsFlags[$item['id']] = [];
			$newsActions[$item['id']] = [];
			$allRatings[$item['id']] = [];
			if ($item['origin_user']) $relatedUsers[$item['origin_user']] = [];
			if ($item['touched_by']) $relatedUsers[$item['touched_by']] = [];
		}
		if (count($allRatings)) {
			$ratingList = DB::query("SELECT * FROM l_news_rating WHERE news_id IN (".implode(",",array_keys($allRatings)).")");
			foreach($ratingList as $item) {
				$allRatings[$item['news_id']][$item['user_id']] = $item['rating'];
			}
		}
		if (count($relatedUsers)) {
			$userList = Users::getList([ "ids" => array_keys($relatedUsers) ]);
			foreach($userList as $item) {
				$relatedUsers[$item['id']] = [
					"id" => $item['id'],
					"first_name" => $item['first_name'],
					"last_name" => $item['last_name'],
					"mid_name" => $item['mid_name'],
					"origin_channel" => $item['origin_channel'],
					"origin_id" => $item['origin_id'],
					"origin_handle" => $item['origin_handle'],
					"is_blacklisted" => $item['is_blacklisted']
				];
			}
		}
		if (count($newsFlags)) {
			$flagList = DB::query("SELECT news_id, flag_id, l_flags.name, l_flags.id, l_flags.color, l_flags.priority, l_flags.added_by, l_flags.add_time FROM l_news_flags LEFT JOIN l_flags ON l_news_flags.flag_id=l_flags.id WHERE news_id IN (".implode(",",array_keys($newsFlags)).")");
			foreach($flagList as $item) {
				$newsId = $item['news_id'];
				unset($item['news_id'], $item['flag_id']);
				$newsFlags[$newsId][] = $item;
			}
		}
		if (count($newsActions)) {
			$pipelineList = DB::query("SELECT news_id, MAX(id) AS id FROM l_news_pipeline WHERE news_id IN (".implode(",",array_keys($newsActions)).") AND is_deleted=0 GROUP BY news_id");
			$newsActions = [];
			foreach($pipelineList as $item) {
				$newsActions[$item['id']] = [];
			}
			if (count($newsActions)) {
				$pipelineFiles = [];
				$fileList = DB::query("SELECT l_news_files.pipeline_id, l_files.* FROM l_news_files LEFT JOIN l_files ON l_news_files.file_id=l_files.id WHERE pipeline_id IN (".implode(",",array_keys($newsActions)).") AND is_deleted=0");
				foreach($fileList as $item) {
					$pipelineId = $item['pipeline_id'];
					unset($item['pipeline_id'], $item['is_deleted'], $item['path']);
					$item['thumbnails'] = json_decode($item['thumbnails'], true);
					if (!$item['thumbnails']) $item['thumbnails'] = [];
					$pipelineFiles[$pipelineId][] = $item;
				}
				$pipelineList = DB::query("SELECT * FROM l_news_pipeline WHERE id IN (".implode(",",array_keys($newsActions)).")");
				$newsActions = [];
				foreach($pipelineList as $item) {
					$newsId = $item['news_id'];
					unset($item['news_id']);
					if ($pipelineFiles[$item['id']]) $item['files'] = $pipelineFiles[$item['id']];
					$newsActions[$newsId] = $item;
				}
				foreach($data as $key=>$item) {
					if ($newsActions[$item['id']]) {
						$item['last_action'] = $newsActions[$item['id']];
					}
					if ($newsFlags[$item['id']]) {
						$localFlags = $newsFlags[$item['id']];
						foreach($localFlags as $item) {
							if ($item['is_tag']) {
								$item['tags'][] = $item;
							} else {
								$item['keywords'][] = $item;
							}
						}
					}
					$data[$key] = $item;
				}
			}
		}
		foreach($data as $key=>$item) {
			if ($allRatings[$item['id']]) {
				$item['rating_list'] = $allRatings[$item['id']];
			}
			if ($item['origin_user']) {
				if ($relatedUsers[$item['origin_user']]) {
					$item['origin_user'] = $relatedUsers[$item['origin_user']];
				} else {
					unset($item['origin_user']);
				}
			} else {
				unset($item['origin_user']);
			}
			if ($item['touched_by']) {
				if ($relatedUsers[$item['touched_by']]) {
					$item['touched_by'] = $relatedUsers[$item['touched_by']];
				} else {
					unset($item['touched_by']);
				}
			} else {
				unset($item['touched_by']);
			}
			$data[$key] = $item;
		}
		return $data;
	}
	
	public function getInitialStage($data) {
		// Добавить определение начальной папки на основании подозрительности контента и надежности источника
		$stageId = 3;
		return $stageId;
	}	

	public static function update($id, $data, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		$news = self::get($id);
		if ($news) {
			$prepared = array();
			$fields = [
				"title",
				"synopsis",
				"text",
				"source_url",
				"publish_url",
				"publish_time",
				"priority",
				"reward_amount",
				"reward_status" ];
			if ($data['publish_time']) {
				$data['publish_time'] = (int) $data['publish_time'];
				$data['publish_date'] = date("Y-m-d", $data['publish_time']);
			}
			foreach($fields as $field) {
				if (isset($data[$field])) $prepared[$field] = $data[$field];
			}
			if (count($prepared)) {
				$what = [];
				foreach($prepared as $key=>$item) {
					$what[] = "`".$key."`=".DB::escape($item);
				}
				$what = implode(",",$what);
				DB::query("UPDATE l_news SET $what WHERE id=$id");
				self::touch($id, $userId);
			}
			if ($data['stage_id']) {
				$stageId = (int) $data['stage_id'];
				if ($stageId != $news['stage_id']) {
					self::setStage($id, $stageId, $userId);
				}
			}
		}
	}

	public static function setStage($id, $stageId, $comment, $userId) {
		$id = (int) $id;
		$stageId = (int) $stageId;
		$userId = (int) $userId;
		$news = self::get($id);
		if ($news) {
			if ($stageId != $news['stage_id']) {
				DB::query("UPDATE l_news SET stage_id=$stageId WHERE id=$id");
				$higherId = DB::getValue("SELECT id FROM l_stages WHERE id IN ($news[stage_id], $stageId) ORDER BY oid DESC");
				if ($higherId == $stageId) $type = PIPELINE_ADVANCE; else $type = PIPELINE_REVERT;
				if ($stageId == 2) $type = PIPELINE_TRASH;
				$actionData = [ "type" => $type ];
				if ($comment) $actionData['comment'] = $comment;
				$newActionId = self::advancePipeline($id, $actionData, $userId);
				self::touch($id, $userId);
			}
		}
	}

	public static function addComment($id, $comment, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		$news = self::get($id);
		if ($news) {
			$type = PIPELINE_COMMENT;
			$actionData = [ "type" => $type ];
			if ($comment) $actionData['comment'] = $comment;
			$newActionId = self::advancePipeline($id, $actionData, $userId);
		}
		self::touch($id, $userId);
	}

	// Под каждое действие с новостью создается отдельный блок на ее конвейере
	public static function advancePipeline($id, $data, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		$news = self::get($id);
		if ($news) {
			$fields = [	"type",
						"comment",
						"bonus",
						"data" ];
			foreach($fields as $field) {
				if (isset($data[$field])) $prepared[$field] = $data[$field]; 
			}
			if (!$prepared['news_id']) $prepared['news_id'] = $id;
			$prepared['time'] = time();
			$prepared['date'] = date("Y-m-d");
			$prepared['user_id'] = $userId;
			$prepared['stage_id'] = $news['stage_id'];
			DB::insert("l_news_pipeline", $prepared);
			$iid = DB::insertId();
			if ($iid and $data['files']) {
				foreach($data['files'] as $key=>$file) {
					if ($file['tmp_name']) {
						$hash = md5(json_encode($file));
						$newPath = $root."/upload/".substr($hash,0,2);
						if (!file_exists($newPath)) mkdir($root."/upload", 0777, true);
						move_uploaded_file($file['tmp_name'], $newPath."/".$hash);
						$fileData = [ "type" => 0, "name" => $file['name'], "hash" => $hash, "origin_name" => $file['name'], "mime" => $file['type'], "size" => $file['size'], "origin_key" => $key ];
						$fileId = Files::create($fileData);
						if ($fileId) {
							self::addFile($iid, $fileId, $userId);
						}
					} else {
						$path = $file['path'];
						if (file_exists($path)) {
							$fileName = array_pop(explode("/", $path));
							if (!$file['title']) $file['title'] = $fileName;
							$fileData = [ "type" => 0, "name" => $file['title'], "origin_name" => $fileName, "mime" => $file['mime'], "size" => filesize($file['path']) ];
							$fileId = Files::create($fileData);
							if ($fileId) {
								self::addFile($iid, $fileId, $userId);
							}
						}
					}
				}
				self::touch($id, $userId);
			}
			return $iid;
		} else {
			return 0;
		}
	}
	
	public static function splitKeywords($id) {
		global $morphyConfig, $stopWords;
		$id = (int) $id;
		$news = self::get($id);
		if ($news) {
			$morphy = new phpMorphy($morphyConfig['dir'], $morphyConfig['lang'], []);
			$wordBlocks = [ "title" => [], "synopsis" => [], "text" => [] ];
			preg_match_all("/\b([а-яё]+)\b/uUsi", mb_strtolower($news['title']), $r);
			foreach($r[1] as $item) {
				$word = mb_strtoupper($item);
				$wordNormal = $morphy->lemmatize($word, phpMorphy::NORMAL);
				if ($wordNormal) $wordNormal = mb_strtolower($wordNormal[0]);
				if ($wordNormal and !$stopWords[$wordNormal]) {
					$wordBlocks['title'][$wordNormal]++;
				}
			}
			preg_match_all("/\b([а-яё]+)\b/uUsi", mb_strtolower($news['synopsis']), $r);
			foreach($r[1] as $item) {
				$word = mb_strtoupper($item);
				$wordNormal = $morphy->lemmatize($word, phpMorphy::NORMAL);
				if ($wordNormal) $wordNormal = mb_strtolower($wordNormal[0]);
				if ($wordNormal and !$stopWords[$wordNormal]) {
					$wordBlocks['synopsis'][$wordNormal]++;
				}
			}
			preg_match_all("/\b([а-яё]+)\b/uUsi", mb_strtolower($news['text']), $r);
			foreach($r[1] as $item) {
				$word = mb_strtoupper($item);
				$wordNormal = $morphy->lemmatize($word, phpMorphy::NORMAL);
				if ($wordNormal) $wordNormal = mb_strtolower($wordNormal[0]);
				if ($wordNormal and !$stopWords[$wordNormal]) {
					$wordBlocks['text'][$wordNormal]++;
				}
			}
			print_r($wordBlocks);
		}
	}

	// При взятии новости кем-то на редактирование, если в есть шанс конфликтов
	public static function block($id, $userId, $t) {
		$id = (int) $id;
		$userId = (int) $userId;
		$t = (int) $t;
		if (!$t) $t = 600;
		DB::query("UPDATE l_news SET blocked_by=$userId, blocked_till=".(time()+$t)." WHERE id=$id");
		self::touch($id, $userId);
	}

	// При освобождении новости после редактирования
	public static function unblock($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		DB::query("UPDATE l_news SET blocked_by=0, blocked_till=0 WHERE id=$id");
		self::touch($id, $userId);
	}

	public static function addFile($pipelineId, $fileId, $userId) {
		$pipelineId = (int) $pipelineId;
		$fileId = (int) $fileId;
		$userId = (int) $userId;
		DB::insert("l_news_files", [ "pipeline_id" => $pipelineId, "file_id" => $fileId, "added_by" => $userId, "add_time" => time() ]);
		self::touch($id, $userId);
	}
	
	public static function removeFile($id, $fileId, $userId) {
		$pipelineId = (int) $pipelineId;
		$flagId = (int) $flagId;
		$userId = (int) $userId;
		DB::query("DELETE FROM l_news_files WHERE pipeline_id=$pipelineId AND file_id=$fileId");
		self::touch($id, $userId);
	}
	
	public static function addFlag($id, $flagId, $userId) {
		$id = (int) $id;
		$flagId = (int) $flagId;
		$userId = (int) $userId;
		DB::insert("l_news_flags", [ "news_id" => $id, "flag_id" => $flagId, "set_by" => $userId, "set_time" => time() ]);
		self::advancePipeline($id, [ "type" => PIPELINE_FLAG, "subtype" => "add", "flag_id" => $flagId ], $userData);
		self::touch($id, $userId);
	}
	
	public static function removeFlag($id, $flagId, $userId) {
		$id = (int) $id;
		$flagId = (int) $flagId;
		$userId = (int) $userId;
		DB::query("DELETE FROM l_news_flags WHERE news_id=$id AND flag_id=$flagId");
		self::advancePipeline($id, [ "type" => PIPELINE_FLAG, "subtype" => "remove", "flag_id" => $flagId ], $userData);
		self::touch($id, $userId);
	}
	
}


// -----------------------------------------------------------------------


class Files {
	
	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		$fields = [	"type",
					"name",
					"hash",
					"mime",
					"origin_key",
					"origin_name",
					"comment",
					"metadata",
					"size",
					"thumbnails" ];
		// Добавить автополучение тамбнейлов из картинок
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		if ($proceed) {
			$prepared['create_time'] = time();
			$prepared['created_by'] = $userId;
			DB::insert("l_files", $prepared);
			$iid = DB::insertId();
			return $iid;
		} else {
			return 0;
		}
	}

	public static function delete($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		DB::query("UPDATE l_files SET is_deleted=1 WHERE id=$id");
	}

	public static function get($id) {
		$id = (int) $id;
		$data = DB::getSingle("SELECT * FROM l_files WHERE id=$id AND is_deleted=0");
		if ($data) {
			$data['thumbnails'] = json_decode($data['thumbnails'], true);
			if (!$data['thumbnails']) $data['thumbnails'] = [];
		}
		return $data;
	}
	
	public static function getList($data) {
		$where = "is_deleted=0";
		if ($data['ids'] and is_string($data['ids'])) {
			$data['ids'] = explode(",", $data['ids']);
		}
		if (is_array($data['ids'])) {
			$tmp = [];
			foreach($data['ids'] as $tmpId) $tmp[] = (int) $tmpId;
			$where .= " AND id IN (".implode(",",$tmp).")";
		}
		$order = "id DESC";
		$onPage = 50;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
		$limit = $startFrom.",".$onPage;
		$data = DB::query("SELECT * FROM l_files WHERE $where ORDER BY $order LIMIT $limit");
		foreach($data as $key=>$item) {
			$item['thumbnails'] = json_decode($item['thumbnails'], true);
			if (!$item['thumbnails']) $item['thumbnails'] = [];
			$data[$key] = $item;
		}
		return $data;
	}
	
}


// -----------------------------------------------------------------------


class Users {
	
	public static function auth($token, $originId) {
		VK::setToken($token);
		$profile = VK::getUserProfile($originId);
		if ($profile) {
			$originId = $profile['id'];
			$firstName = $profile['first_name'];
			$lastName = $profile['last_name'];
			$existingUser = self::getByOrigin("vk", $originId);
			if ($existingUser) {
				return $existingUser;
			} else {
				$newUserId = self::create([ "channel" => "vk", "origin_id" => $originId, "first_name" => $firstName, "last_name" => $lastName ]);
				if ($newUserId) {
					$newUser = self::get($newUserId);
					return $newUser;
				}
			}
		} else {
			return null;
		}
	}
	
	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		$fields = [	"first_name",
					"last_name",
					"mid_name",
					"role_id",
					"origin_channel",
					"origin_id",
					"origin_data" ];
		if ($data['channel']) {
			$data['channel'] = trim(strtolower($data['channel']));
			$data['origin_channel'] = $data['channel'];
		}
		if ($data['id']) $data['origin_id'] = $data['id'];
		if ($data['handle']) $data['origin_handle'] = $data['handle'];
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		if ($proceed) {
			$prepared['create_time'] = time();
			DB::insert("l_users", $prepared);
			$iid = DB::insertId();
			return $iid;
		} else {
			return 0;
		}
	}

	public static function update($id, $data, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		$source = self::get($id);
		if ($source) {
			$prepared = array();
			$fields = [
				"first_name",
				"last_name",
				"mid_name",
				"role_id",
				"is_blacklisted" ];
			foreach($fields as $field) {
				if (isset($data[$field])) $prepared[$field] = $data[$field];
			}
			if (count($prepared)) {
				$what = [];
				foreach($prepared as $key=>$item) {
					$what[] = "`".$key."`=".DB::escape($item);
				}
				$what = implode(",",$what);
				DB::query("UPDATE l_users SET $what WHERE id=$id");
			}
		}
	}
	
	public static function delete($id) {
		$id = (int) $id;
		DB::query("UPDATE l_users SET is_deleted=1 WHERE id=$id");
	}

	public static function get($id) {
		$id = (int) $id;
		$data = DB::getSingle("SELECT * FROM l_users WHERE id=$id AND is_deleted=0");
		return $data;
	}

	public static function getByOrigin($channel, $id, $handle) {
		$id = DB::escape(trim($id));
		$handle = DB::escape(trim($handle));
		$channel = DB::escape(trim(strtolower($channel)));
		if ($id) {
			$data = DB::getSingle("SELECT * FROM l_users WHERE origin_channel=$channel AND origin_id=$id AND is_deleted=0");
			if (!$data) {
				$data = DB::getSingle("SELECT * FROM l_users WHERE origin_channel=$channel AND origin_handle=$id AND is_deleted=0");
			}
		} else {
			if ($handle) {
				$data = DB::getSingle("SELECT * FROM l_users WHERE origin_channel=$channel AND origin_handle=$handle AND is_deleted=0");
			}
		}
		return $data;
	}
	
	public static function getList($data) {
		$where = "is_deleted=0";
		if ($data['ids'] and is_string($data['ids'])) {
			$data['ids'] = explode(",", $data['ids']);
		}
		if (is_array($data['ids'])) {
			$tmp = [];
			foreach($data['ids'] as $tmpId) $tmp[] = (int) $tmpId;
			$where .= " AND id IN (".implode(",",$tmp).")";
		}
		$order = "id DESC";
		$onPage = 50;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
		$limit = $startFrom.",".$onPage;
		$data = DB::query("SELECT * FROM l_users WHERE $where ORDER BY $order LIMIT $limit");
		foreach($data as $key=>$item) {
			$data[$key] = $item;
		}
		return $data;
	}
	
}


// -----------------------------------------------------------------------


class Roles {
		
	public static function getAll() {
		$out = [];
		$data = DB::query("SELECT * FROM l_roles");
		foreach($data as $key=>$item) {
			$out[$item['id']] = $item;
		}
		return $out;
	}
	
}


// -----------------------------------------------------------------------


class Stages {
		
	public static function getAll() {
		$out = [];
		$data = DB::query("SELECT * FROM l_stages ORDER BY oid ASC");
		foreach($data as $key=>$item) {
			$out[$item['id']] = $item;
		}
		return $out;
	}
	
}


// -----------------------------------------------------------------------


class Notifications {

	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		$fields = [	"type",
					"news_id",
					"user_from",
					"text",
					"data" ];
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		$prepared['is_fresh'] = 1;
		if ($data['from']) $data['user_from'] = $data['from'];
		$prepared['user_to'] = $userId;
		if ($proceed) {
			$prepared['time'] = time();
			$prepared['date'] = date("Y-m-d", time());
			DB::insert("l_notifications", $prepared);
			$iid = DB::insertId();
			return $iid;
		} else {
			return 0;
		}
	}
		
	public static function get($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		$data = DB::getSingle("SELECT * FROM l_notifications WHERE id=$id AND user_to=$userId AND is_deleted=0");
		return $data;
	}

	public static function getList($data, $userId) {
		$userId = (int) $userId;
		$where = "";
		$order = "id DESC";
		$onPage = 20;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
		$limit = $startFrom.",".$onPage;
		if ($data['news_id']) $where .= " AND news_id=".((int) $data['news_id']);
		if ($data['fresh_only']) $where .= " AND is_fresh=1";
		$data = DB::query("SELECT * FROM l_notifications WHERE user_to=$userId $where ORDER BY $order LIMIT $limit");
		$ids = [];
		foreach($data as $key=>$item) {
			$item['data'] = json_decode($item['data'], true);
			$ids[] = $item['id'];
			$data[$key] = $item;
		}
		if ($data['mark'] and count($ids)) {
			DB::query("UPDATE l_notifications SET is_fresh=0 WHERE user_to=$userId AND is_fresh=1 AND id IN (".implode(",",$ids).")");
		}
		return $data;
	}

	public static function getFreshCount($userId) {
		$userId = (int) $userId;
		$notificationCount = DB::getValue("SELECT COUNT(*) FROM l_notifications WHERE user_to=$userId AND is_fresh=1");
		return $notificationCount;
	}
	
	public static function getLastId() {
		$notificationId = DB::getValue("SELECT MAX(id) FROM l_notifications");
		return $notificationId;
	}

	public static function mark($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		DB::query("UPDATE l_notifications SET is_fresh=0 WHERE id=$id AND user_to=$userId");
	}

	public static function markTo($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		DB::query("UPDATE l_notifications SET is_fresh=0 WHERE user_to=$userId AND is_fresh=1 AND id<=$id");
	}
	
}


// -----------------------------------------------------------------------


class Stats {

	public static function getNewsByStage() {
		$data = DB::query("SELECT stage_id, COUNT(*) AS `c` FROM l_news WHERE is_deleted=0 GROUP BY stage_id");
		$out = [];
		foreach($data as $item) {
			$out[$item['stage_id']] = $item['c'];
		}
		return $out;
	}

	public static function getAcceptedStats($data) {
		$dateFrom = date("Y-m-d", strtotime("-30 day"));
		$data = DB::query("SELECT date, COUNT(*) FROM l_news_pipeline WHERE date>=".DB::escape($dateFrom)." AND type=".PIPELINE_ADVANCE." GROUP BY date");
		return $data;
	}

}


// -----------------------------------------------------------------------


class Utility {

	public static function getLastActionId() {
		$actionId = DB::getValue("SELECT MAX(id) FROM l_news_pipeline");
		return $actionId;
	}

}


// -----------------------------------------------------------------------


class Sources {

	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		$fields = [	"name",
					"comment",
					"url",
					"domain",
					"latency",
					"trust_level" ];
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		if ($proceed) {
			$prepared['create_time'] = time();
			$prepared['created_by'] = $userId;
			DB::insert("l_source", $prepared);
			$iid = DB::insertId();
			return $iid;
		} else {
			return 0;
		}
	}

	public static function update($id, $data, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		$source = self::get($id);
		if ($source) {
			$prepared = array();
			$fields = [
				"name",
				"comment",
				"url",
				"domain",
				"latency",
				"trust_level",
				"is_blacklisted" ];
			foreach($fields as $field) {
				if (isset($data[$field])) $prepared[$field] = $data[$field];
			}
			if (count($prepared)) {
				$what = [];
				foreach($prepared as $key=>$item) {
					$what[] = "`".$key."`=".DB::escape($item);
				}
				$what = implode(",",$what);
				DB::query("UPDATE l_sources SET $what WHERE id=$id");
			}
		}
	}
	
	public static function delete($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
		DB::query("UPDATE l_sources SET is_deleted=1 WHERE id=$id");
	}

	public static function blacklist($id, $userId, $reason) {
		$id = (int) $id;
		$userId = (int) $userId;
		$reason = DB::escape($reason);
		DB::query("UPDATE l_sources SET is_blacklisted=1 WHERE id=$id");
	}

	public static function get($id) {
		$id = (int) $id;
		$data = DB::getSingle("SELECT * FROM l_sources WHERE id=$id AND is_deleted=0");
		return $data;
	}

	public static function getByDomain($name) {
		$name = DB::escape("%\\\\".$name."%");
		$data = DB::getSingle("SELECT * FROM l_sources WHERE url LIKE $name AND is_deleted=0");
		return $data;
	}
	
	public static function getList($data) {
		$where = "is_deleted=0";
		$order = "id DESC";
		$onPage = 50;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
		$limit = $startFrom.",".$onPage;
		$data = DB::query("SELECT * FROM l_sources WHERE $where ORDER BY $order LIMIT $limit");
		foreach($data as $key=>$item) {
			
		}
		return $data;
	}
	
}

?>