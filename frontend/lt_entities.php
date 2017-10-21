<?php

require_once("lt_core.php");

const 	PIPELINE_CREATE = 0,
		PIPELINE_ADVANCE = 1,
		PIPELINE_REVERT = 2,
		PIPELINE_TRASH = 3,
		PIPELINE_COMMENT = 4,
		PIPELINE_APPROVE = 5,
		PIPELINE_FILE = 6,
		PIPELINE_ALERT = 7,
		PIPELINE_FEEDBACK = 8,
		PIPELINE_FLAG = 9;

const	NOTIFICATION_INBOX = 0,
		NOTIFICATION_FEEDBACK = 1,
		NOTIFICATION_ALERT = 2,
		NOTIFICATION_MESSAGE = 3,
		NOTIFICATION_PUBLISH = 4;

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
		$stageId = self::getInitialStage($data);
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
					"priority",
					"trust_level",
					"truth_level",
					"dup_level" ];
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		if (!isset($prepared['stage_id'])) {
			$prepared['stage_id'] = $stageId;
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
			if ($extUser['handle']) $userData['origin_nickname'] = $extUser['handle'];
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
				Users::create($userData, $userId);
			} else {
				$userId = $existingExtUser['id'];
			}
		}
		if (!$prepared['source_url'] and !$prepared['synopsis'] and !$prepared['text']) $proceed = false; 
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

	public static function get($id) {
		$id = (int) $id;
		$data = DB::getSingle("SELECT * FROM l_news WHERE id=$id AND is_deleted=0");
		if ($data) {
			$pipeline = self::getPipeline($id);
			$flags = self::getFlags($id);
			foreach($flags as $item) {
				if ($item['is_tag']) {
					$data['tags'][] = $item;
				} else {
					$data['keywords'][] = $item;
				}
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
		foreach($list as $item) {
			$pipelineFiles[$item['id']] = [];
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
			foreach($list as $item) {
				if ($pipelineFiles[$item['id']]) $item['files'] = $pipelineFiles[$item['id']];
			}
		}
		return $list;
	}
	
	public static function getList($data) {
		$where = "l_news.is_deleted=0";
		$order = "touch_time ASC";
		$onPage = 50;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
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
		} else {
			$data = DB::query("SELECT l_news.* FROM l_news_flags LEFT JOIN l_news ON l_news_flags.news_id=l_news.id WHERE $where ORDER BY $order LIMIT $limit");
		}
		$newsFlags = array();
		$newsActions = array();
		foreach($data as $key=>$item) {
			$newsFlags[$item['id']] = [];
			$newsActions[$item['id']] = [];
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
		return $data;
	}
	
	public function getInitialStage($data) {
		// Добавить определение начальной папки на основании подозрительности контента и надежности источника
		return 3;
	}	

	public static function setStage($id, $stageId, $userId, $comment) {
		$id = (int) $id;
		$stageId = (int) $stageId;
		$userId = (int) $userId;
		$comment = DB::escape($comment);
		$news = self::get($id);
		if ($news) {
			if ($stageId != $news['stage_id']) {
				DB::query("UPDATE l_news SET stage_id=$stageId WHERE id=$id");
				$higherId = DB::getValue("SELECT id FROM l_stages WHERE id IN ($news[stage_id], $stageId) ORDER BY oid DESC");
				if ($higherId == $stageId) $type = PIPELINE_ADVANCE; else $type = PIPELINE_REVERT;
				if ($stageId == 2) $type = PIPELINE_TRASH;
				$actionData = [ "type" => $type ];
				if ($comment) $actionData['comment'] = $comment;
				$newActionId = self::advancePipeline($id, $actionData);
			}
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
			$
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

	public static function delete($id, $userId) {
		$id = (int) $id;
		$userId = (int) $userId;
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
		$data = DB::query("SELECT * FROM l_stages");
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
		foreach($data as $key=>$item) {
			$item['data'] = json_decode($item['data'], true);
			$data[$key] = $item;
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