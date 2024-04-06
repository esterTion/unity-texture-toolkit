<?php
require_once 'UnityBundle.php';

class AssetFile {
  public $stream;
  public $filePath;
  public $fileName;
  public $fileGen;
  public $m_Version = '2.5.0f5';
  public $platform = 0x6000000;
  public $platformStr = '';
  public $baseDefinitions = false;
  public $classIDs = [];
  public $ClassStructures = [];
  public $preloadTable = [];
  public $buildType;
  public $version;
  public $sharedAssetsList = [];
  public $valid = false;

  function __desctruct() {
    unset($this->sharedAssetsList);
    unset($this->stream);
  }
  function __construct($file) {
    $file = realpath($file);
    if ($file == false) {
      throw new Exception('Invalid filename');
    }
    $this->stream = new FileStream($file);
    $this->filePath = $file;
    $this->fileName = pathinfo($file, PATHINFO_BASENAME);
    try {
      $stream = $this->stream;
      $tableSize = $stream->long;
      $dataEnd = $stream->long;
      $this->fileGen = $stream->long;
      $dataOffset = $stream->long;

      if ($this->fileGen >= 9) {
        $endian = $stream->byte;
        $stream->readData(3);
      } else {
        $stream->position = $dataEnd - $tableSize;
        $endian = $stream->byte;
      }
      if ($this->fileGen >= 22) {
        $tableSize = $stream->ulong;
        $dataEnd = $stream->longlong;
        $dataOffset = $stream->longlong;
        $stream->longlong;
      }
      if ($endian === "\0") {
        $stream->littleEndian = true;
      }

      if ($this->fileGen >= 7) {
        $this->m_Version = $stream->string;
        if ($this->m_Version === '0.0.0' && defined('UNITY_ASSET_VERSION_OVERRIDE')) {
          $this->m_Version = constant('UNITY_ASSET_VERSION_OVERRIDE');
        }
      }
      if ($this->fileGen >= 8) {
        $this->platform = $stream->long;
      }
      if ($this->fileGen >= 13) {
        $this->baseDefinitions = $stream->byte === "\x01";
      }

      switch ($this->platform) {
        case -2:  $this->platformStr = 'Unity Package'; break;
        case 4:   $this->platformStr = 'OSX'; break;
        case 5:   $this->platformStr = 'PC'; break;
        case 6:   $this->platformStr = 'Web'; break;
        case 7:   $this->platformStr = 'Web streamed'; break;
        case 9:   $this->platformStr = 'iOS'; break;
        case 10:  $this->platformStr = 'PS3'; break;
        case 11:  $this->platformStr = 'Xbox 360'; break;
        case 13:  $this->platformStr = 'Android'; break;
        case 16:  $this->platformStr = 'Google NaCl'; break;
        case 19:  $this->platformStr = 'CollabPreview'; break;
        case 21:  $this->platformStr = 'WP8'; break;
        case 25:  $this->platformStr = 'Linux'; break;
        case 29:  $this->platformStr = 'Wii U'; break;
        default:  $this->platformStr = 'Unknown Platform';
      }

      $baseCount = $stream->long;
      for ($i=0; $i<$baseCount; $i++) {
        if ($this->fileGen < 14) {
          throw new Exception('fileGen < 14');
        } else {
          $this->readSerializedType();
        }
      }

      if ($this->fileGen >= 7 && $this->fileGen < 14) {
        $stream->position += 4;
      }
      $assetCount = $stream->long;
      $assetIDfmt = '%0'.strlen(''.$assetCount).'d';
      for($i=0; $i<$assetCount; $i++) {
        if ($this->fileGen >= 14) {
          $stream->alignStream(4);
        }
        $asset = new AssetPreloadData;
        $asset->m_PathID = $this->fileGen<14 ? $stream->long : $stream->longlong;
        $asset->offset = $this->fileGen<22 ? $stream->ulong : $stream->longlong;
        $asset->offset += $dataOffset;
        $asset->size = $stream->long;
        if ($this->fileGen > 15) {
          $index = $stream->long;
          $asset->type1 = $this->classIDs[$index][0];
          $asset->type2 = $this->classIDs[$index][1];
        } else {
          $asset->type1 = $stream->long;
          $asset->type2 = $stream->ushort;
          $stream->position += 2;
        }
        if ($this->fileGen == 15) {
          $stream->byte;
        }
        $asset->typeString = isset(AssetFile::classIDReference[$asset->type2]) ? AssetFile::classIDReference[$asset->type2] : 'Unknown Type ' . $asset->type2;
        $asset->uniqueID = sprintf($assetIDfmt, $i);
        $asset->fullSize = $asset->size;
        $asset->sourceFile = $this;
        $this->preloadTable[$asset->m_PathID] = $asset;
        /*
        should not met this type
        if ($asset->type2 == 141 && $this->fileGen == 6) {
          throw new Exception('old gen file');
        }*/
      }
      $this->buildType = explode('.', preg_replace('/\d/', '', $this->m_Version));
      $this->version = explode('.', preg_replace('/\D/', '.', $this->m_Version));
      if ($this->version[0] ==2 &&$this->version[1]==0&&$this->version[2]==1&&$this->version[3]==7) {
        array_splice($this->version, 0, 4, 2017);
      }

      if ($this->fileGen >= 14) {
        $someCount = $stream->long;
        for ($i=0; $i<$someCount; $i++) {
          $stream->long;
          $stream->alignStream(4);
          $stream->longlong;
        }
      }

      $sharedFileCount = $stream->long;
      for ($i=0; $i<$sharedFileCount; $i++) {
        $shared = new class{};
        $shared->name = $stream->string;
        $stream->position += 20;
        $sharedFileName = $stream->string;
        $shared->fileName = $sharedFileName;
        $shared->index = $i;
        $this->sharedAssetsList[] = $shared;
      }
      $this->valid = true;
    } catch (Exception $e) { }
  }

