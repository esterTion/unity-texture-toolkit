<?php

chdir(__DIR__);
require_once 'UnityBundle.php';
require_once 'resource_fetch.php';
if (!file_exists('last_version')) {
  $last_version = array('data'=>'0','master'=>'0');
} else {
  $last_version = json_decode(file_get_contents('last_version'), true);
}

$logFile = fopen('bang.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  //echo $s."\n";
}

function parseProto($file) {
  $proto = file_get_contents($file);
  $proto = preg_replace('(//.+\n)', "\n", $proto);
  preg_match_all('(message (\w+) \{([\w\W]+?)\})', $proto, $messages);
  $output = [];
  for ($i=0; $i<count($messages[0]); $i++) {
    $messageName = $messages[1][$i];
    $lines = explode(';', trim($messages[2][$i], "; \n"));
    $output[$messageName] = [];
    foreach ($lines as $line) {
      $fields = explode('=', trim($line));
      $tag = trim($fields[1]);
      $fields = explode(' ', trim($fields[0]));
      $output[$messageName][$tag] = array(
        'repeated' => $fields[0] == 'repeated',
        'type' => $fields[1],
        'name' => $fields[2]
      );
    }
  }
  return $output;
}

function readVarInt32($pb) {
	$b = ord($pb->readData(1));
  $result = $b & 0x7f;
  $shift = 0;
  while ($b & 0x80) {
    $shift += 7;
    if ($shift > 64) throw new Exception('Too many bytes for varint');
		$b=ord($pb->readData(1));
		$result |= ($b & 0x7f) << $shift;
  }
	return $result;
}

function setValue(&$arr, $key, &$val, $isArr) {
  if ($isArr) {
    if (!isset($arr[$key])) $arr[$key] = [];
    $arr[$key][] = $val;
  } else {
    $arr[$key] = $val;
  }
}

function parseProtoBuf($messageName, $end, &$pb, &$proto) {
  $message = [];
  while ($pb->position() < $end){
    $id = readVarInt32($pb);
    $type = $id & 7;
    $id = $id >> 3;
    if (isset($proto[$messageName][$id])) {
      $def = $proto[$messageName][$id];
    } else {
      $def = array('type'=>'byte', 'name'=>'UNKNOWN_'.$id, 'repeated'=>false);
    }
    $typeName = $def['type'];
    $name = $def['name'];
    $isRepeated = $def['repeated'];
    switch($type){
      case 0:
        $data = readVarInt32($pb);
        setValue($message, $name, $data, $isRepeated);
        break;
      case 1:
        $data = $pb->readData(8);
        if ($typeName == 'double') {
          $data = unpack('d', $data)[1];
        } else {
          $data = '<fix64> '.bin2hex($data);
        }
        setValue($message, $name, $data, $isRepeated);
        break;
      case 2:
        $length = readVarInt32($pb);
        if (isset($proto[$typeName])) {
          $sub = parseProtoBuf($typeName, $pb->position()+$length, $pb, $proto);
          setValue($message, $name, $sub, $isRepeated);
        } else if ($length > 0) {
          $data = $pb->readData($length);
          if ($typeName == 'string') {
          } else {
            if (mb_detect_encoding($data, 'UTF-8', true)) {
              $data = '<guessed string> '.$data;
            } else {
              $data = '<byte> '.bin2hex($data);
            }
          }
          setValue($message, $name, $data, $isRepeated);
        }
        break;
      case 5:
        $data = $pb->readData(4);
        if ($typeName == 'float') {
          $data = unpack('f', $data)[1];
        } else {
          $data = '<fix32> '.bin2hex($data);
        }
        setValue($message, $name, $data, $isRepeated);
        break;
      default:
        throw new Exception('unsupported type '.$type);
    }
  }
  return $message;
}

function processDict(&$arr) {
  foreach ($arr as $key=>&$val) {
    if (gettype($val) == 'array') {
      if (isset($val[0]) && gettype($val[0]) == 'array') {
        $keys = array_keys($val[0]);
        if (count($keys) == 2
        && substr($keys[0], -4, 4) == '_key'
        && substr($keys[1], -6, 6) == '_value') {
          $newDict = [];
          foreach ($val as &$item) {
            $newDict[ $item[$keys[0]] ] = $item[$keys[1]];
          }
          unset($arr[$key]);
          $arr[$key] = $newDict;
          $val = &$arr[$key];
        }
      }
      processDict($val);

      $keys = array_keys($val);
      if (count($keys) == 1 && $keys[0] === 'entries') {
        $arr[$key] = $val['entries'];
      }
    }
  }
}

function prettifyJSON($in) {
  $in = new MemoryStream($in);
  $out = new MemoryStream('');

  $offset = 0;
  $length = $in->size;
  
  $level = 0;
  while($offset < $length) {
    $char = $in->readData(1);
    switch($char) {
      case '"':
        // write until unqoute
        $out->write($char);
        $skipNext = false;
        while (1) {
          $char = $in->readData(1);
          $out->write($char);
          $offset++;
          if ($skipNext) $skipNext = false;
          else {
            if ($char == '\\') $skipNext = true;
            else if ($char == '"') break;
          }
        }
        break;
      case '{':
      case '[':
        // increase level
        $level++;
        $out->write($char);
        $out->write("\n".str_repeat('  ', $level));
        break;
      case '}':
      case ']':
        $level--;
        $nextChar = $in->readData(1);
        if ($nextChar == ',') {
          $out->write("\n".str_repeat('  ', $level));
          $out->write($char);
          $out->write($nextChar);
          $out->write("\n".str_repeat('  ', $level));
          $offset++;
        } else {
          $out->write("\n".str_repeat('  ', $level));
          $out->write($char);
          if ($nextChar == '') $out->write("\n");
          $in->seek($offset+1);
        }
        break;
      case ',':
        // add space after comma
        $out->write($char);
        $out->write(' ');
        break;
      default:
        $out->write($char);
    }
    $offset++;
  }
  $out->seek(0);
  $output = $out->readData($out->size);
  unset($out);
  return $output;
}

