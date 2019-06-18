<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);
/**
 * diff-parser from https://github.com/ptlis/diff-parser
 * packed with https://github.com/mpyw/comphar
 */
require_once '../diff_parser.phar';
use ptlis\DiffParser\Parser;
use ptlis\DiffParser\File;
use ptlis\DiffParser\Line;

function parse_db_diff($diff, $master, $cbMap) {
  global $db;
  $db = $master;
  $versionDiff = [];
  $operates = [
    File::CREATED => 0,
    File::CHANGED => 0,
    File::DELETED => 0,
  ];

  // remove other files from diff
  $ori = fopen($diff, 'r');
  $new = fopen($diff.'.new', 'w');
  $observeFiles = array_keys($cbMap);
  $line = fgets($ori);
  $newtable = [];
  while (!feof($ori)) {
    if (substr($line, 0, 11) === "diff --git ") {
      $operates[File::CHANGED]++;
      $fname = substr(explode(' ', trim($line))[3], 2);
      if (!in_array($fname, $observeFiles)) {
        while (!feof($ori)) {
          $line = fgets($ori);
          if (trim($line) === '--- /dev/null') {
            $operates[File::CHANGED]--;
            $operates[File::CREATED]++;
            $newtable[] = substr($fname, 0, -4);
          } else if (trim($line) === '+++ /dev/null') {
            $operates[File::CHANGED]--;
            $operates[File::DELETED]++;
          }
          if (substr($line, 0, 11) === 'diff --git ') {
            $operates[File::CHANGED]++;
            break;
          }
        }
        continue;
      }
    }
    fwrite($new, $line);
    $line = fgets($ori);
  }
  fclose($ori); fclose($new);
  unlink($diff);
  rename($diff.'.new', $diff);

  $parser = new Parser();

  $changeset = $parser->parseFile($diff, Parser::VCS_GIT);
  $files = $changeset->getFiles();

  foreach ($files as &$file) {
    $fname = $file->getNewFilename();
    if (isset($cbMap[$fname])) {
      $data = call_user_func($cbMap[$fname], $file);
      if (!empty($data[1])) {
        $versionDiff[$data[0]] = isset($versionDiff[$data[0]]) ? array_merge($versionDiff[$data[0]], $data[1]) : $data[1];
      }
    }
  }
  $versionDiff['diff'] = $operates;
  if (!empty($newtable)) {
    $versionDiff['new_table'] = $newtable;
  }
  return $versionDiff;
}

