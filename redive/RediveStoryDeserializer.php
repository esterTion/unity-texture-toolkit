<?php
require_once 'UnityBundle.php';

class CommandNumber {
  const NONE = -1;
  const TITLE = 0;
  const OUTLINE = 1;
  const VISIBLE = 2;
  const FACE = 3;
  const FOCUS = 4;
  const BACKGROUND = 5;
  const PRINT = 6;
  const TAG = 7;
  const GOTO = 8;
  const BGM = 9;
  const TOUCH = 10;
  const CHOICE = 11;
  const VO = 12;
  const WAIT = 13;
  const IN_L = 14;
  const IN_R = 15;
  const OUT_L = 16;
  const OUT_R = 17;
  const FADEIN = 18;
  const FADEOUT = 19;
  const IN_FLOAT = 20;
  const OUT_FLOAT = 21;
  const JUMP = 22;
  const SHAKE = 23;
  const POP = 24;
  const NOD = 25;
  const SE = 26;
  const BLACK_OUT = 27;
  const BLACK_IN = 28;
  const WHITE_OUT = 29;
  const WHITE_IN = 30;
  const TRANSITION = 31;
  const SITUATION = 32;
  const COLOR_FADEIN = 33;
  const FLASH = 34;
  const SHAKE_TEXT = 35;
  const TEXT_SIZE = 36;
  const SHAKE_SCREEN = 37;
  const DOUBLE = 38;
  const SCALE = 39;
  const TITLE_TELOP = 40;
  const WINDOW_VISIBLE = 41;
  const LOG = 42;
  const NOVOICE = 43;
  const CHANGE = 44;
  const FADEOUT_ALL = 45;
  const MOVIE = 46;
  const MOVIE_STAY = 47;
  const BATTLE = 48;
  const STILL = 49;
  const BUSTUP = 50;
  const ENV = 51;
  const TUTORIAL_REWARD = 52;
  const NAME_EDIT = 53;
  const EFFECT = 54;
  const EFFECT_DELETE = 55;
  const EYE_OPEN = 56;
  const MOUTH_OPEN = 57;
  const AUTO_END = 58;
  const EMOTION = 59;
  const EMOTION_END = 60;
  const ENV_STOP = 61;
  const BGM_PAUSE = 62;
  const BGM_RESUME = 63;
  const BGM_VOLUME_CHANGE = 64;
  const ENV_RESUME = 65;
  const ENV_VOLUME = 66;
  const SE_PAUSE = 67;
  const CHARA_FULL = 68;
  const SWAY = 69;
  const BACKGROUND_COLOR = 70;
  const PAN = 71;
  const STILL_UNIT = 72;
  const SLIDE_CHARA = 73;
  const SHAKE_SCREEN_ONCE = 74;
  const TRANSITION_RESUME = 75;
  const SHAKE_LOOP = 76;
  const SHAKE_DELETE = 77;
  const UNFACE = 78;
  const WAIT_TOKEN = 79;
  const EFFECT_ENV = 80;
  const BRIGHT_CHANGE = 81;
  const CHARA_SHADOW = 82;
  const UI_VISIBLE = 83;
  const FADEIN_ALL = 84;
  const CHANGE_WINDOW = 85;
  const BG_PAN = 86;
  const STILL_MOVE = 87;
  const STILL_NORMALIZE = 88;
  const VOICE_EFFECT = 89;
  const TRIAL_END = 90;
  const SE_EFFECT = 91;
  const CHARACTER_UP_DOWN = 92;
  const BG_CAMERA_ZOOM = 93;
  const BACKGROUND_SPLIT = 94;
  const CAMERA_ZOOM = 95;
  const SPLIT_SLIDE = 96;
  const BGM_TRANSITION = 97;
  const SHAKE_ANIME = 98;
  const INSERT_STORY = 99;
  const PLACE = 100;
  const IGNORE_BGM = 101;
  const MULTI_LIPSYNC = 102;
  const JINGLE = 103;
  const TOUCH_TO_START = 104;
  const EVENT_ADV_MOVE_HORIZONTAL = 105;
  const BG_PAN_X = 106;
  const BACKGROUND_BLUR = 107;
  const SEASONAL_REWARD = 108;
  const MINI_GAME = 109;
  const MAX = 110;
}
class CommandCategory {
  const Non = 0;
  const System = 1;
  const Motion = 2;
  const Effect = 3;
}

