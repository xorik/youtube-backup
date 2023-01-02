<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\PrivacyStatus;
use xorik\YtUpload\Model\VideoState;
use xorik\YtUpload\Service\QueueManager;
use xorik\YtUpload\Service\TokenStorage;
use xorik\YtUpload\Service\YoutubeApi;

#[AsCommand(name: 'yt:publish')]
class PublishCommand extends Command
{
    private const PROCESSING_CHECK_INTERVAL = 600;

    public function __construct(
        readonly private QueueManager $queueManager,
        readonly private YoutubeApi $youtubeApi,
        readonly private TokenStorage $tokenStorage,
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

        while (true) {
            // Get token in loop, since processing can take hours
            $token = $this->tokenStorage->getToken();

            if ($this->youtubeApi->hasProcessingCompleted($token, $video->videoId)) {
                break;
            }

            sleep(self::PROCESSING_CHECK_INTERVAL);
        }

        // Set thumbnail
        if ($video->videoDetails->thumbnailPath !== null) {
            $token = $this->tokenStorage->getToken();
            $this->youtubeApi->updateThumbnail($token, $video->videoId, $video->videoDetails->thumbnailPath);
        }

        // Set playlist ID
        if ($video->videoDetails->playlistId !== null) {
            $token = $this->tokenStorage->getToken();
            $this->youtubeApi->addToPlaylist($token, $video->videoId, $video->videoDetails->playlistId);
        }

        // Set privacy status
        if ($video->videoDetails->privacyStatus !== PrivacyStatus::PRIVATE) {
            $this->youtubeApi->updatePrivacyStatus($token, $video->videoId, $video->videoDetails->privacyStatus);
        }

        $this->queueManager->saveAndUnlock($video->publish());

        return Command::SUCCESS;
    }
}
