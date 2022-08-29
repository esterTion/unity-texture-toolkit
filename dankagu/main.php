<?php

chdir(__DIR__);
require 'flatc/D4AO_autoloader.php';
use \D4L\TakashoFes\Master\AssetPathRaw;
use \Google\FlatBuffers\ByteBuffer;
require_once 'UnityBundle.php';
require_once 'resource_fetch.php';
require_once 'sec_dankagu.php';
if (!file_exists('last_version.json')) {
  $last_version = array('data'=>'0');
} else {
  $last_version = json_decode(file_get_contents('last_version.json'), true);
}

$logFile = fopen('dankagu.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo $s."\n";
}

$exe = "E:\\VS\\danmaku\\build\\MinSizeRel\\danmaku";
$exe = "./danmaku";
$exeConfig = json_decode(file_get_contents('danmaku.json'), true);
list($appVer, $assetKey, $gameId) = [$exeConfig['app_ver'], $exeConfig['asset_key'], $exeConfig['game_id']];

exec("$exe get_root $appVer > net-root.json");
exec("$exe get_filelist $appVer AssetUploadV2+$assetKey+g$gameId > net-filelist.json");

$filelist = json_decode(file_get_contents('net-filelist.json'), true);
if (empty($filelist)) exit;

$latest_filelist = ['Date'=>0];
foreach ($filelist['Uploads'] as $i) {
  if ($i['Date'] > $latest_filelist['Date']) {
    $latest_filelist = $i;
  }
}

$dataVersions = array_reduce($latest_filelist['FileList'], function ($c, $i){$c[$i['DataVersion']] = $i['OpenAt'];return $c;}, []);
$currentDataVer = [0,0];
$now = time();
foreach ($dataVersions as $v=>$t) {
  if ($t > $currentDataVer[1] && $t < $now) {
    $currentDataVer = [$v, $t];
  }
}
$currentDataVer = $currentDataVer[0];
$manifestUrls = array_values(array_filter($latest_filelist['FileList'], function ($i) use($currentDataVer) {
  return $i['DataVersion'] == $currentDataVer && substr($i['DB'], 0, 5) === 'arg_m';
}));

$dataVer = $currentDataVer;
$assetDir = $latest_filelist['Path'];
$assetDir = "/assets/$assetDir/data";
$assetDate = pathinfo(dirname($assetDir), PATHINFO_BASENAME);

$dataVerNum = $dataVer;
$dataVer = "$dataVer-$assetDate";

if ($dataVer != $last_version['data']) {

  _log("data ver $dataVer, downloading manifest");
  $header = [
    'User-Agent: dankagu/1.0.311 CFNetwork/1126 Darwin/19.5.0',
    'X-Unity-Version: 2019.4.14f1'
  ];
  $curl = curl_init();
  curl_setopt_array($curl, array(
    //CURLOPT_PROXY=>'127.0.0.1:23456',
    //CURLOPT_PROXYTYPE=>CURLPROXY_SOCKS5,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_HEADER=>false,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>$header
  ));

  $manifestList = [];
  $lwe = Encryptor::$encryptors[1004];
  $rijn = Encryptor::$encryptors[1001];

  $rijnPwd = 'Pg97xygbey7aw';
  $rijnSalt = '857fesfd';
  $rijnIter = 117;
  $buf = openssl_pbkdf2($rijnPwd, $rijnSalt, 32, $rijnIter);
  $rijnKey = substr($buf, 0, 16);
  $rijnIv = substr($buf, 16, 16);

  foreach ($manifestUrls as $item) {
    $url = $assetDir.'/'.$item['HashPath'];
    $category = pathinfo(dirname(dirname($url)), PATHINFO_FILENAME);
    curl_setopt($curl, CURLOPT_URL, ASSET_DOMAIN.$url);
    $fData = curl_exec($curl);
    $salt = $item['Salt'];
    $lwe->Modify($fData, 0, strlen($fData), 0, $salt);
    $fData = $rijn->Transform($fData, 0, strlen($fData));
    if ($data = gzdecode($fData)) {
      $size = octdec(substr($data, 124, 11));
      $data = substr($data, 512, $size);
      $bb = ByteBuffer::wrap($data);
      $apr = AssetPathRaw::getRootAsAssetPathRaw($bb);
      if (!isset($manifestList[$category]) || $apr->getEntryLength() > $manifestList[$category]->getEntryLength()) {
        $manifestList[$category] = $apr;
      }
    }
  }

  foreach ($manifestList as $category=>$apr) {
    $count = $apr->getEntryLength();
    $entries = [];
    for ($j=0; $j<$count; $j++) {
      $entry = $apr->getEntry($j);
      $entry = [
        'ID' => $entry->getID(),
        'AssetPath' => $entry->getAssetPath(),
        'HashedPath' => $entry->getHashedPath(),
        'FileHash' => $entry->getFileHash(),
        'FileSize' => $entry->getFileSize(),
        'FileRev' => $entry->getFileRev(),
        'FileType' => $entry->getFileType(),
        'DownloadOption' => $entry->getDownloadOption(),
      ];
      $entries[] = $entry;
    }
    $manifestList[$category] = $entries;
  }

  _log("done");
  file_put_contents("manifest/$dataVer.json", json_encode(['assetDir' => $assetDir, 'manifest' => $manifestList], JSON_UNESCAPED_SLASHES));
  $last_version['data'] = $dataVer;
  file_put_contents('last_version.json', json_encode($last_version));

  checkAndUpdateResource($dataVer);

  $current = glob("master/*".str_pad($dataVerNum, 8, '0', STR_PAD_LEFT).'*');
  if (count($current)) {
    chdir(array_reverse($current)[0]);
    $messages = json_decode(file_get_contents('MessageMap-parsed.json'), true);

    $out = [];
    foreach ($messages['Music']['Title'] as $id=>$name) {
      $out[str_pad($id, 8, '0', STR_PAD_LEFT)] = "$name - ".$messages['Music']['Circle'][$id];
    }
    file_put_contents(RESOURCE_PATH_PREFIX.'jacket/index.json', json_encode($out));

    $musicscore = json_decode(file_get_contents('MusicScore.json'), true);
    $out = [];
    foreach ($musicscore['Entries'] as $entry) {
      $mid = floor($entry['ID'] / 10000);
      if (!isset($out[$mid])) $out[$mid] = [0];
      $out[$mid][$entry['ID']%10000] = $entry['MusicLevel'];
    }
    file_put_contents(RESOURCE_PATH_PREFIX.'jacket/index-level.json', json_encode($out));

    $creators = $messages['CreaterName']['CreaterName'];
    $music = json_decode(file_get_contents('Music.json'), true);
    $out = [];
    foreach ($music['Entries'] as $entry) {
      $mid = floor($entry['ID']);
      $out[$mid] = $creators[$entry['CreaterNo']?:0];
    }
    file_put_contents(RESOURCE_PATH_PREFIX.'jacket/index-cd.json', json_encode($out));

    $cardInfo = json_decode(file_get_contents('Card.json'), true)['Entries'];
    $out = [];
    foreach ($cardInfo as $card) {
      $id = $card['ID'];
      $rarity = ['', 'N','R','SR','SSR'][$card['Rarity']];
      $out[str_pad($id, 8, '0', STR_PAD_LEFT)] = "$rarity ".$messages['Card']['Name'][$id];
    }
    file_put_contents(RESOURCE_PATH_PREFIX.'card/index.json', json_encode($out));
  }
}

