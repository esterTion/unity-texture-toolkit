<?php

chdir(__DIR__);
date_default_timezone_set("Asia/Shanghai");
$rijndaelKey = "s%5VNQ(H$&Bqb6#3+78h29!Ft4wSg)ex";
$salt = "r!I@nt8e5i=";

$userID  = '775891250';
$viewerID = '910841675';
$udid = "600a5efd-cae5-41ff-a0c7-7deda751c5ed";

$userID = '807297179';
$viewerID = '995100198';
$udid = unlolfuscate('002423;541<818p713l356;558<788<512B1167165B452A426p575p3447527>745B625l856B6417737l515;535;726A7217263;113@551n768l838:516m832;624A287p772p121o626:634466828748373881115683521611874');

function _log($s) {
  //global $logFile;
  //fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo $s."\n";
}
function execQuery($db, $query) {
  $returnVal = [];
  $result = $db->query($query);
  $returnVal = $result->fetchAll(PDO::FETCH_ASSOC);
  return $returnVal;
}

function lolfuscate($s) {
  $mid = implode('',array_map(function($i){
    return rand(0,9).rand(0,9).chr(ord($i)+10).rand(0,9);
  }, str_split($s)));
  $post = rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999);
  return sprintf('%04x', strlen($s)).$mid.$post;
}
function unlolfuscate($in) {
  $num = hexdec(substr($in, 0, 4));
  $text = '';
  for ($i=6; $i<strlen($in) && strlen($text) < $num; $i+=4) {
      $text .= chr(ord($in[$i]) - 10);
  }
  return $text;
}
function encrypt256($string = '', $key, $iv) {
  $key = utf8_encode($key);

  //make it 32 chars long. pad with \0 for shorter keys
  $key = str_pad($key, 32, "\0");
  $padding = 32 - (strlen($string) % 32);
  $string .= str_repeat(chr(0), $padding);
  $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, $iv);
  return rtrim($encrypted);
}

function decrypt256($string = '', $key, $iv) {
  return trim(
          mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, $iv),
          "\0");
}

$curl=curl_init();
function callapi($path, $data) {
  global $curl;
  global $userID;
  global $viewerID;
  global $rijndaelKey;
  global $udid;
  global $sid;
  global $salt;
  $iv = rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999);
  $data['viewer_id'] = $iv.base64_encode(encrypt256($viewerID, $rijndaelKey, $iv));
  $data = base64_encode(msgpack_pack($data));
  $key = rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999).rand(10000000,99999999);
  $msgIV = str_replace('-','',$udid);
  $body = base64_encode(encrypt256($data, $key, $msgIV).$key);

  $sid = $viewerID . $udid;
  $header = [
    'PARAM: '.sha1($udid.$viewerID.$path.$data),
    'KEYCHAIN: 127767137',
    'USER_ID: '.lolfuscate($userID),
    'CARRIER:  ',
    'UDID: '.lolfuscate($udid),
    'APP_VER: '.file_get_contents('../appver'),
    'RES_VER: '.json_decode(file_get_contents('../last_version'),true)['TruthVersion'],
    'IP_ADDRESS: 127.0.0.1',
    'DEVICE_NAME: iPad5,3',
    'X-Unity-Version: 5.4.5p1',
    'SID: '.md5($sid.$salt),
    'GRAPHICS_DEVICE_NAME: Apple A8X GPU',
    'DEVICE_ID: 3F015210-232E-46BD-B794-17C12F1DEB60',
    'PLATFORM_OS_VERSION: iPhone OS 11.2.2',
    'DEVICE: 1',
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: BNEI0242/116 CFNetwork/893.14.2 Darwin/17.3.0',
    'IDFA: 5D4DA824-C48A-4390-85F2-17EAAEB1F5FD',
    'Accept-Language: zh-cn'
  ];
  //return ['body'=>$body,'header'=>$header];
  curl_setopt_array($curl, [
    CURLOPT_URL=>'https://apis.game.starlight-stage.jp'.$path,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_HTTPHEADER=>$header,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$body,
    CURLOPT_HEADER=>false,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_ENCODING=>'gzip, deflate'
  ]);
  $response = curl_exec($curl);

  $response = base64_decode($response);
  $key = substr($response, -32, 32);
  $iv = str_replace('-','',$udid);
  $response = msgpack_unpack(base64_decode(decrypt256(substr($response, 0, -32), $key, $iv)));
  return $response;
}

$db = new PDO('sqlite:../master.db');

$time = time();
$time -= $time % 900;
$now = date('Y-m-d H:i:s', $time + 3600 - 50); // 第一个整点不获取
$now_slight_after = date('Y-m-d H:i:s', $time + 3600 + 850); // 最后一个整点不获取

//$now = '2018-05-06 00:00:00';
//$now_slight_before = '2018-05-06 00:00:00';

$current = execQuery($db, "SELECT * FROM event_data WHERE event_start < \"${now}\" AND event_end > \"${now}\"");
if (empty($current)) {
  // final_result
  $current = execQuery($db, "SELECT * FROM event_data WHERE result_start < \"${now_slight_after}\" AND result_start > \"${now}\"");
  if (empty($current)) exit;
}

$current = $current[0];
$id = $current['id'];
$type = $current['type'];

$fetchPt    = false;
$fetchScore = false;
switch ($type) {
  case 1:
    $fetchPt = true;
    $fetchScore = true;
    $ptTable = 'atapon_point_rank_disp';
    $scoreTable = 'atapon_score_rank_disp';
    $path = '/event/atapon/ranking_list';
    break;
  case 3:
    $fetchPt = true;
    $fetchScore = true;
    $ptTable = 'medley_point_rank_disp';
    $scoreTable = 'medley_score_rank_disp';
    $path = '/event/medley/ranking_list';
    break;
  case 5:
    $fetchScore = true;
    $scoreTable = 'tour_score_rank_disp';
    $path = '/event/tour/ranking_list';
    break;
}

