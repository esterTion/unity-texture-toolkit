<?php
chdir(__DIR__);
require_once 'UnityBundle.php';
require_once 'UnityAsset.php';
require_once 'diff_parse.php';
if (!file_exists('last_version')) {
  $last_version = array('TruthVersion'=>0,'hash'=>'');
} else {
  $last_version = json_decode(file_get_contents('last_version'), true);
}
$logFile = fopen('redive.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  //echo $s."\n";
}
function execQuery($db, $query) {
  $returnVal = [];
  /*if ($stmt = $db->prepare($query)) {
    $result = $stmt->execute();
    if ($result->numColumns()) {
      $returnVal = $result->fetchArray(SQLITE3_ASSOC);
    }
  }*/
  $result = $db->query($query);
  $returnVal = $result->fetchAll(PDO::FETCH_ASSOC);
  return $returnVal;
}
function autoProxy() {
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL=>'https://www.us-proxy.org/',
    CURLOPT_HTTPHEADER=>[
      'Host: www.us-proxy.org',
      'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/60.0.1',
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Language: zh-CN,zh-TW;q=0.7,en-US;q=0.3',
      'Accept-Encoding: gzip, deflate, br',
      'Connection: keep-alive',
      'Upgrade-Insecure-Requests: 1'
    ],
    CURLOPT_CONNECTTIMEOUT=>3,
    CURLOPT_HEADER=>0,
    CURLOPT_RETURNTRANSFER=>1,
    CURLOPT_SSL_VERIFYPEER=>false
  ]);
  $proxylist = brotli_uncompress(curl_exec($curl));
  preg_match_all('/<tr><td>(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})<\/td><td>(\d+)<\/td>/', $proxylist, $matches);
  //print_r($matches);

  curl_setopt($curl, CURLOPT_URL, 'https://app.priconne-redive.jp/');
  $oldproxy = file_get_contents('currentproxy.txt');
  for ($i=0; $i<count($matches[1]); $i++) {
    $proxy = $matches[1][$i].':'.$matches[2][$i];
    if ($proxy == $oldproxy) continue;
    curl_setopt($curl, CURLOPT_PROXY, $proxy);
    curl_exec($curl);
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 404) {
      /*
      $oldproxy = file_get_contents('currentproxy.txt');
      list($ip, $port) = explode(':', $oldproxy);
      $search  = [' -d '.$ip.' --dport '.$port.' ', ' --to-destination '.$ip.':'.$port];
      
      list($ip, $port) = explode(':', $proxy);
      $replace = [' -d '.$ip.' --dport '.$port.' ', ' --to-destination '.$ip.':'.$port];
      file_put_contents('/etc/firewalld/direct.xml', str_replace($search, $replace, file_get_contents('/etc/firewalld/direct.xml')));
      exec('/usr/bin/firewall-cmd --reload >/dev/null');
      */
      _log('Found new proxy: '. $proxy);
      file_put_contents('currentproxy.txt', $proxy);
      return true;
    };
  }
  return false;
}

