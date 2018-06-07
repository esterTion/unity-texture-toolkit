<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);
/*
Some sort of skel binary to json converter
a few fields are not processed, and will throw error when encountered

Used for converting resource from priconne-redive
*/
require_once 'UnityBundle.php';

function readVarInt32($pb, $optimizePositive = true) {
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
  if (!$optimizePositive) $result = (($result >> 1) ^ -($result & 1));
  if ($result > 0) $result = unpack('l', pack('L', $result))[1];
	return $result;
}

function readString($s) {
  $len = readVarInt32($s);
  if ($len == 0) return NULL;
  if ($len == 1) return '';
  return $s->readData($len - 1);
}

function readColor($s) {
  return strtoupper(bin2hex($s->readData(4)));
  /*return [
    ord($s->byte),
    ord($s->byte),
    ord($s->byte),
    ord($s->byte)
  ];*/
}

function readCurve($fs) {
  $type = ord($fs->byte);
  if ($type == CURVE_BEZIER) {
    return [
      'type' => $type,
      'c1' => $fs->float,
      'c2' => $fs->float,
      'c3' => $fs->float,
      'c4' => $fs->float
    ];
  } else {
    return [
      'type' => $type
    ];
  }
}

function readSkin($fs, $name, &$skel) {
  $skin = [];
  $skin['name'] = $name;
  $slotCount = readVarInt32($fs);
  for ($i=0; $i<$slotCount; $i++) {
    $slot = [];
    $slot['index'] = readVarInt32($fs);
    $attachmentCount = readVarInt32($fs);
    $slot['attachment'] = [];
    for ($j=0; $j<$attachmentCount; $j++) {
      $attachment = [];
      $attachment['placeholderName'] = readString($fs);
      $attachment['name'] = readString($fs);
      if ($attachment['name'] === NULL) $attachment['name'] = $attachment['placeholderName'];
      $attachment['type'] = ord($fs->byte);
      switch($attachment['type']) {
        case ATTACHMENT_REGION: {
          $attachment['path'] = readString($fs);
          $attachment['rotation'] = $fs->float;
          $attachment['x'] = $fs->float;
          $attachment['y'] = $fs->float;
          $attachment['scaleX'] = $fs->float;
          $attachment['scaleY'] = $fs->float;
          $attachment['width'] = $fs->float;
          $attachment['height'] = $fs->float;
          $attachment['color'] = readColor($fs);
          break;
        }
        case ATTACHMENT_BOUNDING_BOX: {
          $vertexCount = readVarInt32($fs);
          $attachment['vertexCount'] = $vertexCount;
          list($attachment['vertices'], $attachment['weighted']) = readVertex($fs, $vertexCount);
          if ($skel['nonessential']) $attachment['color'] = readColor($fs);
          break;
        }
        case ATTACHMENT_MESH: {
          $attachment['path'] = readString($fs);
          $attachment['color'] = readColor($fs);
          $uvCount = readVarInt32($fs);
          $attachment['uvs'] = [];
          for ($k=0; $k<$uvCount; $k++) {
            $attachment['uvs'][] = $fs->float;
            $attachment['uvs'][] = $fs->float;
          }
          $triangleCount = readVarInt32($fs);
          $attachment['triangles'] = [];
          for ($k=0; $k<$triangleCount; $k++) {
            $attachment['triangles'][] = $fs->short;
          }
          list($attachment['vertices'], $attachment['weighted']) = readVertex($fs, $uvCount);
          $attachment['hull'] = readVarInt32($fs);
          if ($skel['nonessential']) {
            $edgeCount = readVarInt32($fs);
            $attachment['edges'] = [];
            for ($k=0; $k<$edgeCount; $k++) {
              $attachment['edges'][] = $fs->short;
            }
            $attachment['width'] = $fs->float;
            $attachment['height'] = $fs->float;
          }
          break;
        }
        case ATTACHMENT_LINKED_MESH: {
          $attachment['path'] = readString($fs);
          $attachment['color'] = readColor($fs);
          $attachment['skin'] = readString($fs);
          $attachment['parent'] = readString($fs);
          $attachment['deform'] = $fs->bool;
          if ($skel['nonessential']) {
            $attachment['width'] = $fs->float;
            $attachment['height'] = $fs->float;
          }
          break;
        }
        case ATTACHMENT_PATH: {
          $attachment['closed'] = $fs->bool;
          $attachment['constantSpeed'] = $fs->bool;
          $vertexCount = readVarInt32($fs);
          list($attachment['vertices'], $attachment['weighted']) = readVertex($fs, $vertexCount);
          $attachment['lengths'] = [];
          for ($k=0; $k<$vertexCount/3; $k++) {
            $attachment['lengths'][] = $fs->float;
          }
          if ($skel['nonessential']) $attachment['color'] = readColor($fs);
          break;
        }
        case ATTACHMENT_POINT: {
          $attachment['rotation'] = $fs->float;
          $attachment['x'] = $fs->float;
          $attachment['y'] = $fs->float;
          if ($skel['nonessential']) $attachment['color'] = readColor($fs);
          break;
        }
        case ATTACHMENT_CLIPPING: {
          $attachment['endSlot'] = $fs->long;
          $vertexCount = readVarInt32($fs);
          list($attachment['vertices'], $attachment['weighted']) = readVertex($fs, $vertexCount);
          if ($skel['nonessential']) $attachment['color'] = readColor($fs);
          break;
        }
      }
      $slot['attachment'][] = $attachment;
    }
    $skin[] = $slot;
  }
  return $skin;
}

