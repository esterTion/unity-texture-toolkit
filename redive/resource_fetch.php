<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

require_once 'UnityAsset.php';

$resourceToExport = [
  'all' => [
    [ 'bundleNameMatch'=>'/^a\/all_battleunitprefab_\d+\.unity3d$/', 'customAssetProcessor'=> 'exportPrefab' ],
  ],
  'bg'=> [
    [ 'bundleNameMatch'=>'/^a\/bg_still_unit_\d+\.unity3d$/',       'nameMatch'=>'/^still_unit_(\d+)$/',     'exportTo'=>'card/full/$1' ]
  ],
  'icon'=>[
    [ 'bundleNameMatch'=>'/^a\/icon_icon_skill_\d+\.unity3d$/',     'nameMatch'=>'/^icon_skill_(\d+)$/',     'exportTo'=>'icon/skill/$1' ],
    [ 'bundleNameMatch'=>'/^a\/icon_icon_equipment_\d+\.unity3d$/', 'nameMatch'=>'/^icon_equipment_(\d+)$/', 'exportTo'=>'icon/equipment/$1' ],
    [ 'bundleNameMatch'=>'/^a\/icon_icon_item_\d+\.unity3d$/', 'nameMatch'=>'/^icon_item_(\d+)$/', 'exportTo'=>'icon/item/$1' ],
    [ 'bundleNameMatch'=>'/^a\/icon_unit_plate_\d+\.unity3d$/',     'nameMatch'=>'/^unit_plate_(\d+)$/',     'exportTo'=>'icon/plate/$1' ],
  ],
  'unit'=>[
    [ 'bundleNameMatch'=>'/^a\/unit_icon_unit_\d+\.unity3d$/',      'nameMatch'=>'/^icon_unit_(\d+)$/',      'exportTo'=>'icon/unit/$1' ],
    [ 'bundleNameMatch'=>'/^a\/unit_icon_shadow_\d+\.unity3d$/',    'nameMatch'=>'/^icon_shadow_(\d+)$/',    'exportTo'=>'icon/unit_shadow/$1' ],
    [ 'bundleNameMatch'=>'/^a\/unit_thumb_actual_unit_profile_\d+\.unity3d$/',    'nameMatch'=>'/^thumb_actual_unit_profile_(\d+)$/',    'exportTo'=>'card/actual_profile/$1', 'extraParam'=>'-s 1024x682' ],
    [ 'bundleNameMatch'=>'/^a\/unit_thumb_unit_profile_\d+\.unity3d$/',           'nameMatch'=>'/^thumb_unit_profile_(\d+)$/',           'exportTo'=>'card/profile/$1',        'extraParam'=>'-s 1024x682' ],
  ],
  'comic'=>[
    [ 'bundleNameMatch'=>'/^a\/comic_comic_l_\d+_\d+.unity3d$/',      'nameMatch'=>'/^comic_l_(\d+_\d+)$/',      'exportTo'=>'comic/$1', 'extraParam'=>'-s 682x512' ],
  ],
  'storydata'=>[
    [ 'bundleNameMatch'=>'/^a\/storydata_still_\d+.unity3d$/',      'nameMatch'=>'/^still_(\d+)$/',      'exportTo'=>'card/story/$1', 'extraParamCb'=>function(&$item){return ($item->width!=$item->height)?'-s '.$item->width.'x'.($item->width/16*9):'';} ],
    [ 'bundleNameMatch'=>'/^a\/storydata_\d+.unity3d$/',      'customAssetProcessor'=> 'exportStory' ],
    [ 'bundleNameMatch'=>'/^a\/storydata_spine_full_\d+.unity3d$/',      'customAssetProcessor'=> 'exportStoryStill' ],
  ],
  'spine'=>[
    [ 'bundleNameMatch'=>'/^a\/spine_000000_chara_base\.cysp\.unity3d$/', 'customAssetProcessor'=> 'exportSpine' ],
    [ 'bundleNameMatch'=>'/^a\/spine_\d\d_common_battle\.cysp\.unity3d$/', 'customAssetProcessor'=> 'exportSpine' ],
    [ 'bundleNameMatch'=>'/^a\/spine_10\d\d01_battle\.cysp\.unity3d$/',   'customAssetProcessor'=> 'exportSpine' ],
    [ 'bundleNameMatch'=>'/^a\/spine_sdnormal_10\d{4}\.unity3d$/',        'customAssetProcessor'=> 'exportAtlas' ],
  ],
  'sound'=>[
    [ 'bundleNameMatch'=>'/^v\/vo_cmn_(\d+)\.acb$/', 'exportTo'=> 'sound/unit_common/$1' ],
    [ 'bundleNameMatch'=>'/^v\/vo_navi_(\d+)\.acb$/', 'exportTo'=> 'sound/unit_common/$1' ],
    [ 'bundleNameMatch'=>'/^v\/vo_enavi_(\d+)\.acb$/', 'exportTo'=> 'sound/unit_common/$1' ],
    [ 'bundleNameMatch'=>'/^v\/t\/vo_adv_(\d+)\.acb$/', 'exportTo'=> 'sound/story_vo/$1' ],
    [ 'bundleNameMatch'=>'/^v\/vo_btl_(\d+)\.acb$/', 'exportTo'=> 'sound/unit_battle_voice/$1' ],
    [ 'bundleNameMatch'=>'/^v\/vo_(ci|speciallogin)_(\d+)\.acb$/', 'exportTo'=> 'sound/vo_$1/$2' ],
  ],
  'movie'=>[
    [ 'bundleNameMatch'=>'/^m\/(t\/)?(.+?)_(\d[\d_]*)\.usm$/', 'exportTo'=> 'movie/$2/$3' ],
    [ 'bundleNameMatch'=>'/^m\/(t\/)?(.+)\.usm$/', 'exportTo'=> 'movie/$2' ],
  ]
];

