<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

require_once 'UnityAsset.php';

$poseData = [];
$resourceToExport = [
  [ 'bundleNameMatch'=>'/^card_bg_\d+.unity3d$/', 'nameMatch'=>'/^bg_(\d+)$/', 'exportTo'=>'card/$1' ],
  [ 'bundleNameMatch'=>'/^chara_(\d{3}_\d{2})_base.unity3d$/', 'namePrefixCb'=>function ($s)use(&$poseData){return isset($poseData[$s[1]])?$poseData[$s[1]]:$s[1];}, 'nameMatch'=>'/^([\d_]+\d).+?$/', 'exportTo'=>'card/$1_trim' ],
  [ 'bundleNameMatch'=>'/^latte_art_page_\d+.unity3d$/', 'nameMatch'=>'/^latte_art_page_(\d+)_(\d+)$/', 'exportTo'=>'gekijou/$1/$2' ],
  [ 'bundleNameMatch'=>'/^title_bg_\d+.unity3d$/', 'nameMatch'=>'/^(\d+)$/', 'exportTo'=>'title/$1' ],
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

define('RESOURCE_PATH_PREFIX', '/data/home/web/_redive/cgss/');
//define('RESOURCE_PATH_PREFIX', 'D:/cygwin64/home/ester/quickcode/cgss/');

function checkAndUpdateResource() {
  global $resourceToExport;
  $curl = curl_init();
  global $poseData;
  chdir(__DIR__);
  $currenttime = time();
  curl_setopt_array($curl, array(
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_HTTPHEADER=>['X-Unity-Version: 2020.3.8f1'],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_FILETIME=>true
  ));
  $manifest = new PDO('sqlite:manifest.db');;

  foreach (execQuery($manifest, 'SELECT name,hash FROM manifests') as $info) {
    $name = $info['name'];
    $hash = $info['hash'];
    if (($rule = findRule($name, $resourceToExport)) !== false && shouldUpdate($name, $hash)) {
      _log('download '. $name);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'https://asset-starlight-stage.akamaized.net/dl/resources/AssetBundles/'.substr($hash, 0, 2).'/'.$hash,
      ));
      $bundleData = curl_exec($curl);
      $bundleTime = curl_getinfo($curl, CURLINFO_FILETIME);
      $bundleTime -= $bundleTime % 60;
      $gotHash = hash('md5', $bundleData);
      if ($gotHash != $hash) {
        _log('download failed  '.$name." expected $hash got $gotHash");
        continue;
      }
      $bundleData = new MemoryStream(cgss_data_uncompress($bundleData));
      $assets = extractBundle($bundleData);

      try{
      
      foreach ($assets as $asset) {
        if (substr($asset, -5,5) == '.resS') continue;
        $asset = new AssetFile($asset);
    
        foreach ($asset->preloadTable as &$item) {
          if ($item->typeString == 'Texture2D') {
            $item = new Texture2D($item, true);
            if (isset($rule['print'])) {
              var_dump($item->name);
              continue;
            }
            $itemname = $item->name;
            if (isset($rule['namePrefix'])) {
              $itemname = preg_replace($rule['bundleNameMatch'], $rule['namePrefix'], $name).$itemname;
            }
            if (isset($rule['namePrefixCb'])) {
              $itemname = preg_replace_callback($rule['bundleNameMatch'], $rule['namePrefixCb'], $name).$itemname;
            }
            if (shouldExportFile($itemname, $rule)) {
              $saveTo = RESOURCE_PATH_PREFIX. preg_replace($rule['nameMatch'], $rule['exportTo'], $itemname);
              $item->exportTo($saveTo, 'webp', '-lossless 1');
              touch($saveTo. '.webp', $bundleTime);
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
      unset($bundleData);
      if (isset($rule['print'])) exit;
      setHashCached($name, $hash);
    }
  }
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  chdir(__DIR__);
  $curl = curl_init();
  function _log($s) {echo "$s\n";}
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
  function execQuery($db, $query) {
    $returnVal = [];
    $result = $db->query($query);
    $returnVal = $result->fetchAll(PDO::FETCH_ASSOC);
    return $returnVal;
  }
  $db = new PDO('sqlite:master.db');
  $poseData=[];
  foreach(execQuery($db, 'SELECT id,name,chara_id,pose FROM card_data') as $row) {
    $poseData[sprintf('%03d_%02d', $row['chara_id'], $row['pose'])] = $row['id'];
  }
  unset($db);
  checkAndUpdateResource();
  //print_r(extractBundle(new FileStream('bundle/-card_bg_100062.unity3d')));
  /*$asset = new AssetFile('CAB-ed8bb316f8c8bc10b8cb0f403c1e3f35');
  foreach ($asset->preloadTable as &$item) {
    if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      _log($item->name);
      $item->exportTo($item->name, 'bmp');
    }
  }*/
}
//print_r($asset);

