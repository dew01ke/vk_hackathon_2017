<?php

require_once 'lt_core.php';
require_once 'lt_entities.php';
//ini_set('display_errors', 1);
//error_reporting(-1);
$secret = 'xG2kNupglLf88BF3toKq';
session_start();
$vkUser = null;
if (array_key_exists('api_url', $_GET)) {
  $sign = '';
  foreach ($_GET as $i => $p) {
    if ($i === 'hash' || $i === 'sign')
      continue;
    $sign .= $p;
  }
  
  $sig = $secret ? hash_hmac('sha256', $sign, $secret) : '';
  if ($sig !== $_GET['sign']) {
    http_response_code(404);
    exit;
  }
  $_SESSION['vk'] = $_GET;
}

$vkUserId = $_SESSION['vk']['viewer_id'];
if (!$vkUserId) {
  http_response_code(404);
  session_unset();
  exit;
}

$user = \Users::getByOrigin($vkUserId);

$root = '.';
initSmarty();

class Request
{
  public $path;
  
  function __construct()
  {
    $fullPath = parse_url(filter_input(INPUT_SERVER, 'REQUEST_URI'), PHP_URL_PATH);
    $this->path = strpos($fullPath, '/vk-iframe.php') === 0
      ? substr($fullPath, strlen('/vk-iframe.php')) : $fullPath;
  }
}

$r = new \Request();
$matches = [];
if ($r->path === '' || $r->path === '/') {
  $news = \News::getList(['limit' => 3, 'origin_user' => $user['id'], 'order' => ['create_time' => 'DESC']]);
  $top = \News::getList(['limit' => 3, 'origin_user' => $user['id']], 'order' => ['rating' => 'DESC']);
  header('Content-Type: text/html');

  $smarty
    ->assign('top1', array_pop($top))
    ->assign('top2', array_pop($top))
    ->assign('top3', array_pop($top))
    ->assign('news', $news)
    ->display('vk-iframe.html.tpl');
  exit;
} elseif ($r->path === '/news/add') {
  header('Content-Type: application/json');
  $data = filter_input_array(INPUT_POST, [
					  'text' => FILTER_DEFAULT,
					  'url' => FILTER_VALIDATE_URL,
					  'title' => FILTER_DEFAULT,
					  'anonymous' => FILTER_VALIDATE_BOOLEAN,
					  'images' => ['filter' => FILTER_DEFAULT,
						       'flags' => FILTER_REQUIRE_ARRAY],
					  ]);
  if (!count(array_filter($data))) {
    echo json_encode(['errors' => ['Все поля пустые :( Заполните хотя бы одно.']]);
    exit;
  }
  $usersData = json_decode(file_get_contents('https://api.vk.com/method/users.get?user_ids=' . $vkUserId));
  $vkUser = $usersData->response[0];
  $id = \News::create([
		       'ext_user' => ['channel' => 'vk', 'id' => $vkUserId, 'first_name' => $vkUser->first_name, 'last_name' => $vkUser->last_name],
		       'title' => $data['title'],
		       'source_url' => $data['url'],
		       'synopsis' => $data['text'],
		       'files' => $data['files'],
		       'anonymous' => $data['anonymous'],
		       ]);
  if ($id) {
    $newsItem = \News::get($id);
    echo json_encode(['messages' => ['Ваша новость добавлена!'], 'newsItem' => $newsItem]);
  } else {
    echo json_encode(['errors' => ['Что-то пошло не так!']]);
  }
  exit;  
} elseif (preg_match('#^/news/(\d+)/delete/?$#', $r->path, $matches)) {
  header('Content-Type: application/json');
  $newsItem = \News::get($matches[1]);
  if (!$newsItem || !$user || $newsItem['user_id'] !== $user['id']) {
    echo json_encode(['errors' => ['Новость не найдена']]);
  } else {
    \News::delete($newItem['id'], $user['id']);
    echo json_encode(['messages' => ['Новость удалена']]);
  }
  exit;  
} elseif ($r->path === '/uploadFile') {
  header('Content-Type: application/json');
  $data = filter_input_array(INPUT_POST, [
					  
					  ]);

  exit;
} else {
  http_response_code(404);
  header('Content-Type: application/json');
  exit;
}
