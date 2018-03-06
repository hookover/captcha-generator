<?php

namespace App\Command;

use App\Codes\Captcha;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CaptchaGeneratorCommand extends Command
{
    protected $widget     = [];
    protected $max_widget = 0;

    protected function configure()
    {
        $this->setName('command:captcha-generator')//这里的command:name即是调用名称,如改成make:controller,是调用方式将变为php app make:controller
        ->setDescription('生成图片')
            ->setDefinition([
                new InputArgument('save-path', InputArgument::OPTIONAL, '生成的图片保存目录', './data'),
                new InputArgument('bath-size', InputArgument::OPTIONAL, '每个字体生成多少张图片', 100),
                new InputArgument('font-number', InputArgument::OPTIONAL, '第n个字体文件', 0),
                new InputArgument('font-end', InputArgument::OPTIONAL, '第n个字体文件结束', 0),
                new InputArgument('img-width', InputArgument::OPTIONAL, '图片宽度', 300),
                new InputArgument('img-height', InputArgument::OPTIONAL, '图片宽度', 150),
            ])->setHelp('没得啥子可帮助的');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $save_path   = $input->getArgument('save-path');
        $bath_size   = $input->getArgument('bath-size');
        $font_number = $input->getArgument('font-number');
        $font_end    = $input->getArgument('font-end');
        $img_width   = $input->getArgument('img-width');
        $img_height  = $input->getArgument('img-height');

        $this->widget     = $this->buildWidgetLengthNumber();
        $this->max_widget = count($this->widget) - 1;

        $n       = 1;
        $builder = new Captcha();

        $font_count = $font_end ? $font_end : count($builder->getFonts());

        $processBar = (new SymfonyStyle($input, $output))->createProgressBar(($font_count * $bath_size));

        for ($i = $font_number; $i < $font_count; $i++) {

            $font = $builder->getFonts()[ $i ];

            for ($k = 0; $k < $bath_size; $k++) {
                $sub_path = date('Ymd/H/i');

                $length = $this->widget[ mt_rand(0, $this->max_widget) ];

                $builder->build($img_width, $img_height, $font, $length);

                $data      = $builder->getCoordinate();
                $font_name = $data[0]['font_name'];

                $data = array_map(function ($word) {
                    return $word['word'] . '_' . $word['x'] . '#' . $word['y'] . '_' . $word['angle'] . '_' . $word['width'] . '_' . $word['height'];
                }, $data);

                $file_name = $sub_path . '/' . implode('.', [$n, $i, $k]) . '.' . implode('~', $data);
                $file_name = $save_path . '/' . $file_name . '@' . $font_name . '.jpg';

                $builder->save($file_name, 90);

                if ($n % 10 == 1) {
                    $output->writeln("<info> 已生成：{$n}张，当前使用第" . $i . "个字体文件</info>");
                }

                ++$n;

                $processBar->advance();
            }
        }

    }

    /**
     * 权重配置
     */
    protected function buildWidgetLengthNumber()
    {
        $data = [
            1 => 1,
            2 => 1,
            3 => 5,
            4 => 60,
            5 => 18,
            6 => 10,
            7 => 3,
            8 => 1,
            9 => 1,
        ];

        $arr = [];

        foreach ($data as $k => $v) {
            for ($n = 0; $n < $v; $n++) {
                $arr[] = $k;
            }
        }


        return $arr;
    }

    protected function getTotalMillisecond()
    {
        $time  = explode(" ", microtime());
        $time  = $time [1] . ($time [0] * 1000);
        $time2 = explode(".", $time);
        $time  = $time2 [0];

        $time_second = substr($time, 0, 10);
        $time_micro  = substr($time, 10);

        return [
            'time_second' => $time_second,
            'time_micro'  => $time_micro,
        ];
    }
}