$db = NULL;
function diff_clan_battle($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op != Line::ADDED) continue;
      if ($line->getNewLineNo() === 1) break 2;
      preg_match('(/\*clan_battle_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      list($item) = execQuery($db, 'SELECT * FROM clan_battle_period WHERE clan_battle_id='.$id);
      $items[] = [
        'id' => $id,
        'start' => $item['start_time'],
        'end' => $item['end_time']
      ];
    }
  }
  return ['clan_battle', $items];
}
function diff_dungeon_area($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op != Line::ADDED) continue;
      if ($line->getNewLineNo() === 1) break 2;
      preg_match('(/\*dungeon_area_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      list($item) = execQuery($db, 'SELECT * FROM dungeon_area_data WHERE dungeon_area_id='.$id);
      $items[] = [
        'id' => $id,
        'name' => $item['dungeon_name']
      ];
    }
  }
  return ['dungeon_area', $items];
}
function diff_gacha($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op != Line::ADDED) continue;
      if ($line->getNewLineNo() === 1) break 2;
      preg_match('(/\*gacha_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      if (
        $id < 30000
      ) continue;
      list($item) = execQuery($db, 'SELECT * FROM gacha_data WHERE gacha_id='.$id);
      $items[] = [
        'id' => $id,
        'detail' => preg_replace('/^(.+登場).+/', '$1', $item['description']),
        'start' => $item['start_time'],
        'end' => $item['end_time']
      ];
    }
  }
  return ['gacha', $items];
}
function diff_quest_area($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op != Line::ADDED) continue;
      if ($line->getNewLineNo() === 1) break 2;
      preg_match('(/\*area_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      list($item) = execQuery($db, 'SELECT * FROM quest_area_data WHERE area_id='.$id);
      $isHard = $id > 12000 && $id < 13000;
      $items[] = [
        'id' => $id,
        'name' => $item['area_name'].($isHard?' (Hard)':'')
      ];
    }
  }
  return ['quest_area', $items];
}
function diff_story_data($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    $balance = 0;
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op == Line::REMOVED) {$balance--; continue;}
      if ($op != Line::ADDED) continue;
      if ($balance++ < 0) continue;
      $balance--;
      preg_match('(/\*story_group_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      list($item) = execQuery($db, 'SELECT * FROM story_data WHERE story_group_id='.$id);
      $items[] = [
        'id' => $id,
        'name' => $item['title']
      ];
    }
  }
  return ['story', $items];
}
function diff_unit($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    $balance = 0;
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      // 忽略非更新角色
      if (
        $op == Line::REMOVED && 
        strpos($line->getContent(), '/*motion_type*/0,') === false
      ) {$balance--; continue;}

      if ($op != Line::ADDED) continue;
      if ($balance++ < 0) continue;
      $balance--;
      // 忽略追加的未实装角色
      if (strpos($line->getContent(), '/*motion_type*/0,') !== false) continue;

      preg_match('(/\*unit_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      if (
        $id < 100000 || $id > 200000
      ) continue;
      list($item) = execQuery($db, 'SELECT * FROM unit_data WHERE unit_id='.$id);
      $items[] = [
        'id' => $id,
        'name' => $item['unit_name'],
        'real_name' => execQuery($db, 'SELECT unit_name FROM unit_background WHERE unit_id='.$id)[0]['unit_name'] ?: '',
        'rarity' => $item['rarity']
      ];
    }
  }
  return ['unit', $items];
}
function diff_exp($file) {
  global $db;
  $lines = array_reverse($file->gethunks())[0]->getLines();
  array_pop($lines);
  $line = array_reverse($lines)[0]->getContent();
  $lv_limit = [];
  preg_replace_callback('(/\*([\w_]+)\*/(\d+))', function ($m) use(&$lv_limit) {
    if (in_array($m[1], ['team_level', 'total_exp', 'max_stamina'])) $lv_limit[$m[1]] = $m[2];
  }, $line);
  return [
    'max_lv',
    [
      'lv' => $lv_limit['team_level'],
      'exp' => $lv_limit['total_exp'],
      'max_stamina' => $lv_limit['max_stamina']
    ]
  ];
}
function diff_rank($file) {
  global $db;
  if (count($file->getHunks()) < 10) return ['max_rank', ''];
  $prevRank = 0;
  $currRank = 0;
  foreach ($file->getHunks() as $hunk) {
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if (!preg_match('(/\*promotion_level\*/(\d+))', $line->getContent(), $rank)) continue;
      $rank = $rank[1]|0;
      if ($op == Line::ADDED) $currRank = max($currRank, $rank);
      else $prevRank = max($prevRank, $rank);
    }
  }
  return [
    'max_rank',
    $currRank > $prevRank ? $currRank.'' : ''
  ];
}
function diff_event_hatsune($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    $balance = 0;
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op == Line::REMOVED) {$balance--; continue;}
      if ($op != Line::ADDED) continue;
      if ($balance++ < 0) continue;
      $balance--;
      preg_match('(/\*event_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      list($item) = execQuery($db, 'SELECT a.start_time as start, a.end_time as end, b.title as title FROM hatsune_schedule as a, event_story_data as b WHERE a.event_id='.$id.' AND b.value='.$id);
      $items[] = [
        'id' => $id,
        'name' => $item['title'],
        'start' => $item['start'],
        'end' => $item['end'],
        'type' => 'story'
      ];
    }
  }
  return ['event', $items];
}
function diff_event_tower($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    $balance = 0;
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op == Line::REMOVED) {$balance--; continue;}
      if ($op != Line::ADDED) continue;
      if ($balance++ < 0) continue;
      $balance--;
      preg_match('(/\*tower_schedule_id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      list($item) = execQuery($db, 'SELECT a.start_time as start, a.end_time as end FROM tower_schedule as a WHERE a.tower_schedule_id='.$id);
      $items[] = [
        'id' => $id,
        'name' => "",
        'start' => $item['start'],
        'end' => $item['end'],
        'type' => 'tower'
      ];
    }
  }
  return ['event', $items];
}
function diff_campaign($file) {
  global $db;
  $items = [];
  foreach ($file->getHunks() as $hunk) {
    foreach ($hunk->getLines() as $line) {
      $op = $line->getOperation();
      if ($op != Line::ADDED) continue;
      preg_match('(/\*id\*/(\d+))', $line->getContent(), $id);
      if (empty($id)) continue;
      $id = $id[1];
      list($item) = execQuery($db, 'SELECT * FROM campaign_schedule WHERE id='.$id);
      $items[] = [
        'id' => $id,
        'category' => $item['campaign_category'],
        'value' => floatval($item['value']),
        'start'=>$item['start_time'],
        'end'=>$item['end_time']
      ];
    }
  }
  return ['campaign', $items];
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  function execQuery($db, $query) {
    $returnVal = [];
    $result = $db->query($query);
    $returnVal = $result->fetchAll(PDO::FETCH_ASSOC);
    return $returnVal;
  }
  require_once '../mysql.php';
  $mysqli->select_db('db_diff');
  chdir(__DIR__);
  chdir('data');
  exec('git log --pretty=format:"%H %s %at"', $commits);
  $data = [];
  $commits = array_map(function ($commit) {
    preg_match('((.{40}) (.+) (\d+))',$commit,$detail);
    $comm = [
      'hash' => $detail[1],
      'ver' => $detail[2],
      'time' => (int)$detail[3],
      'skip' => false
    ];
    if (!is_numeric($comm['ver'])) {
      preg_match('/(\d+) (\d+)\/(\d+)\/(\d+) (\d+):(\d+)/', $comm['ver'], $ver);
      if (empty($ver)) {
        //$comm['skip'] = true;
        $comm['ver'] = intval($comm['ver']).'';
        if ($comm['ver'] == '0') $comm['skip'] = true;
      } else {
        $comm['ver'] = $ver[1];
        $comm['time'] = mktime($ver[5], $ver[6], 0, $ver[3], $ver[4], $ver[2]) - 3600;
      }
    }
    return $comm;
  }, $commits);
  $i=0;
  $chkTypes = [
    'clan_battle',
    'dungeon_area',
    'gacha',
    'quest_area',
    'story',
    'unit',
    'max_lv',
    'event',
    'campaign'
  ];
  foreach($commits as $no=>$commit){
    if (!isset($commits[$no+1])) continue;
    if ($commit['skip']) continue;
    echo "\n".$commit['ver'].' '.date('Y-m-d H:i', $commit['time'] + 3600).' '.$no.'/'.count($commits);
    exec('git diff '.$commits[$no+1]['hash'].' '.$commit['hash'].' >../a.diff');
    chdir('..');
    $master = new PDO('sqlite:'.__DIR__.'/redive.db');
    $versionDiff = parse_db_diff('a.diff', $master, [
      'clan_battle_period.sql' => 'diff_clan_battle', // clan_battle
      'dungeon_area_data.sql' => 'diff_dungeon_area', // dungeon_area
      'gacha_data.sql' => 'diff_gacha',               // gacha
      'quest_area_data.sql' => 'diff_quest_area',     // quest_area
      'story_data.sql' => 'diff_story_data',          // story_data
      'unit_data.sql' => 'diff_unit',                 // unit
      'experience_team.sql' => 'diff_exp',            // experience
      'unit_promotion.sql' => 'diff_rank',            // rank
      'hatsune_schedule.sql' => 'diff_event_hatsune', // event_hatsune,
      'tower_schedule.sql' => 'diff_event_tower',     // event_tower,
      'campaign_schedule.sql' => 'diff_campaign',     // campaign
    ]);
    unlink('a.diff');
    chdir('data');
    $versionDiff['hash'] = $commit['hash'];
    $versionDiff['ver'] = $commit['ver'];
    $versionDiff['time'] = $commit['time'];
    $versionDiff['timeStr'] = date('Y-m-d H:i', $commit['time'] + 3600);
    $col = ['ver','data'];
    $val = [$commit['ver'], '"'.$mysqli->real_escape_string(brotli_compress(
      json_encode($versionDiff, JSON_UNESCAPED_SLASHES), 11, BROTLI_TEXT
    )).'"'];
    foreach ($chkTypes as $type) {
      if (isset($versionDiff[$type])) {
        $col[] = 'has_'.$type;
        $val[] = 1;
      }
    }
    $mysqli->query('REPLACE INTO redive ('.implode(',', $col).') vALUES ('.implode(',', $val).')');
    exit;
    //if (++$i > 10) break;
    //if ($commit['ver'] < 10040000) break;
  }
  file_put_contents('../diff_parsed.json',json_encode($data));
  //print_r($versionDiff);
}

