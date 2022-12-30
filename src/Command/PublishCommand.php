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

#[AsCommand(name: 'yt:publish')]
class PublishCommand extends Command
{
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
            $details = $this->youtubeApi->getProcessingDetails($token, $video->videoId);

            // TODO: make it more clear
            $processingStatus = $details->getProcessingDetails()->getProcessingStatus();
            $hdProcessingFinished = $details->getSnippet()->getThumbnails()->getMaxres() !== null;

            if ($processingStatus === 'succeeded' && $hdProcessingFinished) {
                $privacyStatus = $video->videoDetails->privacyStatus;
                if ($details->getStatus()->getPrivacyStatus() !== $privacyStatus->value) {
                    $this->youtubeApi->updatePrivacyStatus($token, $video->videoId, $privacyStatus);
                }

                $this->queueManager->saveAndUnlock($video->publish());

                return Command::SUCCESS;
            }

            if ($processingStatus !== 'succeeded' && $processingStatus !== 'processing') {
                throw new \RuntimeException(sprintf('Incorrect processing status is for YouTube video %s: %s', $video->videoId, $processingStatus));
            }

            // Try to get time for waiting, or wait 10 minutes
            $timeLeft = $details->getProcessingDetails()->getProcessingProgress()?->getTimeLeftMs();
            if ($timeLeft === null) {
                sleep(600);
                continue;
            }

            // Sleep from 10 seconds to 1 hour
            $waitTime = min(max($timeLeft / 1000, 10), 3600);
            sleep($waitTime);
        }
    }
}
