<?php

class VK {
	
	private static $token;

	public static function setToken($token, $userId) {
		self::$token = $token;
	}
	
	public static function tryToken($token) {
		self::setToken($token);
		$profile = self::getUserProfile();
		if ($profile) {
			/*
			$prepared = [
					"user_id" => $userId,
					"token" => $token,
					"create_time" => time(),
					"is_active" => 1
				];
			DB::insert("l_auth_tokens", $prepared, [ "is_active" => 1 ]);
			*/
			return true;
		} else {
			return false;
		}
	}

	public static function request($method, $data, $token) {
		if (!$token) $token = self::$token;
		$url = "https://api.vk.com/method/$method?access_token=".urlencode($token)."&v=5.68";
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		$res = curl_exec($curl);
		curl_close($curl);
		$res = json_decode($res, true);
		return $res;
	}
	
	public static function getUserProfile($id) {
		$res = self::request("users.get", [ "user_ids" => $id ]);
		return $res;
	}	
	
	
}


?>