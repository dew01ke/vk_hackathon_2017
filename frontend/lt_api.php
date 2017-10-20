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
		
class Flags {
	
	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		$fields = [	"???",
					"???" ];
		foreach($fields as $field) {
			if (isset($data[$field])) $prepared[$field] = $data[$field]; 
		}
		if ($proceed) {
			$prepared['create_time'] = time();
			DB::insert("l_flags", $prepared);
			$iid = DB::insertId();
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

	public static function getList($data) {
		$where = "is_deleted=0";
		$data = DB::query("SELECT * FROM l_flags WHERE $where");
		foreach($data as $key=>$item) {
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
							move_uploaded_file($file['tmp_name']);
							$fileData = [ "type" => 0, "name" => $file['name'], "origin_name" => $file['name'], "mime" => $file['type'], "size" => $file['size'], "origin_key" => $key ];
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
	
	public static function touch($id) {
		$id = (int) $id;
		DB::query("UPDATE l_news SET touch_time=".time()." WHERE id=$id");
	}

	public static function get($id) {
		$id = (int) $id;
		$data = DB::getSingle("SELECT * FROM l_news WHERE id=$id AND is_deleted=0");
		return $data;
	}
	
	public static function getList($data) {
		$where = "is_deleted=0";
		$data = DB::query("SELECT * FROM l_flags WHERE $where");
		foreach($data as $key=>$item) {
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
			print_r($news);
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
		$fields = [	"???",
					"???" ];
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
		return $data;
	}
	
	public static function getList($data) {
		$where = "is_deleted=0";
		$data = DB::query("SELECT * FROM l_files WHERE $where");
		foreach($data as $key=>$item) {
		}
		return $data;
	}
	
}

class Users {
	
	public static function create($data, $userId) {
		$userId = (int) $userId;
		$proceed = true;
		$prepared = [];
		$fields = [	"???",
					"???" ];
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
	
	public static function getList($data) {
		$where = "is_deleted=0";
		$data = DB::query("SELECT * FROM l_users WHERE $where");
		foreach($data as $key=>$item) {
		}
		return $data;
	}
	
}

echo News::create([ "source_url" => "http://yandex.ru", "title" => "Боже мой, это же Яндекс", "synopsis" => "Какой-то краткий текст" ]);

?>