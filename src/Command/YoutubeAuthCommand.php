<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use xorik\YtUpload\Service\TokenStorage;
use xorik\YtUpload\Service\YoutubeApi;

#[AsCommand(name: 'yt:auth')]
class YoutubeAuthCommand extends Command
{
    public function __construct(
        private YoutubeApi $youtubeApi,
        private TokenStorage $tokenStorage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('Click on the link, authorize the app and paste here URL after reditect (starts with http://localhost:8000)');
        $io->writeln($this->youtubeApi->getAuthUrl());

        $url = $io->ask('Full URL: ');

        $query = parse_url($url, \PHP_URL_QUERY);
        parse_str($query, $params);

        $token = $this->youtubeApi->auth($params['code']);
        $this->tokenStorage->save($token);

        $io->success('Token was saved!');

        return Command::SUCCESS;
    }
}
