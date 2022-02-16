<?php
chdir(__DIR__);
require_once 'UnityBundle.php';
require_once 'resource_fetch.php';
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
  echo $s."\n";
}
function execQuery($db, $query) {
  $returnVal = [];
  /*if ($stmt = $db->prepare($query)) {
    $result = $stmt->execute();
    if ($result->numColumns()) {
      $returnVal = $result->fetchArray(SQLITE3_ASSOC);
    }
  }*/
  if (!$db) {
    throw new Exception('Invalid db handle');
  }
  $result = $db->query($query);
  if ($result === false) {
    throw new Exception('Failed executing query: '. $query);
  }
  $returnVal = $result->fetchAll(PDO::FETCH_ASSOC);
  return $returnVal;
}
function autoProxy() {
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL=>'https://free-proxy-list.net/',
    CURLOPT_HTTPHEADER=>[
      //'Host: www.us-proxy.org',
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
    //_log($proxy.' '.curl_getinfo($curl, CURLINFO_HTTP_CODE));
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
function do_commit($TruthVersion, $db = NULL, $extraMsg = '') {
  exec('git diff --cached | sed -e "s/@@ -1 +1 @@/@@ -1,1 +1,1 @@/g" >a.diff');
  $versionDiff = parse_db_diff('a.diff', $db, [
    'clan_battle_period.sql' => 'diff_clan_battle', // clan_battle
    'dungeon_area_data.sql' => 'diff_dungeon_area', // dungeon_area
    'gacha_data.sql' => 'diff_gacha',               // gacha
    'quest_area_data.sql' => 'diff_quest_area',     // quest_area
    'story_data.sql' => 'diff_story_data',          // story_data
    'unit_data.sql' => 'diff_unit',                 // unit
    'experience_team.sql' => 'diff_exp',            // experience
    'unit_promotion.sql' => 'diff_rank',            // rank
    'hatsune_schedule.sql' => 'diff_event_hatsune', // event_hatsune,
    'tower_schedule.sql' => 'diff_event_tower',     // event_tower,
    'campaign_schedule.sql' => 'diff_campaign',     // campaign
  ]);
  unlink('a.diff');
  $versionDiff['ver'] = $TruthVersion.$extraMsg;
  $versionDiff['time'] = time();
  $versionDiff['timeStr'] = date('Y-m-d H:i', $versionDiff['time'] + 3600);

  $diff_send = [];
  $commitMessage = [$TruthVersion.$extraMsg];
  if (isset($versionDiff['new_table'])) {
    $diff_send['new_table'] = $versionDiff['new_table'];
    $commitMessage[] = '- '.count($diff_send['new_table']).' new table: '. implode(', ', $diff_send['new_table']);
  }
  if (isset($versionDiff['unit'])) {
    $diff_send['card'] = array_map(function ($a){ return str_repeat('★', $a['rarity']).$a['name'];}, $versionDiff['unit']);
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
  $diff_db = new PDO('sqlite:'.__DIR__.'/../db_diff.db');

  $col = ['ver','data'];
  $val = [$TruthVersion, brotli_compress(
    json_encode($versionDiff, JSON_UNESCAPED_SLASHES), 11, BROTLI_TEXT
  )];
  $chkTypes = [
    'clan_battle',
    'dungeon_area',
    'gacha',
    'quest_area',
    'story',
    'unit',
    'max_lv',
    'event',
    'campaign'
  ];
  foreach ($chkTypes as $type) {
    if (isset($versionDiff[$type])) {
      $col[] = 'has_'.$type;
      $val[] = 1;
    }
  }
  $stmt = $diff_db->prepare('REPLACE INTO redive ('.implode(',', $col).') VALUES ('.implode(',', array_map(function (){return '?';}, $val)).')');
  $stmt->execute($val);
  exec('git push origin master');
  
  $data = json_encode(array(
    'game'=>'redive',
    'hash'=>$hash[0],
    'ver' =>$TruthVersion.$extraMsg,
    'data'=>$diff_send
  ));
  $header = [
    'X-GITHUB-EVENT: push_direct_message',
    'X-HUB-SIGNATURE: sha1='.hash_hmac('sha1', $data, file_get_contents(__DIR__.'/../webhook_secret'), false)
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
        'link'=>'https://itunes.apple.com/jp/app/id'.$itunesid,
        'desc'=>$appinfo['results'][0]['releaseNotes']
      ));
      $header = [
        'X-GITHUB-EVENT: app_update',
        'X-HUB-SIGNATURE: sha1='.hash_hmac('sha1', $data, file_get_contents(__DIR__.'/../webhook_secret'), false)
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

      // fetch bundle manifest
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HEADER=>0,
        CURLOPT_SSL_VERIFYPEER=>false
      ));
      curl_setopt($curl, CURLOPT_URL, "http://prd-priconne-redive.akamaized.net/dl/Bundles/${appver}/Jpn/AssetBundles/iOS/manifest/bdl_assetmanifest");
      $manifest = curl_exec($curl);
      file_put_contents('data/+manifest_bundle.txt', $manifest);
      chdir('data');
      exec('git add +manifest_bundle.txt');
      exec('git commit -m "bundle manifest v'.$appver.'"');
      chdir(__DIR__);
    }
  }
}