function encodeValue($value) {
  $arr = [];
  foreach ($value as $key=>$val) {
    $arr[] = '/*'.$key.'*/' . (is_numeric($val) ? $val : ('"'.str_replace('"','\\"',$val).'"'));
  }
  return implode(", ", $arr);
}
function do_commit($TruthVersion, $db = NULL) {
  exec('git diff --cached | sed -e "s/@@ -1 +1 @@/@@ -1,1 +1,1 @@/g" >a.diff');
  $versionDiff = parse_db_diff('a.diff', $master, [
    'clan_battle_period.sql' => 'diff_clan_battle', // clan_battle
    'dungeon_area_data.sql' => 'diff_dungeon_area', // dungeon_area
    'gacha_data.sql' => 'diff_gacha',               // gacha
    'quest_area_data.sql' => 'diff_quest_area',     // quest_area
    'story_data.sql' => 'diff_story_data',          // story_data
    'unit_data.sql' => 'diff_unit',                 // unit
    'experience_team.sql' => 'diff_exp',            // experience
    'unit_promotion.sql' => 'diff_rank',            // rank
    'hatsune_schedule.sql' => 'diff_event',         // event,
    'campaign_schedule.sql' => 'diff_campaign',     // campaign
  ]);
  unlink('a.diff');
  $versionDiff['ver'] = $TruthVersion;
  $versionDiff['time'] = time();
  $versionDiff['timeStr'] = date('Y-m-d H:i', $versionDiff['time'] + 3600);

  $diff_send = [];
  $commitMessage = [$TruthVersion];
  if (isset($versionDiff['new_table'])) {
    $diff_send['new_table'] = $versionDiff['new_table'];
    $commitMessage[] = '- '.count($diff_send['new_table']).' new table: '. implode(', ', $diff_send['new_table']);
  }
  if (isset($versionDiff['unit'])) {
    $diff_send['card'] = array_map(function ($a){ return str_repeat('★', $a['rarity']).$a['name'];}, $versionDiff['card']);
    $commitMessage[] = '- new unit: '. implode(', ', $diff_send['card']);
  }
  if (isset($versionDiff['event'])) {
    $diff_send['event'] = array_map(function ($a){ return $a['name'];}, $versionDiff['event']);
    $commitMessage[] = '- new event '. implode(', ',$diff_send['event']);
  }
  if (isset($versionDiff['gacha'])) {
    $diff_send['gacha'] = array_map(function ($a){ return $a['detail'];}, $versionDiff['gacha']);
    $commitMessage[] = '- new gacha '. implode(', ',$diff_send['gacha']);
  }
  if (isset($versionDiff['quest_area'])) {
    $diff_send['quest_area'] = array_map(function ($a){ return $a['name'];}, $versionDiff['quest_area']);
    $commitMessage[] = '- new quest area '. implode(', ',$diff_send['quest_area']);
  }
  if (isset($versionDiff['clan_battle'])) {
    $commitMessage[] = '- new clan battle';
  }
  if (isset($versionDiff['dungeon_area'])) {
    $diff_send['dungeon_area'] = array_map(function ($a){ return $a['name'];}, $versionDiff['dungeon_area']);
    $commitMessage[] = '- new dungeon area '. implode(', ',$diff_send['dungeon_area']);
  }
  if (isset($versionDiff['story_data'])) {
    $diff_send['story_data'] = array_map(function ($a){ return $a['name'];}, $versionDiff['story_data']);
    $commitMessage[] = '- new story '. implode(', ',$diff_send['story_data']);
  }
  if (isset($versionDiff['max_lv'])) {
    $commitMessage[] = '- max level to '.$versionDiff['max_lv']['lv'];
  }
  if (isset($versionDiff['max_rank'])) {
    $commitMessage[] = '- max rank to '.$versionDiff['max_rank'];
  }
  $diff_send['diff'] = $versionDiff['diff'];

  exec('git commit -m "'.implode("\n", $commitMessage).'"');
  exec('git rev-parse HEAD', $hash);
  $versionDiff['hash'] = $hash[0];
  require_once __DIR__.'/../mysql.php';
  $mysqli->select_db('db_diff');
  $mysqli->query('REPLACE INTO redive (ver,data) vALUES ('.$TruthVersion.',"'.$mysqli->real_escape_string(brotli_compress(
    json_encode($versionDiff, JSON_UNESCAPED_SLASHES), 11, BROTLI_TEXT
  )).'")');
  exec('git push origin master');
  
  $data = json_encode(array(
    'game'=>'redive',
    'hash'=>$hash[0],
    'ver' =>$TruthVersion,
    'data'=>$diff_send
  ));
  $header = [
    'X-GITHUB-EVENT: push_direct_message',
    'X-HUB-SIGNATURE: sha1='.hash_hmac('sha1', $data, 'sec', false)
  ];
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL=>'https://redive.estertion.win/masterdb_subscription/webhook.php',
    CURLOPT_HEADER=>0,
    CURLOPT_RETURNTRANSFER=>1,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_HTTPHEADER=>$header,
    CURLOPT_POST=>1,
    CURLOPT_POSTFIELDS=>$data
  ));
  curl_exec($curl);
  curl_close($curl);
}

