<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-22
 * Time: 上午10:59
 */

namespace Server\Console;


use Noodlehaus\Config;
use Server\Components\Backstage\ChannelMonitorClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ChannelCmd extends Command
{
    protected $config;

    /**
     * ChannelCmd constructor.
     * @param null $name
     * @throws \Noodlehaus\Exception\EmptyDirectoryException
     */
    public function __construct($name = null)
    {
        $this->config = new Config(getConfigDir());
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('channel')->setDescription("channel monitor");
        $port = $this->config->get("backstage.websocket_port");

        $this->addArgument('filters', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, "filters");
        $this->addOption('host', 's', InputOption::VALUE_OPTIONAL, 'host', "localhost");
        $this->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'port', $port);
        $this->addOption('uid', 'u', InputOption::VALUE_REQUIRED, 'uid');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->get('backstage.enable', false)) {
            throw new \Exception("配置文件backstage.enable必须设置为true，才能使用");
        }
        $host = $input->getOption("host");
        $port = $input->getOption("port");
        $uid = $input->getOption("uid");
        $filters = $input->getArgument("filters");
        if(empty($uid)){
            throw new \Exception("uid不能为空,请使用-u参数");
        }
        go(function () use ($host, $port, $uid, $filters) {
            new ChannelMonitorClient($host, $port, $uid, $filters);
        });
    }
}