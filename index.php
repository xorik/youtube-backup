<?php

declare(strict_types=1);

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Dotenv\Dotenv;
use xorik\YtUpload\Command\UploadToYoutubeCommand;
use xorik\YtUpload\Command\YoutubeAuthCommand;
use xorik\YtUpload\Command\YoutubeQueueCommand;

require __DIR__ . '/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');
if (is_file(__DIR__ . '/.env.local')) {
    $dotenv->load(__DIR__ . '/.env.local');
}

$application = new Application();
try {
    // Setup DI
    $containerBuilder = new ContainerBuilder();
    $loader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__));
    $loader->load('services.php');
    $containerBuilder->compile(true);

    // Setup commands
    $commandLoader = new ContainerCommandLoader($containerBuilder, [
        'yt:upload' => UploadToYoutubeCommand::class,
        'yt:auth' => YoutubeAuthCommand::class,
        'yt:queue' => YoutubeQueueCommand::class,
    ]);

    $application->setCommandLoader($commandLoader);
    $application->run();
} catch (\Throwable $e) {
    $application->renderThrowable($e, new ConsoleOutput());
}