function exportSpine($asset, $remoteTime) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);

      // base chara skeleton
      if ($item->name == '000000_CHARA_BASE.cysp') {
        checkAndCreateFile(RESOURCE_PATH_PREFIX.'spine/common/000000_CHARA_BASE.cysp', $item->data, $remoteTime);
      }
      // class type animation
      else if (preg_match('/\d\d_COMMON_BATTLE\.cysp/', $item->name)) {
        checkAndCreateFile(RESOURCE_PATH_PREFIX.'spine/common/'.$item->name, $item->data, $remoteTime);
      }
      // character skill animation
      else if (preg_match('/10\d{4}_BATTLE\.cysp/', $item->name)) {
        checkAndCreateFile(RESOURCE_PATH_PREFIX.'spine/unit/'.$item->name, $item->data, $remoteTime);
      }
    }
  }
}
function exportAtlas($asset, $remoteTime) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      checkAndCreateFile(RESOURCE_PATH_PREFIX.'spine/unit/'.$item->name, $item->data, $remoteTime);
    } else if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      $saveTo = RESOURCE_PATH_PREFIX.'spine/unit/'.$item->name;
      $item->exportTo($saveTo, 'png');
      if (filemtime($saveTo.'.png') > $remoteTime)
      touch($saveTo.'.png', $remoteTime);
    }
  }
}

