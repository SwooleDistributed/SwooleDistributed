<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-22
 * Time: 上午10:59
 */

namespace Server\Console;


use Noodlehaus\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OptimizationCmd extends Command
{
    protected $config;
    protected $sh = "IyEvYmluL2Jhc2gKCiMgc2V0IHVsaW1pdApsaW1pdCgpCnsKZWNobyAidWxpbWl0IC1TSG4gMTAyNDAwIiA+Pi9ldGMvcmMubG9jYWwKY2F0ID4+IC9ldGMvc2VjdXJpdHkvbGltaXRzLmNvbmYgPDwgRU9GCiogICAgICAgICAgIHNvZnQgICBub2ZpbGUgICAgICAgNjU1MzUKKiAgICAgICAgICAgaGFyZCAgIG5vZmlsZSAgICAgICA2NTUzNQpFT0YKfQoKIyBzZXQgc3lzY3RsCnN5c2N0bCgpCnsKY3AgL2V0Yy9zeXNjdGwuY29uZiAvZXRjL3N5c2N0bC5jb25mLSQoZGF0ZSArJUYpLmJhawp0cnVlID4gL2V0Yy9zeXNjdGwuY29uZgpjYXQgPj4gL2V0Yy9zeXNjdGwuY29uZiA8PCBFT0YKbmV0LmlwdjQuaXBfZm9yd2FyZCA9IDAKbmV0LmlwdjQuY29uZi5kZWZhdWx0LnJwX2ZpbHRlciA9IDEKbmV0LmlwdjQuY29uZi5kZWZhdWx0LmFjY2VwdF9zb3VyY2Vfcm91dGUgPSAwCmtlcm5lbC5zeXNycSA9IDAKa2VybmVsLmNvcmVfdXNlc19waWQgPSAxCm5ldC5pcHY0LnRjcF9zeW5jb29raWVzID0gMQprZXJuZWwubXNnbW5iID0gNjU1MzYKa2VybmVsLm1zZ21heCA9IDY1NTM2Cmtlcm5lbC5zaG1tYXggPSA2ODcxOTQ3NjczNgprZXJuZWwuc2htYWxsID0gNDI5NDk2NzI5NgpuZXQuaXB2NC50Y3BfbWF4X3R3X2J1Y2tldHMgPSA2MDAwCm5ldC5pcHY0LnRjcF9zYWNrID0gMQpuZXQuaXB2NC50Y3Bfd2luZG93X3NjYWxpbmcgPSAxCm5ldC5pcHY0LnRjcF9ybWVtID0gNDA5NiA4NzM4MCA0MTk0MzA0Cm5ldC5pcHY0LnRjcF93bWVtID0gNDA5NiAxNjM4NCA0MTk0MzA0Cm5ldC5jb3JlLndtZW1fZGVmYXVsdCA9IDgzODg2MDgKbmV0LmNvcmUucm1lbV9kZWZhdWx0ID0gODM4ODYwOApuZXQuY29yZS5ybWVtX21heCA9IDE2Nzc3MjE2Cm5ldC5jb3JlLndtZW1fbWF4ID0gMTY3NzcyMTYKbmV0LmNvcmUubmV0ZGV2X21heF9iYWNrbG9nID0gMjYyMTQ0Cm5ldC5jb3JlLnNvbWF4Y29ubiA9IDI2MjE0NApuZXQuaXB2NC50Y3BfbWF4X29ycGhhbnMgPSAzMjc2ODAwCm5ldC5pcHY0LnRjcF9tYXhfc3luX2JhY2tsb2cgPSAyNjIxNDQKbmV0LmlwdjQudGNwX3RpbWVzdGFtcHMgPSAwCm5ldC5pcHY0LnRjcF9zeW5hY2tfcmV0cmllcyA9IDEKbmV0LmlwdjQudGNwX3N5bl9yZXRyaWVzID0gMQpuZXQuaXB2NC50Y3BfdHdfcmVjeWNsZSA9IDEKbmV0LmlwdjQudGNwX3R3X3JldXNlID0gMQpuZXQuaXB2NC50Y3BfbWVtID0gOTQ1MDAwMDAgOTE1MDAwMDAwIDkyNzAwMDAwMApuZXQuaXB2NC50Y3BfZmluX3RpbWVvdXQgPSAxCm5ldC5pcHY0LnRjcF9rZWVwYWxpdmVfdGltZSA9IDEyMDAKbmV0LmlwdjQuaXBfbG9jYWxfcG9ydF9yYW5nZSA9IDEwMjQgNjU1MzUKI25ldC5pcHY0LmljbXBfZWNob19pZ25vcmVfYWxsID0gMSAgI+emgXBpbmfvvIzlpoLmnpzmnIluYWdpb3Pnm5HmjqfvvIzov5nmraXlj6/nnIHljrsKRU9GCi9zYmluL3N5c2N0bCAtcAplY2hvICJzeXNjdGwgc2V0IE9LISEiCn0KCiMtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpsaW1pdApzeXNjdGwK";
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->config = new Config(getConfigDir());
    }

    protected function configure()
    {
        $this->setName('opt')->setDescription("Server optimization");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        ob_start();
        phpinfo(INFO_GENERAL);
        $data = ob_get_clean();
        $lines = explode("\n", $data);
        $ini_path = "";
        foreach ($lines as $line) {
            if (strpos($line, "Loaded Configuration File ") !== false) {
                list($name, $ini_path) = explode("=>", $line);
                break;
            }
        }
        //修改php的内存限制
        $this->ini_file(trim($ini_path),"PHP","memory_limit","2048M");
        //优化socket
        system(base64_decode($this->sh));
        $io->success("服务器优化完成");
    }

    protected function ini_file($inifilename, $mode = null, $key, $value = null)
    {
        if (!file_exists($inifilename))
            return null;
        //读取
        $confarr = parse_ini_file($inifilename, true);
        $newini = "";
        if ($mode != null) {
        //节名不为空
            if ($value == null) {
                return @$confarr[$mode][$key] == null ? null : $confarr[$mode][$key];
            } else {
                $YNedit = @$confarr[$mode][$key] == $value ? false : true;//若传入的值和原来的一样，则不更改
                @$confarr[$mode][$key] = $value;
            }
        } else {//节名为空

            if ($value == null) {
                return @$confarr[$key] == null ? null : $confarr[$key];
            } else {
                $YNedit = @$confarr[$key] == $value ? false : true;//若传入的值和原来的一样，则不更改
                @$confarr[$key] == $value;
                $newini = $newini . $key . "=" . $value . "\r\n";
            }

        }
        if (!$YNedit)
            return true;

        //更改

        $Mname = array_keys($confarr);
        $jshu = 0;

        foreach ($confarr as $k => $v) {
            if (!is_array($v)) {
                $newini = $newini . $Mname[$jshu] . "=" . $v . "\r\n";
                $jshu += 1;
            } else {
                $newini = $newini . '[' . $Mname[$jshu] . "]\r\n";//节名
                $jshu += 1;
                $jieM = array_keys($v);
                $jieS = 0;
                foreach ($v as $k2 => $v2) {
                    if(strpos($v2,"=")!==false){
                        $v2 = '"'.$v2.'"';
                    }
                    $newini = $newini . $jieM[$jieS] . "=" . $v2 . "\r\n";
                    $jieS += 1;
                }
            }

        }
        if (($fi = fopen($inifilename, "w"))) {
            flock($fi, LOCK_EX);//排它锁
            fwrite($fi, $newini);
            flock($fi, LOCK_UN);
            fclose($fi);
            return true;
        }
        return false;//写文件失败
    }
}