  private function readSerializedType() {
    $stream = $this->stream;
    $classID = $stream->long;
    if ($this->fileGen > 15) {
      $stream->readData(1);
      if (($type = $stream->readInt16()) >= 0) {
        $type = -1 - $type;
      } else {
        $type = $classID;
      }
      $this->classIDs[] = [$type, $classID];
      if ($classID == 114) {
        $stream->position += 16;
      }
      $classID = $type;
    } else if ($classID < 0) {
      $stream->position += 16;
    }
    $stream->position += 16;
    if ($this->baseDefinitions) {
      $nodeInfoSize = 24;
      if ($this->fileGen >= 19) {
        $nodeInfoSize += 8;
      }
      $varCount = $stream->long;
      $stringSize = $stream->long;
      $stream->position += $varCount * $nodeInfoSize;
      $stringReader = new MemoryStream($stringSize?$stream->readData($stringSize):'');
      $className = '';
      $classVar = [];
      $stream->position -= $varCount * $nodeInfoSize + $stringSize;
      for ($i=0; $i<$varCount; $i++) {
        $stream->readInt16();
        $level = ord($stream->readData(1));
        $stream->readBoolean();

        $varTypeIndex = $stream->readInt16();
        if ($stream->readInt16() == 0) {
          $stringReader->seek($varTypeIndex);
          $varTypeStr = $stringReader->readStringToNull();
        } else {
          $varTypeStr = isset(AssetFile::baseStrings[$varTypeIndex]) ? AssetFile::baseStrings[$varTypeIndex] : $varTypeIndex;
        }

        $varNameIndex = $stream->readInt16();
        if ($stream->readInt16() == 0) {
          $stringReader->seek($varNameIndex);
          $varNameStr = $stringReader->readStringToNull();
        } else {
          $varNameStr = isset(AssetFile::baseStrings[$varNameIndex]) ? AssetFile::baseStrings[$varNameIndex] : $varTypeIndex;
        }

        $size = $stream->long;
        $flag2 = $stream->readInt32() != 0;
        $flag = $stream->long;
        if ($this->fileGen >= 19) {
          $RefTypeHash = $stream->ulonglong;
        }
        if (!$flag2) {
          $className = $varTypeStr . ' ' . $varNameStr;
        } else {
          $classVar[] = array(
            'level'=> $level -1,
            'type'=> $varTypeStr,
            'name'=> $varNameStr,
            'size'=> $size,
            'flag'=> $flag
          );
        }
      }
      unset($stringReader);
      $stream->position += $stringSize;
      $aClass = array(
        'ID'=>$classID,
        'text'=>$className,
        'members'=>$classVar
      );
      //aClass.SubItems.Add(classID.ToString());
      $this->ClassStructures[$classID] = $aClass;

      if ($this->fileGen >= 21) {
        $arrSize = $stream->long;
        $stream->position += $arrSize * 4;
      }
    }
  }