function exportStory($asset, $remoteTime) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      require_once 'RediveStoryDeserializer.php';
      $parser = new RediveStoryDeserializer($item->data);
      $name = substr($item->name, 10);
      checkAndCreateFile(RESOURCE_PATH_PREFIX.'story/data/'.$name.'.json', json_encode($parser->commandList), $remoteTime);
      checkAndCreateFile(RESOURCE_PATH_PREFIX.'story/data/'.$name.'.htm', $parser->data, $remoteTime);

      $storyStillName = json_decode(file_get_contents(RESOURCE_PATH_PREFIX.'spine/still/still_name.json'), true);
      $nextId = NULL;
      foreach($parser->commandList as $cmd) {
        if ($cmd['name'] == 'face') {
          $nextId = str_pad(
            substr($cmd['args'][0], 0, -1) . 1
            , 6, '0', STR_PAD_LEFT);
        } else if ($cmd['name'] == 'print' && $nextId) {
          $storyStillName[$nextId] = $cmd['args'][0];
          $nextId = NULL;
        } else if ($cmd['name'] == 'touch') {
          $nextId = NULL;
        }
      }
      file_put_contents(RESOURCE_PATH_PREFIX.'spine/still/still_name.json', json_encode($storyStillName));
    }
  }
}
function exportStoryStill($asset, $remoteTime) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      checkAndCreateFile(RESOURCE_PATH_PREFIX.'spine/still/unit/'.$item->name, $item->data, $remoteTime);
    } else if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      $saveTo = RESOURCE_PATH_PREFIX.'spine/still/unit/'.$item->name;
      $item->exportTo($saveTo, 'png');
      if (filemtime($saveTo.'.png') > $remoteTime)
      touch($saveTo.'.png', $remoteTime);
    }
  }
}

$prefabUpdated = false;
function exportPrefab($asset, $remoteTime) {
  global $prefabUpdated;
  $prefabUpdated = true;
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'MonoBehaviour') {
      $stream = $asset->stream;
      $stream->position = $item->offset;
      if (isset($asset->ClassStructures[$item->type1])) {
        $deserializedStruct = ClassStructHelper::DeserializeStruct($stream, $asset->ClassStructures[$item->type1]['members']);
        $organizedStruct = ClassStructHelper::OrganizeStruct($deserializedStruct);
        if (isset($organizedStruct['Attack'])) {
          $gameObjectPath = $organizedStruct['m_GameObject']['m_PathID'];
          $gameObject = $asset->preloadTable[$gameObjectPath];
          $stream->position = $gameObject->offset;
          $unitId = ClassStructHelper::OrganizeStruct(ClassStructHelper::DeserializeStruct($stream, $asset->ClassStructures[$gameObject->type1]['members']))['m_Name'];
          file_put_contents('prefabs/'.$unitId.'.json', json_encode($organizedStruct));
          break;
        }
      }
    }
  }
}

function shouldExportFile($name, $rule) {
  return preg_match($rule['nameMatch'], $name) != 0;
}

function parseManifest($manifest) {
  $manifest = new MemoryStream($manifest);
  $list=[];
  while (!empty($line = $manifest->line)) {
    list($name, $hash, $stage, $size) = explode(',', $line);
    $list[$name] = [
      'hash' =>$hash,
      'size' =>$size
    ];
  }
  unset($manifest);
  return $list;
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
  //var_dump($name, $rules);
  foreach ($rules as $rule) {
    if (preg_match($rule['bundleNameMatch'], $name) != 0) return $rule;
  }
  return false;
}

define('RESOURCE_PATH_PREFIX', '/data/home/web/_redive/');