$appver = file_exists('appver') ? file_get_contents('appver') : '2.1.0';
$itunesid = 1195834442;
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
        'game'=>'bangdream',
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

$header = [
  'Accept: application/octet-stream',
  'Content-Type: application/octet-stream',
  'X-ClientVersion: '.$appver,
  'X-Signature: 4edf79a0-2992-40a3-9646-2ca2ed402ec9',
  'X-ClientPlatform: iOS'
];
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL=>'https://api.star.craftegg.jp/api/application',
  CURLOPT_SSL_VERIFYPEER=>false,
  CURLOPT_HEADER=>false,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_HTTPHEADER=>$header
));
$VersionInfoData = new MemoryStream(decrypt(curl_exec($curl), 'mikumikulukaluka', 'lukalukamikumiku'));
$VersionInfoProto = parseProto('AppGetResponse_gen.proto');
$VersionInfo = parseProtoBuf('AppGetResponse', $VersionInfoData->size, $VersionInfoData, $VersionInfoProto);

$commit = [];
if (empty($VersionInfo['dataVersion']) || empty($VersionInfo['masterDataVersion'])) {
  _log('invalid response');
  exit;
}
$dataVer = $VersionInfo['dataVersion'];
$masterVer = $VersionInfo['masterDataVersion'];
_log("master: ${masterVer} data: ${dataVer}");

if ($masterVer != $last_version['master']) {

_log('downloading master');
$header[] = 'X-DataVersion: '.$dataVer;
$header[] = 'X-MasterDataVersion: '.$masterVer;
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api.star.craftegg.jp/api/suite/master',
  CURLOPT_HTTPHEADER=>$header
));
file_put_contents('master.dat', decrypt(curl_exec($curl), 'mikumikulukaluka', 'lukalukamikumiku'));
$masterData = new FileStream('master.dat');
//$masterData = new FileStream('master');

$MasterProto = parseProto('SuiteMaster_gen.proto');
$master = parseProtoBuf('SuiteMasterGetResponse', $masterData->size, $masterData, $MasterProto);
processDict($master);
unset($masterData, $MasterProto);

$count = count(array_keys($master));
_log("dumping master ${count} entries");

chdir('data');
exec('git rm *.json --cached');
chdir(__DIR__);
foreach (glob('data/*.json') as $file) {$file!='data/AssetBundleInfo.json'&&unlink($file);}

foreach ($master as $part=>&$data) {
  file_put_contents("data/${part}.json", prettifyJSON(json_encode($data, JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE)));
}

$situationMap = &$master['masterCharacterSituationMap'];
$charaInfo = &$master['masterCharacterInfoMap'];
foreach ($situationMap as &$entry) {
  $id = substr($entry['resourceSetName'], 3);
  $names[$id] = str_repeat('★',$entry['rarity']).'['.$entry['prefix'].']'.$charaInfo[$entry['characterId']]['characterName'];
}
file_put_contents(RESOURCE_PATH_PREFIX.'card/index.json', json_encode($names, JSON_UNESCAPED_SLASHES));
unset($situationMap, $charaInfo, $names, $master);
file_put_contents('data/!masterDataVersion.txt', $masterVer."\n");
$commit[] = 'master: '.$masterVer;
$last_version['master'] = $masterVer;

}

if ($dataVer != $last_version['data']) {

_log('downloading manifest');
$header = [
  'User-Agent: band/0 CFNetwork/758.4.3 Darwin/15.5.0',
  'Accept-Language: ja-jp',
  'X-Unity-Version: 5.4.1f1'
];
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://d2ktlshvcuasnf.cloudfront.net/Release/'.$dataVer.'/iOS/AssetBundleInfo',
  CURLOPT_HTTPHEADER=>$header
));
file_put_contents('manifest.dat', curl_exec($curl));
$bundleInfoData = new FileStream('manifest.dat');
$bundleInfoProto = parseProto('AssetBundleInfo_gen.proto');
_log('dumping manifest');
$bundleInfo = parseProtoBuf('AssetBundleInfo', $bundleInfoData->size, $bundleInfoData, $bundleInfoProto);
processDict($bundleInfo);
foreach ($bundleInfo['bundles'] as &$item) {
  unset($item['version']);
}
file_put_contents('data/AssetBundleInfo.json', prettifyJSON(json_encode($bundleInfo, JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE)));
unset($bundleInfoData, $bundleInfoProto, $bundleInfo);
file_put_contents('data/!dataVersion.txt', $dataVer."\n");
$commit[] = 'data: '.$dataVer;
$last_version['data'] = $dataVer;
$dataUpdated = true;

}

if (!empty($commit)) {
  chdir('data');
  exec('git add *.json !dataVersion.txt !masterDataVersion.txt');
  exec('git commit -m "'.implode(' ', $commit).'"');
  exec('git push origin master');
}

chdir(__DIR__);
file_put_contents('last_version', json_encode($last_version));

if (isset($dataUpdated)) {
  checkAndUpdateResource($dataVer);
}
