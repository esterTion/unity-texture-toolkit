<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

require_once 'UnityBundle.php';

class TapeArchive {
  private $s;
  function __construct($stream) {
    $this->s = $stream;
  }
  function next() {
    if (!$this->s) return null;
    $info = [
      'name'     =>        $this->s->readData(100),                /*   0 */
      'mode'     => octdec($this->s->readData(8)),                 /* 100 */
      'uid'      => octdec($this->s->readData(8)),                 /* 108 */
      'gid'      => octdec($this->s->readData(8)),                 /* 116 */
      'size'     => octdec($this->s->readData(12)),                /* 124 */
      'mtime'    => octdec($this->s->readData(12)),                /* 136 */
      'chksum'   => octdec($this->s->readData(8)),                 /* 148 */
      'typeflag' =>        $this->s->readData(1),                  /* 156 */
      'linkname' =>        $this->s->readData(100),                /* 157 */
      'magic'    =>        $this->s->readData(6),                  /* 257 */
      'version'  =>        $this->s->readData(2),                  /* 263 */
      'uname'    =>        $this->s->readData(32),                 /* 265 */
      'gname'    =>        $this->s->readData(32),                 /* 297 */
      'devmajor' =>        $this->s->readData(8),                  /* 329 */
      'devminor' =>        $this->s->readData(8),                  /* 337 */
      'prefix'   =>        $this->s->readData(155),                /* 345 */
    ];
    if ($info['magic'] !== "ustar\0") {
      $this->s = null;
      return null;
    }
    $this->s->alignStream(512);
    $data = $this->s->readData($info['size']);
    $this->s->alignStream(512);
    return [
      'info'=>$info,
      'data'=>$data
    ];
  }
}

class DankaguSerializedScore {
  static function deserialize($data) {
    $s = new MemoryStream($data);
    $s->littleEndian = true;
  
    $parsed = DankaguSerializedScore::readChunk($s);
    return $parsed;
  }
  static function readChunk(Stream $s) {
    $tag = $s->readData(4);
    $size = $s->long;
    $data = $s->readData($size);
    switch ($tag) {
      case 'RIFF': {return DankaguSerializedScore::parseRIFF($data);}
      case 'SCRE': {return DankaguSerializedScore::parseSCRE($data);}
      case 'BPM ': {return DankaguSerializedScore::parseBPM_($data);}
      case 'INPT': {return DankaguSerializedScore::parseINPT($data);}
      case 'EVNT': {return DankaguSerializedScore::parseEVNT($data);}
      default: {
        throw new Error("unknown chunk $tag");
      }
    }
  }
  static function parseRIFF($data) {
    $s = new MemoryStream($data);
    $s->littleEndian = true;
    $chunks = [];
    while ($s->position < $s->size) {
      $chunks[] = DankaguSerializedScore::readChunk($s);
    }
    return $chunks;
  }
  static function parseSCRE($data) {
    $s = new MemoryStream($data);
    $s->littleEndian = true;
    $capacity = $s->long;
    $u1 = $s->ushort;
    $u2 = $s->ushort;
    $version = $s->long;
    //assert($capacity == 1);
    //assert($u1 == 4);
    //assert($version == 1);
    $chunkInfo = ['chunk' => 'score', 'version' => $version];
    return $chunkInfo;
  }
  static function parseBPM_($data) {
    $s = new MemoryStream($data);
    $s->littleEndian = true;
    $capacity = $s->long;
    $u1 = $s->ushort;
    $u2 = $s->ushort;
    //assert($u1 == 20);
    $chunkInfo = ['chunk' => 'bpm', 'length'=>$capacity, 'bpmList' => []];
    for ($i=0; $i<$capacity; $i++) {
      $chunkInfo['bpmList'][] = [
        'time' => $s->long,
        'bpm' => $s->float,
        'offset' => $s->float,
        'length' => $s->long,
        'meter' => ord($s->byte),
        'tacet' => ord($s->byte),
      ];
      $s->readData(2);
    }
    return $chunkInfo;
  }
  static function parseINPT($data) {
    $s = new MemoryStream($data);
    $s->littleEndian = true;
    $capacity = $s->long;
    $u1 = $s->ushort;
    $u2 = $s->ushort;
    //assert($u1 == 20);
    $chunkInfo = ['chunk' => 'input', 'length'=>$capacity, 'noteList' => []];
    for ($i=0; $i<$capacity; $i++) {
      $noteInfo = [
        'time' => $s->long,
        'trackId' => $s->ushort & 0xff,
        'syncIndex' => $s->ushort,
        'sync' => ord($s->byte),
        'longTouch' => ord($s->byte),
        'slide' => ord($s->byte),
        'flickType' => ord($s->byte),
        'prevIndex' => $s->ushort,
        'nextIndex' => $s->ushort,
        'value' => $s->long,
        'thisIndex' => $i,
      ];
      $chunkInfo['noteList'][] = $noteInfo;
    }
    return $chunkInfo;
  }
  static function parseEVNT($data) {
    $s = new MemoryStream($data);
    $s->littleEndian = true;
    $capacity = $s->long;
    $u1 = $s->ushort;
    $u2 = $s->ushort;
    $chunkInfo = ['chunk' => 'event', 'length'=>$capacity, 'type' => $u2, 'eventList' => []];
    for ($i=0; $i<$capacity; $i++) {
      $chunkInfo['eventList'][] = [
        'time' => $s->long,
        'value' => $s->long,
      ];
    }
    return $chunkInfo;
  }
}

