<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-4-11
 * Time: 下午3:30
 */

$xml = simplexml_load_file(__DIR__ . "/proto.xml");
if (!is_dir(__DIR__ . "/build_js")) {
    mkdir(__DIR__ . "/build_js");
}
$out_data = "";
//处理rpc
$rpcTemplate = file_get_contents(__DIR__ . "/template_js/RPCTemplate.proto");
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
        $data = handelVar($data,$req,$req,"","","req");
        $data = handelVar($data,$rep,$rep,"","","rep");
        $data = str_replace("%class1", $class1, $data);
        $data = str_replace("%class2", $class2, $data);
        $data = str_replace("%cmd", $cmd, $data);
        $data = str_replace("%rep", $rep, $data);
        $data = str_replace("%req", $req, $data);
        $data = str_replace("%class_des", $des, $data);
        $out_data .= $data . "\n";
        if (array_key_exists($cmd, $regist)) {
            throw new Exception("cmd重复");
        }
        $regist[$cmd] = [$class1, $class2];
    }
}
//处理regist
$registTemplate = file_get_contents(__DIR__ . "/template_js/ProtoRegistTemplate.proto");
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
    $class_data = str_replace("[[$varhandel]]", substr(trim($result),0,-1), $class_data);
}
$out_data .= $class_data . "\n";

//处理struct
$structTemplate = file_get_contents(__DIR__ . "/template_js/StructTemplate.proto");
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
    $out_data .= $class_data . "\n";
}

//处理protobuild
$protoBuildTemplate = file_get_contents(__DIR__ . "/template_js/ProtoBuildTemplate.proto");
$protoBuildTemplateResult = getHandelStr($protoBuildTemplate);
$class_data = $protoBuildTemplateResult;
$varhandels = handelFindVar($protoBuildTemplate);
foreach ($varhandels as $varhandel => $str) {
    $result = "";
    foreach ($struct as $onestruct) {
        $class = (string)$onestruct["class"];
        $arr = explode("[[$varhandel]]", $class_data);
        $arr = explode("\n", $arr[0]);
        $trim = $arr[count($arr) - 1];
        $result = $result . str_replace("%class", $class, $str) . "\n";
    }
    $class_data = str_replace("[[$varhandel]]", trim($result), $class_data);
}
$out_data .= $class_data . "\n";
//处理marco
$marcoTemplate = file_get_contents(__DIR__ . "/template_js/MarcoTemplate.proto");
$marcoTemplateResult = getHandelStr($marcoTemplate);
$varhandels = handelFindVar($marcoTemplate);

$class_data = "";
$marco = $xml->marco;
foreach ($varhandels as $varhandel => $str) {
    $arr = explode("[[$varhandel]]", $marcoTemplate);
    $arr = explode("\n", $arr[0]);
    $trim = $arr[count($arr) - 1];
    $result = "";
    foreach ($marco as $onevar) {
        $var = (string)$onevar["name"];
        $var_type = (string)$onevar["type"];
        $var_des = (string)$onevar['des'];
        $var_value = (string)$onevar['value'];
        $data = handelVar($str, $var, $var_type, $var_des, $var_value);
        $result = $result . $trim . $data . "\n";
    }
    $class_data = str_replace("[[$varhandel]]", substr(trim($result),0,-1), $marcoTemplateResult);
}
$out_data .= $class_data . "\n";
$dir = __DIR__ . "/build_js/proto.js";
file_put_contents($dir, $out_data);


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
        $data = str_replace("%extends", "/**
     * parent
     * @type {BaseRep}
     */
    this.parent = new $extends(json);", $data);
    } else {
        $data = str_replace("%extends", "", $data);
    }
    $data = str_replace("%class", $class, $data);
    return $data;
}

function handelVar($data, $var, $var_type, $var_des, $var_value,$prefix='')
{
    $var_type_class = "";
    $old_var_type = $var_type;
    //数组
    if (strpos($old_var_type, "[]")) {
        $var_type = str_replace("[]", "", $var_type);
        if ($var_type == "bool") {
            $var_type_des = "Array.<boolean>";
        } else {
            $var_type_des = "Array.<$var_type>";
        }
        $var_type_build = $var_type . "_array";
        $var_type_class = $var_type;
        $var_type = "array";
    }else {
        if ($old_var_type == "bool") {
            $var_type_build = $old_var_type;
            $var_type_des = "boolean";
        } else {
            $var_type_build = $old_var_type;
            $var_type_des = $old_var_type;
        }
    }
    $data = str_replace("%{$prefix}var_type_des", $var_type_des, $data);
    $data = str_replace("%{$prefix}var_type_build", $var_type_build, $data);
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
            if ($var_type_class == "int" || $var_type_class == "string" || $var_type_class == "bool" || empty($var_type_class)) {
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