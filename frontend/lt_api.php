<?php

require_once("lt_core.php");

class Flags {
	
	public static function create($data) {
		DB::insert("l_flags", $prepared);
		$iid = mysqli_insert_id();
		return $iid;
	}
	
	public static function delete($id) {
		$id = (int) $id;
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
	
	public static function create($data) {
		DB::insert("l_news", $prepared);
		$iid = mysqli_insert_id();
		return $iid;
	}
		
	public static function delete($id) {
		$id = (int) $id;
		DB::query("UPDATE l_news SET is_deleted=1 WHERE id=$id");
		self::touch($id);
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
	

	public static function setStage($id, $stageId) {
		$id = (int) $id;
		$stageId = (int) $stageId;
		DB::query("UPDATE l_news S SET is_deleted=1 WHERE id=$id");
		self::touch($id);
	}

	public static function advancePipeline($id, $data) {
		$id = (int) $id;
		$news = self::get($id);
		if ($news) {
			DB::insert("l_news_pipeline", $data);
			self::touch($id);
		}
	}

	public static function block($id, $userId, $t) {
		$id = (int) $id;
		$userId = (int) $userId;
		$t = (int) $t;
		if (!$t) $t = 600;
		DB::query("UPDATE l_news SET blocked_by=$userId, blocked_till=".(time()+$t)." WHERE id=$id");
		self::touch($id);
	}

	public static function unblock($id) {
		$id = (int) $id;
		DB::query("UPDATE l_news SET blocked_by=0, blocked_till=0 WHERE id=$id");
		self::touch($id);
	}

	public static function addFlag($id, $flagId, $userId) {
		$id = (int) $id;
		$flagId = (int) $flagId;
		$userId = (int) $userId;
		DB::insert("l_news_flags", [ "news_id" => $id, "flag_id" => $flagId, "set_by" => $userId, "set_time" => time() ]);
		self::touch($id);
	}
	
	public static function removeFlag($id, $flagId, $userId) {
		$id = (int) $id;
		$flagId = (int) $flagId;
		DB::query("DELETE FROM l_news_flags WHERE news_id=$id AND flag_id=$flagId");
		self::touch($id);
	}
	
}

class Files {
	
	public static function create($data) {
		DB::insert("l_files", $prepared);
		$iid = mysqli_insert_id();
		return $iid;
	}

	public static function delete($id) {
		$id = (int) $id;
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
	
	public static function create($data) {
		DB::insert("l_users", $prepared);
		$iid = mysqli_insert_id();
		return $iid;
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
	
	public static function getList($data) {
		$where = "is_deleted=0";
		$data = DB::query("SELECT * FROM l_users WHERE $where");
		foreach($data as $key=>$item) {
		}
		return $data;
	}
	
}

?>