function main() {

global $last_version;
chdir(__DIR__);

//check app ver at 00:00
$appver = file_exists('appver') ? file_get_contents('appver') : '1.1.4';
$itunesid = 1134429300;
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL=>'https://itunes.apple.com/lookup?id='.$itunesid.'&lang=ja_jp&country=jp&rnd='.rand(10000000,99999999),
  CURLOPT_HEADER=>0,
  CURLOPT_RETURNTRANSFER=>1,
  CURLOPT_SSL_VERIFYPEER=>false
));
$appinfo = curl_exec($curl);
curl_close($curl);
if ($appinfo !== false) {
  $appinfo = json_decode($appinfo, true);
  if (!empty($appinfo['results'][0]['version'])) {
    $prevappver = $appver;
    $appver = $appinfo['results'][0]['version'];

    if (version_compare($prevappver,$appver, '<')) {
      file_put_contents('appver', $appver);
      _log('new game version: '. $appver);
      $data = json_encode(array(
        'game'=>'redive',
        'ver'=>$appver,
        'link'=>'https://itunes.apple.com/jp/app/id'.$itunesid
      ));
      $header = [
        'X-GITHUB-EVENT: app_update',
        'X-HUB-SIGNATURE: sha1='.hash_hmac('sha1', $data, 'sec', false)
      ];
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'https://redive.estertion.win/masterdb_subscription/webhook.php',
        CURLOPT_HEADER=>0,
        CURLOPT_RETURNTRANSFER=>1,
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>$header,
        CURLOPT_POST=>1,
        CURLOPT_POSTFIELDS=>$data
      ));
      curl_exec($curl);
      curl_close($curl);
    }
  }
}

//check TruthVersion
$game_start_header = [
  'Host: app.priconne-redive.jp',
  'User-Agent: princessconnectredive/12 CFNetwork/758.4.3 Darwin/15.5.0',
  'PARAM: 7db6304d4d0be697a1e37b14883a9bc7848c5665',
  'REGION_CODE: ',
  'BATTLE_LOGIC_VERSION: 1',
  'PLATFORM_OS_VERSION: iPhone OS 9.3.2',
  'Proxy-Connection: keep-alive',
  'DEVICE_ID: 06D993AF-FE6D-4778-8553-085FAF6707CE',
  'KEYCHAIN: 577247511',
  'GRAPHICS_DEVICE_NAME: Apple A9 GPU',
  'SHORT_UDID: 000961@277B487>163;656<178>844@861C276>212744265538282151286561554573842',
  'DEVICE_NAME: iPhone8,4',
  'BUNDLE_VER: ',
  'LOCALE: Jpn',
  'IP_ADDRESS: 192.168.1.112',
  'SID: 956c729eb8eb60d80239ea6c662ebd9b',
  'Content-Length: 176',
  'X-Unity-Version: 2017.1.2p2',
  'PLATFORM: 1',
  'Connection: keep-alive',
  'Accept-Language: en-us',
  'APP_VER: '.$appver,
  'RES_VER: 10000000',
  'Accept: */*',
  'Content-Type: application/x-www-form-urlencoded',
  'Accept-Encoding: gzip, deflate',
  'DEVICE: 1'
];
global $curl;
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.priconne-redive.jp/check/game_start',
  CURLOPT_HTTPHEADER=>$game_start_header,
  CURLOPT_HEADER=>false,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_CONNECTTIMEOUT=>3,
  CURLOPT_SSL_VERIFYPEER=>false,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>base64_decode('Fh28009IuC3baOl9zp5LX7v/MuF7Ye2SI7fPSKlU84ru+bTFQPoEEoUnHBbZn4tf/gD6bCI+GD6opqtyeuAKq5Yile53RJYRU5ERCk6UpHWDmts6K8Z+vt5+3yb9sCU9EedYA2xnOoltbNjffQ1bTVBCErKRlDoo3agsFuCWF2AZEn3plfN7UpR7udogIePAWkRFeVpUWXlaakV5TldGbVltVXlOR1poWXpka05HUXg='),
  CURLOPT_PROXY=>file_get_contents('currentproxy.txt'),
  CURLOPT_HTTPPROXYTUNNEL=>true
