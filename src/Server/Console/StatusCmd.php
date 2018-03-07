<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-22
 * Time: 上午10:59
 */

namespace Server\Console;


use Noodlehaus\Config;
use Server\CoreBase\PortManager;
use Server\SwooleServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCmd extends Command
{
    protected $config;

    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->config = new Config(getConfigDir());
    }

    protected function configure()
    {
        $this->setName('status')->setDescription("Server Status");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $server_name = $this->config['name'] ?? 'SWD';
        $master_pid = exec("ps -ef | grep $server_name-Master | grep -v 'grep ' | awk '{print $2}'");
        $io->title('WELCOME START SWOOLE DISTRIBUTED, HAVE FUN!');
        $io->table(
            [
                "System",
                "PHP Version",
                "Swoole Version",
                "SwooleDistributed Version",
                "Worker Num",
                "Task Num"
            ],
            [
                [
                    PHP_OS,
                    PHP_VERSION,
                    SWOOLE_VERSION,
                    SwooleServer::version,
                    $this->config->get('server.set.worker_num', 0),
                    $this->config->get('server.set.task_worker_num', 0)
                ]
            ]
        );
        $io->section('Port information');
        $ports = $this->config['ports'];
        $show = [];
        foreach ($ports as $key => $value) {
            $middleware = '';
            foreach ($value['middlewares'] ?? [] as $m) {
                $middleware .= '[' . $m . ']';
            }
            $show[] = [
                PortManager::getTypeName($value['socket_type']),
                $value['socket_name'],
                $value['socket_port'],
                $value['pack_tool'] ?? PortManager::getTypeName($value['socket_type']),
                $middleware
            ];
        }
        $show[] = [
            'CLUSTER',
            '0.0.0.0',
            $this->config->get('cluster.port', '--'),
            $this->config->get('consul.enable', false) ? '<question>OPEN</question>' : '<question>CLOSE</question>'];
        $io->table(
            ['S_TYPE', 'S_NAME', 'S_PORT', 'S_PACK', 'S_MIDD'],
            $show
        );
        if (!empty($master_pid)) {
            $io->note("$server_name server already running");
        } else {
            $io->note("$server_name server not run");
            return;
        }
    }
}