$ptBorders=[];
if ($fetchPt) {
  foreach(execQuery($db, "SELECT rank_min FROM $ptTable WHERE event_id=$id") as $row) {
    $ptBorders[] = $row['rank_min']+0;
  }
}
$scoreBorders=[1];
if ($fetchScore) {
  foreach(execQuery($db, "SELECT rank_max FROM $scoreTable WHERE event_id=$id") as $row) {
    $scoreBorders[] = $row['rank_max']+1;
  }
}
unset($db);
gc_collect_cycles();

if (!$fetchPt && !$fetchScore) exit;

$time = time();
$time -= $time%900;
$time *= 1000;
$availableEvents = ['current'=>$id, 'available'=>[], 'name'=>[]];
$eventData = [];
if (file_exists("data/$id.json")) $eventData = json_decode(file_get_contents("data/$id.json"), true);
if (file_exists("data/available.json")) $availableEvents = json_decode(file_get_contents("data/available.json"), true);
if (!in_array($id, $availableEvents['available'])) {
  array_unshift($availableEvents['available'], $id);
  $availableEvents['name'][$id] = $current['name'];
}
$availableEvents['current'] = $id;
file_put_contents("data/available.json", json_encode($availableEvents));

function saveTop($type, &$data) {
  global $eventData;
  global $time;
  if (!isset($eventData['top'])) $eventData['top']=[];
  if (!isset($eventData['top'][$type])) $eventData['top'][$type]=[];
  $currentPool = &$eventData['top'][$type];

  foreach ($data['data']['ranking_list'] as &$player) {
    if (!isset($currentPool[$player['user_info']['viewer_id']]))
      $currentPool[$player['user_info']['viewer_id']] = [
        'info' => [],
        'data' => []
      ];
    $currentPool[$player['user_info']['viewer_id']]['info'] = $player['user_info'];
    $currentPool[$player['user_info']['viewer_id']]['data'][] = [
      $time, $player['score']
    ];
  }
}

if ($fetchPt) {
  if (!isset($eventData['pt'])) $eventData['pt'] = [];
  $max = 0;
  $missed=[];
  foreach($ptBorders as $border) {
    $data = callapi($path, [
      'page'=>$border/10+1,
      'ranking_type'=>1,
      'timezone'=>'09:00:00'
    ]);
    if (isset($eventData['pt'][$border])) $max = $border;
    
    if (!isset($data['data']['ranking_list'][0])) {
      //var_dump($border);
      //print_r($data);
      $missed[] = $border;
      continue;
    }
    $max = $border;
    if (!isset($eventData['pt'][$border])) $eventData['pt'][$border] = [];
    $eventData['pt'][$border][] = [$time, $data['data']['ranking_list'][0]['score']];
    if ($border == 1) saveTop('pt', $data);
  }
  //var_dump($max);
  //print_r($missed);
  foreach ($missed as $border) {
    if ($border > $max) continue;
    $retry = 0;
    while (1) {
      sleep(1);
      if ($retry++ > 5) break;
      $data = callapi($path, [
        'page'=>$border/10+1,
        'ranking_type'=>1,
        'timezone'=>'09:00:00'
      ]);
      if (!isset($data['data']['ranking_list'][0])) {
        //var_dump($border);
        continue;
      }
      if (!isset($eventData['pt'][$border])) $eventData['pt'][$border] = [];
      //echo $border.' '.$data['data']['ranking_list'][0]['score']."\n";
      $eventData['pt'][$border][] = [$time, $data['data']['ranking_list'][0]['score']];
      if ($border == 1) saveTop('pt', $data);
      break;
    }
  }
}

if ($fetchScore) {
  if (!isset($eventData['score'])) $eventData['score'] = [];
  $max = 0;
  $missed=[];
  foreach($scoreBorders as $border) {
    $data = callapi($path, [
      'page'=>$border/10+1,
      'ranking_type'=>2,
      'timezone'=>'09:00:00'
    ]);
    if (isset($eventData['score'][$border])) $max = $border;
    
    if (!isset($data['data']['ranking_list'][0])) {
      //var_dump($border);
      //print_r($data);
      $missed[] = $border;
      continue;
    }
    if (!isset($eventData['score'][$border])) $eventData['score'][$border] = [];
    $eventData['score'][$border][] = [$time, $data['data']['ranking_list'][0]['score']];
    if ($border == 1) saveTop('score', $data);
  }
  //var_dump($max);
  //print_r($missed);
  foreach ($missed as $border) {
    if ($border > $max) continue;
    $retry = 0;
    while (1) {
      sleep(1);
      if ($retry++ > 5) break;
      $data = callapi($path, [
        'page'=>$border/10+1,
        'ranking_type'=>2,
        'timezone'=>'09:00:00'
      ]);
      if (!isset($data['data']['ranking_list'][0])) {
        //var_dump($border);
        continue;
      }
      if (!isset($eventData['score'][$border])) $eventData['score'][$border] = [];
      //echo $border.' '.$data['data']['ranking_list'][0]['score']."\n";
      $eventData['score'][$border][] = [$time, $data['data']['ranking_list'][0]['score']];
      if ($border == 1) saveTop('score', $data);
      break;
    }
  }
}

file_put_contents("data/$id.json", json_encode($eventData));