function checkSubResource($manifest, $rules) {
  global $curl;
  foreach ($manifest as $name => $info) {
    if (($rule = findRule($name, $rules)) !== false && shouldUpdate($name, $info['hash'])) {
      _log('download '. $name.' '.$info['hash']);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/pool/AssetBundles/'.substr($info['hash'],0,2).'/'.$info['hash'],
      ));
      $bundleData = curl_exec($curl);
      $remoteTime = curl_getinfo($curl, CURLINFO_FILETIME);
      if (md5($bundleData) != $info['hash']) {
        _log('download failed  '.$name);
        continue;
      }
      $bundleData = new MemoryStream($bundleData);
      $assets = extractBundle($bundleData);
      foreach ($assets as $asset) {
        if (substr($asset, -4,4) == '.resS') continue;
        $asset = new AssetFile($asset);
    
        if (isset($rule['customAssetProcessor'])) {
          call_user_func($rule['customAssetProcessor'], $asset, $remoteTime);
        } else
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
              $param = '-lossless 1';
              if (isset($rule['extraParam'])) $param .= ' '.$rule['extraParam'];
              if (isset($rule['extraParamCb'])) $param .= ' '.call_user_func($rule['extraParamCb'], $item);
              $item->exportTo($saveTo, 'webp', $param);
              if (filemtime($saveTo. '.webp') > $remoteTime)
              touch($saveTo. '.webp', $remoteTime);
            }
            unset($item);
          }
        }
        $asset->__desctruct();
        unset($asset);
        gc_collect_cycles();
      }
      foreach ($assets as $asset) {
        unlink($asset);
      }
      unset($bundleData);
      if (isset($rule['print'])) exit;
      setHashCached($name, $info['hash']);
    }
  }
}

