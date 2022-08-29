<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

define('ASSET_DOMAIN', 'https://assets-danmakujp.cdn-dena.com');

require_once 'UnityAsset.php';
require_once 'sec_dankagu.php';
require_once 'DankaguScore.php';

$resourceToExport = [
  [ 'bundleNameMatch'=>'/^\/ios\/texture\/res_ch_card_c\/res_ch_card_c_(\d+)\.xab$/', 'nameMatch'=>'/^res_ch_card_c_(\d+)$/',     'exportTo'=>'card/$1' ],
  [ 'bundleNameMatch'=>'/^\/ios\/texture\/res_jacket\/res_jacket_(\d+)\.xab$/', 'nameMatch'=>'/^res_jacket_(\d+)$/',     'exportTo'=>'jacket/$1' ],
];

function shouldExportFile($name, $rule) {
  return preg_match($rule['nameMatch'], $name) != 0;
}

$cacheHashDb = new PDO('sqlite:'.__DIR__.'/cacheHash.db');
$chkHashStmt = $cacheHashDb->prepare('SELECT hash FROM cacheHash WHERE res=?');
function shouldUpdate($name, $hash) {
  global $chkHashStmt;
  $chkHashStmt->execute([$name]);
  $row = $chkHashStmt->fetch();
  return !(!empty($row) && $row['hash'] == $hash);
}
$setHashStmt = $cacheHashDb->prepare('REPLACE INTO cacheHash (res,hash) VALUES (?,?)');
function setHashCached($name, $hash) {
  global $setHashStmt;
  $setHashStmt->execute([$name, $hash]);
}

function findRule($name, $rules) {
  foreach ($rules as $rule) {
    if (preg_match($rule['bundleNameMatch'], $name) != 0) return $rule;
  }
  return false;
}

$chkTextureHashStmt = $cacheHashDb->prepare('SELECT hash FROM textureHash WHERE res=?');
function textureHasUpdated($name, Texture2D &$item) {
  global $chkTextureHashStmt;
  $hash = crc32($item->imageData);
  $item->imageDataHash = $hash;
  $chkTextureHashStmt->execute([$name]);
  $row = $chkTextureHashStmt->fetch();
  return !(!empty($row) && $row['hash'] == $hash);
}
$setTextureHashStmt = $cacheHashDb->prepare('REPLACE INTO textureHash (res,hash) VALUES (?,?)');
function updateTextureHash($name, Texture2D &$item) {
  global $setTextureHashStmt;
  $setTextureHashStmt->execute([$name, $item->imageDataHash]);
}

define('RESOURCE_PATH_PREFIX', '/data/home/web/_redive/dankagu/');
//define('RESOURCE_PATH_PREFIX', 'D:/cygwin64/home/ester/quickcode/bang/img/');

