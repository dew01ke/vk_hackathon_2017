<?php

error_reporting(1);

header("Content-Type: application/json");

require_once("lt_entities.php");
require_once("lt_vk.php");

session_start();

$path = $_GET['request_path'];
if ($path[0] == "/") $path = substr($path, 1);
$path = explode("/", $path);

$requestEntity = strtolower($path[0]);
$requestMethod = $path[1];

$reply = [];
$success = false;

if ($_POST) {
	$request = $_POST;
} else {
	if ($HTTP_RAW_POST_DATA) {
		$request = json_decode($HTTP_RAW_POST_DATA, true);
	} else {
		$request = $_GET;
	}
}

if (!$request) $request = [];
if ($_FILES and !$request['files']) $request['files'] = $_FILES;

$userId = 0;
$userData = array();
if ($request['token']) {
	$extToken = $request['token'];
	$originId = $request['origin_id'];
	$userData = Users::auth($token, $originId);
	if ($userData) {
		$userId = $userData['id'];
	} else {
		$reply['status'] = 201;
		$reply['statusMessage'] = "Authentication failed.";
	}
}

if ($userData) {
switch ($requestEntity) {

		case "flags": {
		
			switch ($requestMethod) {
				
				case "create": {

					$newFlagId = Flags::create($request, $userId);
					if ($newFlagId) {
						$reply['id'] = $newFlagId;
						$success = true;
					}
				
					break;
				}

				case "delete": {

					$id = (int) $request['id'];
					if ($id) {
						Flags::delete($id, $userId);
						$success = true;
					}
				
					break;
				}

				case "get": {

					$id = (int) $request['id'];
					if ($id) {
						$data = Flags::get($id);
						if ($data) {
							$reply['item'] = $data;
							$success = true;
						}
					}
				
					break;
				}

				case "getByName": {

					$name = trim($request['name']);
					if ($name) {
						$data = Flags::getByName($name);
						if ($data) {
							$reply['item'] = $data;
							$success = true;
						}
					}
				
					break;
				}

				case "getList": {

					$data = Flags::getList($request);
					if ($data) {
						$reply['list'] = $data;
						$success = true;
					}
				
					break;
				}
				
			}
		
			break;
		}
	
		case "news": {
		
			switch ($requestMethod) {
				
				case "create": {

					$newNewsId = News::create($request, $userId);
					if ($newNewsId) {
						$reply['id'] = $newNewsId;
						$success = true;
					}
				
					break;
				}

				case "update": {

					$id = (int) $request['id'];
					if ($id) {
						News::update($id, $request, $userId);
						$success = true;
					}
				
					break;
				}

				case "delete": {

					$id = (int) $request['id'];
					if ($id) {
						News::delete($id, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "get": {

					$id = (int) $request['id'];
					if ($id) {
						$data = News::get($id);
						if ($data) {
							$reply['item'] = $data;
							$success = true;
						}
					}
				
					break;
				}

				case "rate": {

					$id = (int) $request['id'];
					$rating = (int) $request['rating']; 
					if ($id) {
						$rating = News::rate($id, $rating, $userId);
						$reply['rating'] = $data;
						$success = true;
					}
				
					break;
				}
				
				case "getFlags": {

					$id = (int) $request['id'];
					if ($id) {
						$data = News::getFlags($id);
						if ($data) {
							$reply['list'] = $data;
							$success = true;
						}
					}
				
					break;
				}
				
				case "getPipeline": {

					$id = (int) $request['id'];
					if ($id) {
						$data = News::getPipeline($id);
						if ($data) {
							$reply['list'] = $data;
							$success = true;
						}
					}
				
					break;
				}
				
				case "getList": {

					$data = News::getList($request);
					if ($data) {
						$reply['list'] = $data;
						$reply['count_by_stage'] = Stats::getNewsByStage();
						$success = true;
					}
				
					break;
				}
				
				case "setStage": {

					$id = (int) $request['id'];
					$stageId = (int) $request['stage_id'];
					if ($id) {
						News::setStage($id, $stageId, null, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "addComment": {

					$id = (int) $request['id'];
					$comment = $request['comment'];
					if ($id) {
						News::addComment($id, $comment, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "advancePipeline": {

					$id = (int) $request['id'];
					if ($id) {
						$newActionId = News::advancePipeline($id, $request, $userId);
						if ($newActionId) {
							$reply['id'] = $newActionId;
							$success = true;
						}
					}
				
					break;
				}
				
				case "block": {

					$id = (int) $request['id'];
					if ($id) {
						News::block($id, $userId, $request['block_for']);
						$success = true;
					}
				
					break;
				}
				
				case "unblock": {

					$id = (int) $request['id'];
					if ($id) {
						News::unblock($id, $userId);
						$success = true;
					}
				
					break;
				}

				case "addFile": {

					$id = (int) $request['id'];
					$fileId = (int) $request['file_id'];
					if ($id) {
						News::addFile($id, $fileId, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "removeFile": {

					$id = (int) $request['id'];
					$fileId = (int) $request['file_id'];
					if ($id) {
						News::removeFile($id, $fileId, $userId);
						$success = true;
					}
				
					break;
				}

				case "addFlag": {

					$id = (int) $request['id'];
					$flagId = (int) $request['flag_id'];
					if ($id) {
						News::addFlag($id, $flagId, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "removeFlag": {

					$id = (int) $request['id'];
					$flagId = (int) $request['flag_id'];
					if ($id) {
						News::removeFlag($id, $flagId, $userId);
						$success = true;
					}
				
					break;
				}

			}
		
			break;
		}

		case "files": {
		
			switch ($requestMethod) {
				
				case "create": {
					
					$newFileId = Files::create($request, $userId);
					if ($newFileId) {
						$reply['id'] = $newFileId;
						$success = true;
					}
					
					break;
				}
				
				case "delete": {

					$id = (int) $request['id'];
					if ($id) {
						Files::delete($id, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "get": {

					$id = (int) $request['id'];
					if ($id) {
						$data = Files::get($id);
						if ($data) {
							$reply['item'] = $data;
							$success = true;
						}
					}
				
					break;
				}
				
				case "getList": {

					$data = Files::getList($request);
					if ($data) {
						$reply['list'] = $data;
						$success = true;
					}
				
					break;
				}

			}
		
			break;
		}

		case "users": {
		
			switch ($requestMethod) {
				
				case "create": {

					$newUserId = Users::create($request, $userId);
					if ($newUserId) {
						$reply['id'] = $newUserId;
						$success = true;
					}
				
					break;
				}

				case "update": {

					$id = (int) $request['id'];
					if ($id) {
						Users::update($id, $request, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "delete": {
					
					$id = (int) $request['id'];
					if ($id) {
						Users::delete($id, $userId);
						$success = true;
					}
					
					break;
				}
				
				case "get": {

					$id = (int) $request['id'];
					if ($id) {
						$data = Users::get($id);
						if ($data) {
							$reply['item'] = $data;
							$success = true;
						}
					}
				
					break;
				}
				
				case "getByOrigin": {
					
					break;
				}
				
				case "getList": {

					$data = Users::getList($request);
					if ($data) {
						$reply['list'] = $data;
						$success = true;
					}
				
					break;
				}
				
			}
		
			break;
		}
		
		case "roles": {
		
			switch ($requestMethod) {
				
				case "getAll": {
					
					$list = Roles::getAll();
					if ($list) {
						$reply['list'] = $list;
						$success = true;
					}
					
					break;
				}
				
			}
		
			break;
		}

		case "stages":
		
			switch ($requestMethod) {
				
				case "getAll": {

					$list = Stages::getAll();
					if ($list) {
						$reply['list'] = $list;
						$success = true;
					}
					
					break;
				}
				
			}
		
		break;

		case "stats":
		
			switch ($requestMethod) {
				
				case "countByStage": {

					$list = Stats::getNewsByStage();
					if ($list) {
						$reply['count_by_stage'] = $list;
						$success = true;
					}
					
					break;
				}
				
			}
		
		break;

		case "sources": {
		
			switch ($requestMethod) {
				
				case "create": {

					$newSourceId = Sources::create($request, $userId);
					if ($newSourceId) {
						$reply['id'] = $newSourceId;
						$success = true;
					}
				
					break;
				}

				case "update": {

					$id = (int) $request['id'];
					if ($id) {
						Sources::update($id, $request, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "delete": {

					$id = (int) $request['id'];
					if ($id) {
						Sources::delete($id, $userId);
						$success = true;
					}
				
					break;
				}
				
				case "blacklist": {
					
					break;
				}
				
				case "get": {
					
					$id = (int) $request['id'];
					if ($id) {
						$data = Sources::get($id);
						if ($data) {
							$reply['item'] = $data;
							$success = true;
						}
					}
					
					break;
				}
				
				case "getByDomain": {

					$data = Sources::getByDomain($request['name']);
					if ($data) {
						$reply['item'] = $data;
						$success = true;
					}
				
					break;
				}
				
				case "getList": {

					$data = Sources::getList($request);
					if ($data) {
						$reply['list'] = $data;
						$success = true;
					}
				
					break;
				}
				
			}
		
			break;
		}

		case "notifications": {
		
			switch ($requestMethod) {

				case "create": {

					$newNotificationId = Files::create($request, $userId);
					if ($newNotificationId) {
						$reply['id'] = $newNotificationId;
						$success = true;
					}
				
					break;
				}
			
				case "get": {
					
					$id = (int) $request['id'];
					if ($id) {
						$data = Notifications::get($id, $userId);
						if ($data) {
							$reply['item'] = $data;
							$success = true;
						}
					}
					
					break;
				}
				
				case "getList": {

					$data = Notifications::getList($request, $userId);
					if ($data) {
						$reply['list'] = $data;
						$success = true;
					}
				
					break;
				}

				case "getFreshCount": {
					
					$count = Notifications::getFreshCount($userId);
					$reply['count'] = $count;
					$success = true;
					
					break;
				}

				case "getLastId": {
					
					$id = Notifications::getLastId($userId);
					$reply['id'] = $id;
					$success = true;
					
					break;
				}

				case "mark": {
					
					$id = (int) $request['id'];
					if ($id) {
						$data = Notifications::mark($id, $userId);
						$success = true;
					}
					
					break;
				}

				case "markTo": {
					
					$id = (int) $request['id'];
					if ($id) {
						$data = Notifications::markTo($id, $userId);
						$success = true;
					}
					
					break;
				}
				
			}
		
			break;
		}
		
}
$reply['user_profile'] = $userData;
} else {
	if (!$reply['status']) {
		$reply['status'] = 200;
		$reply['statusMessage'] = "Not authenticated.";
	}
}

if ($success) {
	$reply['status'] = 100;
	$reply['statusMessage'] = "OK";
} else {
	if (!$reply['status']) {
		$reply['status'] = 101;
		$reply['statusMessage'] = "Empty result";
	}
}

echo json_encode($reply);

?>