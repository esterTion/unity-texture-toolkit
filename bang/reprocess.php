<?php

chdir(__DIR__);
require_once 'UnityBundle.php';
require_once 'UnityAsset.php';
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

function readVarInt32($pb){
	$b=0;
	$result=0;
	
	do{
		$b=ord($pb->readData(1));
		$result  = $b & 0x7f;
		if(!( $b & 0x80 ))
			break;
		
		$b=ord($pb->readData(1));
		$result |= ($b & 0x7f)<<7;
		if(!( $b & 0x80 ))
			break;
		
		$b=ord($pb->readData(1));
		$result |= ($b & 0x7f)<<14;
		if(!( $b & 0x80 ))
			break;
		
		$b=ord($pb->readData(1));
		$result |= ($b & 0x7f)<<21;
		if(!( $b & 0x80 ))
			break;
		
		$b=ord($pb->readData(1));
		$result |= ($b & 0x7f)<<28;
		if(!( $b & 0x80 ))
			break;
		
		for($i=0;$i<5;$i++){
			$b=ord($pb->readData(1));
			if(!( $b & 0x80 ))
				break;
		}
	}while(false);
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
            $data = '<byte> '.bin2hex($data);
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

$masterVer = $last_version['master'];
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
$charaInfo = &$master['masterCharacterInfoMap']['entries'];
foreach ($situationMap['entries'] as &$entry) {
  $id = substr($entry['resourceSetName'], 3);
  $names[$id] = str_repeat('★',$entry['rarity']).'['.$entry['prefix'].']'.$charaInfo[$entry['characterId']]['characterName'];
}
file_put_contents(RESOURCE_PATH_PREFIX.'card/index.json', json_encode($names, JSON_UNESCAPED_SLASHES));
unset($situationMap, $charaInfo, $names, $master);
file_put_contents('data/!masterDataVersion.txt', $masterVer."\n");
$commit[] = 'master: '.$masterVer.' (re-process)';
$last_version['master'] = $masterVer;

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
