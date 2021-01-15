<?php

require __DIR__ . '/vendor/autoload.php';

use App\Command\ConvertCommand;
use Symfony\Component\Console\Application;

$app = new Application();
$app->add(new ConvertCommand());
$app->run();