function checkSoundResource($manifest, $rules) {
  global $curl;
  foreach ($manifest as $name => &$info) {
    $info['hasAwb'] = false;
    if (substr($name, -4, 4) === '.awb') {
      $manifest[ substr($name, 0, -4) .'.acb' ]['hasAwb'] = true;
      $manifest[ substr($name, 0, -4) .'.acb' ]['awbName'] = $name;
      $manifest[ substr($name, 0, -4) .'.acb' ]['awbInfo'] = $info;
    }
  }
  foreach ($manifest as $name => $info) {
    if (($rule = findRule($name, $rules)) !== false && shouldUpdate($name, $info['hash'])) {
      _log('download '. $name.' '.$info['hash']);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/pool/Sound/'.substr($info['hash'],0,2).'/'.$info['hash'],
      ));
      $acbData = curl_exec($curl);
      $remoteTime = curl_getinfo($curl, CURLINFO_FILETIME);
      if (md5($acbData) != $info['hash']) {
        _log('download failed  '.$name);
        continue;
      }
      $acbFileName = pathinfo($name, PATHINFO_BASENAME);

      // has streaming awb, download it
      if ($info['hasAwb']) {
        $awbName = $info['awbName'];
        $awbInfo = $info['awbInfo'];
        _log('download '. $awbName.' '.$awbInfo['hash']);
        curl_setopt_array($curl, array(
          CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/pool/Sound/'.substr($awbInfo['hash'],0,2).'/'.$awbInfo['hash'],
        ));
        $awbData = curl_exec($curl);
        if (md5($awbData) != $awbInfo['hash']) {
          _log('download failed  '.$awbName);
          continue;
        }
        $awbFileName = pathinfo($awbName, PATHINFO_BASENAME);
        file_put_contents($awbFileName, $awbData);
      }
      file_put_contents($acbFileName, $acbData);

      // call acb2wavs
      $nullptr = NULL;
      // https://github.com/esterTion/libcgss/blob/master/src/apps/acb2wavs/acb2wavs.cpp
      exec('acb2wavs '.$acbFileName.' -b 00000000 -a 0030D9E8 -n', $nullptr);
      $acbUnpackDir = '_acb_'.$acbFileName;
      $saveTo = RESOURCE_PATH_PREFIX. preg_replace($rule['bundleNameMatch'], $rule['exportTo'], $name);
      foreach (['internal', 'external'] as $awbFolder)
        if (file_exists($acbUnpackDir .'/'. $awbFolder)) {
          foreach (glob($acbUnpackDir .'/'. $awbFolder.'/*.wav') as $waveFile) {
            $m4aFile = substr($waveFile, 0, -3).'m4a';
            $finalPath = $saveTo.'/'.pathinfo($m4aFile, PATHINFO_BASENAME);
            exec('ffmpeg -hide_banner -loglevel quiet -y -i '.$waveFile.' -vbr 5 -movflags faststart '.$m4aFile, $nullptr);
            checkAndMoveFile($m4aFile, $finalPath, $remoteTime);
            if (filemtime($finalPath) > $remoteTime)
            touch($finalPath, $remoteTime);
          }
        }
      delTree($acbUnpackDir);
      unlink($acbFileName);
      $info['hasAwb'] && unlink($awbFileName);
      if (isset($rule['print'])) exit;
      setHashCached($name, $info['hash']);
    }
  }
}
function checkMovieResource($manifest, $rules) {
  global $curl;
  $curl_movie = curl_copy_handle($curl);
  mkdir('usm_temp', 0777, true);
  foreach ($manifest as $name => $info) {
    if (($rule = findRule($name, $rules)) !== false && shouldUpdate($name, $info['hash'])) {
      _log('download '. $name.' '.$info['hash']);
      $usmFileName = pathinfo($name, PATHINFO_BASENAME);
      $usmFilePath = 'usm_temp/'.$usmFileName;
      $fh = fopen($usmFilePath, 'w');
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/pool/Movie/'.substr($info['hash'],0,2).'/'.$info['hash'],
        CURLOPT_RETURNTRANSFER=>false,
        CURLOPT_FILE => $fh
      ));
      curl_exec($curl);
      $remoteTime = curl_getinfo($curl, CURLINFO_FILETIME);
      if (md5_file($usmFilePath) != $info['hash']) {
        _log('download failed  '.$name);
        unlink($usmFilePath);
        continue;
      }

      // call UsmDemuxer
      // https://github.com/esterTion/UsmDemuxer
      $nullptr = NULL;
      exec('mono UsmDemuxer.exe '.$usmFilePath, $nullptr);
      unlink($usmFilePath);
      $streams = glob(substr($usmFilePath, 0, -4).'_*');
      $videoFile = '';
      $audioFiles = [];
      foreach ($streams as $stream) {
        $ext = pathinfo($stream, PATHINFO_EXTENSION);
        if ($ext == 'hca' || $ext == 'bin') {
          $waveFile = substr($stream, 0, -3). 'wav';
          exec('hca2wav '.$stream.' '.$waveFile.' 0030D9E8 00000000', $nullptr);
          $audioFiles[] = $waveFile;
        } else if ($ext == 'adx') {
          $audioFiles[] = $stream;
        } else if ($ext == 'm2v') {
          $videoFile = $stream;
        } else {
          _log('---unknown stream '.$stream);
        }
      }
      if (empty($videoFile)) {
        _log('---no video stream found');
        setHashCached($name, $info['hash']);
        continue;
      }
      $saveTo = RESOURCE_PATH_PREFIX. preg_replace($rule['bundleNameMatch'], $rule['exportTo'], $name);
      $code=0;
      exec('ffmpeg -hide_banner -loglevel quiet -y -i '.$videoFile.' '.implode(' ', array_map(function($i){return '-i '.$i;}, $audioFiles)).' '.(empty($audioFiles)?'':'-filter_complex amix=inputs='.count($audioFiles).':duration=longest').' -c:v copy '.(empty($audioFiles)?'':'-c:a aac -vbr 5').' -movflags faststart out.mp4', $nullptr, $code);
      if ($code !==0 || !file_exists('out.mp4')) {
        _log('encode failed, code '.$code);
        _log('ffmpeg -hide_banner -loglevel quiet -y -i '.$videoFile.' '.implode(' ', array_map(function($i){return '-i '.$i;}, $audioFiles)).' '.(empty($audioFiles)?'':'-filter_complex amix=inputs='.count($audioFiles).':duration=longest').' -c:v copy '.(empty($audioFiles)?'':'-c:a aac -vbr 5').' -movflags faststart out.mp4');
        delTree('usm_temp');
        mkdir('usm_temp');
        continue;
      }

      $saveToFull = $saveTo .'.mp4';

      // avc chk
      $shouldReencode = false;
      if (filesize('out.mp4') > 10*1024*1024) $shouldReencode = true;
      if (!$shouldReencode) {
        $mp4 = new FileStream('out.mp4');
        $mp4->littleEndian = false;
        $ftypLen = $mp4->ulong;
        $mp4->position = $ftypLen;
        $moovLen = $mp4->ulong;
        $moov = $mp4->readData($moovLen - 4);
        unset($mp4);
        $shouldReencode = strpos($moov, 'avcC') === false;
      }
      if ($shouldReencode) {
        // > 10M / not avc, reencode
        _log('reencoding to avc');
        rename('out.mp4', 'out_ori.mp4');
        exec('ffmpeg -hide_banner -loglevel quiet -y -i out_ori.mp4 -c copy -c:v h264 -crf 20 -movflags faststart out.mp4', $nullptr);
        checkAndMoveFile('out_ori.mp4', $saveTo.'_ori.mp4', $remoteTime);
      }

      checkAndMoveFile('out.mp4', $saveToFull, $remoteTime);

      unlink($videoFile);
      array_map('unlink', $audioFiles);
      if (isset($rule['print'])) exit;
      setHashCached($name, $info['hash']);
    }
  }
  delTree('usm_temp');
}
function delTree($dir) {
  $files = array_diff(scandir($dir), array('.','..'));
  foreach ($files as $file) {
    (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
  }
  return rmdir($dir);
}

function checkAndUpdateResource($TruthVersion) {
  global $resourceToExport;
  global $curl;
  chdir(__DIR__);
  curl_setopt_array($curl, array(
    CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_ENCODING=>'gzip',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_FILETIME=>true,
    CURLOPT_SSL_VERIFYPEER=>false
  ));
  $manifest = curl_exec($curl);

  $manifest = parseManifest($manifest);
  foreach ($resourceToExport as $name=>$rules) {
    $name = "manifest/${name}_assetmanifest";
    if (isset($manifest[$name]) && shouldUpdate($name, $manifest[$name]['hash'])) {
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/'.$name,
      ));
      $submanifest = curl_exec($curl);
      if (md5($submanifest) != $manifest[$name]['hash']) {
        _log('download failed  '.$name);
        continue;
      }
      $submanifest = parseManifest($submanifest);
      checkSubResource($submanifest, $rules);
      setHashCached($name, $manifest[$name]['hash']);
    }
  }

  global $prefabUpdated;
  if ($prefabUpdated) {
    chdir('prefabs');
    exec('7za a -mx=9 ../prefabs.zip *');
    chdir('..');
    $lastVer = json_decode(file_get_contents('last_version'), true);
    $lastVer['PrefabVer'] = $TruthVersion;
    file_put_contents('last_version', json_encode($lastVer));
  }

  // sound res check
  do {
    $name = "manifest/soundmanifest";
    if (isset($manifest[$name]) && shouldUpdate($name, $manifest[$name]['hash'])) {
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Sound/manifest/sound2manifest',
      ));
      $submanifest = curl_exec($curl);
      $submanifest = parseManifest($submanifest);
      checkSoundResource($submanifest, $resourceToExport['sound']);
      setHashCached($name, $manifest[$name]['hash']);
    }
  } while(0);

  // movie res check
  do {
    $name = "manifest/movie2manifest";
    curl_setopt_array($curl, array(
      CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Movie/SP/High/'.$name,
    ));
    $submanifest = curl_exec($curl);
    $submanifest = parseManifest($submanifest);
    checkMovieResource($submanifest, $resourceToExport['movie']);
  } while(0);
}
if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  chdir(__DIR__);
  $curl = curl_init();
  function _log($s) {echo "$s\n";}
  checkAndUpdateResource('10009600');
  /*$assets = extractBundle(new FileStream('bundle/spine_000000_chara_base.cysp.unity3d'));
  $asset = new AssetFile($assets[0]);
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      print_r($item);
    }
  }*/
}
//print_r($asset);