  const baseStrings = array(
    0=>"AABB",
    5=>"AnimationClip",
    19=>"AnimationCurve",
    34=>"AnimationState",
    49=>"Array",
    55=>"Base",
    60=>"BitField",
    69=>"bitset",
    76=>"bool",
    81=>"char",
    86=>"ColorRGBA",
    96=>"Component",
    106=>"data",
    111=>"deque",
    117=>"double",
    124=>"dynamic_array",
    138=>"FastPropertyName",
    155=>"first",
    161=>"float",
    167=>"Font",
    172=>"GameObject",
    183=>"Generic Mono",
    196=>"GradientNEW",
    208=>"GUID",
    213=>"GUIStyle",
    222=>"int",
    226=>"list",
    231=>"long long",
    241=>"map",
    245=>"Matrix4x4f",
    256=>"MdFour",
    263=>"MonoBehaviour",
    277=>"MonoScript",
    288=>"m_ByteSize",
    299=>"m_Curve",
    307=>"m_EditorClassIdentifier",
    331=>"m_EditorHideFlags",
    349=>"m_Enabled",
    359=>"m_ExtensionPtr",
    374=>"m_GameObject",
    387=>"m_Index",
    395=>"m_IsArray",
    405=>"m_IsStatic",
    416=>"m_MetaFlag",
    427=>"m_Name",
    434=>"m_ObjectHideFlags",
    452=>"m_PrefabInternal",
    469=>"m_PrefabParentObject",
    490=>"m_Script",
    499=>"m_StaticEditorFlags",
    519=>"m_Type",
    526=>"m_Version",
    536=>"Object",
    543=>"pair",
    548=>"PPtr<Component>",
    564=>"PPtr<GameObject>",
    581=>"PPtr<Material>",
    596=>"PPtr<MonoBehaviour>",
    616=>"PPtr<MonoScript>",
    633=>"PPtr<Object>",
    646=>"PPtr<Prefab>",
    659=>"PPtr<Sprite>",
    672=>"PPtr<TextAsset>",
    688=>"PPtr<Texture>",
    702=>"PPtr<Texture2D>",
    718=>"PPtr<Transform>",
    734=>"Prefab",
    741=>"Quaternionf",
    753=>"Rectf",
    759=>"RectInt",
    767=>"RectOffset",
    778=>"second",
    785=>"set",
    789=>"short",
    795=>"size",
    800=>"SInt16",
    807=>"SInt32",
    814=>"SInt64",
    821=>"SInt8",
    827=>"staticvector",
    840=>"string",
    847=>"TextAsset",
    857=>"TextMesh",
    866=>"Texture",
    874=>"Texture2D",
    884=>"Transform",
    894=>"TypelessData",
    907=>"UInt16",
    914=>"UInt32",
    921=>"UInt64",
    928=>"UInt8",
    934=>"unsigned int",
    947=>"unsigned long long",
    966=>"unsigned short",
    981=>"vector",
    988=>"Vector2f",
    997=>"Vector3f",
    1006=>"Vector4f",
    1015=>"m_ScriptingClassIdentifier",
    1042=>"Gradient",
    1051=>"Type*"
  );
  const classIDReference = array(
    1=>"GameObject",
    2=>"Component",
    3=>"LevelGameManager",
    4=>"Transform",
    5=>"TimeManager",
    6=>"GlobalGameManager",
    8=>"Behaviour",
    9=>"GameManager",
    11=>"AudioManager",
    12=>"ParticleAnimator",
    13=>"InputManager",
    15=>"EllipsoidParticleEmitter",
    17=>"Pipeline",
    18=>"EditorExtension",
    19=>"Physics2DSettings",
    20=>"Camera",
    21=>"Material",
    23=>"MeshRenderer",
    25=>"Renderer",
    26=>"ParticleRenderer",
    27=>"Texture",
    28=>"Texture2D",
    29=>"SceneSettings",
    30=>"GraphicsSettings",
    33=>"MeshFilter",
    41=>"OcclusionPortal",
    43=>"Mesh",
    45=>"Skybox",
    47=>"QualitySettings",
    48=>"Shader",
    49=>"TextAsset",
    50=>"Rigidbody2D",
    51=>"Physics2DManager",
    53=>"Collider2D",
    54=>"Rigidbody",
    55=>"PhysicsManager",
    56=>"Collider",
    57=>"Joint",
    58=>"CircleCollider2D",
    59=>"HingeJoint",
    60=>"PolygonCollider2D",
    61=>"BoxCollider2D",
    62=>"PhysicsMaterial2D",
    64=>"MeshCollider",
    65=>"BoxCollider",
    66=>"SpriteCollider2D",
    68=>"EdgeCollider2D",
    70=>"CapsuleCollider2D",
    72=>"ComputeShader",
    74=>"AnimationClip",
    75=>"ConstantForce",
    76=>"WorldParticleCollider",
    78=>"TagManager",
    81=>"AudioListener",
    82=>"AudioSource",
    83=>"AudioClip",
    84=>"RenderTexture",
    86=>"CustomRenderTexture",
    87=>"MeshParticleEmitter",
    88=>"ParticleEmitter",
    89=>"Cubemap",
    90=>"Avatar",
    91=>"AnimatorController",
    92=>"GUILayer",
    93=>"RuntimeAnimatorController",
    94=>"ScriptMapper",
    95=>"Animator",
    96=>"TrailRenderer",
    98=>"DelayedCallManager",
    102=>"TextMesh",
    104=>"RenderSettings",
    108=>"Light",
    109=>"CGProgram",
    110=>"BaseAnimationTrack",
    111=>"Animation",
    114=>"MonoBehaviour",
    115=>"MonoScript",
    116=>"MonoManager",
    117=>"Texture3D",
    118=>"NewAnimationTrack",
    119=>"Projector",
    120=>"LineRenderer",
    121=>"Flare",
    122=>"Halo",
    123=>"LensFlare",
    124=>"FlareLayer",
    125=>"HaloLayer",
    126=>"NavMeshAreas",
    127=>"HaloManager",
    128=>"Font",
    129=>"PlayerSettings",
    130=>"NamedObject",
    131=>"GUITexture",
    132=>"GUIText",
    133=>"GUIElement",
    134=>"PhysicMaterial",
    135=>"SphereCollider",
    136=>"CapsuleCollider",
    137=>"SkinnedMeshRenderer",
    138=>"FixedJoint",
    140=>"RaycastCollider",
    141=>"BuildSettings",
    142=>"AssetBundle",
    143=>"CharacterController",
    144=>"CharacterJoint",
    145=>"SpringJoint",
    146=>"WheelCollider",
    147=>"ResourceManager",
    148=>"NetworkView",
    149=>"NetworkManager",
    150=>"PreloadData",
    152=>"MovieTexture",
    153=>"ConfigurableJoint",
    154=>"TerrainCollider",
    155=>"MasterServerInterface",
    156=>"TerrainData",
    157=>"LightmapSettings",
    158=>"WebCamTexture",
    159=>"EditorSettings",
    160=>"InteractiveCloth",
    161=>"ClothRenderer",
    162=>"EditorUserSettings",
    163=>"SkinnedCloth",
    164=>"AudioReverbFilter",
    165=>"AudioHighPassFilter",
    166=>"AudioChorusFilter",
    167=>"AudioReverbZone",
    168=>"AudioEchoFilter",
    169=>"AudioLowPassFilter",
    170=>"AudioDistortionFilter",
    171=>"SparseTexture",
    180=>"AudioBehaviour",
    181=>"AudioFilter",
    182=>"WindZone",
    183=>"Cloth",
    184=>"SubstanceArchive",
    185=>"ProceduralMaterial",
    186=>"ProceduralTexture",
    187=>"Texture2DArray",
    188=>"CubemapArray",
    191=>"OffMeshLink",
    192=>"OcclusionArea",
    193=>"Tree",
    194=>"NavMeshObsolete",
    195=>"NavMeshAgent",
    196=>"NavMeshSettings",
    197=>"LightProbesLegacy",
    198=>"ParticleSystem",
    199=>"ParticleSystemRenderer",
    200=>"ShaderVariantCollection",
    205=>"LODGroup",
    206=>"BlendTree",
    207=>"Motion",
    208=>"NavMeshObstacle",
    210=>"TerrainInstance",
    212=>"SpriteRenderer",
    213=>"Sprite",
    214=>"CachedSpriteAtlas",
    215=>"ReflectionProbe",
    216=>"ReflectionProbes",
    218=>"Terrain",
    220=>"LightProbeGroup",
    221=>"AnimatorOverrideController",
    222=>"CanvasRenderer",
    223=>"Canvas",
    224=>"RectTransform",
    225=>"CanvasGroup",
    226=>"BillboardAsset",
    227=>"BillboardRenderer",
    228=>"SpeedTreeWindAsset",
    229=>"AnchoredJoint2D",
    230=>"Joint2D",
    231=>"SpringJoint2D",
    232=>"DistanceJoint2D",
    233=>"HingeJoint2D",
    234=>"SliderJoint2D",
    235=>"WheelJoint2D",
    236=>"ClusterInputManager",
    237=>"BaseVideoTexture",
    238=>"NavMeshData",
    240=>"AudioMixer",
    241=>"AudioMixerController",
    243=>"AudioMixerGroupController",
    244=>"AudioMixerEffectController",
    245=>"AudioMixerSnapshotController",
    246=>"PhysicsUpdateBehaviour2D",
    247=>"ConstantForce2D",
    248=>"Effector2D",
    249=>"AreaEffector2D",
    250=>"PointEffector2D",
    251=>"PlatformEffector2D",
    252=>"SurfaceEffector2D",
    253=>"BuoyancyEffector2D",
    254=>"RelativeJoint2D",
    255=>"FixedJoint2D",
    256=>"FrictionJoint2D",
    257=>"TargetJoint2D",
    258=>"LightProbes",
    259=>"LightProbeProxyVolume",
    271=>"SampleClip",
    272=>"AudioMixerSnapshot",
    273=>"AudioMixerGroup",
    280=>"NScreenBridge",
    290=>"AssetBundleManifest",
    292=>"UnityAdsManager",
    300=>"RuntimeInitializeOnLoadManager",
    301=>"CloudWebServicesManager",
    303=>"UnityAnalyticsManager",
    304=>"CrashReportManager",
    305=>"PerformanceReportingManager",
    310=>"UnityConnectSettings",
    319=>"AvatarMask",
    328=>"VideoPlayer",
    329=>"VideoClip",
    363=>"OcclusionCullingData",
    1001=>"Prefab",
    1002=>"EditorExtensionImpl",
    1003=>"AssetImporter",
    1004=>"AssetDatabase",
    1005=>"Mesh3DSImporter",
    1006=>"TextureImporter",
    1007=>"ShaderImporter",
    1008=>"ComputeShaderImporter",
    1011=>"AvatarMask",
    1020=>"AudioImporter",
    1026=>"HierarchyState",
    1027=>"GUIDSerializer",
    1028=>"AssetMetaData",
    1029=>"DefaultAsset",
    1030=>"DefaultImporter",
    1031=>"TextScriptImporter",
    1032=>"SceneAsset",
    1034=>"NativeFormatImporter",
    1035=>"MonoImporter",
    1037=>"AssetServerCache",
    1038=>"LibraryAssetImporter",
    1040=>"ModelImporter",
    1041=>"FBXImporter",
    1042=>"TrueTypeFontImporter",
    1044=>"MovieImporter",
    1045=>"EditorBuildSettings",
    1046=>"DDSImporter",
    1048=>"InspectorExpandedState",
    1049=>"AnnotationManager",
    1050=>"PluginImporter",
    1051=>"EditorUserBuildSettings",
    1052=>"PVRImporter",
    1053=>"ASTCImporter",
    1054=>"KTXImporter",
    1101=>"AnimatorStateTransition",
    1102=>"AnimatorState",
    1105=>"HumanTemplate",
    1107=>"AnimatorStateMachine",
    1108=>"PreviewAssetType",
    1109=>"AnimatorTransition",
    1110=>"SpeedTreeImporter",
    1111=>"AnimatorTransitionBase",
    1112=>"SubstanceImporter",
    1113=>"LightmapParameters",
    1120=>"LightmapSnapshot",
    367388927=>"SubDerived",
    334799969=>"SiblingDerived",
    687078895=>"SpriteAtlas",
    1091556383=>"Derived",
    1480428607=>"LowerResBlitTexture",
    1571458007=>"RenderPassAttachment"
  );
}

