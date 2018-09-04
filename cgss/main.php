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
$logFile = fopen('cgss.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  //echo $s."\n";
}
function execQuery($db, $query) {
  $returnVal = [];
  $result = $db->query($query);
  $returnVal = $result->fetchAll(PDO::FETCH_ASSOC);
  return $returnVal;
}

function decrypt256($string = '', $key = '', $iv = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0") {
  return trim(
          mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, $iv),
          "\0");
}
function decodeUDID($in) {
  $num = hexdec(substr($in, 0, 4));
  $text = '';
  for ($i=6; $i<strlen($in) && strlen($text) < $num; $i+=4) {
      $text .= chr(ord(substr($in, $i, 1)) - 10);
  }
  return $text;
}
function cgss_data_uncompress($data) {
  $data = new MemoryStream($data);
  $data->littleEndian = true;
  $num1 = $data->long;
  $uncompressedSize = $data->long;
  $data->long;
  $num2 = $data->long;
  if ($num1 != 100 || $num2 != 1) {
    _log('invalid data');
    exit;
  }
  $uncompressed = lz4_uncompress_stream($data->readData($data->size - 16), $uncompressedSize);
  unset($data);
  return $uncompressed;
}

function encodeValue($value) {
  $arr = [];
  foreach ($value as $key=>$val) {
    $arr[] = '/*'.$key.'*/' . (is_numeric($val) ? $val : ('"'.str_replace('"','\\"',$val).'"'));
  }
  return implode(", ", $arr);
}

function getNextVersion($ver) {
  $mod = $ver % 100;
  $ver += 100 - $mod;
  return $ver;
}

