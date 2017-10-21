<?php

error_reporting(1);

$root = dirname(__FILE__);
$smarty = null;

class DB {

	private static $config = [
		"user" => "root",
		"password" => "dwj03mnexr",
		"name" => "hackathon"
	];

	private static $handler = null;

	private static function connect() {
		self::$handler = mysqli_connect("localhost", self::$config['user'], self::$config['password'], self::$config['name']);
		if (!self::$handler) self::error(mysqli_connect_errno(), mysqli_connect_error());
		mysqli_set_charset(self::$handler, "utf8");
		return self::$handler;
	}

	public static function processQuery($query) {
		return $query;
	}

	public static function query($query) {
		if (!self::$handler) self::$handler = self::connect();
		$query = self::processQuery($query);
		$result = mysqli_query(self::$handler, $query);
		$out = [];
		if (is_object($result)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$out[] = $row;
			}
		}
		if (is_object($result)) $result->close();
		return $out;
	}
	
	public static function insertId() {
		return mysqli_insert_id(self::$handler);
	}

	public static function affectedRows() {
		return mysql_affected_rows(self::$handler);
	}

	public static function insert($table, $data, $updateData) {
		$keyPart = [];
		$valuePart = [];
		if ($data) {
			foreach($data as $key=>$item) {
				$keyPart[] = "`".$key."`";
				if (!is_array($item)) {
					$valuePart[] = self::escape(trim($item));
				} else {
					$valuePart[] = self::escape(json_encode($item));
				}
			}
			$keyPart = implode(",", $keyPart);
			$valuePart = implode(",", $valuePart);
			if (!$updateData) {
				self::query("INSERT IGNORE INTO $table ($keyPart) VALUES ($valuePart)");
			} else {
				$updatePart = [];
				foreach($updateData as $key=>$item) {
					if (!is_array($item)) {
						$updatePart[] = "`".$key."`=".self::escape($item); 
					} else {
						$updatePart[] = "`".$key."`=".self::escape(json_encode($item)); 
					}
				}
				$updatePart = implode($updatePart);
				self::query("INSERT INTO $table ($keyPart) VALUES ($valuePart) ON DUPLICATE KEY UPDATE $updatePart");
			}
		}
	}

	public static function getValue($query) {
		if (!self::$handler) self::$handler = self::connect();
		$query = self::processQuery($query);
		$result = mysqli_query(self::$handler, $query);
		$out = [];
		if (is_object($result)) {
			$row = mysqli_fetch_row($result);
		} else {
			$row = null;
		}
		if (is_object($result)) $result->close();
		if ($row) {
			return (int) $row[0];
		} else {
			return 0;
		}
	}

	public static function getSingle($query) {
		if (!self::$handler) self::$handler = self::connect();
		$query = self::processQuery($query);
		$result = mysqli_query(self::$handler, $query);
		$out = [];
		if (is_object($result)) {
			$row = mysqli_fetch_assoc($result);
		} else {
			$row = null;
		}
		if (is_object($result)) $result->close();
		if ($row) {
			return $row;
		} else {
			return null;
		}
	}

	public static function escape($s) {
		if (!self::$handler) self::$handler = self::connect();
		return "'".mysqli_real_escape_string(self::$handler, $s)."'";
	}

	public static function error($errNo, $errText) {
		die("[SQL error ".$errNo.": ".$errText."]");
	}
	
}

function initSmarty() {
	global $smarty, $root;
	$smarty = new \Smarty;
	$smarty->debugging=false;
	$smarty->caching=false;
	$smarty->cache_lifetime=0;
	$smarty->template_dir = $root.'/templates';
	$smarty->compile_dir = $root.'/templates/compiled';
	$smarty->cache_dir = $root.'/cache';
	$smarty->config_dir = $root.'/cache';
}

function writeLog($type, $name, $context, $data) {	
	$type = (int) $type;
	$data = json_encode($data);
	DB::query("INSERT INTO l_log SET name=".DB::escape($name).", time=".time().", date=".DB::escape(date("Y-m-d")).", type=$type, context=".DB::escape($context).", data=".DB::escape($data));
}

?>