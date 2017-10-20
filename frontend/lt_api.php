<?php

require_once("lt_core.php");

$root = dirname(__FILE__);

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
		if ($isKeyword == 1) $where = "AND is_tag = 1";
		if ($isKeyword == 2) $where = "AND is_keyword = 1";
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
				$pipelineId = self::advancePipeline($iid, [ "news_id" => $iid, "stage_id" => $stageId, "type" => PIPELINE_CREATE, "data" => $data ]);
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
				$item['thumbnails'] = json_decode($item['thumbnails']);
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
		$where = "is_deleted=0";
		$order = "touch_time ASC";
		$onPage = 50;
		$startFrom = 0;
		if ($data['offset']) $startFrom = (int) $data['offset'];
		if ($data['limit']) $onPage = (int) $data['limit'];
		$limit = $startFrom.",".$onPage;
		$data = DB::query("SELECT l_news.* FROM l_news WHERE $where ORDER BY $order LIMIT $limit");
		$newsFlags = array();
		$newsPipelines = array();
		foreach($data as $key=>$item) {
			$newsFlags[$item['id']] = [];
			$newsPipelines[$item['id']] = [];
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
			$pipelineList = DB::query("SELECT news_id, MAX(id) FROM l_news_pipeline WHERE news_id IN (".implode(",",array_keys($newsActions)).") AND is_deleted=0 GROUP BY news_id");
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
					$item['thumbnails'] = json_decode($item['thumbnails']);
					if (!$item['thumbnails']) $item['thumbnails'] = [];
					$pipelineFiles[$pipelineId][] = $item;
				}
				$pipelineList = DB::query("SELECT * FROM l_news_pipelines WHERE id IN (".implode(",",array_keys($newsActions)).")");
				$newsActions = [];
				foreach($pipelineList as $item) {
					$newsId = $item['news_id'];
					unset($item['news_id']);
					if ($pipelineFiles[$item['id']]) $item['files'] = $pipelineFiles[$item['id']];
					$newsActions[$newsId] = $item;
				}
				foreach($data as $key=>$item) {
					if ($newsPipelines[$item['id']]) {
						$item['last_action'] = $newsPipelines[$item['id']];
					}
					if ($newsFlags[$item['id']]) {
						$item['flags'] = $newsFlags[$item['id']];
					}
				}
			}
		}
		return $data;
	}
	
	public function getInitialStage($data) {
		// Добавить определение начальной папки на основании подозрительности контента и надежности источника
		return 3;
	}	

	public static function setStage($id, $stageId, $userId) {
		$id = (int) $id;
		$stageId = (int) $stageId;
		$userId = (int) $userId;
		DB::query("UPDATE l_news S SET is_deleted=1 WHERE id=$id");
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
			$data['time'] = time();
			$data['date'] = date("Y-m-d");
			$data['user_id'] = $userId;
			DB::insert("l_news_pipeline", $data);
			$iid = DB::insertId();
			self::touch($id, $userId);
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
		$id = (int) $id;
		$fileId = (int) $fileId;
		$userId = (int) $userId;
		DB::insert("l_news_files", [ "pipeline_id" => $id, "file_id" => $fileId, "added_by" => $userId, "add_time" => time() ]);
		self::touch($id, $userId);
	}
	
	public static function removeFile($id, $fileId, $userId) {
		$id = (int) $id;
		$flagId = (int) $flagId;
		$userId = (int) $userId;
		DB::query("DELETE FROM l_news_files WHERE news_id=$id AND file_id=$fileId");
		self::touch($id, $userId);
	}
	
	public static function addFlag($id, $flagId, $userId) {
		$id = (int) $id;
		$flagId = (int) $flagId;
		$userId = (int) $userId;
		DB::insert("l_news_flags", [ "news_id" => $id, "flag_id" => $flagId, "set_by" => $userId, "set_time" => time() ]);
		self::touch($id, $userId);
	}
	
	public static function removeFlag($id, $flagId, $userId) {
		$id = (int) $id;
		$flagId = (int) $flagId;
		$userId = (int) $userId;
		DB::query("DELETE FROM l_news_flags WHERE news_id=$id AND flag_id=$flagId");
		self::touch($id, $userId);
	}
	
}

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
			$data['thumbnails'] = json_decode($data['thumbnails']);
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
			$item['thumbnails'] = json_decode($item['thumbnails']);
			if (!$item['thumbnails']) $item['thumbnails'] = [];
			$data[$key] = $item;
		}
		return $data;
	}
	
}

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

class Roles {
		
	public static function getAll() {
		$out = [];
		$data = DB::query("SELECT * FROM l_users");
		foreach($data as $key=>$item) {
			$out[$item['id']] = $item;
		}
		return $out;
	}
	
}

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

/*
$newsId = News::create([
	"source_url" => "http://yandex.ru",
	"title" => "Боже мой, это же Яндекс",
	"synopsis" => "Какой-то краткий текст" ]);
*/

?>