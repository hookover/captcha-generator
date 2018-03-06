<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DefaultCommand extends Command
{
    protected function configure()
    {
        $this->setName('command:name')  //这里的command:name即是调用名称,如改成make:controller,是调用方式将变为php app make:controller
            ->setDescription('一个测试命令')
            ->setDefinition(
                [
                    new InputArgument('username', InputArgument::REQUIRED, '请输入用户名'),
                ]
            )->setHelp('帮助信息');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');

        $output->writeln("Hello： {$username}");
    }
}