function readVertex($fs, $count) {
  $weighted = $fs->bool;
  $data = [];
  for ($i=0; $i<$count; $i++) {
    if (!$weighted) {
      $data[] = $fs->float;
      $data[] = $fs->float;
    } else {
      $boneCount = readVarInt32($fs);
      for ($j=0, $bones=[]; $j<$boneCount; $j++) {
        $index = readVarInt32($fs);
        $x = $fs->float;
        $y = $fs->float;
        $weight = $fs->float;
        $data[] = $weight;
        $data[] = $index;
        $data[] = $x;
        $data[] = $y;
      }
    }
  }
  return [$data,$weighted];
}

function readBone($fs, $nonessential, $isFirst) {
  $bone = [];
  $bone['name'] = readString($fs);
  if ($isFirst) $bone['parent'] = readVarInt32($fs);
  $bone['rotation'] = $fs->float;
  $bone['x'] = $fs->float;
  $bone['y'] = $fs->float;
  $bone['scaleX'] = $fs->float;
  $bone['scaleY'] = $fs->float;
  $bone['shearX'] = $fs->float;
  $bone['shearY'] = $fs->float;
  $bone['length'] = $fs->float;
  $bone['transformMode'] = ord($fs->byte);
  if ($nonessential) $bone['color'] = readColor($fs);
  return $bone;
}

function readSlot($fs) {
  $slot = [];
  $slot['name'] = readString($fs);
  $slot['bone'] = readVarInt32($fs);
  $slot['color'] = bin2hex($fs->readData(4));
  if ($slot['color'] == 'ffffffff') unset($slot['color']);
  $slot['dark'] = bin2hex($fs->readData(4));
  if ($slot['dark'] == 'ffffffff') unset($slot['dark']);
  $slot['attachment'] = readString($fs);
  $slot['blend'] = ord($fs->byte);
  return $slot;
}

function GetSkinAttachment(&$skin, $slotIndex, $attachmentName) {
  $slot = &$skin[$slotIndex];
  for ($i=0, $n=count($slot['attachment']); $i<$n; $i++) {
    if ($slot['attachment'][$i]['placeholderName'] == $attachmentName) return $slot['attachment'][$i];
  }
  return NULL;
}