//  CURLOPT_PROXY=>'vultr.biliplus.com:87',
//  CURLOPT_PROXYTYPE=>CURLPROXY_SOCKS5
));
$response = curl_exec($curl);
$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
if ($code == 500 || $code == 0 || ($code == 200 && strlen($response) == 0)) {
  _log('proxy failed: '. $code);
  if (autoProxy()) {
    return main();
  } else {
    exit;
  }
}
if ($response === false) {
  _log('error fetching TruthVersion');
  return;
}
//$response = 'RerDjP3EYxdZtDcDm24Ni5WJLz/mnmKHltcuvXd8wUPHpVgkz7h8eNxSs25yL+xckTnO5EwR/YdCFu/jQ0tspFDhep7GI1hw1zPxX5AIzQnxS1uayolDzl9nfHZtJR28uj043NMdQ9Noqr0TNbbe0MUu66gYUFTzjTvboGf5l7nt/QwXR6hY3tzo67aMTpETbf0ZCi3urhOnEQlJlBMhjU2gtl6Ws2J7+wkTlNswpN2fn+d99xFuIdNln0J0jNRa/Ku/f2ix18wMiKA34ATWXUj5WBHcg6rZjbDrr7xp2QUbU4W3t62nRt7xR0klFxblxD5u4vmTZv5eYXHKlCgbMTM0YWVkYmQ3NzBkOTcyZDZiOTVhZTA0OGE5MjYyZGY2';
$response = base64_decode($response);
$key = substr($response, -32, 32);
$udid = 'b94fb285-fdda-4136-b66c-e694f141cdab';
$udid = '662dcf1f-cf2e-4560-a325-f550a1c01954';
$iv = substr(str_replace('-','',$udid),0,16);
$response = msgpack_unpack(decrypt(substr($response, 0, -32), $key, $iv));

//print_r($response);
//exit;
if (!isset($response['data_headers']['required_res_ver'])) {
  _log('invalid response: '. json_encode($response));
  return;
}
$TruthVersion = $response['data_headers']['required_res_ver'];

if ($TruthVersion == $last_version['TruthVersion']) {
  _log('no update found');
  return;
}
$last_version['TruthVersion'] = $TruthVersion;
_log("TruthVersion: ${TruthVersion}");
file_put_contents('data/!TruthVersion.txt', $TruthVersion."\n");

//$TruthVersion = '10000000';
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_HEADER=>0,
  CURLOPT_SSL_VERIFYPEER=>false
));
//$manifest = file_get_contents('history/'.$TruthVersion);

// fetch all manifest & save
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_manifest.txt', $manifest);
foreach (explode("\n", trim($manifest)) as $line) {
  list($manifestName) = explode(',', $line);
  if ($manifestName == 'manifest/soundmanifest') {
    curl_setopt($curl, CURLOPT_URL, 'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Sound/manifest/soundmanifest');
    $manifest = curl_exec($curl);
    file_put_contents('data/+manifest_sound.txt', $manifest);
  } else {
    curl_setopt($curl, CURLOPT_URL, 'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/'.$manifestName);
    $manifest = curl_exec($curl);
    file_put_contents('data/+manifest_'.substr($manifestName, 9, -14).'.txt', $manifest);
  }
}
curl_setopt($curl, CURLOPT_URL, 'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Movie/SP/High/manifest/moviemanifest');
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_movie.txt', $manifest);

$manifest = file_get_contents('data/+manifest_masterdata.txt');
$manifest = explode(',', $manifest);
$bundleHash = $manifest[1];
$bundleSize = $manifest[3]|0;
if ($last_version['hash'] == $bundleHash) {
  _log("Same hash as last version ${bundleHash}");
  file_put_contents('last_version', json_encode($last_version));
  chdir('data');
  exec('git add !TruthVersion.txt +manifest_*.txt');
  do_commit($TruthVersion);
  return;
}
$last_version['hash'] = $bundleHash;
//download bundle
_log("downloading bundle for TruthVersion ${TruthVersion}, hash: ${bundleHash}, size: ${bundleSize}");
$bundleFileName = "master_${TruthVersion}.unity3d";
curl_setopt_array($curl, array(
  CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/pool/AssetBundles/'.substr($bundleHash,0,2).'/'.$bundleHash,
  CURLOPT_RETURNTRANSFER=>true
));
$bundle = curl_exec($curl);
//curl_close($curl);
$downloadedSize = strlen($bundle);
$downloadedHash = md5($bundle);
if ($downloadedSize != $bundleSize || $downloadedHash != $bundleHash) {
  _log("download failed, received hash: ${downloadedHash}, received size: ${downloadedSize}");
  return;
}