class AssetPreloadData {
  public $m_PathID;
  public $offset;
  public $size;
  public $type1;
  public $type2;
  public $typeString;
  public $fullSize;
  public $infoText;
  public $extension;
  public $sourceFile;
  public $uniqueID;
}

function swapRGB($format, $data) {
  if ($format == TextureFormat::RGB24) {
    return preg_replace('/([\x00-\xff])([\x00-\xff])([\x00-\xff])/',"$3$2$1\xff", $data);
  } else if ($format == TextureFormat::RGBA32) {
    return preg_replace('/([\x00-\xff])([\x00-\xff])([\x00-\xff])([\x00-\xff])/','$3$2$1$4', $data);
  } else if ($format == TextureFormat::ARGB32) {
    return preg_replace('/([\x00-\xff])([\x00-\xff])([\x00-\xff])([\x00-\xff])/','$4$3$2$1', $data);
  } else if ($format == TextureFormat::RGB565) {
    for ($i=0,$len=strlen($data),$out=str_repeat("\xff",$len*2); $i<$len; $i+=2) {
      $pxl = ord($data[$i])+ord($data[$i+1])*256;
      $r = ($pxl & 0xf800) >> 8;
      $out[$i*2+2] = chr($r | (($r & 0xe0) >> 5));
      $g = ($pxl & 0x7e0) >> 3;
      $out[$i*2+1] = chr($g | (($g & 0xb0) >> 6));
      $b = ($pxl & 0x1f) << 3;
      $out[$i*2  ] = chr($b | (($b & 0xe0) >> 5));
    }
    return $out;
  } else if ($format == TextureFormat::RGBA4444) {
    for ($i=0,$len=strlen($data),$out=str_repeat("\xff",$len*2); $i<$len; $i+=2) {
      $pxl = ord($data[$i])+ord($data[$i+1])*256;
      $r = ($pxl & 0xf000) >> 8;
      $out[$i*2+2] = chr($r | ($r >> 4));
      $g = ($pxl &  0xf00) >> 8;
      $out[$i*2+1] = chr($g | ($g >> 4));
      $b = ($pxl &   0xf0);
      $out[$i*2  ] = chr($b | ($b >> 4));
      $a = ($pxl &    0xf);
      $out[$i*2+3] = chr($a | ($a << 4));
    }
    return $out;
  }
}

