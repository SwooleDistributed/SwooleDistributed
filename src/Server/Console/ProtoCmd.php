<?php
namespace Server\Console;
use Server\Unity\CsvReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2018/4/18
 * Time: 18:01
 */

class ProtoCmd extends Command
{
    protected function configure()
    {
        $this->setName('proto')->setDescription("Proto build");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $cmd = MYROOT."/protoGen/proto_php.php";
        echo exec("php $cmd");
        $cmd = MYROOT."/protoGen/proto_js.php";
        echo exec("php $cmd");
        $csvReader = new CsvReader(MYROOT . "/src/csv");
        $json = json_encode($csvReader->all,JSON_UNESCAPED_UNICODE);
        file_put_contents(MYROOT."/protoGen/build_js/csv.json",$json);
        $io->success("proto build success");
    }
}