function readAnimation($fs, &$skel) {
  $animation = [];
  $animation['name'] = readString($fs);
  $slotCount = readVarInt32($fs);
  $animation['slot'] = [];
  for ($j=0; $j<$slotCount; $j++) {
    $slot = [];
    $slot['index'] = readVarInt32($fs);
    $timelineCount = readVarInt32($fs);
    $slot['timelineCount'] = $timelineCount;
    $slot['timeline'] = [];
    for ($k=0; $k<$timelineCount; $k++) {
      $timeline = [];
      $timeline['type'] = ord($fs->byte);
      $frameCount = readVarInt32($fs);
      $timeline['frame'] = [];
      for ($l=0; $l<$frameCount; $l++) {
        switch ($timeline['type']) {
          case SLOT_ATTACHMENT: {
            $frame = [
              'time' => $fs->float,
              'name' => readString($fs)
            ];
            $timeline['frame'][] = $frame;
            break;
          }
          case SLOT_COLOR: {
            $frame = [
              'time' => $fs->float,
              'color' => readColor($fs)
            ];
            if ($l < $frameCount - 1) $frame['curve'] = readCurve($fs);
            $timeline['frame'][] = $frame;
            break;
          }
          case SLOT_TWO_COLOR: {
            $frame = [
              'time' => $fs->float,
              'light' => readColor($fs),
              'dark' => readColor($fs)
            ];
            if ($l < $frameCount - 1) $frame['curve'] = readCurve($fs);
            $timeline['frame'][] = $frame;
            break;
          }
        }
      }
      $slot['timeline'][] = $timeline;
    }
    $animation['slot'][] = $slot;
  }

  $boneCount = readVarInt32($fs);
  $animation['bone'] = [];
  for ($j=0; $j<$boneCount; $j++) {
    $bone = [];
    $bone['index'] = readVarInt32($fs);
    $timelineCount = readVarInt32($fs);
    $bone['timelineCount'] = $timelineCount;
    $bone['timeline'] = [];
    for ($k=0; $k<$timelineCount; $k++) {
      $timeline = [];
      $timeline['type'] = ord($fs->byte);
      $frameCount = readVarInt32($fs);
      $timeline['frame'] = [];
      for ($l=0; $l<$frameCount; $l++) {
        switch ($timeline['type']) {
          case BONE_ROTATE: {
            $frame = [
              'time' => $fs->float,
              'rotation' => $fs->float
            ];
            if ($l < $frameCount - 1) $frame['curve'] = readCurve($fs);
            $timeline['frame'][] = $frame;
            break;
          }
          case BONE_TRANSLATE: case BONE_SCALE: case BONE_SHEAR: {
            $frame = [
              'time' => $fs->float,
              'x' => $fs->float,
              'y' => $fs->float
            ];
            if ($l < $frameCount - 1) $frame['curve'] = readCurve($fs);
            $timeline['frame'][] = $frame;
            break;
          }
        }
      }
      $bone['timeline'][] = $timeline;
    }
    $animation['bone'][] = $bone;
  }

  $ikCount = readVarInt32($fs);
  if ($ikCount) {
    throw new Exception('has ik timeline data');
  }

  $transformCount = readVarInt32($fs);
  if ($transformCount) {
    throw new Exception('has transform timeline data');
  }

  $pathCount = readVarInt32($fs);
  if ($pathCount) {
    throw new Exception('has path timeline data');
  }

  $deformCount = readVarInt32($fs);
  if ($deformCount) {
    $animation['deform'] = [];
    for ($i=0; $i<$deformCount; $i++) {
      $deform=[];
      $deform['skin'] = readVarInt32($fs);
      $skin = &$skel['skin'][$deform['skin']];
      $deform['slots'] = [];
      for ($ii=0, $nn=readVarInt32($fs); $ii<$nn; $ii++) {
        $slot = [];
        $slot['index'] = readVarInt32($fs);
        $slot['mesh'] = [];
        for ($iii=0, $nnn=readVarInt32($fs); $iii<$nnn; $iii++) {
          $mesh = [];
          $mesh['attachment'] = readString($fs);
          $attachment = GetSkinAttachment($skin, $slot['index'], $mesh['attachment']);
          $weighted = $attachment['weighted'];
          $vertices = $attachment['vertices'];
          $deformLength = $weighted ? count($vertices) /3*2 : count($vertices);

          $frameCount = readVarInt32($fs);
          $mesh['frame'] = [];
          for ($iiii=0; $iiii<$frameCount; $iiii++) {
            $frame = [
              'time' => $fs->float,
              'end' => readVarInt32($fs)
            ];
            if ($frame['end'] == 0) {
              $frame['vertices'] = $weighted ? array_fill(0, $deformLength, 0) : $vertices;
            } else {
              $deform = array_fill(0, $deformLength, 0);
              $start = readVarInt32($fs);
              $end += $start;

              // scale???
              for ($v=$start; $v<$end; $v++) {
                $deform[$v] = $fs->float;
              }
              if (!$weighted) {
                for ($v=0; $v<$deformLength; $v++) {
                  $deform[$v] += $vertices[$v];
                }
              }
              $frame['vertices'] = $deform;
            }
            if ($iiii < $frameCount - 1) $frame['curve'] = readCurve($fs);
            $mesh['frame'][] = $frame;
          }
          $slot['mesh'][] = $mesh;
        }
        $deform['slots'][] = $slot;
      }
      $animation['deform'][] = $deform;
    }
  }

  $drawOrderCount = readVarInt32($fs);
  if ($drawOrderCount) {
    $animation['drawOrder'] = [];
    for ($j=0; $j<$drawOrderCount; $j++) {
      $drawOrder = [
        'time' => $fs->float,
        'change' => []
      ];
      $changeCount = readVarInt32($fs);
      for ($k=0; $k<$changeCount; $k++) {
        $drawOrder['change'][] = [
          'index' => readVarInt32($fs),
          'amount' => readVarInt32($fs)
        ];
      }
      $animation['drawOrder'][] = $drawOrder;
    }
  }

  $eventCount = readVarInt32($fs);
  if ($eventCount) {
    $animation['event'] = [];
    for ($j=0; $j<$eventCount; $j++) {
      $event = [
        'time' => $fs->float,
        'index' => readVarInt32($fs),
        'int' => readVarInt32($fs, false),
        'float' => $fs->float
      ];
      $hasString = $fs->bool;
      if ($hasString) $event['string'] = readString($fs);
      $animation['event'][] = $event;
    }
  }
  return $animation;
}