$isWin = DIRECTORY_SEPARATOR === '\\';
$cmdPrepend = $isWin ? '' : 'wine ';
$cmdAppend = $isWin ? '' : ' >/dev/null 2>&1';
//check TruthVersion
/*
$game_start_header = [
  'Host: app.priconne-redive.jp',
  'User-Agent: princessconnectredive/39 CFNetwork/758.4.3 Darwin/15.5.0',
  'PARAM: 527336ff17818cd82e482d5c2cbdea2bc859b316',
  'REGION_CODE: ',
  'BATTLE_LOGIC_VERSION: 2',
  'PLATFORM_OS_VERSION: iPhone OS 9.3.2',
  'Proxy-Connection: keep-alive',
  'DEVICE_ID: BFCC3361-7BCE-4706-A44C-DDF8252669BB',
  'KEYCHAIN: 577247511',
  'GRAPHICS_DEVICE_NAME: Apple A9 GPU',
  'SHORT_UDID: 000973A123;453A538?687=244:861<473>161;683111718423523554437738453547512',
  'DEVICE_NAME: iPhone8,4',
  'BUNDLE_VER: ',
  'LOCALE: Jpn',
  'IP_ADDRESS: 192.168.0.110',
  'SID: bc41c108715c98f0cae62f6f94a990c2',
  'X-Unity-Version: 2017.1.2p2',
  'PLATFORM: 1',
  'Connection: keep-alive',
  'Accept-Language: en-us',
  'APP_VER: '.$appver,
  'RES_VER: 10002700',
  'Accept: *\/*',
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
  CURLOPT_POSTFIELDS=>'wN4AAjnMd5CaTfLGxSL+rUQzafWYMcXIhaUZxKbsOCuR64ldQuDc0mGuXMU72S2nYcOBLpJjWXNeoj59TV2mXcIKl/YXMxtsHHuKKdBCIOujxxJHW79q3jQ3F2LRg8iDTN2EGo+1NLCHqyDD8kt4iYT47D5gJa3HM0d4+n6X/U0/6hB+3utmirBPHRZJ5hVZZaSXbuzefQSbgrQ=',
  CURLOPT_PROXY=>file_get_contents('currentproxy.txt'),
  CURLOPT_HTTPPROXYTUNNEL=>true,
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
$response = base64_decode($response);
file_put_contents('resp.data', $response);
system($cmdPrepend.'Coneshell_call.exe -unpack-edcadba12a674a089107d8065a031742 resp.data resp.json'.$cmdAppend);
unlink('resp.data');
if (!file_exists('resp.json')) {
  _log('Unpack response failed');
  return;
}
$response = json_decode(file_get_contents('resp.json'), true);
unlink('resp.json');

//print_r($response);
//exit;
if (!isset($response['data_headers']['required_res_ver'])) {
  if (isset($response['data_headers']['result_code']) && $response['data_headers']['result_code'] == 101) {
    // maintenance, wait for 00:30/30:30
    $now = time();
    $until = $now - $now % 1800 + 1830;
    $wait = $until - $now;
    if ($wait > 600) {
      _log("maintaining, exit");
      return;
    }
    _log("maintaining, wait for ${wait} secs now");
    sleep($wait);
    return main();
  }
  _log('invalid response: '. json_encode($response));
  return;
}
$TruthVersion = $response['data_headers']['required_res_ver'];
*/

//if (file_exists('stop_cron')) return;

// guess latest res_ver
global $curl;
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_HEADER=>0,
  CURLOPT_SSL_VERIFYPEER=>false
));
$TruthVersion = $last_version['TruthVersion'];
$current_ver = $TruthVersion|0;