function checkAndUpdateResource($dataVer) {
  global $resourceToExport;
  global $curl;
  chdir(__DIR__);
  $currenttime = time();
  curl_setopt_array($curl, array(
    //CURLOPT_PROXY=>'127.0.0.1:23456',
    //CURLOPT_PROXYTYPE=>CURLPROXY_SOCKS5,
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_HTTPHEADER => [
      'User-Agent: dankagu/1.0.311 CFNetwork/1126 Darwin/19.5.0',
      'X-Unity-Version: 2019.4.14f1'
    ],
  ));
  $manifest = json_decode(file_get_contents("manifest/$dataVer.json"), true);
  $assetDir = ASSET_DOMAIN.$manifest['assetDir'];

  // platform bundle
  foreach ($manifest['manifest'][2] as $info) {
    //break;
    $name = $info['AssetPath'];
    if (($rule = findRule($name, $resourceToExport)) !== false && shouldUpdate($name, $info['FileHash'])) {
      _log('download '. $name);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>$assetDir.$info['HashedPath'],
      ));
      $bundleData = curl_exec($curl);
      if (strlen($bundleData) != $info['FileSize']) {
        _log('download failed  '.$name);
        continue;
      }
      Encryptor::$encryptors[2001]->Modify($bundleData, 0, strlen($bundleData), 0, $info['DownloadOption'] & 0xfff);
      $bundleData = new MemoryStream($bundleData);
      $assets = extractBundle($bundleData);
      unset($bundleData);

      try{
      
      foreach ($assets as $asset) {
        if (substr($asset, -5,5) == '.resS') continue;
        $asset = new AssetFile($asset);
    
        foreach ($asset->preloadTable as &$item) {
          if ($item->typeString == 'Texture2D') {
            try {
              $item = new Texture2D($item, true);
            } catch (Exception $e) { continue; }
            $itemname = $item->name;
            if (isset($rule['namePrefix'])) {
              $itemname = preg_replace($rule['bundleNameMatch'], $rule['namePrefix'], $name).$itemname;
            }
            if (isset($rule['print'])) {
              var_dump($itemname);
              continue;
            }
            if (shouldExportFile($itemname, $rule) && textureHasUpdated("$name:$itemname", $item)) {
              $saveTo = RESOURCE_PATH_PREFIX. preg_replace($rule['nameMatch'], $rule['exportTo'], $itemname);
              $param = '-lossless 1';
              if (isset($rule['extraParam'])) $param .= ' '.$rule['extraParam'];
              if (isset($rule['extraParamCb'])) @$param .= ' '.call_user_func($rule['extraParamCb'], $item);
              $item->exportTo($saveTo, 'webp', $param);
              if (filemtime($saveTo. '.webp') > $currenttime)
              touch($saveTo. '.webp', $currenttime);
              updateTextureHash("$name:$itemname", $item);
            }
            unset($item);
          }
        }
        $asset->__desctruct();
        unset($asset);
        gc_collect_cycles();
      }

      } catch(Exception $e) {
        $asset->__desctruct();
        unset($asset);
        _log('Not supported: '. $e->getMessage());
      }

      foreach ($assets as $asset) {
        unlink($asset);
      }
      //if (isset($rule['print'])) exit;
      setHashCached($name, $info['FileHash']);
    }
  }

  // common data
  $exportRule = [
    [ 'bundleNameMatch'=>'/^\/common\/scenario\/(.+).dat$/', 'exportTo' => 'scenario/$1', 'callback'=>'dumpScenario' ],
    [ 'bundleNameMatch'=>'/^\/common\/sc\/(.+).dat$/', 'exportTo' => 'score', 'callback'=>'dumpScore' ],
  ];
  foreach ($manifest['manifest'][3] as $info) {
    //break;
    $name = $info['AssetPath'];
    if (($rule = findRule($name, $exportRule)) !== false && shouldUpdate($name, $info['FileHash'])) {
      _log('download '. $name);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>$assetDir.$info['HashedPath'],
      ));
      $dlData = curl_exec($curl);
      if (strlen($dlData) != $info['FileSize']) {
        _log('download failed  '.$name);
        continue;
      }
      $exportName = RESOURCE_PATH_PREFIX.preg_replace($rule['bundleNameMatch'], $rule['exportTo'], $name);
      call_user_func($rule['callback'], $exportName, $dlData, $info['DownloadOption'] & 0xfff);
      setHashCached($name, $info['FileHash']);
    }
  }
  $awbIdx = [];
  for ($i=0; $i<count($manifest['manifest'][3]); $i++) {
    if (substr($manifest['manifest'][3][$i]['AssetPath'], -4, 4) === '.awb') {
      $awbIdx[$manifest['manifest'][3][$i]['AssetPath']] = $i;
    }
  }
  // sound
  $exportRule = [
    [ 'bundleNameMatch'=>'/^\/common\/sn\/bgm\/BGM_(\d+)\.acb$/', 'exportTo' => 'sound/music', 'callback'=>'dumpMusic' ],
  ];
  foreach ($manifest['manifest'][3] as $info) {
    $name = $info['AssetPath'];
    if (($rule = findRule($name, $exportRule)) !== false && shouldUpdate($name, $info['FileHash'])) {
      _log('download '. $name);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>$assetDir.$info['HashedPath'],
      ));
      $acbData = curl_exec($curl);
      if (strlen($acbData) != $info['FileSize']) {
        _log('download failed  '.$name);
        continue;
      }
      $awbName = substr($name, 0, -4) . '.awb';
      if (isset($awbIdx[$awbName])) {
        $awbInfo = $manifest['manifest'][3][$awbIdx[$awbName]];
        _log('download '. $awbName);
        curl_setopt_array($curl, array(
          CURLOPT_URL=>$assetDir.$awbInfo['HashedPath'],
        ));
        $awbData = curl_exec($curl);
        if (strlen($awbData) != $awbInfo['FileSize']) {
          _log('download failed  '.$awbName);
          continue;
        }
        $awbName = pathinfo($awbName, PATHINFO_BASENAME);
        file_put_contents($awbName, $awbData);
      }
      $acbName = pathinfo($name, PATHINFO_BASENAME);
      file_put_contents($acbName, $acbData);
      $exportName = RESOURCE_PATH_PREFIX.preg_replace($rule['bundleNameMatch'], $rule['exportTo'], $name);
      call_user_func($rule['callback'], $exportName, $acbName, $info['DownloadOption'] & 0xfff);
      setHashCached($name, $info['FileHash']);
    }
  }

  // master
  $dbDumpAfter = gmdate('Ymd-His', time() - 28 * 24 * 3600 + 9 * 3600);
  foreach ($manifest['manifest'][4] as $info) {
    $name = $info['AssetPath'];
    if (pathinfo($name, PATHINFO_EXTENSION) === 'db') {
      if (substr(pathinfo($name, PATHINFO_FILENAME), 14, 15) < $dbDumpAfter) {
        continue;
      }
    }
    if (shouldUpdate($name, $info['FileHash'])) {
      _log('download '. $name);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>$assetDir.$info['HashedPath'],
      ));
      $dbData = curl_exec($curl);
      if (strlen($dbData) != $info['FileSize']) {
        _log('download failed  '.$name);
        continue;
      }
      dumpDb($name, $dbData, $info['DownloadOption'] & 0xfff);
      setHashCached($name, $info['FileHash']);
    }
  }
}