{
  // define constant
  define('ATTACHMENT_REGION', 0);
  define('ATTACHMENT_BOUNDING_BOX', 1);
  define('ATTACHMENT_MESH', 2);
  define('ATTACHMENT_LINKED_MESH', 3);
  define('ATTACHMENT_PATH', 4);
  define('ATTACHMENT_POINT', 5);
  define('ATTACHMENT_CLIPPING', 6);

  define('BLEND_MODE_NORMAL', 0);
  define('BLEND_MODE_ADDITIVE', 1);
  define('BLEND_MODE_MULTIPLY', 2);
  define('BLEND_MODE_SCREEN', 3);

  define('CURVE_LINEAR', 0);
  define('CURVE_STEPPED', 1);
  define('CURVE_BEZIER', 2);

  define('BONE_ROTATE', 0);
  define('BONE_TRANSLATE', 1);
  define('BONE_SCALE', 2);
  define('BONE_SHEAR', 3);

  define('TRANSFORM_NORMAL', 0);
  define('TRANSFORM_ONLY_TRANSLATION', 1);
  define('TRANSFORM_NO_ROTATION_OR_REFLECTION', 2);
  define('TRANSFORM_NO_SCALE', 3);
  define('TRANSFORM_NO_SCALE_OR_REFLECTION', 4);

  define('SLOT_ATTACHMENT', 0);
  define('SLOT_COLOR', 1);
  define('SLOT_TWO_COLOR', 2);

  define('PATH_POSITION', 0);
  define('PATH_SPACING', 1);
  define('PATH_MIX', 2);

  define('PATH_POSITION_FIXED', 0);
  define('PATH_POSITION_PERCENT', 1);

  define('PATH_SPACING_LENGTH', 0);
  define('PATH_SPACING_FIXED', 1);
  define('PATH_SPACING_PERCENT', 2);

  define('PATH_ROTATE_TANGENT', 0);
  define('PATH_ROTATE_CHAIN', 1);
  define('PATH_ROTATE_CHAIN_SCALE', 2);

}

