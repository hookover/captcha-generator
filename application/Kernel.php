<?php

namespace App;

use App\Command\CaptchaGeneratorCommand;
use App\Command\DefaultCommand;
use App\Command\FormatFontCommand;
use App\Command\TestFontCommand;
use Symfony\Component\Console\Application;

class Kernel extends Application
{
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        $this->addCommands([
            new DefaultCommand(),
            new CaptchaGeneratorCommand(),
            new TestFontCommand(),
            new FormatFontCommand(),
            //添加更多的命令
        ]);
    }
}