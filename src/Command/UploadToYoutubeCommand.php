<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use xorik\YtUpload\Service\TokenStorage;
use xorik\YtUpload\Service\YoutubeApi;

#[AsCommand(name: 'yt:upload')]
class UploadToYoutubeCommand extends Command
{
    public function __construct(
        private YoutubeApi $youtubeApi,
        private TokenStorage $tokenStorage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $token = $this->tokenStorage->getToken();

        var_dump($token);

        return Command::SUCCESS;
    }
}