//extract db
_log('extracting bundle');
$bundle = new MemoryStream($bundle);
$assetsList = extractBundle($bundle);
unset($bundle);

$assetsFile = new FileStream($assetsList[0]);
$tableSize = $assetsFile->readInt32();
$dataEnd = $assetsFile->readInt32();
$fileGen = $assetsFile->readInt32();
$dataOffset = $assetsFile->readInt32();

$assetsFile->seek($dataOffset + 16);
file_put_contents('redive.db', $assetsFile->readData($dataEnd - $dataOffset - 16));
unset($assetsFile);
unlink($assetsList[0]);

//dump sql
_log('dumping sql');
$db = new PDO('sqlite:redive.db');

$tables = execQuery($db, 'SELECT * FROM sqlite_master');

foreach (glob('data/*.sql') as $file) {unlink($file);}

foreach ($tables as $entry) {
  if ($entry['name'] == 'sqlite_stat1') continue;
  if ($entry['type'] == 'table') {
    $tblName = $entry['name'];
    $f = fopen("data/${tblName}.sql", 'w');
    fwrite($f, $entry['sql'].";\n");
    $values = execQuery($db, "SELECT * FROM ${tblName}");
    foreach($values as $value) {
      fwrite($f, "INSERT INTO `${tblName}` VALUES (".encodeValue($value).");\n");
    }
    fclose($f);
  } else if ($entry['type'] == 'index') {
    file_put_contents("data/${tblName}.sql", $entry['sql'].";\n", FILE_APPEND);
  }
}
$name = [];
foreach(execQuery($db, 'SELECT unit_id,unit_name FROM unit_data WHERE unit_id > 100000 AND unit_id < 200000') as $row) {
  $name[$row['unit_id']+30] = $row['unit_name'];
}
file_put_contents(RESOURCE_PATH_PREFIX.'card/full/index.json', json_encode($name, JSON_UNESCAPED_SLASHES));
$storyStillName = [];
foreach(execQuery($db, 'SELECT story_group_id,title FROM story_data') as $row) {
  $storyStillName[$row['story_group_id']] = $row['title'];
}
foreach(execQuery($db, 'SELECT story_group_id,title FROM event_story_data') as $row) {
  $storyStillName[$row['story_group_id']] = $row['title'];
}
file_put_contents(RESOURCE_PATH_PREFIX.'card/story/index.json', json_encode($storyStillName, JSON_UNESCAPED_SLASHES));
$info = [];
foreach (execQuery($db, 'SELECT unit_id,motion_type,unit_name FROM unit_data WHERE unit_id > 100000 AND unit_id < 200000') as $row) {
  $info[$row['unit_id']] = [
    'name' => $row['unit_name'],
    'type'=>$row['motion_type']
  ];
}
file_put_contents(RESOURCE_PATH_PREFIX.'spine/classMap.json', json_encode($info));

unset($name);
file_put_contents('last_version', json_encode($last_version));

chdir('data');
exec('git add *.sql !TruthVersion.txt +manifest_*.txt');
do_commit($TruthVersion, $db);
unset($db);

checkAndUpdateResource($TruthVersion);

file_put_contents(RESOURCE_PATH_PREFIX.'spine/still/index.json', json_encode(
  array_map(function ($i){
    return substr($i, -10, -4);
  },
  glob(RESOURCE_PATH_PREFIX.'spine/still/unit/*.png'))
));

}

/*foreach(glob('history/100*') as $ver) {
  main(substr($ver, 8));
}*/
main();