function exportSkel($file) {
  $fs = new FileStream($file);
  $out = pathinfo($file, PATHINFO_DIRNAME).'/'.pathinfo($file, PATHINFO_FILENAME).'.json';
  
  // read binary. some not implemented
  $skel = ['skeleton'=>[]];
  $skel['skeleton']['hash'] = readString($fs);
  $skel['skeleton']['version'] = readString($fs);
  $skel['skeleton']['width'] = $fs->float;
  $skel['skeleton']['height'] = $fs->float;
  $skel['nonessential'] = $fs->bool;
  $nonessential = $skel['nonessential'];
  if ($nonessential) {
    $skel['skeleton']['fps'] = $fs->float;
    $skel['skeleton']['images'] = readString($fs);
  }

  $boneCount = readVarInt32($fs);
  if ($boneCount) {
    $skel['bone'] = [];
    for ($i=0; $i<$boneCount; $i++) {
      $skel['bone'][] = readBone($fs, $nonessential, $i!=0);
    }
  }

  $slotCount = readVarInt32($fs);
  if ($slotCount) {
    $skel['slot'] = [];
    for ($i=0; $i<$slotCount; $i++) {
      $skel['slot'][] = readSlot($fs);
    }
  }
  
  $ikCount = readVarInt32($fs);
  if ($ikCount) {
    $skel['ik'] = [];
    throw new Exception('has ik data');
  }
  
  $transformCount = readVarInt32($fs);
  if ($transformCount) {
    $skel['transform'] = [];
    for ($i=0; $i<$transformCount; $i++) {
      $next = readString($fs);
      $next = readVarInt32($fs);
      $len = readVarInt32($fs);
      for ($k=0; $k<$len; $k++) $next = readVarInt32($fs);
      $next = readVarInt32($fs);
      $next = $fs->bool;
      $next = $fs->bool;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
      $next = $fs->float;
    }
    throw new Exception('has transform data');
  }
  
  $pathCount = readVarInt32($fs);
  if ($pathCount) {
    $skel['path'] = [];
    throw new Exception('has path data');
  }

  $skel['skin'] = [
    readSkin($fs, 'default', $skel)
  ];
  $skinCount = readVarInt32($fs);
  for ($i=0; $i<$skinCount; $i++) {
    $name = readString($fs);
    $skel['skin'][] = readSkin($fs, $name, $skel);
  }

  $eventCount = readVarInt32($fs);
  if ($eventCount) {
    $skel['event'] = [];
    for ($i=0; $i<$eventCount; $i++) {
      $skel['event'][] = [
        'name' => readString($fs),
        'int' => readVarInt32($fs, false),
        'float' => $fs->float,
        'string' => readString($fs)
      ];
    }
  }

  $animationCount = readVarInt32($fs);
  if ($animationCount) {
    $skel['animation'] = [];
    for ($i=0; $i<$animationCount; $i++) {
      $skel['animation'][] = readAnimation($fs, $skel);
    }
  }

  $json = processToJson($skel);

  file_put_contents($out, json_encode($json));
}