function do_commit($TruthVersion, $db = NULL) {
  exec('git diff --cached | sed -e "s/@@ -1 +1 @@/@@ -1,1 +1,1 @@/g" >a.diff');
  $versionDiff = parse_db_diff('a.diff', $db, [
    'event_data.sql' => 'diff_event_data', // event
    'cappuccino_data.sql' => 'diff_chino', // gekijou_anime
    'card_data.sql' => 'diff_card',        // card
    'gacha_data.sql' => 'diff_gacha',      // gacha
    'latte_art_data.sql' => 'diff_gekijou',// gekijou
    'music_data.sql' => 'diff_music',      // music
    'party_data_re.sql' => 'diff_party',   // party
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
  if (isset($versionDiff['card'])) {
    $diff_send['card'] = array_map(function ($a){ 
      return ['','N','N+','R','R+','SR','SR+','SSR','SSR+'][$a['rarity']].
      $a['name'].
      ' ('.$a['condition'].'s/'.
      ['','','Low','Medium','High'][$a['probability_type']].
      '/'.(function ($a){
        switch ($a) {
          case 1: case 2: case 3:             { return 'Perfect Bonus'; }
          case 4:                             { return 'Combo Bonus'; }
          case 5: case 6: case 7: case 8:     { return 'Perfect Support'; }
          case 9: case 10: case 11:           { return 'Combo Support'; }
          case 12:                            { return 'Damage Guard'; }
          case 13: case 17: case 18: case 19: { return 'Healer'; }
          case 14:                            { return 'Overload'; }
          case 15:                            { return 'Concentration'; }
          case 16:                            { return 'Encore'; }
          case 20:                            { return 'Skill Boost'; }
          case 21: case 22: case 23:          { return 'Focus'; }
          case 24:                            { return 'All Round'; }
          case 25:                            { return 'Life Sparkle'; }
          case 26:                            { return 'Synergy'; }
          case 27:                            { return 'Coordination'; }
          default:                            { return 'Skill '.$a; }
        }
      })($a['skill_type']).
      ')';
    }, $versionDiff['card']);
    $commitMessage[] = "- new cards: \n  - ".implode("\n  - ", $diff_send['card']);
  }
  if (isset($versionDiff['event'])) {
    $diff_send['event'] = array_map(function ($a){ return $a['name'];}, $versionDiff['event']);
    $commitMessage[] = '- new event '. implode(', ',$diff_send['event']);
  }
  if (isset($versionDiff['gacha'])) {
    $diff_send['gacha'] = array_map(function ($a){ return $a['name'].'（'.$a['detail'].'）';}, $versionDiff['gacha']);
    $commitMessage[] = '- new gacha '. implode(', ',$diff_send['gacha']);
  }
  if (isset($versionDiff['music'])) {
    $diff_send['music'] = array_map(function ($a){ return $a['name'];}, $versionDiff['music']);
    $commitMessage[] = '- new music '. implode(', ',$diff_send['music']);
  }
  if (isset($versionDiff['gekijou'])) {
    $diff_send['gekijou'] = array_map(function ($a){ return $a['title'];}, $versionDiff['gekijou']);
    $commitMessage[] = '- new comic '. implode(', ',$diff_send['gekijou']);
  }
  $diff_send['diff'] = $versionDiff['diff'];

  exec('git commit -m "'.implode("\n", $commitMessage).'"');
  exec('git rev-parse HEAD', $hash);
  $versionDiff['hash'] = $hash[0];
  require_once __DIR__.'/../mysql.php';
  $mysqli->select_db('db_diff');
  $mysqli->query('REPLACE INTO cgss (ver,data) vALUES ('.$TruthVersion.',"'.$mysqli->real_escape_string(brotli_compress(
    json_encode($versionDiff, JSON_UNESCAPED_SLASHES), 11, BROTLI_TEXT
  )).'")');
  exec('git push origin master');
  
  $data = json_encode(array(
    'game'=>'cgss',
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
$appver = file_exists('appver') ? file_get_contents('appver') : '3.8.6';
$itunesid = 1016318735;
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
      _log('new game version: '. $appver);
      file_put_contents('appver', $appver);
      $data = json_encode(array(
        'game'=>'cgss',
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
  'Host: apis.game.starlight-stage.jp',
  'User-Agent: BNEI0242/130 CFNetwork/758.4.3 Darwin/15.5.0',
  'PARAM: 6e9fad10fe4b1b61cffafd7fc36ed0d32e90c674',
  'USER_ID: 000922B231:137A242<328C557A146;268A832C537251241528442121655364561571862',
  'PLATFORM_OS_VERSION: iPhone OS 9.3.2',
  'Proxy-Connection: keep-alive',
  'IP_ADDRESS: 192.168.1.112',
  'DEVICE_ID: A8448751-7ECF-4C9F-B821-05AE570801C6',
  'KEYCHAIN: 127767137',
  'GRAPHICS_DEVICE_NAME: Apple A9 GPU',
  'DEVICE_NAME: iPhone8,4',
  'UDID: 002423;541<818p713l356;558<788<512B1167165B452A426p575p3447527>745B625l856B6417737l515;535;726A7217263;113@551n768l838:516m832;624A287p772p121o626:634466828748373881115683521611874',
  'SID: 5348ecbff2589a204166891e1798483a',
  'Content-Length: 984',
  'X-Unity-Version: 2017.4.2f2',
  'Connection: keep-alive',
  'CARRIER: ',
  'Accept-Language: en-us',
  'APP_VER: '.$appver,
  'RES_VER: 10037460',
  'Accept: */*',
  'Accept-Encoding: gzip, deflate',
  'Content-Type: application/x-www-form-urlencoded',
  'DEVICE: 1',
  'IDFA: 00000000-0000-0000-0000-000000000000',
];
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apis.game.starlight-stage.jp/load/check',
  CURLOPT_HTTPHEADER=>$game_start_header,
  CURLOPT_HEADER=>0,
  CURLOPT_RETURNTRANSFER=>1,
  CURLOPT_SSL_VERIFYPEER=>false,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>'2K08Y07sznT2iYguP5fKlllXf3qpoGnwUpS+lOzFmN0rQsxqGePsatS6lg5BzOt5D0nV/VdFyjSTNaxs2l5Dfwli9ExWhd8WjGGduM1I7ucfWo6m2u41wW+MCJu4Lh7emHWnNAwlBvr2oFXrDeyCxTE8MoNRfYGMuTM1VDxurml2gYJHhZsDcAwgStx6OKkqq0RheKOSlbifO37eE7RRswv44aTVbTP6rJ9Hf2P/XKtS0E9jDvJ07yiOL4hRjDC/Duzv3PugPmmEVqslQ2GAZksfv0/10LwS35maqLuSxN3IfMHxPJxE0bI5Jqzo6/1VLBLqYc4H7twxiphAHzGan6u5kf+RVwtHv8bkTdBEf8hJZJ4wjdNitIjmLlR+wlqQqiR92CtyASw8wiwG2f0vB4LejXB6hpiTeavat87pSGRv7+mhSejKEj9TkHMdqQdDQqV2gHFRB/5B16J2+bZvkwQifIDOkBH6g47H6GFhGDyi3zICGSJqLO1ILNyJfvFs+C34gRvMuhcsspvaxczFmUAeleZHYOrJM1e7ysnEOc7Pt9Br2RDQc5Tvnmol6gRYpIQrz2bUZ2jQO+CcAY4wJ3wkslA2+jQb/wv8j9gicQ3oojJ7Blitgmcnts0wDSgSO4EdX620cUJL3ujxcEzOsqydnfllGe1L0K2HCv8gzzvwhTAJo1GneIVKFzEP3rGZbJufptgtYdQoTqyaMJfRSD1yM7337n6aOv6H0TjWOLsANu4gGN/jkhx96eN+XdQRkD41YJOVWIdhJfZeHYS14bBD1B1xrIoo3DICUe9j+MGeCiPoX9g6cXaeToxutRMycFgItVJ/s05l91h2ZLYlB7GZro3Ow9bw8IpyAaR7MDrixT6R16us7zn1QQjBqOu7Cyui2nK/BAnnypNuMSMYhFmpm5912U7h3DET0aOriMVNVFl6TkdNeU1HUTRNREpoTjJSbFpHTXdNamM1WW1Oag=='
));
$response = curl_exec($curl);
curl_close($curl);
if ($response === false) {
  _log('error fetching TruthVersion');
  return;
}
$response = base64_decode($response);
$key = substr($response, -32, 32);
$encudid = '002423;541<818p713l356;558<788<512B1167165B452A426p575p3447527>745B625l856B6417737l515;535;726A7217263;113@551n768l838:516m832;624A287p772p121o626:634466828748373881115683521611874';
$udid = decodeUDID($encudid);
$iv = str_replace('-','',$udid);
$response = msgpack_unpack(base64_decode(decrypt256(substr($response, 0, -32), $key, $iv)));

if (!isset($response['data_headers']['required_res_ver'])) {
  _log('invalid response: '. json_encode($response));
  return;
}
$TruthVersion = intval($response['data_headers']['required_res_ver']);

global $curl;
$curl = curl_init();
if ($TruthVersion == $last_version['TruthVersion']) {
  _log('no update found');
  return;
}
$last_version['TruthVersion'] = $TruthVersion;
_log("TruthVersion: ${TruthVersion}, downloading manifest");
file_put_contents('data/!TruthVersion.txt', $TruthVersion."\n");
curl_setopt_array($curl, array(
  CURLOPT_URL=>'http://storages.game.starlight-stage.jp/dl/'.$TruthVersion.'/manifests/iOS_AHigh_SHigh',
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_HEADER=>0,
  CURLOPT_SSL_VERIFYPEER=>false,
  CURLOPT_HTTPHEADER=>['X-Unity-Version: 2017.4.2f2']
));
$manifest_comp = curl_exec($curl);

//$manifest_comp = file_get_contents('manifest/'.$TruthVersion);

file_put_contents('manifest.db', cgss_data_uncompress($manifest_comp));
unset($manifest_comp);
$manifest = new PDO('sqlite:manifest.db');
$f = fopen("data/manifests.sql", 'w');
$values = execQuery($manifest, "SELECT * FROM manifests order by name");
foreach ($values as $value) {
  fwrite($f, "INSERT INTO `manifests` VALUES (".encodeValue($value).");\n");
}
fclose($f);
$masterHash = execQuery($manifest, "SELECT hash FROM manifests WHERE name = 'master.mdb'");
//$dateString = date(' Y/m/d H:i', filemtime('manifest/'.$TruthVersion));
$dateString = '';
unset($manifest);
//unlink('manifest.db');

if (!isset($masterHash[0])) {
  _log('old master format, skipping');
  chdir('data');
  exec('git add manifests.sql !TruthVersion.txt');
  do_commit($TruthVersion);
  return;
}
$masterHash = $masterHash[0]['hash'];

if ($last_version['hash'] == $masterHash) {
  _log("Same hash as last version ${masterHash}");
  file_put_contents('last_version', json_encode($last_version));
  chdir('data');
  exec('git add manifests.sql !TruthVersion.txt');
  do_commit($TruthVersion);
  return;
}
$last_version['hash'] = $masterHash;
//download bundle
_log("downloading bundle for TruthVersion ${TruthVersion}, hash: ${masterHash}");
curl_setopt_array($curl, array(
  CURLOPT_URL=>'http://storages.game.starlight-stage.jp/dl/resources/Generic/'.$masterHash,
  CURLOPT_HTTPHEADER=>['X-Unity-Version: 2017.4.2f2'],
  CURLOPT_RETURNTRANSFER=>true
));
$master_compressed = curl_exec($curl);
//curl_close($curl);
$downloadedHash = md5($master_compressed);
if ($downloadedHash != $masterHash) {
  _log("download failed, received hash: ${downloadedHash}");
  chdir('data');
  exec('git add manifests.sql !TruthVersion.txt');
  do_commit($TruthVersion);
  return;
}

//extract db
_log('extracting bundle');
file_put_contents('master.db', cgss_data_uncompress($master_compressed));
unset($master_compressed);

//dump sql
_log('dumping sql');
$db = new PDO('sqlite:master.db');

$tables = execQuery($db, 'SELECT * FROM sqlite_master');

chdir('data');
exec('git rm *.sql --cached');
chdir(__DIR__);
foreach (glob('data/*.sql') as $file) {$file!='data/manifests.sql'&&unlink($file);}

$i=0;
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
  } else if ($entry['type'] == 'index' && $entry['sql']) {
    $tblName = $entry['tbl_name'];
    file_put_contents("data/${tblName}.sql", $entry['sql'].";\n", FILE_APPEND);
  }
  //echo "\r".++$i.'/'.count($tables);
}
echo "\n";

global $poseData;
$poseData=[];
$names=[];
foreach(execQuery($db, 'SELECT id,name,chara_id,pose,rarity FROM card_data') as $row) {
  $names[$row['id']] = ['','N','N+','R','R+','SR','SR+','SSR','SSR+'][$row['rarity']].$row['name'];
  $poseData[sprintf('%03d_%02d', $row['chara_id'], $row['pose'])] = $row['id'];
}
foreach(execQuery($db, 'SELECT chara_id,name FROM chara_data') as $row) {
  $names[sprintf('%03d', $row['chara_id'])] = $row['name'];
}
file_put_contents(RESOURCE_PATH_PREFIX.'card/index.json', json_encode($names, JSON_UNESCAPED_SLASHES));

file_put_contents('last_version', json_encode($last_version));

chdir('data');
exec('git add *.sql !TruthVersion.txt');
do_commit($TruthVersion, $db);
unset($db);
checkAndUpdateResource();
_log('finished');

}

/*$files = glob('manifest/100*');
$num=0;
foreach($files as $ver) {
  echo '--'.++$num.'/'.count($files)."\n";
  main(substr($ver, 9));
}*/
//main('10037450');
main();