for ($i=1; $i<=20; $i++) {
  $guess = $current_ver + $i * 10;
  curl_setopt($curl, CURLOPT_URL, 'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$guess.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest');
  curl_exec($curl);
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($code == 200) {
    $TruthVersion = $guess.'';
    break;
  }
}
curl_close($curl);
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
  CURLOPT_URL=>'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
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
    continue;
  } else {
    curl_setopt($curl, CURLOPT_URL, 'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/'.$manifestName);
    $manifest = curl_exec($curl);
    file_put_contents('data/+manifest_'.substr($manifestName, 9, -14).'.txt', $manifest);
  }
}
curl_setopt($curl, CURLOPT_URL, 'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Sound/manifest/sound2manifest');
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_sound.txt', $manifest);
curl_setopt($curl, CURLOPT_URL, 'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Movie/SP/High/manifest/moviemanifest');
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_movie.txt', $manifest);
curl_setopt($curl, CURLOPT_URL, 'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Movie/SP/Low/manifest/moviemanifest');
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_movie_low.txt', $manifest);

$manifest = file_get_contents('data/+manifest_masterdata.txt');
$manifest = array_map(function ($i){ return explode(',', $i); }, explode("\n", $manifest));
foreach ($manifest as $entry) {
  if ($entry[0] === 'a/masterdata_master_0003.cdb') { $manifest = $entry; break; }
}
if ($manifest[0] !== 'a/masterdata_master_0003.cdb') {
  _log('masterdata_master_0003.cdb not found');
  //file_put_contents('stop_cron', '');
  file_put_contents('last_version', json_encode($last_version));
  chdir('data');
  exec('git add !TruthVersion.txt +manifest_*.txt');
  do_commit($TruthVersion, NULL, ' (no master db)');
  checkAndUpdateResource($TruthVersion);
  return;
}
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
_log("downloading cdb for TruthVersion ${TruthVersion}, hash: ${bundleHash}, size: ${bundleSize}");
$bundleFileName = "master_${TruthVersion}.unity3d";
curl_setopt_array($curl, array(
  CURLOPT_URL=>'http://prd-priconne-redive.akamaized.net/dl/pool/AssetBundles/'.substr($bundleHash,0,2).'/'.$bundleHash,
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
_log('dumping cdb');
file_put_contents('master.cdb', $bundle);
unset($bundle);
system($cmdPrepend.'Coneshell_call.exe -cdb master.cdb master.mdb'.$cmdAppend);
if (!file_exists('master.mdb')) {
  _log('Dump master.mdb failed');
  return;
}
unlink('master.cdb');
rename('master.mdb', 'redive.db');
$dbData = file_get_contents('redive.db');
file_put_contents('redive.db.br', brotli_compress($dbData, 9));

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
  } else if ($entry['type'] == 'index' && !empty($entry['sql'])) {
    $tblName = $entry['tbl_name'];
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
foreach(execQuery($db, 'SELECT story_group_id,title FROM tower_story_data') as $row) {
  $storyStillName[$row['story_group_id']] = $row['title'];
}
file_put_contents(RESOURCE_PATH_PREFIX.'card/story/index.json', json_encode($storyStillName, JSON_UNESCAPED_SLASHES));
$info = [];
foreach (execQuery($db, 'SELECT unit_id,motion_type,unit_name FROM unit_data WHERE unit_id > 100000 AND unit_id < 200000') as $row) {
  $info[$row['unit_id']] = [
    'name' => $row['unit_name'],
    'type'=>$row['motion_type'],
    'hasRarity6' => false
  ];
}
foreach (execQuery($db, 'SELECT unit_id FROM unit_rarity WHERE rarity=6') as $row) {
  $info[$row['unit_id']]['hasRarity6'] = true;
}
$spineManifest = file_get_contents('data/+manifest_spine2.txt');
foreach ($info as $id => &$item) {
  if (strpos($spineManifest, "a/spine_${id}_chara_base.cysp.unity3d") !== false) {
    $item['hasSpecialBase'] = true;
  }
}
file_put_contents(RESOURCE_PATH_PREFIX.'spine/classMap.json', json_encode($info));

unset($name);
file_put_contents('last_version', json_encode($last_version));

chdir('data');
exec('git add *.sql !TruthVersion.txt +manifest_*.txt');
do_commit($TruthVersion, $db);
unset($db);

if (file_exists(__DIR__.'/action_after_update.php')) require_once __DIR__.'/action_after_update.php';

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