class DankaguScore {
  static function parseScoreArchive($data) {
    $tar = new TapeArchive(new MemoryStream($data));
    $entries = [];
    while ($file = $tar->next()) {
      $entries[$file['info']['name']] = DankaguSerializedScore::deserialize($file['data']);
    }
    return $entries;
  }
  static function roundReal($n) {
    return round($n * 100000) / 100000;
  }
  static function roundBeat($n) {
    return round($n * 4) / 4;
  }
  static function scoreToSvg($score) {
    $bpmList = [];
    $noteList = [];
    $feverEvent = [];
    $endOfChartTs = 0;
    foreach ($score as $c) {
      switch ($c['chunk']) {
        case 'bpm': {
          foreach ($c['bpmList'] as $i) {
            $bpmList[$i['time']] = $i;
          }
          $bpmList = array_values($bpmList);
          usort($bpmList, function ($a, $b) { return $a['time'] - $b['time'];});
          break;
        }
        case 'input': {
          $noteList = $c['noteList'];
          break;
        }
        case 'event': {
          switch ($c['type']) {
            case 10: {
              $feverEvent = $c['eventList'];
              usort($feverEvent, function ($a, $b) { return $a['time'] - $b['time'];});
              break;
            }
            case 11: {
              if (!empty($c['eventList'])) {
                $endOfChartTs = $c['eventList'][0]['time'];
              }
              break;
            }
          }
          break;
        }
      }
    }
    if (empty($bpmList) || empty($noteList) || empty($endOfChartTs)) {
      return;
    }
    $bpmList[0]['beat'] = 0;
    for ($i=1; $i<count($bpmList); $i++) {
      $bpmList[$i]['beat'] = $bpmList[$i-1]['beat'] + DankaguScore::roundBeat(($bpmList[$i]['time'] - $bpmList[$i-1]['time'] - $bpmList[$i-1]['offset']*1000) / (60000 / $bpmList[$i-1]['bpm']));
    }
    $lastBpm = $bpmList[count($bpmList)-1];
    $endOfChartBeat = DankaguScore::roundBeat(($endOfChartTs - $lastBpm['time']) / (60000 / $lastBpm['bpm'])) + $lastBpm['beat'];
    $svgWidth = ceil($endOfChartBeat / 20) * 200;

    $svgParts = ['<?xml version="1.0" standalone="no"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" ><svg xmlns="http://www.w3.org/2000/svg" width="'.$svgWidth.'" height="900" style="background:black">'];
    $svgParts[] = '<style>text{font:12px Arial;fill:#CDCDCD;-webkit-user-select:none;user-select:none}.bpm_mark{fill:#15CD15}</style>';
    $svgParts[] = '<defs>';
    $svgParts[] = '  <radialGradient id="tap_gradient"><stop offset="40%" stop-color="black" /><stop offset="50%" stop-color="rgb(242,115,0)" /><stop offset="50%" stop-color="rgb(255,137,0)" /><stop offset="65%" stop-color="rgb(255,137,0)" /><stop offset="65%" stop-color="rgb(251,249,113)" /><stop offset="100%" stop-color="rgb(251,249,113)" /></radialGradient>';
    $svgParts[] = '  <linearGradient id="tap_gradient_center"><stop offset="0%" stop-color="rgba(0,0,0,0)" /><stop offset="5%" stop-color="rgba(0,0,0,0)" /><stop offset="5%" stop-color="rgb(255,137,0)" /><stop offset="14%" stop-color="rgb(255,137,0)" /><stop offset="25%" stop-color="black" /><stop offset="27%" stop-color="black" /><stop offset="27%" stop-color="rgb(255,255,85)" /><stop offset="73%" stop-color="rgb(255,255,85)" /><stop offset="73%" stop-color="black" /><stop offset="75%" stop-color="black" /><stop offset="86%" stop-color="rgb(255,137,0)" /><stop offset="95%" stop-color="rgb(255,137,0)" /><stop offset="95%" stop-color="rgba(0,0,0,0)" /><stop offset="100%" stop-color="rgba(0,0,0,0)" /></linearGradient>';
    $svgParts[] = '  <radialGradient id="slide_gradient"><stop offset="40%" stop-color="black" /><stop offset="50%" stop-color="rgb(19,70,205)" /><stop offset="50%" stop-color="rgb(42,117,255)" /><stop offset="65%" stop-color="rgb(42,117,255)" /><stop offset="65%" stop-color="rgb(54,166,251)" /><stop offset="100%" stop-color="rgb(82,255,255)" /></radialGradient>';
    $svgParts[] = '  <linearGradient id="slide_gradient_center"><stop offset="0%" stop-color="rgba(0,0,0,0)" /><stop offset="5%" stop-color="rgba(0,0,0,0)" /><stop offset="5%" stop-color="rgb(42,117,255)" /><stop offset="14%" stop-color="rgb(42,117,255)" /><stop offset="25%" stop-color="black" /><stop offset="75%" stop-color="black" /><stop offset="86%" stop-color="rgb(42,117,255)" /><stop offset="95%" stop-color="rgb(42,117,255)" /><stop offset="95%" stop-color="rgba(0,0,0,0)" /><stop offset="100%" stop-color="rgba(0,0,0,0)" /></linearGradient>';
    $svgParts[] = '  <linearGradient id="fuzzy_gradient" x1="100%" y1="100%" x2="0%" y2="0%"><stop offset="25%" stop-color="black" /><stop offset="30%" stop-color="rgb(0,73,22)" /><stop offset="30%" stop-color="rgb(0,194,55)" /><stop offset="36%" stop-color="rgb(0,194,55)" /><stop offset="36%" stop-color="rgb(218,255,48)" /><stop offset="45%" stop-color="rgb(218,255,48)" /><stop offset="45%" stop-color="rgb(5,139,72)" /><stop offset="50%" stop-color="rgba(5,139,72,0)" /></linearGradient>';
    $svgParts[] = '  <linearGradient id="fuzzy_gradient_center"><stop offset="0%" stop-color="rgba(0,0,0,0)" /><stop offset="8%" stop-color="rgba(0,0,0,0)" /><stop offset="8%" stop-color="rgb(9,200,1)" /><stop offset="14%" stop-color="rgb(9,200,1)" /><stop offset="25%" stop-color="black" /><stop offset="27%" stop-color="black" /><stop offset="27%" stop-color="rgb(243,255,48)" /><stop offset="73%" stop-color="rgb(243,255,48)" /><stop offset="73%" stop-color="black" /><stop offset="75%" stop-color="black" /><stop offset="86%" stop-color="rgb(9,200,1)" /><stop offset="92%" stop-color="rgb(9,200,1)" /><stop offset="92%" stop-color="rgba(0,0,0,0)" /><stop offset="100%" stop-color="rgba(0,0,0,0)" /></linearGradient>';
    $svgParts[] = '  <linearGradient id="line_slide_gradient"><stop offset="0%" stop-color="rgba(34,100,141,0)" /><stop offset="10%" stop-color="rgb(34,100,141)" /><stop offset="15%" stop-color="rgb(35,255,255)" /><stop offset="15%" stop-color="rgb(21,132,235)" /><stop offset="30%" stop-color="rgb(7,65,211)" /><stop offset="70%" stop-color="rgb(7,65,211)" /><stop offset="85%" stop-color="rgb(21,132,235)" /><stop offset="85%" stop-color="rgb(35,255,255)" /><stop offset="90%" stop-color="rgb(34,100,141)" /><stop offset="100%" stop-color="rgba(34,100,141,0)" /></linearGradient>';
    $svgParts[] = '  <linearGradient id="line_fuzzy_gradient"><stop offset="0%" stop-color="rgba(48,146,52,0)" /><stop offset="10%" stop-color="rgb(48,146,52)" /><stop offset="15%" stop-color="rgb(60,255,69)" /><stop offset="15%" stop-color="rgb(31,195,42)" /><stop offset="30%" stop-color="rgb(7,123,56)" /><stop offset="70%" stop-color="rgb(7,123,56)" /><stop offset="85%" stop-color="rgb(31,195,42)" /><stop offset="85%" stop-color="rgb(60,255,69)" /><stop offset="90%" stop-color="rgb(48,146,52)" /><stop offset="100%" stop-color="rgba(48,146,52,0)" /></linearGradient>';
    $svgParts[] = '  <g style="opacity:0.7" width="20" height="10" id="tap"><path d="M10,0A10 5 360 1 1 10,10A10 5 360 1 1 10,0" fill="url(\'#tap_gradient\')" /><path d="M0,4.5h20v1h-20Z" fill="url(\'#tap_gradient_center\')" /></g>';
    $svgParts[] = '  <g style="opacity:0.7" width="20" height="10" id="slide"><path d="M10,0A10 5 360 1 1 10,10A10 5 360 1 1 10,0" fill="url(\'#slide_gradient\')" /><path d="M0,4.5h20v1h-20Z" fill="url(\'#slide_gradient_center\')" /><path d="M7,4.75h6a1 0.75 0 1 1 0,0.5h-6a1 0.75 0 1 1 0,-0.5" fill="rgb(132,255,255)" /></g>';
    $svgParts[] = '  <g style="opacity:0.7" width="20" height="10" id="fuzzy"><path d="M10,0v5h-9.8v-0.2z" fill="url(\'#fuzzy_gradient\')" /><path d="M10,0v5h-9.8v-0.2z" fill="url(\'#fuzzy_gradient\')" style="transform:scale(1,-1);transform-origin:10px 5px" /><path d="M10,0v5h-9.8v-0.2z" fill="url(\'#fuzzy_gradient\')" style="transform:scale(-1,1);transform-origin:10px 5px" /><path d="M10,0v5h-9.8v-0.2z" fill="url(\'#fuzzy_gradient\')" style="transform:scale(-1,-1);transform-origin:10px 5px" /><path d="M0,4.5h20v1h-20Z" fill="url(\'#fuzzy_gradient_center\')" /></g>';
    $svgParts[] = '  <g style="opacity:0.9" width="20" height="20" id="line_slide"><path d="M0,0h20v20h-20z" fill="url(\'#line_slide_gradient\')" /></g>';
    $svgParts[] = '  <g style="opacity:0.9" width="20" height="20" id="line_fuzzy"><path d="M5,0h10v20h-10z" fill="url(\'#line_fuzzy_gradient\')" /></g>';
    $svgParts[] = '  <g style="opacity:0.7" width="20" height="10" id="slide_node"><path d="M2,4.25h16v1.5h-16z" fill="rgb(132,255,255)" /></g>';
    $svgParts[] = '  <g style="opacity:0.7" width="20" height="10" id="fuzzy_node"><path d="M0,4h20v2h-20z" fill="rgb(243,255,48)" /></g>';
    $svgParts[] = '  <g style="opacity:0.8" width="20" height="14" id="attack_note"><path d="M1,0l2,0.5a1.75 1.75 270 1 1 -1.75,1.75l-0.5,-2 M19,0l-2,0.5a1.75 1.75 270 1 0 1.75,1.75l0.5,-2 M1,14l2,-0.5a1.75 1.75 270 1 0 -1.75,-1.75l-0.5,2 M19,14l-2,-0.5a1.75 1.75 270 1 1 1.75,-1.75l0.5,2" fill="rgb(255,60,60)" /><path d="M3,1a1.25 1.25 180 1 1 0,2.5a1.25 1.25 180 1 1 0,-2.5 M17,1a1.25 1.25 180 1 1 0,2.5a1.25 1.25 180 1 1 0,-2.5 M3,10.5a1.25 1.25 180 1 1 0,2.5a1.25 1.25 180 1 1 0,-2.5 M17,10.5a1.25 1.25 180 1 1 0,2.5a1.25 1.25 180 1 1 0,-2.5" fill="rgb(255,160,160)" /></g>';
    $svgParts[] = '  <g id="beat_section" width="140" height="40"><path d="M0,0v40M140,0v40M0,0" stroke="#CCCCCC" /><path d="M0,40h140M20,0v40M40,0v40M60,0v40M80,0v40M100,0v40M120,0v40M0,0" stroke="#777777" /></g>';
    $svgParts[] = '  <g id="beat_section_top" width="140" height="40"><path d="M0,0v40M140,0v40M0,0" stroke="#CCCCCC" /><path d="M0,0h140M0,40h140M20,0v40M40,0v40M60,0v40M80,0v40M100,0v40M120,0v40M0,0" stroke="#777777" /></g>';
    $svgParts[] = '</defs>';

    $svgParts[] = '<g class="bg_layer">';
    for ($i=0; $i<$endOfChartBeat; $i++) {
      $isTop = ($i+1) == $endOfChartBeat || ($i%20) == 19;
      $x = 200 * floor($i / 20) + 30;
      $y = 810 - 40 * ($i % 20);
      $svgParts[] = '<use href="#beat_section'.($isTop?'_top':'').'" x="'.$x.'" y="'.$y.'" />';
    }
    $feverPeriod = [];
    foreach ($feverEvent as $trigger) {
      list($x, $y, $beat) = DankaguScore::getNotePos($trigger['time'], 0, $bpmList);
      $x -= 10;
      $y -= 1;
      $color = '';
      switch ($trigger['value']) {
        case 0:{
          $color='#A04040';
          break;
        }
        case 1:
        case 2:{
          $color='#D04040';
          $feverPeriod[] = $trigger['time'];
          break;
        }
      }
      if (!empty($color)) {
        $svgParts[] = '<rect class="fever_trigger" width="140" height="2" fill="'.$color.'" x="'.$x.'" y="'.$y.'" />';
      }
    }
    $svgParts[] = '</g>';
    if (count($feverPeriod) % 2 === 1) $feverPeriod[] = $endOfChartTs;
    $feverPeriod = array_chunk($feverPeriod, 2);

    $syncLayerIdx = count($svgParts);

    $lines = [];
    foreach ($noteList as $note) {
      if ($note['nextIndex'] == 0) continue;
      $start = $note;
      $startPos = DankaguScore::getNotePos($start['time'], $start['trackId'], $bpmList);
      $end = $noteList[$note['nextIndex']];
      $endPos = DankaguScore::getNotePos($end['time'], $end['trackId'], $bpmList);
      $line = [
        'start' => [
          't' => $start['time'],
          'l' => $start['trackId'],
          'x' => $startPos[0],
          'y' => $startPos[1],
          'b' => $startPos[2],
        ],
        'end' => [
          't' => $end['time'],
          'l' => $end['trackId'],
          'x' => $endPos[0],
          'y' => $endPos[1],
          'b' => $endPos[2],
        ],
        'type' => $start['flickType'] === 5 ? 'line_fuzzy' : 'line_slide'
      ];
      while (floor($line['start']['b'] / 20) != floor($line['end']['b'] / 20)) {
        $insertEndBeat = floor($line['start']['b'] / 20) * 20 + 20;
        $insertEnd = [
          't' => $line['start']['t'] + ($line['end']['t'] - $line['start']['t']) * ($insertEndBeat - $line['start']['b']) / ($line['end']['b'] - $line['start']['b']),
          'l' => $line['start']['l'] + ($line['end']['l'] - $line['start']['l']) * ($insertEndBeat - $line['start']['b']) / ($line['end']['b'] - $line['start']['b']),
          'x' => 0,
          'y' => 850,
          'b' => $insertEndBeat,
        ];
        $insertEnd['x'] = $line['start']['x'] + ($insertEnd['l'] - $line['start']['l']) * 20 + 200;
        $lines[] = [
          'start' => $line['start'],
          'end' => [
            't' => $insertEnd['t'],
            'l' => $insertEnd['l'],
            'x' => $insertEnd['x'] - 200,
            'y' => $insertEnd['y'] - 800,
            'b' => $insertEnd['b'],
          ],
          'type' => $line['type']
        ];
        $line['start'] = $insertEnd;
      }
      $lines[] = $line;
    }
    $svgParts[] = '<g class="line_layer">';
    foreach ($lines as $line) {
      $x = $line['end']['x'] - 10;
      $y = $line['end']['y'];
      $width = $line['start']['x'] - $line['end']['x'];
      $height = $line['start']['y'] - $line['end']['y'];
      if ($height == 0) continue;
      $transformScaleY = DankaguScore::roundReal($height / 20);
      $transformSkewX = DankaguScore::roundReal(atan($width / $height));
      $transform = [];
      if ($transformSkewX) $transform[] = "skewX(${transformSkewX}rad)";
      if ($transformScaleY) $transform[] = "scaleY(${transformScaleY})";
      $style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.DankaguScore::roundReal($x).'px '.DankaguScore::roundReal($y).'px"' : '';
      if ($height == 0) print_r($line);
      $noteType = $line['type'];
      $svgParts[] = '<use href="#'.$noteType.'" x="'.DankaguScore::roundReal($x).'" y="'.DankaguScore::roundReal($y).'" b1="'.$line['start']['b'].'" b2="'.$line['end']['b'].'" '.$style.' />';
    }
    $svgParts[] = '</g>';

    $syncPoints = [];
    $svgParts[] = '<g class="note_layer">';
    foreach ($noteList as $note) {
      if ($note['trackId'] < 0 || $note['trackId'] > 6) continue;
      $noteTime = $note['time'];
      list($x, $y, $noteBeat) = DankaguScore::getNotePos($noteTime, $note['trackId'], $bpmList);
      $x = DankaguScore::roundReal($x) - 10;
      $y = DankaguScore::roundReal($y) - 5;
      if (!isset($syncPoints[$noteTime])) {
        $syncPoints[$noteTime] = [$x + 18, $x + 2, $y + 5];
      }
      $syncPoints[$noteTime][0] = min($syncPoints[$noteTime][0], $x + 18);
      $syncPoints[$noteTime][1] = max($syncPoints[$noteTime][1], $x + 2);
      $noteType = 'tap';
      if ($note['flickType'] == 5) {
        if ($note['nextIndex'] && $note['prevIndex']) $noteType = 'fuzzy_node';
        else $noteType = 'fuzzy';
      } else if ($note['nextIndex'] && $note['prevIndex']) $noteType = 'slide_node';
      else if ($note['nextIndex'] || $note['prevIndex']) $noteType = 'slide';
      $isAttackNote = $note['value'] === 2;
      foreach ($feverPeriod as $p) {
        if ($noteTime < $p[0]) break;
        if ($noteTime >= $p[0] && $noteTime <= $p[1]) {
          $isAttackNote = true;
          break;
        }
      }
      if ($isAttackNote) {
        $svgParts[] = '<use href="#attack_note" x="'.$x.'" y="'.($y - 2).'" />';
      }
      $svgParts[] = '<use href="#'.$noteType.'" x="'.$x.'" y="'.$y.'" />';
      if ($noteBeat - floor($noteBeat / 20) * 20 > 19.9) {
        if ($isAttackNote) {
          $svgParts[] = '<use href="#attack_note" x="'.($x + 200).'" y="'.($y + 800 - 2).'" />';
        }
        $svgParts[] = '<use href="#'.$noteType.'" x="'.($x + 200).'" y="'.($y + 800).'" />';
      } else if ($noteBeat - floor($noteBeat / 20) * 20 < 0.1) {
        if ($isAttackNote) {
          $svgParts[] = '<use href="#attack_note" x="'.($x - 200).'" y="'.($y - 800 - 2).'" />';
        }
        $svgParts[] = '<use href="#'.$noteType.'" x="'.($x - 200).'" y="'.($y - 800).'" />';
      }
    }
    $svgParts[] = '</g>';

    $syncLayerParts = [];
    $syncLayerParts[] = '<g class="sync_line_layer">';
    foreach ($syncPoints as $t=>$sync) {
      list($x1, $x2, $y) = $sync;
      if ($x1 >= $x2) continue;
      $syncLayerParts[] = "<path d=\"M$x1,$y H$x2\" stroke=\"#B4B4B4\" />";
    }
    $syncLayerParts[] = '</g>';
    array_splice($svgParts, $syncLayerIdx, 0, $syncLayerParts);

    $svgParts[] = '<g class="text_mark_layer">';
    $meter = $bpmList[0]['meter'];
    $nextBpmBeat = 9999;
    $currentBpmI = 0;
    $currentBar = 1;
    if (isset($bpmList[$currentBpmI+1])) $nextBpmBeat = $bpmList[$currentBpmI+1]['beat'];
    for ($i=$bpmList[0]['tacet']; $i<=$endOfChartBeat; $i+=$meter) {
      if ($i >= $nextBpmBeat) {
        $i = $nextBpmBeat;
        $currentBpmI++;
        if (isset($bpmList[$currentBpmI+1])) $nextBpmBeat = $bpmList[$currentBpmI+1]['beat'];
        else $nextBpmBeat = 9999;
      }
      $column = floor($i / 20);
      $x = 200 * $column + 172;
      $y = 850 - 40 * ($i - $column * 20)+$meter;
      $svgParts[] = '<text class="section_mark" x="'.$x.'" y="'.$y.'">'.($currentBar++).'</text>';
    }
    foreach ($bpmList as $bpm) {
      list($x, $y, $beat) = DankaguScore::getNotePos($bpm['time'], 0, $bpmList);
      $x -= 35;
      $y += 4;
      $svgParts[] = '<text class="bpm_mark" x="'.$x.'" y="'.$y.'">'.DankaguScore::roundReal($bpm['bpm']).'</text>';
    }
    $svgParts[] = '</g>';

    $svgParts[] = '</svg>';
    return implode("\n",$svgParts);
  }
  static function getNotePos($time, $track, $bpmList) {
    $bpm = $bpmList[0];
    for ($i=1; $i<count($bpmList); $i++) {
      if ($bpmList[$i]['time'] <= $time) {
        $bpm = $bpmList[$i];
      }
    }
    $beat = DankaguScore::roundBeat((($time - $bpm['time'] - $bpm['offset']*1000) / (60000 / $bpm['bpm'])) * 384) / 384 + $bpm['beat'];
    $beat = max($beat, 0);
    $beatInt = floor($beat);
    $x = 200 * floor($beatInt / 20) + 40 + 20 * $track;
    $y = 850 - 40 * (($beatInt % 20) + ($beat - $beatInt));
    return [$x, $y, $beat];
  }
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  chdir(__DIR__);
  exit;
  function _log($s) {echo "$s\n";}
  require_once 'resource_fetch.php';
  