class Texture2D {
  public $dwFlags = 4103;
  public $dwCaps = 4096;
  public $mipMap = false;

  function __construct($preloadData, $readSwitch) {
    $sourceFile = $preloadData->sourceFile;
    $stream = $sourceFile->stream;
    $stream->position = $preloadData->offset;
    if ($sourceFile->platform == -2) {
      throw new Exception('no support');
    }

    $struct = ClassStructHelper::OrganizeStruct(ClassStructHelper::DeserializeStruct($stream, $preloadData->sourceFile->ClassStructures[28]['members']));
    $this->name = $struct['m_Name'];
    $this->width = $struct['m_Width'];
    $this->height = $struct['m_Height'];
    $this->completeImageSize = $struct['m_CompleteImageSize'];
    $this->textureFormat = $struct['m_TextureFormat'];
    $this->imageCount = $struct['m_ImageCount'];
    $this->textureDimension = $struct['m_TextureDimension'];
    $this->imageData = $struct['image data'];
    $this->imageDataSize = strlen($this->imageData);
    if (isset($struct['m_StreamData'])) {
      $this->offset = $struct['m_StreamData']['offset'];
      $this->size = $struct['m_StreamData']['size'];
      $this->imageDataSize = $this->size;
      $this->path = $struct['m_StreamData']['path'];
    }
    $this->textureFormatStr = TextureFormat::map[$this->textureFormat];

    if ($readSwitch) {
      if (isset($this->path) && !empty($this->path)) {
        $this->path = dirname($sourceFile->filePath) . '/'. str_replace('archive:/','', $this->path);
        if (file_exists($this->path) || file_exists($this->path = dirname($sourceFile->filePath) . '/'. pathinfo($this->path, PATHINFO_BASENAME))) {
          $reader = new FileStream($this->path);
          $reader->position = $this->offset;
          $this->imageData = $reader->readData($this->imageDataSize);
          unset($reader);
        } else {
          throw new Exception('require resource not found: '. $this->path);
        }
      } else {
        $this->imageData = $stream->readData($this->imageDataSize);
      }
      
			switch ($this->textureFormat) {
        case TextureFormat::ASTC_RGB_4x4:
        case TextureFormat::ASTC_RGB_5x5:
        case TextureFormat::ASTC_RGB_6x6:
        case TextureFormat::ASTC_RGB_8x8:
        case TextureFormat::ASTC_RGB_10x10:
        case TextureFormat::ASTC_RGB_12x12:
        case TextureFormat::ASTC_RGBA_4x4:
        case TextureFormat::ASTC_RGBA_5x5:
        case TextureFormat::ASTC_RGBA_6x6:
        case TextureFormat::ASTC_RGBA_8x8:
        case TextureFormat::ASTC_RGBA_10x10:
        case TextureFormat::ASTC_RGBA_12x12:
          $this->outputMethod = 'astcenc';
          $this->transcodeFormat = 'tga';
          // use astcenc
          break;
        case TextureFormat::RGBA32:
        case TextureFormat::ARGB32:
          $this->outputMethod = 'bmp';
          $this->transcodeFormat = 'bmp';
          $this->bitDepth = 32;
          break;
        case TextureFormat::RGB24:
          $this->outputMethod = 'bmp';
          $this->transcodeFormat = 'bmp';
          $this->bitDepth = 24;
          break;
        case TextureFormat::RGBA4444:
        case TextureFormat::RGB565:
          $this->outputMethod = 'bmp';
          $this->transcodeFormat = 'bmp';
          $this->bitDepth = 16;
          break;
        case TextureFormat::PVRTC_RGB4:
          $this->outputMethod = 'pvr';
          $this->transcodeFormat = 'bmp';
          // use PVRTexToolCLI
          break;
        default:
        throw new Exception('not implemented: '.$this->textureFormat.' '.$this->textureFormatStr);
      }
    }
  }

