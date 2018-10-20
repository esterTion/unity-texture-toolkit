<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

require_once 'UnityAsset.php';

$resourceToExport = [
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
    [ 'bundleNameMatch'=>'/^a\/spine_0\d_common_battle\.cysp\.unity3d$/', 'customAssetProcessor'=> 'exportSpine' ],
    [ 'bundleNameMatch'=>'/^a\/spine_10\d\d01_battle\.cysp\.unity3d$/',   'customAssetProcessor'=> 'exportSpine' ],
    [ 'bundleNameMatch'=>'/^a\/spine_sdnormal_10\d{4}\.unity3d$/',        'customAssetProcessor'=> 'exportAtlas' ],
  ],
  'sound'=>[
    [ 'bundleNameMatch'=>'/^v\/vo_cmn_(\d+).acb$/', 'exportTo'=> 'sound/unit_common/$1' ],
    [ 'bundleNameMatch'=>'/^v\/t\/vo_adv_(\d+).acb$/', 'exportTo'=> 'sound/story_vo/$1' ],
  ]
];

function exportSpine($asset) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);

      // base chara skeleton
      if ($item->name == '000000_CHARA_BASE.cysp') {
        if (!file_exists(RESOURCE_PATH_PREFIX.'spine/common/')) mkdir(RESOURCE_PATH_PREFIX.'spine/common/', 0777, true);
        file_put_contents(RESOURCE_PATH_PREFIX.'spine/common/000000_CHARA_BASE.cysp', $item->data);
      }
      // class type animation
      else if (preg_match('/0\d_COMMON_BATTLE\.cysp/', $item->name)) {
        if (!file_exists(RESOURCE_PATH_PREFIX.'spine/common/')) mkdir(RESOURCE_PATH_PREFIX.'spine/common/', 0777, true);
        file_put_contents(RESOURCE_PATH_PREFIX.'spine/common/'.$item->name, $item->data);
      }
      // character skill animation
      else if (preg_match('/10\d{4}_BATTLE\.cysp/', $item->name)) {
        if (!file_exists(RESOURCE_PATH_PREFIX.'spine/unit/')) mkdir(RESOURCE_PATH_PREFIX.'spine/unit/', 0777, true);
        file_put_contents(RESOURCE_PATH_PREFIX.'spine/unit/'.$item->name, $item->data);
      }
    }
  }
}
function exportAtlas($asset) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      if (!file_exists(RESOURCE_PATH_PREFIX.'spine/unit/')) mkdir(RESOURCE_PATH_PREFIX.'spine/unit/', 0777, true);
      file_put_contents(RESOURCE_PATH_PREFIX.'spine/unit/'.$item->name, $item->data);
    } else if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      $item->exportTo(RESOURCE_PATH_PREFIX.'spine/unit/'.$item->name, 'png');
    }
  }
}

function exportStory($asset) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      if (!file_exists(RESOURCE_PATH_PREFIX.'story/data/')) mkdir(RESOURCE_PATH_PREFIX.'story/data/', 0777, true);
      require_once 'RediveStoryDeserializer.php';
      $parser = new RediveStoryDeserializer($item->data);
      $name = substr($item->name, 10);
      file_put_contents(RESOURCE_PATH_PREFIX.'story/data/'.$name.'.json', json_encode($parser->commandList));
      file_put_contents(RESOURCE_PATH_PREFIX.'story/data/'.$name.'.htm', $parser->data);

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
function exportStoryStill($asset) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      if (!file_exists(RESOURCE_PATH_PREFIX.'spine/still/unit/')) mkdir(RESOURCE_PATH_PREFIX.'spine/still/unit/', 0777, true);
      file_put_contents(RESOURCE_PATH_PREFIX.'spine/still/unit/'.$item->name, $item->data);
    } else if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      $item->exportTo(RESOURCE_PATH_PREFIX.'spine/still/unit/'.$item->name, 'png');
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
function shouldUpdate($name, $hash) {
  $cacheHash = file_exists('cacheHash.json') ? json_decode(file_get_contents('cacheHash.json'), true) : [];
  return !(isset($cacheHash[$name]) && ($cacheHash[$name] === $hash));
}
function setHashCached($name, $hash) {
  $cacheHash = file_exists('cacheHash.json') ? json_decode(file_get_contents('cacheHash.json'), true) : [];
  $cacheHash[$name] = $hash;
  file_put_contents('cacheHash.json', json_encode($cacheHash, JSON_UNESCAPED_SLASHES));
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
          call_user_func($rule['customAssetProcessor'], $asset);
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
      if (!file_exists($saveTo)) mkdir($saveTo, 0777, true);
      foreach (['internal', 'external'] as $awbFolder)
        if (file_exists($acbUnpackDir .'/'. $awbFolder)) {
          foreach (glob($acbUnpackDir .'/'. $awbFolder.'/*.wav') as $waveFile) {
            $m4aFile = substr($waveFile, 0, -3).'m4a';
            exec('ffmpeg -hide_banner -loglevel quiet -y -i '.$waveFile.' -vbr 5 -movflags faststart '.$m4aFile, $nullptr);
            rename($m4aFile, $saveTo.'/'.pathinfo($m4aFile, PATHINFO_BASENAME));
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

  // sound res check
  do {
    $name = "manifest/soundmanifest";
    if (isset($manifest[$name]) && shouldUpdate($name, $manifest[$name]['hash'])) {
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'http://priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/Sound/'.$name,
      ));
      $submanifest = curl_exec($curl);
      if (md5($submanifest) != $manifest[$name]['hash']) {
        _log('download failed  '.$name);
        continue;
      }
      $submanifest = parseManifest($submanifest);
      checkSoundResource($submanifest, $resourceToExport['sound']);
      setHashCached($name, $manifest[$name]['hash']);
    }
  } while(0);
}
if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  chdir(__DIR__);
  $curl = curl_init();
  function _log($s) {echo "$s\n";}
  checkAndUpdateResource(10004300);
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