  $curl = curl_init();
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

  $exportRule = [
    [ 'bundleNameMatch'=>'/^\/common\/sc\/(.+).dat$/', 'exportTo' => 'score', 'callback'=>'dumpScore' ],
  ];
  foreach (glob('manifest/*.json') as $m) {
    echo "$m\n";
    $currenttime = time();
    $manifest = json_decode(file_get_contents($m), true);
    $assetDir = ASSET_DOMAIN.$manifest['assetDir'];
    foreach ($manifest['manifest'][3] as $info) {
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
  }

  exit;
  foreach (glob('sc/sc????.dat.tar.gz') as $sc) {
    break;
    $scores = DankaguScore::parseScoreArchive(gzdecode(file_get_contents($sc)));
    foreach ($scores as $id=>$score) {
      if ($id[9] === '5') {
        $id = pathinfo($id, PATHINFO_FILENAME);
        $svg = DankaguScore::scoreToSvg($score);
        echo "$id.svg\t".strlen($svg)."\n";
        file_put_contents("__output/$id.svg", $svg);
        break;
      }
      echo "\r$id";
      foreach ($score as $c) {
        if ($c['chunk'] == 'bpm') {
          foreach ($c['bpmList'] as $bpm) {
            //if ($bpm['tacet'] != 0) {
            if ($bpm['offset'] != 0 && count($c['bpmList']) > 1) {
              print_r($c['bpmList']);
              break;
            }
          }
          break;
        }
      }
    }
  }
  //exit;

  $scores = DankaguScore::parseScoreArchive(gzdecode(file_get_contents('sc/sc0910.dat.tar.gz')));
  foreach ($scores as $id=>$score) {
    $id = pathinfo($id, PATHINFO_FILENAME);
    $svg = DankaguScore::scoreToSvg($score);
    echo "$id.svg\t".strlen($svg)."\n";
    file_put_contents("__output/$id.json", json_encode($score));
    file_put_contents("__output/$id.svg", $svg);
  }
}