function processToJson($skel) {
  // process to json
  $json = [
    'skeleton' => $skel['skeleton']
  ];
  if (isset($skel['bone'])) {
    $json['bones'] = array_map(function ($i) use(&$json) {
      // unset default 0 prop
      foreach (['x','y','shearX','shearY','length'] as $prop) {
        if ($i[$prop] == 0) unset($i[$prop]);
      }
      // unset default 1 prop
      foreach (['scaleX','scaleY'] as $prop) {
        if ($i[$prop] == 1) unset($i[$prop]);
      }
      if (isset($i['transformMode'])) {
        $i['transform'] = [
          TRANSFORM_NORMAL => 'normal',
          TRANSFORM_ONLY_TRANSLATION => 'onlyTranslation',
          TRANSFORM_NO_ROTATION_OR_REFLECTION => 'noRotationOrReflection',
          TRANSFORM_NO_SCALE => 'noScale',
          TRANSFORM_NO_SCALE_OR_REFLECTION => 'noScaleOrReflection'
        ][$i['transformMode']];
        unset($i['transformMode']);
        if ($i['transform'] == 'normal') unset($i['transform']);
      }
      return $i;
    }, $skel['bone']);
    foreach ($json['bones'] as &$i) {
      if (isset($i['parent'])) {
        $i['parent'] = $json['bones'][ $i['parent'] ]['name'];
      }
    }
  }
  if (isset($skel['slot'])) {
    $json['slots'] = array_map(function ($i) use(&$json) {
      $i['bone'] = $json['bones'][$i['bone']]['name'];
      if ($i['attachment'] === NULL) unset($i['attachment']);
      $i['blend'] = [
        BLEND_MODE_NORMAL => 'normal',
        BLEND_MODE_ADDITIVE => 'additive',
        BLEND_MODE_MULTIPLY => 'multiply',
        BLEND_MODE_SCREEN => 'screen'
      ][$i['blend']];
      if ($i['blend'] == 'normal') unset($i['blend']);
      return $i;
    }, $skel['slot']);
  }

  // ik transform path

  if (isset($skel['skin'])) {
    $json['skins'] = [];
    foreach ($skel['skin'] as $skin) {
      $procSkin = [];
      $skinname = $skin['name'];
      unset($skin['name']);
      foreach ($skin as $slot) {
        $procSlot = [];
        foreach ($slot['attachment'] as $attachment) {
          // unset default 0 prop
          foreach (['x','y','rotation'] as $prop) {
            if (isset($attachment[$prop]) && $attachment[$prop] == 0) unset($attachment[$prop]);
          }
          // unset default 1 prop
          foreach (['scaleX','scaleY'] as $prop) {
            if (isset($attachment[$prop]) && $attachment[$prop] == 1) unset($attachment[$prop]);
          }
          $attachment['type'] = [
            ATTACHMENT_REGION => 'region',
            ATTACHMENT_BOUNDING_BOX => 'boundingbox',
            ATTACHMENT_MESH => 'mesh',
            ATTACHMENT_LINKED_MESH => 'linkedmesh',
            ATTACHMENT_PATH => 'path',
            ATTACHMENT_POINT => 'point',
            ATTACHMENT_CLIPPING => 'clipping'
          ][$attachment['type']];
          if (in_array($attachment['type'], ['region','mesh','linkedmesh']) && $attachment['path'] === null) unset($attachment['path']);
          if (isset($attachment['color']) && $attachment['color'] === 'FFFFFFFF') unset($attachment['color']);
          $procSlot[ $attachment['placeholderName'] ] = $attachment;
        }
        $procSkin[ $json['slots'][ $slot['index'] ]['name'] ] = $procSlot;
      }
      $json['skins'][$skinname] = $procSkin;
    }
  }
  
  // event

  if (isset($skel['animation'])) {
    $json['animations'] = [];
    foreach ($skel['animation'] as $animname=>$anim) {
      $procAnim = [];

      if (isset($anim['bone'])) {
        $procAnim['bones'] = [];
        foreach ($anim['bone'] as $bone) {
          $procBone = [];
          foreach ($bone['timeline'] as $timeline) {
            $procFrame = [];
            $frameType = [
              BONE_ROTATE => 'rotate',
              BONE_TRANSLATE => 'translate',
              BONE_SCALE => 'scale',
              BONE_SHEAR => 'shear'
            ][$timeline['type']];
            foreach ($timeline['frame'] as $frame) {
              if (isset($frame['rotation'])) {
                $frame['angle'] = $frame['rotation'];
                unset($frame['rotation']);
              }
              if (isset($frame['curve'])) {
                if ($frame['curve']['type'] == CURVE_BEZIER) {
                  $frame['curve'] = [
                    $frame['curve']['c1'],
                    $frame['curve']['c2'],
                    $frame['curve']['c3'],
                    $frame['curve']['c4']
                  ];
                } else if ($frame['curve']['type'] == CURVE_STEPPED) {
                  $frame['curve'] = 'stepped';
                } else {
                  unset($frame['curve']);
                }
              }
              $procFrame[] = $frame;
            }
            $procBone[$frameType] = $procFrame;
          }
          $procAnim['bones'][ $json['bones'][ $bone['index'] ]['name'] ] = $procBone;
        }
      }

      if (isset($anim['slot'])) {
        $procAnim['slots'] = [];
        foreach ($anim['slot'] as $slot) {
          $procSlot = [];
          foreach ($slot['timeline'] as $timeline) {
            $procFrame = [];
            $frameType = [
              SLOT_ATTACHMENT => 'attachment',
              SLOT_COLOR => 'color',
              SLOT_TWO_COLOR => 'two_color'
            ][$timeline['type']];
            foreach ($timeline['frame'] as $frame) {
              if (isset($frame['rotation'])) {
                $frame['angle'] = $frame['rotation'];
                unset($frame['rotation']);
              }
              if (isset($frame['curve'])) {
                if ($frame['curve']['type'] == CURVE_BEZIER) {
                  $frame['curve'] = [
                    $frame['curve']['c1'],
                    $frame['curve']['c2'],
                    $frame['curve']['c3'],
                    $frame['curve']['c4']
                  ];
                } else if ($frame['curve']['type'] == CURVE_STEPPED) {
                  $frame['curve'] = 'stepped';
                } else {
                  unset($frame['curve']);
                }
              }
              $procFrame[] = $frame;
            }
            $procSlot[$frameType] = $procFrame;
          }
          $procAnim['slots'][ $json['slots'][ $slot['index'] ]['name'] ] = $procSlot;
        }
      }

      // ik transform
      
      if (isset($anim['deform'])) {
        $procAnim['deform'] = [];
        foreach ($anim['deform'] as $skin) {
          $procSkin = [];
          foreach ($skin['slots'] as $slot) {
            $procSlot = [];
            foreach ($slot['mesh'] as $mesh) {
              $procMesh = [];
              foreach ($mesh['frame'] as $frame) {
                unset($frame['end']);
                if (isset($frame['curve'])) {
                  if ($frame['curve']['type'] == CURVE_BEZIER) {
                    $frame['curve'] = [
                      $frame['curve']['c1'],
                      $frame['curve']['c2'],
                      $frame['curve']['c3'],
                      $frame['curve']['c4']
                    ];
                  } else if ($frame['curve']['type'] == CURVE_STEPPED) {
                    $frame['curve'] = 'stepped';
                  } else {
                    unset($frame['curve']);
                  }
                }
                $procMesh[] = $frame;
              }
              $procSlot[$mesh['attachment']] = $procMesh;
            }
            $procSkin[ $json['slots'][ $slot['index'] ]['name'] ] = $procSlot;
          }
          $procAnim['deform'][ $skel['skin'][ $skin['skin'] ]['name'] ] = $procSkin;
        }
      }
      // event

      if (isset($anim['drawOrder'])) {
        $procAnim['draworder'] = [];
        foreach ($anim['drawOrder'] as $drawOrder) {
          $procdraw = [];
          $procdraw['time'] = $drawOrder['time'];
          $procdraw['offsets'] = array_map(function ($i) use (&$json) {
            return [
              'slot' => $json['slots'][ $i['index'] ]['name'],
              'offset' => $i['amount']
            ];
          }, $drawOrder['change']);
          $procAnim['draworder'][] = $procdraw;
        }
      }
      $json['animations'][ $anim['name'] ] = $procAnim;
    }
  }
  return $json;
}