  function exportTo($saveTo, $format = 'png', $extraEncodeParam = '') {
    if ($this->outputMethod == 'astcenc') {
      fclose(fopen('output.astc','wb'));
      $astc = new FileStream('output.astc');
      $astc->write(hex2bin('13ABA15C'));
      $astc->write(array(
        48 => chr(4).chr(4),
        49 => chr(5).chr(5),
        50 => chr(6).chr(6),
        51 => chr(8).chr(8),
        52 => chr(10).chr(10),
        53 => chr(12).chr(12),
        54 => chr(4).chr(4),
        55 => chr(5).chr(5),
        56 => chr(6).chr(6),
        57 => chr(8).chr(8),
        58 => chr(10).chr(10),
        59 => chr(12).chr(12),
      )[$this->textureFormat].chr(1));
      $astc->write(substr(pack('V', $this->width), 0, 3));
      $astc->write(substr(pack('V', $this->height), 0, 3));
      $astc->write(hex2bin('010000'));
      $astc->write($this->imageData);
      unset($astc);
      exec('astcenc-4.5 -dl output.astc output.tga -yflip -silent');
      unlink('output.astc');
      $transcodeFile = 'output.tga';
    } else if ($this->outputMethod == 'bmp') {
      $width = $this->width;
      $height = $this->height;
      $bmp = new MemoryStream('BM');
      $bmp->write(pack('V', strlen($this->imageData)*32/$this->bitDepth + 54));
      $bmp->write(hex2bin('0000000036000000'));

      // DIB header
      $bmp->write(hex2bin('28000000')); //header size
      $bmp->write(pack('V', $width));
      $bmp->write(pack('V', $height));
      $bmp->write(hex2bin('0100'));     //channel
      $bmp->write(hex2bin('2000'));     //bitdepth
      $bmp->write(hex2bin('00000000')); //compression
      $bmp->write(pack('V', strlen($this->imageData)*32/$this->bitDepth));
      $bmp->write(hex2bin('00000000000000000000000000000000')); //hoz resolution + ver resolution + color num + important color num    
      $bmp->write(
        swapRGB($this->textureFormat, $this->imageData)
      );
      $bmp->position = 0;
      file_put_contents('output.bmp', $bmp->readData($bmp->size));
      unset($bmp);
      $transcodeFile = 'output.bmp';
    } else if ($this->outputMethod == 'pvr') {
      // https://github.com/esterTion/pvrtc-decompress/
      file_put_contents('output.pvr', $this->imageData);
      exec('pvr-decompress output.pvr output.bmp '.implode(' ', [$this->width, $this->height, [
        TextureFormat::PVRTC_RGB2 => 1,
        TextureFormat::PVRTC_RGB4 => 0,
        TextureFormat::PVRTC_RGBA2 => 1,
        TextureFormat::PVRTC_RGBA4 => 0,
      ][$this->textureFormat]]));
      unlink('output.pvr');
      $transcodeFile = 'output.bmp';
      //throw new Exception('wip');
    } else {
      throw new Exception('not supported');
    }

    if ($this->transcodeFormat != $format) {
      exec('ffmpeg -hide_banner -loglevel quiet -y -i '.$transcodeFile.' '.$extraEncodeParam.' output.'.$format);
      unlink($transcodeFile);
    }
    checkAndMoveFile('output.'.$format, $saveTo.'.'.$format);
  }
}
class TextAsset {
  function __construct($preloadData, $readSwitch) {
    $sourceFile = $preloadData->sourceFile;
    $stream = $sourceFile->stream;
    $stream->position = $preloadData->offset;
    if ($sourceFile->platform == -2) {
      $stream->ulong;
      throw new Exception('platform -2');
    }
    $this->name = $stream->readAlignedString($stream->long);
    if ($readSwitch) {
      $this->data = $stream->readData($stream->long);
    }
  }
}

