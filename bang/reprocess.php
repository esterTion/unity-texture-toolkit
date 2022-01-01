<?php

chdir(__DIR__);
require_once 'resource_fetch.php';
if (!file_exists('last_version')) {
  $last_version = array('data'=>'0','master'=>'0');
} else {
  $last_version = json_decode(file_get_contents('last_version'), true);
}

function _log($s) {
  //fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo $s."\n";
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

function parseProtoBuf($messageName, $end, &$pb, &$proto, $messageCallback = null, $level = 1) {
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
          $sub = parseProtoBuf($typeName, $pb->position()+$length, $pb, $proto, $messageCallback, $level + 1);
          if ($messageCallback === null || call_user_func($messageCallback, $name, $sub, $level)) {
            setValue($message, $name, $sub, $isRepeated);
          }
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
  $a = $in;
  if (gettype($a) == 'string') $a = json_decode($a);
  $a = json_encode($a, JSON_UNESCAPED_UNICODE+JSON_UNESCAPED_SLASHES+JSON_PRETTY_PRINT);
  $a = preg_replace("/([^\]\}]),\n +/", "$1, ", $a);
  $a = preg_replace('/("[^"]+?":) /', '$1', $a);
  $a = preg_replace_callback("/\n +/", function ($m) { return "\n".str_repeat(' ', (strlen($m[0])-1) / 2); }, $a);
  return $a;
}

$masterVer = $last_version['master'];
$masterData = new FileStream('master.dat');
//$masterData = new FileStream('master');

chdir('data');
exec('git rm *.json --cached');
chdir(__DIR__);
foreach (glob('data/*.json') as $file) {$file!='data/AssetBundleInfo.json'&&unlink($file);}

$MasterProto = parseProto('SuiteMaster_gen.proto');
_log("dumping master");
parseProtoBuf('SuiteMasterGetResponse', $masterData->size, $masterData, $MasterProto, function ($name, $sub, $level) {
  if ($level > 1) return true;
  $sub = ['a'=>$sub];
  processDict($sub);
  $sub = $sub['a'];
  if ($name === 'masterCharacterSituationMap') {
    global $situationMap;
    $situationMap = $sub;
  } else if ($name === 'masterCharacterInfoMap') {
    global $charaInfo;
    $charaInfo = $sub;
  }
  file_put_contents("data/${name}.json", prettifyJSON($sub));
  return false;
});
unset($masterData, $MasterProto);

foreach ($situationMap as &$entry) {
  $id = substr($entry['resourceSetName'], 3);
  $names[$id] = str_repeat('★',$entry['rarity']).'['.$entry['prefix'].']'.$charaInfo[$entry['characterId']]['characterName'];
}
file_put_contents(RESOURCE_PATH_PREFIX.'card/index.json', json_encode($names, JSON_UNESCAPED_SLASHES));
unset($situationMap, $charaInfo, $names);
file_put_contents('data/!masterDataVersion.txt', $masterVer."\n");
$commit[] = 'master: '.$masterVer.' (re-process)';
$last_version['master'] = $masterVer;

if (!empty($commit)) {
  chdir('data');
  exec('git add *.json !dataVersion.txt !masterDataVersion.txt');
  exec('git commit -m "'.implode(' ', $commit).'"');
  //exec('git push origin master');
}

chdir(__DIR__);
file_put_contents('last_version', json_encode($last_version));

if (isset($dataUpdated)) {
  checkAndUpdateResource($dataVer);
}