define('_commandConfigList', [
  ['Number'=>CommandNumber::TITLE, 'Name'=>"title", 'ClassName'=>"StoryCommandTitle", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::OUTLINE, 'Name'=>"outline", 'ClassName'=>"StoryCommandOutline", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::VISIBLE, 'Name'=>"visible", 'ClassName'=>"StoryCommandVisible", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::FACE, 'Name'=>"face", 'ClassName'=>"StoryCommandFace", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::FOCUS, 'Name'=>"focus", 'ClassName'=>"StoryCommandFocus", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::BACKGROUND, 'Name'=>"background", 'ClassName'=>"StoryCommandBackground", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::PRINT, 'Name'=>"print", 'ClassName'=>"StoryCommandPrint", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::TAG, 'Name'=>"tag", 'ClassName'=>"StoryCommandTag", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::GOTO, 'Name'=>"goto", 'ClassName'=>"StoryCommandGoto", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::BGM, 'Name'=>"bgm", 'ClassName'=>"StoryCommandBgm", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>6],
  ['Number'=>CommandNumber::TOUCH, 'Name'=>"touch", 'ClassName'=>"StoryCommandTouch", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::CHOICE, 'Name'=>"choice", 'ClassName'=>"StoryCommandChoice", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::VO, 'Name'=>"vo", 'ClassName'=>"StoryCommandVo", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::WAIT, 'Name'=>"wait", 'ClassName'=>"StoryCommandWait", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::IN_L, 'Name'=>"in_L", 'ClassName'=>"StoryCommandInL", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::IN_R, 'Name'=>"in_R", 'ClassName'=>"StoryCommandInR", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::OUT_L, 'Name'=>"out_L", 'ClassName'=>"StoryCommandOutL", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::OUT_R, 'Name'=>"out_R", 'ClassName'=>"StoryCommandOutR", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::FADEIN, 'Name'=>"fadein", 'ClassName'=>"StoryCommandFadein", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::FADEOUT, 'Name'=>"fadeout", 'ClassName'=>"StoryCommandFadeout", 'Category'=>CommandCategory::Motion, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::IN_FLOAT, 'Name'=>"in_float", 'ClassName'=>"StoryCommandInFloat", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::OUT_FLOAT, 'Name'=>"out_float", 'ClassName'=>"StoryCommandOutFloat", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::JUMP, 'Name'=>"jump", 'ClassName'=>"StoryCommandJump", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::SHAKE, 'Name'=>"shake", 'ClassName'=>"StoryCommandShake", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::POP, 'Name'=>"pop", 'ClassName'=>"StoryCommandPop", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::NOD, 'Name'=>"nod", 'ClassName'=>"StoryCommandNod", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::SE, 'Name'=>"se", 'ClassName'=>"StoryCommandSe", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::BLACK_OUT, 'Name'=>"black_out", 'ClassName'=>"StoryCommandBlackOut", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::BLACK_IN, 'Name'=>"black_in", 'ClassName'=>"StoryCommandBlackIn", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::WHITE_OUT, 'Name'=>"white_out", 'ClassName'=>"StoryCommandWhiteOut", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::WHITE_IN, 'Name'=>"white_in", 'ClassName'=>"StoryCommandWhiteIn", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::TRANSITION, 'Name'=>"transition", 'ClassName'=>"StoryCommandTransition", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::SITUATION, 'Name'=>"situation", 'ClassName'=>"StoryCommandSituation", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::COLOR_FADEIN, 'Name'=>"color_fadein", 'ClassName'=>"StoryCommandColorFadein", 'Category'=>CommandCategory::System, 'minArgCount'=>3, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::FLASH, 'Name'=>"flash", 'ClassName'=>"StoryCommandFlash", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::SHAKE_TEXT, 'Name'=>"shake_text", 'ClassName'=>"StoryCommandShakeText", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::TEXT_SIZE, 'Name'=>"text_size", 'ClassName'=>"StoryCommandTextSize", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::SHAKE_SCREEN, 'Name'=>"shake_screen", 'ClassName'=>"StoryCommandShakeScreen", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::DOUBLE, 'Name'=>"double", 'ClassName'=>"StoryCommandDouble", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>4],
  ['Number'=>CommandNumber::SCALE, 'Name'=>"scale", 'ClassName'=>"StoryCommandScale", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>4],
  ['Number'=>CommandNumber::TITLE_TELOP, 'Name'=>"title_telop", 'ClassName'=>"StoryCommandTitleTelop", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::WINDOW_VISIBLE, 'Name'=>"window_visible", 'ClassName'=>"StoryCommandWindowVisible", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::LOG, 'Name'=>"log", 'ClassName'=>"StoryCommandLog", 'Category'=>CommandCategory::System, 'minArgCount'=>3, 'MaxArgCount'=>4],
  ['Number'=>CommandNumber::NOVOICE, 'Name'=>"novoice", 'ClassName'=>"StoryCommandNoVoice", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::CHANGE, 'Name'=>"change", 'ClassName'=>"StoryCommandChange", 'Category'=>CommandCategory::Motion, 'minArgCount'=>2, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::FADEOUT_ALL, 'Name'=>"fadeout_all", 'ClassName'=>"StoryCommandFadeoutAll", 'Category'=>CommandCategory::Motion, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::MOVIE, 'Name'=>"movie", 'ClassName'=>"StoryCommandMovie", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::MOVIE_STAY, 'Name'=>"movie_stay", 'ClassName'=>"StoryCommandMovieStay", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::BATTLE, 'Name'=>"battle", 'ClassName'=>"StoryCommandBattle", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::STILL, 'Name'=>"still", 'ClassName'=>"StoryCommandStill", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>5],
  ['Number'=>CommandNumber::BUSTUP, 'Name'=>"bust", 'ClassName'=>"StoryCommandBustup", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::ENV, 'Name'=>"amb", 'ClassName'=>"StoryCommandEnv", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>4],
  ['Number'=>CommandNumber::TUTORIAL_REWARD, 'Name'=>"reward", 'ClassName'=>"StoryCommandTutorialReward", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::NAME_EDIT, 'Name'=>"name_dialog", 'ClassName'=>"StoryCommandPlayerNameEdit", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::EFFECT, 'Name'=>"effect", 'ClassName'=>"StoryCommandParticleEffect", 'Category'=>CommandCategory::Effect, 'minArgCount'=>1, 'MaxArgCount'=>5],
  ['Number'=>CommandNumber::EFFECT_DELETE, 'Name'=>"effect_delete", 'ClassName'=>"StoryCommandParticleDelete", 'Category'=>CommandCategory::Effect, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::EYE_OPEN, 'Name'=>"eye_open", 'ClassName'=>"StoryCommandEyeOpen", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::MOUTH_OPEN, 'Name'=>"mouth_open", 'ClassName'=>"StoryCommandMouthOpen", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::AUTO_END, 'Name'=>"end", 'ClassName'=>"StoryCommandForcedEnd", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::EMOTION, 'Name'=>"emotion", 'ClassName'=>"StoryCommandEmotion", 'Category'=>CommandCategory::Effect, 'minArgCount'=>1, 'MaxArgCount'=>5],
  ['Number'=>CommandNumber::EMOTION_END, 'Name'=>"emotion_end", 'ClassName'=>"StoryCommandEmotionEnd", 'Category'=>CommandCategory::Effect, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::ENV_STOP, 'Name'=>"amb_stop", 'ClassName'=>"StoryCommandEnvStop", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::BGM_PAUSE, 'Name'=>"bgm_stop", 'ClassName'=>"StoryCommandBgmPause", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::BGM_RESUME, 'Name'=>"bgm_resume", 'ClassName'=>"StoryCommandBgmResume", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::BGM_VOLUME_CHANGE, 'Name'=>"bgm_volume", 'ClassName'=>"StoryCommandBgmVolumeChange", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::ENV_RESUME, 'Name'=>"amb_resume", 'ClassName'=>"StoryCommandEnvResume", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::ENV_VOLUME, 'Name'=>"amb_volume", 'ClassName'=>"StoryCommandEnvVolumeChange", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::SE_PAUSE, 'Name'=>"se_pause", 'ClassName'=>"StoryCommandSeStop", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::CHARA_FULL, 'Name'=>"chara_full", 'ClassName'=>"StoryCommandCharacterFull", 'Category'=>CommandCategory::System, 'minArgCount'=>3, 'MaxArgCount'=>4],
  ['Number'=>CommandNumber::SWAY, 'Name'=>"sway", 'ClassName'=>"StoryCommandSway", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::BACKGROUND_COLOR, 'Name'=>"bg_color", 'ClassName'=>"StoryCommandBackgroundColor", 'Category'=>CommandCategory::System, 'minArgCount'=>3, 'MaxArgCount'=>4],
  ['Number'=>CommandNumber::PAN, 'Name'=>"pan", 'ClassName'=>"StoryCommandStillPan", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::STILL_UNIT, 'Name'=>"still_unit", 'ClassName'=>"StoryCommandStillUnit", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::SLIDE_CHARA, 'Name'=>"slide", 'ClassName'=>"StoryCommandSlideCharacter", 'Category'=>CommandCategory::Motion, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::SHAKE_SCREEN_ONCE, 'Name'=>"shake_once", 'ClassName'=>"StoryCommandShakeScreenOnce", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::TRANSITION_RESUME, 'Name'=>"transition_resume", 'ClassName'=>"StoryCommandTransitionResume", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::SHAKE_LOOP, 'Name'=>"shake_loop", 'ClassName'=>"StoryCommandShakeLoop", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::SHAKE_DELETE, 'Name'=>"shake_delete", 'ClassName'=>"StoryCommandShakeDelete", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::UNFACE, 'Name'=>"unface", 'ClassName'=>"StoryCommandUnface", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::WAIT_TOKEN, 'Name'=>"token", 'ClassName'=>"StoryCommandWaitToken", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::EFFECT_ENV, 'Name'=>"effect_env", 'ClassName'=>"StoryCommandParticleEffectEnv", 'Category'=>CommandCategory::Effect, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::BRIGHT_CHANGE, 'Name'=>"bright_change", 'ClassName'=>"StoryCommandBrightChange", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::CHARA_SHADOW, 'Name'=>"chara_shadow", 'ClassName'=>"StoryCommandCharacterShadow", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::UI_VISIBLE, 'Name'=>"ui_visible", 'ClassName'=>"StoryCommandMenuVisible", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::FADEIN_ALL, 'Name'=>"fadein_all", 'ClassName'=>"StoryCommandFadeinAll", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::CHANGE_WINDOW, 'Name'=>"change_window", 'ClassName'=>"StoryCommandChangeWindow", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::BG_PAN, 'Name'=>"bg_pan", 'ClassName'=>"StoryCommandBackgroundPan", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::STILL_MOVE, 'Name'=>"still_move", 'ClassName'=>"StoryCommandStillMove", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::STILL_NORMALIZE, 'Name'=>"still_normalize", 'ClassName'=>"StoryCommandStillNormalize", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::VOICE_EFFECT, 'Name'=>"vo_effect", 'ClassName'=>"StoryCommandVoiceEffect", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::TRIAL_END, 'Name'=>"trial_end", 'ClassName'=>"StoryCommandTrialEnd", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::SE_EFFECT, 'Name'=>"se_effect", 'ClassName'=>"StoryCommandSeEffect", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::CHARACTER_UP_DOWN, 'Name'=>"updown", 'ClassName'=>"StoryCommandCharacterUpdown", 'Category'=>CommandCategory::Motion, 'minArgCount'=>4, 'MaxArgCount'=>4],
  ['Number'=>CommandNumber::BG_CAMERA_ZOOM, 'Name'=>"bg_zoom", 'ClassName'=>"StoryCommandBgCameraZoom", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::BACKGROUND_SPLIT, 'Name'=>"split", 'ClassName'=>"StoryCommandBackgroundSplit", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>6],
  ['Number'=>CommandNumber::CAMERA_ZOOM, 'Name'=>"camera_zoom", 'ClassName'=>"StoryCommandCameraZoom", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::SPLIT_SLIDE, 'Name'=>"split_slide", 'ClassName'=>"StoryCommandBackgroundSplitSlide", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>6],
  ['Number'=>CommandNumber::BGM_TRANSITION, 'Name'=>"bgm_transition", 'ClassName'=>"StoryCommandBgmTransition", 'Category'=>CommandCategory::System, 'minArgCount'=>2, 'MaxArgCount'=>2],
  ['Number'=>CommandNumber::SHAKE_ANIME, 'Name'=>"shake_anime", 'ClassName'=>"StoryCommandBackgroundAnimation", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::INSERT_STORY, 'Name'=>"insert", 'ClassName'=>"StoryCommandInsertStory", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::PLACE, 'Name'=>"place", 'ClassName'=>"StoryCommandPlace", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::IGNORE_BGM, 'Name'=>"bgm_overview", 'ClassName'=>"StoryCommandBgmIgnoreStop", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>5],
  ['Number'=>CommandNumber::MULTI_LIPSYNC, 'Name'=>"multi_talk", 'ClassName'=>"StoryCommandMultiLipsync", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::JINGLE, 'Name'=>"jingle_start", 'ClassName'=>"StoryCommandJingle", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>1],
  ['Number'=>CommandNumber::TOUCH_TO_START, 'Name'=>"touch_to_start", 'ClassName'=>"StoryCommandTouchToStart", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::EVENT_ADV_MOVE_HORIZONTAL, 'Name'=>"event_change", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::BG_PAN_X, 'Name'=>"bg_pan_slide", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::BACKGROUND_BLUR, 'Name'=>"bg_blur", 'Category'=>CommandCategory::System, 'minArgCount'=>1, 'MaxArgCount'=>3],
  ['Number'=>CommandNumber::SEASONAL_REWARD, 'Name'=>"seasonal_reward", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0],
  ['Number'=>CommandNumber::MINI_GAME, 'Name'=>"mini_game", 'Category'=>CommandCategory::System, 'minArgCount'=>0, 'MaxArgCount'=>0]
]);
class RediveStoryDeserializer {
  function __construct($byte) {
    $fs = new MemoryStream($byte);
    $list = [];
    $array = [];//Array[4]
    for ($i=2; $i<$fs->size; $i+=2) {
      $list2 = [];
      $index = $fs->short;
      $list2[] = $index;
      $num = $i;
      for (;;) {
        $length = $fs->long;
        if ($length == 0) break;
        $array3 = $fs->readData($length); //byte[num2]
        $list2[] = $array3;
        $num += 4 + $length;
      }
      $i = $num + 4;
      $list[] = $list2;
    }

    $commandList = [];
    $count = count($list);
    for ($i=0; $i<$count; $i++) {
      $item = $this->DeserializeLine($list[$i]);
      if ($item['name'] != NULL) {
        $commandList[] = $item;
      }
    }
    $this->commandList = $commandList;
    $text = new MemoryStream('');
    $text->write('<!DOCTYPE HTML><html><head><title>'.$commandList[0]['args'][0].'</title><meta charset="UTF-8" name="viewport" content="width=device-width"><style>.cmd{color:#DDD;font-size:12px;cursor:pointer}.highlight{background:#ccc;color:#000}</style></head><body style="background:#444;color:#FFF;font-family:Meiryo;-webkit-text-size-adjust:none;cursor:default"><b>');
    $text->write($commandList[0]['args'][0]."<br>\n");
    $text->write($commandList[1]['args'][0]."</b><br>\n<br>\n");
    $buff = ['', ''];
    $currentChara = '';
    $currentFace = '';
    foreach ($commandList as $cmd) {
      $cmdName = $cmd['name'];
      $args = $cmd['args'];
      if ($cmdName == 'print') {
        $buff[0] = $args[0];
        $buff[1] .= str_replace("\n", "<br>\n", $args[1]);
      } else if ($cmdName == 'touch') {
        $text->write($buff[0]."：<br>\n".$buff[1]."<br>\n<br>\n");
        $buff = ['',''];
        $currentChara = '';
        $currentFace = '';
      } else if ($cmdName == 'wait') {
        //$buff[1] .= '(pause:'.$args[0].')';
      } else if ($cmdName == 'choice') {
        $text->write('<div>Choice: ('.$args[1].") ".$args[0]."</div>\n");
      } else if ($cmdName == 'tag') {
        $text->write('<div class="cmd">----- Tag '.$args[0]." -----</div>\n");
      } else if ($cmdName == 'goto') {
        $text->write('<div class="cmd">Jump to tag '.$args[0]."</div>\n");
      } else if ($cmdName == 'vo') {
        $text->write('<div class="voice cmd">voice: '.$args[0]."</div>\n");
      } else if ($cmdName == 'movie' || $cmdName == 'movie_stay') {
        $text->write('<div class="movie cmd">movie: '.$args[0]."</div>\n");
      } else if ($cmdName == 'white_out') {
        $text->write("<b>--- Switch scene ---</b><br>\n<br>\n");
      } else if ($cmdName == 'situation') {
        $text->write("-------------- situation: <br>\n<b>".$args[0]."</b><br>\n--------------<br>\n<br>\n");
      } else if ($cmdName == 'place') {
        $text->write("-------------- place: <br>\n<b>".$args[0]."</b><br>\n--------------<br>\n<br>\n");
      } else if ($cmdName == 'face') {
        $face = ['normal', 'normal','joy','anger','sad','shy','surprised','special_a','special_b','special_c','special_d','special_e','default'][$args[1]];
        if ($currentChara == $args[0] && $currentFace == $face) continue;
        $currentChara = $args[0];
        $currentFace = $face;
        $buff[1] .= '<span class="face cmd" data-chara="'.$args[0].'" data-face="'.$face.'">【chara '.$args[0]." face ".$args[1]." (".$face.")】</span>\n";
      } else if ($cmdName == 'still') {
        if ($args[0] == 'end') {
          $text->write("<div class=\"cmd\">still display end</div>\n");
          continue;
        }
        $text->write('<a href="https://redive.estertion.win/card/story/'.$args[0].'.webp" target="_blank"><img alt="story_still_'.$args[0].'" src="https://redive.estertion.win/card/story/'.$args[0].'.webp@w300"></a>'."<br>\n");
      }
    }
    $text->write('<script src="/static/story_data.min.js"></script></body></html>');
    $text->position = 0;
    $this->data = $text->readData($text->size);
  }

  private function GetCommandName($index) {
    return isset(_commandConfigList[$index]) ? _commandConfigList[$index]['Name'] : NULL;
  }
  private function GetCommandCategory($index) {
    return isset(_commandConfigList[$index]) ? _commandConfigList[$index]['Category'] : NULL;
  }
  private function GetCommandNumber($index) {
    return isset(_commandConfigList[$index]) ? _commandConfigList[$index]['Number'] : NULL;
  }
  private function ConvertStringArgs($str) {
    for ($i=0; $i<strlen($str); $i+=3) {
      $str[$i] = chr(255 - ord($str[$i]));
    }
    return str_replace(['\n','{0}'],["\n",'{player}'], base64_decode($str));
  }
  private function DeserializeLine($commandBytes) {
    $result = [];
    $index = $commandBytes[0];
    $result['name'] = $this->GetCommandName($index);
    $result['args'] = [];
    for ($i=1; $i<count($commandBytes); $i++) {
      $result['args'][] = $this->ConvertStringArgs($commandBytes[$i]);
    }
    $result['category'] = $this->GetCommandCategory($index);
    $result['number'] = $this->GetCommandNumber($index);
    return $result;
  }
}