function readCyspSkeleton($skelFile) {
  $fs = new FileStream($skelFile);

  // check file format
  $fs->littleEndian = true;
  if (!(
    $fs->readData(4) == 'cysp' &&
    $fs->long === 0 &&
    $fs->long === 1
  )) {
    throw new Exception('invalid cysp format');
  }
  $bodyCount = $fs->long;
  $fs->littleEndian = false;
  $fs->position = ($bodyCount + 1) * 32;
  
  // read binary. some not implemented
  $skel = ['skeleton'=>[]];
  $skel['skeleton']['hash'] = readString($fs);
  $skel['skeleton']['version'] = readString($fs);
  $skel['skeleton']['width'] = $fs->float;
  $skel['skeleton']['height'] = $fs->float;
  $skel['nonessential'] = $fs->bool;
  $nonessential = $skel['nonessential'];
  if ($nonessential) {
    $skel['skeleton']['fps'] = $fs->float;
    $skel['skeleton']['images'] = readString($fs);
  }

  $boneCount = readVarInt32($fs);
  if ($boneCount) {
    $skel['bone'] = [];
    for ($i=0; $i<$boneCount; $i++) {
      $skel['bone'][] = readBone($fs, $nonessential, $i!=0);
    }
  }

  $slotCount = readVarInt32($fs);
  if ($slotCount) {
    $skel['slot'] = [];
    for ($i=0; $i<$slotCount; $i++) {
      $skel['slot'][] = readSlot($fs);
    }
  }
  
  $ikCount = readVarInt32($fs);
  if ($ikCount) {
    $skel['ik'] = [];
    throw new Exception('has ik data');
  }
  
  $transformCount = readVarInt32($fs);
  if ($transformCount) {
    $skel['transform'] = [];
    throw new Exception('has transform data');
  }
  
  $pathCount = readVarInt32($fs);
  if ($pathCount) {
    $skel['path'] = [];
    throw new Exception('has path data');
  }

  $skel['skin'] = [
    readSkin($fs, 'default', $skel)
  ];
  $skinCount = readVarInt32($fs);
  for ($i=0; $i<$skinCount; $i++) {
    $name = readString($fs);
    $skel['skin'][] = readSkin($fs, $name, $skel);
  }

  $eventCount = readVarInt32($fs);
  if ($eventCount) {
    $skel['event'] = [];
    for ($i=0; $i<$eventCount; $i++) {
      $skel['event'][] = [
        'name' => readString($fs),
        'int' => readVarInt32($fs, false),
        'float' => $fs->float,
        'string' => readString($fs)
      ];
    }
  }

  return $skel;
  $json = processToJson($skel);

  file_put_contents($out, json_encode($json['animations'], JSON_UNESCAPED_SLASHES));
  //file_put_contents($out, json_encode($json, JSON_UNESCAPED_SLASHES));
}
function readCyspAnimation($animFile, $skel) {
  $animFs = new FileStream($animFile);
  $animation = [];
  $animFs->littleEndian = true;
  if (!(
    $animFs->readData(4) == 'cysp' &&
    $animFs->long === 0 &&
    $animFs->long === 1
  )) {
    throw new Exception('invalid cysp format');
  }
  $animationCount = $animFs->long;
  $animFs->littleEndian = false;
  $animFs->position = ($animationCount + 1) * 32;
  if ($animationCount) {
    for ($i=0; $i<$animationCount; $i++) {
      $animation[] = readAnimation($animFs, $skel);
    }
  }
  return $animation;
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
//exportSkel('106311.skel');
//exportSkel('106331.skel');
//exportSkel('alien-pro.skel');
//exportCyspSkel('000000_CHARA_BASE.cysp', '106301_BATTLE.cysp', '106331_BATTLE.json');
$baseSkel = readCyspSkeleton('common/000000_CHARA_BASE.cysp');
file_put_contents('common/000000_CHARA_BASE.json', json_encode(processToJson($baseSkel), JSON_UNESCAPED_SLASHES));
$baseSkel['animation'] = readCyspAnimation('106301_BATTLE.cysp', $baseSkel);
$json = processToJson($baseSkel)['animations'];
file_put_contents('106301_BATTLE.json', json_encode($json, JSON_UNESCAPED_SLASHES));

chdir('common');
foreach (glob('*.cysp.txt') as $file) {
  echo "$file\n";
  /*$skel = ['skeleton'=>[],'animation'=>[]];
  $animFs = new FileStream($file);
  $animFs->littleEndian = true;
  $animFs->position = 12;
  $animationCount = $animFs->long;
  $animFs->littleEndian = false;
  $animFs->position = ($animationCount + 1) * 32;
  for ($i=0; $i<$animationCount; $i++) {
    $skel['animation'][] = readAnimation($animFs);
  }*/
  $baseSkel['animation'] = readCyspAnimation($file, $baseSkel);
  $json = processToJson($baseSkel)['animations'];
  file_put_contents(
    pathinfo(pathinfo($file, PATHINFO_FILENAME), PATHINFO_FILENAME) . '.json',
    json_encode($json, JSON_UNESCAPED_SLASHES)
  );
  //exportCyspSkel('../000000_CHARA_BASE.cysp', $file, pathinfo(pathinfo($file, PATHINFO_FILENAME), PATHINFO_FILENAME) . '.json');
}
/*$fs = new FileStream('106301_BATTLE.cysp');
$fs->position = 32*5;
print_r(readAnimation($fs));*/
}