function dumpScenario($name, $data, $salt) {
  $encryptor = Encryptor::$encryptors[2002];
  $encryptor->Modify($data, 0, strlen($data), 0, $salt);
  $data = gzdecode($data);
  $size = octdec(substr($data, 124, 11));
  $data = substr($data, 512, $size);
  $lines = explode("\n", trim(str_replace("\r", "", $data)));
  $output = ['<!DOCTYPE html><meta charset="UTF-8" name="viewport" content="width=device-width"><style>body{background:#444;color:#FFF;font-family:Meiryo;-webkit-text-size-adjust:none;cursor:default}#contents{white-space:pre-wrap}</style><div id="contents">'];
  $cardTextLines = [];
  for ($i=0; $i<count($lines); $i++) {
    $params = explode(',', $lines[$i]);
    if (!isset($params[0])) continue;
    switch ($params[0]) {
      case 'INFO_BOARD': {
        $infoLines = $params[1];
        $output[] = '--------------';
        for ($j=0; $j<$infoLines; $j++) {
          $output[] = $lines[++$i];
        }
        $output[] = '--------------';
        $output[] = '';
        break;
      }
      case 'TALK': {
        $talkLines = $params[11];
        $output[] = $params[1].'：';
        for ($j=0; $j<$talkLines; $j++) {
          $output[] = $lines[++$i];
        }
        $output[] = '';
        break;
      }
      case 'SET_CARD_TEXT': {
        $infoLines = $params[1];
        for ($j=0; $j<$infoLines; $j++) {
          $cardTextLines[] = $lines[++$i];
        }
        break;
      }
    }
  }
  if (!empty($cardTextLines)) {
    $cardTextLines = implode("\n", $cardTextLines);
    $output[] = '<style>body{max-width:800px;margin:0 auto}.align-left{text-align:left;padding-right:2em}.align-center{text-align:center;padding:0 1em}.align-right{text-align:right;padding-left:2em}</style><div class="align-left">'.preg_replace_callback('/<\/?(\w+)(="?([0-9a-zA-Z\.\,]+)"?)?>/', function ($m) {
      if ($m[1] === 'ALIGNRIGHT') {
        return '</div><div class="align-right">';
      } else if ($m[1] === 'ALIGNLEFT') {
        return '</div><div class="align-left">';
      } else if ($m[1] === 'ALIGNCENTER') {
        return '</div><div class="align-center">';
      } else if ($m[1] === 'PAGEBREAK') {
        return "\n<hr>\n";
      } else if ($m[0][1] === '/') {
        return '</span>';
      } else if ($m[1] === 'size') {
        return '<span style="font-size:'.$m[3].'">';
      } else if ($m[1] === 'color') {
        return '<span style="color:'.$m[3].'">';
      } else if ($m[1] === 'style') {
        return '<span class="'.$m[3].'">';
      }
      return '<span data-unknown-tag="'.base64_encode($m[0]).'">';
    }, $cardTextLines).'</div>';
  }
  $output[] = '</div>';
  checkAndCreateFile("$name.html", implode("\n", $output));
  checkAndCreateFile("$name.txt", $data);
}

function dumpScore($name, $data, $salt) {
  $encryptor = Encryptor::$encryptors[2002];
  $encryptor->Modify($data, 0, strlen($data), 0, $salt);
  $data = gzdecode($data);
  $scores = DankaguScore::parseScoreArchive($data);
  foreach ($scores as $id=>$score) {
    $id = pathinfo($id, PATHINFO_FILENAME);
    $svg = DankaguScore::scoreToSvg($score);
    checkAndCreateFile("$name/json/$id.json", prettifyJSON(json_encode($score)));
    if ($svg) {
      checkAndCreateFile("$name/svg/$id.svg", $svg);
    }
  }
}

