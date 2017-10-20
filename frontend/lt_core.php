<?php

error_reporting(1);

class DB {

	private static $config = [
		"user" => "root",
		"password" => "dwj03mnexr",
		"name" => "hackathon"
	];

	private static $handler = null;

	private static function connect() {
		$handler = mysqli_connect("localhost", self::$config['user'], self::config['password'], self::$config['name']);
		if (!self::$handler) self::error(mysqli_connect_errno(), mysqli_connect_error());
		return self::$handler;
	}

	public static function processQuery($query) {
		return $query;
	}

	public static function query($query) {
		if (!$self::handler) $handler = self::connect();
		$query = self::processQuery($query);
		$result = mysqli_query($query);
		$out = [];
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$out[] = $row;
		}
		$result->close();
		return $out;
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
				self::query("INSERT IGNORE INTO $table ($keyPart) VALUES ($keyPart)");
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
		$result = mysqli_query($query);
		$out = [];
		$row = $result->fetch_array(MYSQL_NUM);
		$result->close();
		if ($row) {
			return $row[0];
		} else {
			return 0;
		}
	}

	public static function getSingle($query) {
		if (!self::$handler) self::$handler = self::connect();
		$query = self::processQuery($query);
		$result = mysqli_query($query);
		$out = [];
		$row = $result->fetch_array(MYSQL_ASSOC);
		$result->close();
		if ($row) {
			return $row;
		} else {
			return null;
		}
	}

	public static function escape($s) {
		return "'".mysql_real_escape_string($s)."'";
	}

	public static function error($errNo, $errText) {
		die("[SQL error ".$errNo.": ".$errText."]");
	}
	
}

function writeLog($type, $name, $context, $data) {	
	$type = (int) $type;
	$data = json_encode($data);
	DB::query("INSERT INTO l_log SET name=".DB::escape($name).", time=".time().", date=".DB::escape(date("Y-m-d")).", type=$type, context=".DB::escape($context).", data=".DB::escape($data));
}

?>