class TextureFormat {
  const Alpha8 = 1;
  const ARGB4444 = 2;
  const RGB24 = 3;
  const RGBA32 = 4;
  const ARGB32 = 5;
  const RGB565 = 7;
  const R16 = 9;
  const DXT1 = 10;
  const DXT5 = 12;
  const RGBA4444 = 13;
  const BGRA32 = 14;
  const RHalf = 15;
  const RGHalf = 16;
  const RGBAHalf = 17;
  const RFloat = 18;
  const RGFloat = 19;
  const RGBAFloat = 20;
  const YUY2 = 21;
  const RGB9e5Float = 22;
  const BC4 = 26;
  const BC5 = 27;
  const BC6H = 24;
  const BC7 = 25;
  const DXT1Crunched = 28;
  const DXT5Crunched = 29;
  const PVRTC_RGB2 = 30;
  const PVRTC_RGBA2 = 31;
  const PVRTC_RGB4 = 32;
  const PVRTC_RGBA4 = 33;
  const ETC_RGB4 = 34;
  const ATC_RGB4 = 35;
  const ATC_RGBA8 = 36;
  const EAC_R = 41;
  const EAC_R_SIGNED = 42;
  const EAC_RG = 43;
  const EAC_RG_SIGNED = 44;
  const ETC2_RGB = 45;
  const ETC2_RGBA1 = 46;
  const ETC2_RGBA8 = 47;
  const ASTC_RGB_4x4 = 48;
  const ASTC_RGB_5x5 = 49;
  const ASTC_RGB_6x6 = 50;
  const ASTC_RGB_8x8 = 51;
  const ASTC_RGB_10x10 = 52;
  const ASTC_RGB_12x12 = 53;
  const ASTC_RGBA_4x4 = 54;
  const ASTC_RGBA_5x5 = 55;
  const ASTC_RGBA_6x6 = 56;
  const ASTC_RGBA_8x8 = 57;
  const ASTC_RGBA_10x10 = 58;
  const ASTC_RGBA_12x12 = 59;
  const ETC_RGB4_3DS = 60;
  const ETC_RGBA8_3DS = 61;
  const RG16 = 62;
  const R8 = 63;
  const ETC_RGB4Crunched = 64;
  const ETC2_RGBA8Crunched = 65;

  const map = array(
     1 => 'Alpha8',
     2 => 'ARGB4444',
     3 => 'RGB24',
     4 => 'RGBA32',
     5 => 'ARGB32',
     7 => 'RGB565',
     9 => 'R16',
    10 => 'DXT1',
    12 => 'DXT5',
    13 => 'RGBA4444',
    14 => 'BGRA32',
    15 => 'RHalf',
    16 => 'RGHalf',
    17 => 'RGBAHalf',
    18 => 'RFloat',
    19 => 'RGFloat',
    20 => 'RGBAFloat',
    21 => 'YUY2',
    22 => 'RGB9e5Float',
    26 => 'BC4',
    27 => 'BC5',
    24 => 'BC6H',
    25 => 'BC7',
    28 => 'DXT1Crunched',
    29 => 'DXT5Crunched',
    30 => 'PVRTC_RGB2',
    31 => 'PVRTC_RGBA2',
    32 => 'PVRTC_RGB4',
    33 => 'PVRTC_RGBA4',
    34 => 'ETC_RGB4',
    35 => 'ATC_RGB4',
    36 => 'ATC_RGBA8',
    41 => 'EAC_R',
    42 => 'EAC_R_SIGNED',
    43 => 'EAC_RG',
    44 => 'EAC_RG_SIGNED',
    45 => 'ETC2_RGB',
    46 => 'ETC2_RGBA1',
    47 => 'ETC2_RGBA8',
    48 => 'ASTC_RGB_4x4',
    49 => 'ASTC_RGB_5x5',
    50 => 'ASTC_RGB_6x6',
    51 => 'ASTC_RGB_8x8',
    52 => 'ASTC_RGB_10x10',
    53 => 'ASTC_RGB_12x12',
    54 => 'ASTC_RGBA_4x4',
    55 => 'ASTC_RGBA_5x5',
    56 => 'ASTC_RGBA_6x6',
    57 => 'ASTC_RGBA_8x8',
    58 => 'ASTC_RGBA_10x10',
    59 => 'ASTC_RGBA_12x12',
    60 => 'ETC_RGB4_3DS',
    61 => 'ETC_RGBA8_3DS',
    62 => 'RG16',
    63 => 'R8',
    64 => 'ETC_RGB4Crunched',
    65 => 'ETC2_RGBA8Crunched'
  );
}

