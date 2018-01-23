<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-22
 * Time: 上午10:59
 */

namespace Server\Console;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KillCmd extends Command
{
    protected $config;

    public function __construct($name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('kill')->setDescription("Kill server");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $server_name = getServerName();
        $result = $io->confirm("Kill the $server_name server?", false);
        if (!$result) {
            $io->warning("Cancel by user");
            return;
        }

        exec("ps -ef|grep $server_name|grep -v grep|cut -c 9-15|xargs kill -9");
    }
}