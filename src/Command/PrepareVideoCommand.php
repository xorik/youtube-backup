<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\VideoState;
use xorik\YtUpload\Service\QueueManager;
use xorik\YtUpload\Service\TokenStorage;
use xorik\YtUpload\Service\YoutubeApi;

#[AsCommand(name: 'yt:prepare')]
class PrepareVideoCommand extends Command
{
    public function __construct(
        private YoutubeApi $youtubeApi,
        private TokenStorage $tokenStorage,
        private QueueManager $queueManager,
    ) {
        parent::__construct();

        $this->addArgument('id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $video = $this->queueManager->getWithLock(Uuid::fromString($input->getArgument('id')));

        if ($video->state !== VideoState::UPLOADED) {
            throw new \RuntimeException(sprintf('Incorrect status for video %s: %s', $video->id, $video->state->value));
        }

        if ($video->videoDetails->thumbnailPath !== null) {
            $token = $this->tokenStorage->getToken();
            $this->youtubeApi->updateThumbnail($token, $video->videoId, $video->videoDetails->thumbnailPath);
        }

        if ($video->videoDetails->playlistId !== null) {
            $token = $this->tokenStorage->getToken();
            $this->youtubeApi->addToPlaylist($token, $video->videoId, $video->videoDetails->playlistId);
        }

        $this->queueManager->saveAndUnlock($video->prepared());

        return Command::SUCCESS;
    }
}