function RoundSigDigs($number, $sigdigs) {
  $ori = $number;
  $power = $ori == 0 ? 0 : ceil(log10(abs($number)));
  $number *= pow(10, -$power);
  return round($number, $sigdigs) * pow(10, $power);
}
class ClassStructHelper {
  static function DeserializeStruct(Stream $stream, $members) {
    $output = [];
    for ($i=0; $i<count($members); $i++) {
      $member = &$members[$i];
      $name = $member['name'];
      $type = $member['type'];
      $level = $member['level'];
      $align = ($member['flag'] & 16384) != 0;
      $value = [
        'level' => $level,
        'name' => $name,
        'type' => $type,
        'value' => NULL
      ];
      if (isset($member['alignBefore'])) {
        $stream->alignStream(4);
      }
      switch ($type) {
        case 'SInt8': {
          $value['value'] = ord($stream->byte);
          if ($value['value'] >= 128) $value['value'] = $value['value'] - 256;
          break;
        }
        case 'UInt8': {
          $value['value'] = ord($stream->byte);
          break;
        }
        case 'SInt16': case 'short': {
          $value['value'] = $stream->short;
          break;
        }
        case 'UInt16': case 'unsigned short': {
          $value['value'] = $stream->ushort;
          break;
        }
        case 'SInt32': case 'int': {
          $value['value'] = $stream->long;
          break;
        }
        case 'UInt32': case 'unsigned int': case 'Type*': {
          $value['value'] = $stream->ulong;
          break;
        }
        case 'SInt64': case 'long long': {
          $value['value'] = $stream->longlong;
          break;
        }
        case 'UInt64': case 'unsigned long long': {
          $value['value'] = $stream->ulonglong;
          break;
        }
        case 'float': {
          $value['value'] = RoundSigDigs($stream->float, 7);
          break;
        }
        case 'double': {
          $value['value'] = RoundSigDigs($stream->double, 14);
          break;
        }
        case 'bool': {
          $value['value'] = $stream->bool;
          break;
        }
        case 'string': {
          $value['value'] = $stream->readAlignedString($stream->long);
          $i += 3;
          break;
        }
        case 'Array': {
          $align = false;
          if (($members[$i - 1]['flag'] & 16384) != 0) $align = true;
          $value['size'] = $stream->long;
          $arr = ClassStructHelper::ReadArray($members, $level, $i);
          $dataArr = [];
          for ($j=0; $j<$value['size']; $j++) {
            $dataArr[] = ClassStructHelper::DeserializeStruct($stream, $arr);
          }
          $alignAfterArray = ($member['flag'] & 16384) != 0;
          if ($alignAfterArray) $stream->alignStream(4);
          $i += count($arr) + 1;
          $value['value'] = $dataArr;
          break;
        }
        case 'TypelessData': {
          $size2 = $stream->long;
          $value['value'] = $stream->readData($size2);
          $i+=2;
          break;
        }
        default: {
          if ($align) {
            $align = false;
            ClassStructHelper::SetAlignBefore($members, $level, $i + 1);
          }
        }
      } // switch
      if ($align) {
        $stream->alignStream(4);
      }
      $output[] = $value;
    }
    return $output;
  }
  static function ReadArray($members, $level, $index) {
    $member2 = [];
    for ($i = $index + 2; $i<count($members); $i++) {
      $member3 = $members[$i];
      if ($member3['level'] <= $level) return $member2;
      $member2[] = $member3;
    }
    return $member2;
  }
  static function SetAlignBefore($members, $level, $index) {
    for ($i=$index; $i<count($members); $i++) {
      $member = $members[$i];
      if ($member['level'] <= $level) {
        $member['alignBefore'] = true;
        return;
      }
    }
  }
  static function OrganizeStruct($deserializedStruct, $level = 0) {
    $currentLevel = [];
    $subLevels = [];
    $lastMember = NULL;
    
    foreach ($deserializedStruct as $member) {
      if ($member['level'] == $level) {
        if (!empty($subLevels)) {
          $lastMember[0][$lastMember[1]] = ClassStructHelper::OrganizeStruct($subLevels, $level + 1);
          $subLevels = [];
        }
        $currentLevel[$member['name']] = $member['value'];
        $lastMember = [&$currentLevel, $member['name']];
      } else if ($member['level'] > $level) {
        if ($member['type'] === 'Array' && !isset($member['arrProcessed'])) {
          $member['arrProcessed'] = true;
          $arrLevel = $member['level'];
          $member['value'] = array_map(function ($i)use($arrLevel){return ClassStructHelper::OrganizeStruct($i, $arrLevel + 1);}, $member['value']);
        }
        $subLevels[] = $member;
      }
    }
    if (!empty($subLevels)) {
      $lastMember[0][$lastMember[1]] = ClassStructHelper::OrganizeStruct($subLevels, $level + 1);
    }
    $keys = array_keys($currentLevel);
    if (count($keys) == 1 && $keys[0] == 'Array') {
      $currentLevel = $currentLevel['Array'];
    }
    return $currentLevel;
  }
}
