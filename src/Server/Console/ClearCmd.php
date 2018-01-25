<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-22
 * Time: 上午10:59
 */

namespace Server\Console;


use app\AppServer;
use Noodlehaus\Config;
use Server\Components\CatCache\CatCacheProcess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearCmd extends Command
{
    protected $config;

    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->config = new Config(getConfigDir());
    }

    protected function configure()
    {
        $this->setName('clear')->setDescription("Clear server actor and timer callback");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        new AppServer();
        $process = new CatCacheProcess("", "");
        $process->clearActor();
        $process->clearTimerBack();
        $process->autoSave();
        $io->success("Clear actor and timer callback success");
    }
}