<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-4-11
 * Time: 下午3:30
 */

$xml = simplexml_load_file(__DIR__ . "/proto.xml");
if (!is_dir(__DIR__ . "/build_php")) {
    mkdir(__DIR__ . "/build_php");
}
//处理controller
$rpcTemplate = file_get_contents(__DIR__ . "/template_php/RPCTemplate.proto");
$rpcTemplateResult = getHandelStr($rpcTemplate);
$rpc = $xml->controller;
$regist = [];
foreach ($rpc as $oneclass) {
    $class1 = $oneclass["name"];
    foreach ($oneclass as $one) {
        $class2 = $one["name"];
        $cmd1 = (int)$oneclass['cmd'];
        $cmd2 = (int)$one['cmd'];
        $cmd = $cmd1 * 100 + $cmd2;
        $rep = (string)$one['rep'];
        $req = (string)$one['req'];
        $des = (string)$one['des'];
        $data = $rpcTemplateResult;
        $use = [];
        $data = handelVar($data,$req,$req,"","","req");
        $data = handelVar($data,$rep,$rep,"","","rep");
        $use_req = getUser($req);
        if(!empty($use_req)){
            $use[$use_req] = $use_req;
        }
        $use_rep = getUser($rep);
        if(!empty($use_rep)){
            $use[$use_rep] = $use_rep;
        }
        if(!empty($use)) {
            $use_str = implode("\n", $use);
            $data = str_replace("%use", $use_str, $data);
        }else{
            $data = str_replace("%use", "", $data);
        }

        $data = str_replace("%class1", $class1, $data);
        $data = str_replace("%class2", $class2, $data);
        $data = str_replace("%cmd", $cmd, $data);
        //$data = str_replace("%rep", $rep, $data);
        //$data = str_replace("%req", $req, $data);
        $data = str_replace("%class_des", $des, $data);
        if (!is_dir(__DIR__ . "/build_php/rpc")) {
            mkdir(__DIR__ . "/build_php/rpc");
        }
        $dir = __DIR__ . "/build_php/rpc/$class1" . "_" . $class2 . "_" . "$cmd.php";
        file_put_contents($dir, $data);
        if (array_key_exists($cmd, $regist)) {
            throw new Exception("cmd重复");
        }
        $regist[$cmd] = [$class1, $class2];
    }
}

//处理regist
$registTemplate = file_get_contents(__DIR__ . "/template_php/ProtoRegistTemplate.proto");
$registTemplateResult = getHandelStr($registTemplate);
$varhandels = handelFindVar($registTemplate);
$class_data = $registTemplateResult;
foreach ($varhandels as $varhandel => $str) {
    $arr = explode("[[$varhandel]]", $class_data);
    $arr = explode("\n", $arr[0]);
    $trim = $arr[count($arr) - 1];
    $result = "";
    foreach ($regist as $cmd => $arr) {
        $class1 = $arr[0];
        $class2 = $arr[1];
        $data = str_replace("%class1", $class1, $str);
        $data = str_replace("%class2", $class2, $data);
        $data = str_replace("%cmd", $cmd, $data);
        $result = $result . $trim . $data . "\n";
    }
    $class_data = str_replace("[[$varhandel]]", trim($result), $class_data);
}
$dir = __DIR__ . "/build_php/ProtoRegister.php";
file_put_contents($dir, $class_data);

//处理IController
$icontrollerTemplate = file_get_contents(__DIR__ . "/template_php/IControllerTemplate.proto");
$icontrollerTemplateResult = getHandelStr($icontrollerTemplate);
$varhandels = handelFindVar($icontrollerTemplate);
$class_data = $icontrollerTemplateResult;
foreach ($rpc as $oneclass) {
    $class1 = $oneclass["name"];
    $class_data = str_replace("%class1", $class1, $icontrollerTemplateResult);
    foreach ($varhandels as $varhandel => $str) {
        $arr = explode("[[$varhandel]]", $class_data);
        $arr = explode("\n", $arr[0]);
        $trim = $arr[count($arr) - 1];
        $result = "";
        foreach ($oneclass as $one) {
            $class2 = $one["name"];
            $cmd1 = (int)$oneclass['cmd'];
            $cmd2 = (int)$one['cmd'];
            $cmd = $cmd1 * 100 + $cmd2;
            $rep = (string)$one['rep'];
            $req = (string)$one['req'];
            $des = (string)$one['des'];
            $data = str_replace("%class1", $class1, $str);
            $data = str_replace("%class2", $class2, $data);
            $data = str_replace("%cmd", $cmd, $data);
            $data = str_replace("%rep", $rep, $data);
            $data = str_replace("%req", $req, $data);
            $data = str_replace("%class_des", $des, $data);
            $result = $result . $trim . $data . "\n";
        }
        $class_data = str_replace("[[$varhandel]]", trim($result), $class_data);
    }
    if (!is_dir(__DIR__ . "/build_php/ic")) {
        mkdir(__DIR__ . "/build_php/ic");
    }
    $dir = __DIR__ . "/build_php/ic/I$class1.php";
    file_put_contents($dir, $class_data);
}