function dumpMusic($saveTo, $acbName, $salt) {
  $nullptr = NULL;
  exec('acb2wavs '.$acbName.' -b 141711 -a 9b4fd22b -n', $nullptr);
  $acbUnpackDir = '_acb_'.$acbName;
  foreach (['internal', 'external'] as $awbFolder) {
    if (file_exists($acbUnpackDir .'/'. $awbFolder)) {
      foreach (glob($acbUnpackDir .'/'. $awbFolder.'/*.wav') as $waveFile) {
        if (substr($waveFile, -12, 12) === '_Preview.wav') continue;
        $m4aFile = substr($waveFile, 0, -3).'m4a';
        $finalPath = $saveTo.'/'.pathinfo($m4aFile, PATHINFO_BASENAME);
        exec('ffmpeg -hide_banner -loglevel quiet -y -i '.$waveFile.' -vbr 5 -movflags faststart '.$m4aFile, $nullptr);
        checkAndMoveFile($m4aFile, $finalPath);
      }
    }
  }
  delTree($acbUnpackDir);
  unlink($acbName);
  $awbName = substr($acbName, 0, -4) . '.awb';
  file_exists($awbName) && unlink($awbName);
}

function dumpDb($name, $data, $salt) {
  $encryptor = Encryptor::$encryptors[2003];
  $rijn = Encryptor::$encryptors[1501];
  $encryptor->Modify($data, 0, strlen($data), 0, $salt);
  $data = $rijn->Transform($data, 0, strlen($data));
  file_put_contents('db.tar', gzdecode($data));
  @mkdir('db_temp'); chdir('db_temp');
  exec('7za e -y -bb0 ../db.tar');
  @mkdir('json'); chdir('json');
  exec('bash ../../convert_db.sh');
  $dir = __DIR__.'/master/'.pathinfo($name, PATHINFO_FILENAME);
  @mkdir($dir, 0777, true);
  foreach (glob('*.json') as $f) {
    $json = json_encode(json_decode(file_get_contents($f)), JSON_UNESCAPED_UNICODE+JSON_UNESCAPED_SLASHES+JSON_PRETTY_PRINT);
    touch("___temp.json");
    prettifyJSON($json, new FileStream("___temp.json"), false);
    checkAndMoveFile("___temp.json", "$dir/$f");
    if ($f === 'MessageMap.json') {
      $messages = json_decode(file_get_contents('MessageMap.json'), true);
      $out = [];
      foreach ($messages['Entries'] as $m) {
        $l = explode('_', $m['Label']);
        $pos = &$out;
        for ($i=0; $i<count($l) - 1; $i++) {
          if (!isset($pos[$l[$i]])) $pos[$l[$i]] = [];
          $pos = &$pos[$l[$i]];
        }
        $pos[$l[$i]] = $m['Value'];
      }
      checkAndCreateFile("$dir/MessageMap-parsed.json", json_encode($out, JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE+JSON_PRETTY_PRINT));
    }
    unlink($f);
  }
  chdir('..'); rmdir('json');
  array_map('unlink', glob('*.*'));
  chdir('..'); rmdir('db_temp');
  unlink('db.tar');
}

function prettifyJSON($in, Stream $out = NULL, $returnData = true) {
  if ($out == NULL) $out = new MemoryStream('');

  $a = json_decode($in);
  $a = json_encode($a, JSON_UNESCAPED_UNICODE+JSON_UNESCAPED_SLASHES+JSON_PRETTY_PRINT);
  $a = preg_replace("/([^\]\}]),\n +/", "$1, ", $a);
  $a = preg_replace('/("[^"]+?":) /', '$1', $a);
  $a = preg_replace_callback("/\n +/", function ($m) { return "\n".str_repeat(' ', (strlen($m[0])-1) / 2); }, $a);
  $out->write($a);

  if (!$returnData) return;
  $out->seek(0);
  $output = $out->readData($out->size);
  unset($out);
  return $output;
}

function delTree($dir) {
  $files = array_diff(scandir($dir), array('.','..'));
  foreach ($files as $file) {
    (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
  }
  return rmdir($dir);
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  chdir(__DIR__);
  $curl = curl_init();
  function _log($s) {echo "$s\n";}
  $dataVer = json_decode(file_get_contents('last_version.json'), true)['data'];
  var_dump($dataVer);
  checkAndUpdateResource($dataVer);
  /*$asset = new AssetFile('CAB-4856cccde53d6f3bfd0054253f1639a8');
  foreach ($asset->preloadTable as &$item) {
    if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      _log($item->name);
      $item->exportTo($item->name, 'webp', '-lossless 1');
    }
  }*/
}
//print_r($asset);