//处理struct
$structTemplate = file_get_contents(__DIR__ . "/template_php/StructTemplate.proto");
$structTemplateResult = getHandelStr($structTemplate);
$struct = $xml->struct;
$varhandels = handelFindVar($structTemplate);
foreach ($struct as $onestruct) {
    $class = (string)$onestruct["class"];
    $extends = (string)$onestruct['extends'];
    $class_des = (string)$onestruct['des'];
    $class_data = handelClass($structTemplateResult, $class, $class_des, $extends);
    foreach ($varhandels as $varhandel => $str) {
        $arr = explode("[[$varhandel]]", $class_data);
        $arr = explode("\n", $arr[0]);
        $trim = $arr[count($arr) - 1];
        $result = "";
        foreach ($onestruct as $onevar) {
            $var = (string)$onevar["name"];
            $var_type = (string)$onevar["type"];
            $var_des = (string)$onevar['des'];
            $var_value = (string)$onevar['value'];
            $data = handelVar($str, $var, $var_type, $var_des, $var_value);
            $result = $result . $trim . $data . "\n";
        }
        $class_data = str_replace("[[$varhandel]]", trim($result), $class_data);
    }
    if (!is_dir(__DIR__ . "/build_php/struct")) {
        mkdir(__DIR__ . "/build_php/struct");
    }
    $dir = __DIR__ . "/build_php/struct/$class.php";
    file_put_contents($dir, $class_data);
}

//处理protobuild
$protoBuildTemplate = file_get_contents(__DIR__ . "/template_php/ProtoBuildTemplate.proto");
$dir = __DIR__ . "/build_php/ProtoBuild.php";
file_put_contents($dir, $protoBuildTemplate);
//处理IRpc
$protoBuildTemplate = file_get_contents(__DIR__ . "/template_php/IRPCTemplate.proto");
$dir = __DIR__ . "/build_php/rpc/IRpc.php";
file_put_contents($dir, $protoBuildTemplate);
//处理marco
$marcoTemplate = file_get_contents(__DIR__ . "/template_php/MarcoTemplate.proto");
$marcoTemplateResult = getHandelStr($marcoTemplate);
$varhandels = handelFindVar($marcoTemplate);
$class_data = "";
$marco = $xml->marco;
foreach ($varhandels as $varhandel => $str) {
    $arr = explode("[[$varhandel]]", $marcoTemplate);
    $arr = explode("\n", $arr[0]);
    $trim = $arr[count($arr) - 1];
    $result = "";
    foreach ($marco as $var => $onevar) {
        $var = (string)$onevar["name"];
        $var_type = (string)$onevar["type"];
        $var_des = (string)$onevar['des'];
        $var_value = (string)$onevar['value'];
        $data = handelVar($str, $var, $var_type, $var_des, $var_value);
        $result = $result . $trim . $data . "\n";
    }
    $class_data = str_replace("[[$varhandel]]", trim($result), $marcoTemplateResult);
}
$dir = __DIR__ . "/build_php/ProtoMarco.php";
file_put_contents($dir, $class_data);


function handelFindVar($data)
{
    $result = [];
    $match = explode("[[[", $data);
    unset($match[0]);
    foreach ($match as $value) {
        $temp = explode("]]]", $value);
        $result[$temp[0]] = $temp[1];
    }
    return $result;
}

function handelClass($data, $class, $class_des, $extends)
{
    $data = str_replace("%class_des", $class_des, $data);
    if (!empty($extends)) {
        $data = str_replace("%extends", "extends $extends", $data);
    } else {
        $data = str_replace("%extends", "", $data);
    }
    $data = str_replace("%class", $class, $data);
    return $data;
}
function getUser($var_type)
{
    $use = "";
    if (strpos($var_type, "[]")) {
        $var_type = str_replace("[]", "", $var_type);
    }
    switch ($var_type) {
        case "int":
        case "string":
        case "bool":
            break;
        default:
            $use = "use proto\\struct\\".$var_type.";";
    }
   return $use;
}
function handelVar($data, $var, $var_type, $var_des, $var_value,$prefix='')
{
    $var_type_des = $var_type;
    $var_type_class = "";
    //数组
    if (strpos($var_type, "[]")) {
        $var_type = str_replace("[]", "", $var_type);
        $var_type_build = $var_type . "_array";
        $var_type_class = $var_type;
        $var_type = "array";
    } else {
        $var_type_build = $var_type;
    }
    $data = str_replace("%{$prefix}var_type_build", $var_type_build, $data);
    $data = str_replace("%{$prefix}var_type_des", $var_type_des, $data);
    $data = str_replace("%{$prefix}var_type", $var_type, $data);
    $data = str_replace("%{$prefix}var_des", $var_des, $data);
    switch ($var_type) {
        case "int":
            $var_value = (int)$var_value;
            break;
        default:
            $var_value = "'$var_value'";
    }
    $data = str_replace("%{$prefix}var_value", $var_value, $data);
    $data = str_replace("%{$prefix}var", $var, $data);
    switch ($var_type) {
        case "int":
        case "string":
        case "bool":
            $data = str_replace("%{$prefix}builder", "ProtoBuild", $data);
            break;
        case "array":
            if ($var_type_class=="int"||$var_type_class=="string"||$var_type_class=="bool"||empty($var_type_class)) {
                $data = str_replace("%{$prefix}builder", "ProtoBuild", $data);
            } else {
                $data = str_replace("%{$prefix}builder", $var_type_class, $data);
            }
            break;
        default:
            $data = str_replace("%{$prefix}builder", $var_type, $data);
    }
    return trim($data);
}

function getHandelStr($str)
{
    $reusult = explode("[[[", $str);
    return $reusult[0];
}