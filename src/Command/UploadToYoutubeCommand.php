<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\UploadState;
use xorik\YtUpload\Model\VideoState;
use xorik\YtUpload\Service\ProgressRepository;
use xorik\YtUpload\Service\QueueManager;
use xorik\YtUpload\Service\TokenStorage;
use xorik\YtUpload\Service\YoutubeApi;

#[AsCommand(name: 'yt:upload')]
class UploadToYoutubeCommand extends Command
{
    public function __construct(
        readonly private YoutubeApi $youtubeApi,
        readonly private TokenStorage $tokenStorage,
        readonly private QueueManager $queueManager,
        readonly private ProgressRepository $progressRepository,
    ) {
        parent::__construct();

        $this->addArgument('id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $video = $this->queueManager->getWithLock(Uuid::fromString($input->getArgument('id')));

        if (!\in_array($video->state, [VideoState::DOWNLOADED, VideoState::UPLOADING], true)) {
            throw new \RuntimeException(sprintf('Incorrect status for video %s: %s', $video->id, $video->state->value));
        }

        $token = $this->tokenStorage->getToken();

        if ($video->state === VideoState::DOWNLOADED) {
            $request = $this->youtubeApi->insertVideo($token, $video->videoDetails);
        } else {
            // Downloading is started, but was interrupted
            $request = $video->uploadState->request;
            $resumeUrl = $video->uploadState->resumeUrl;
        }

        $oldResumeUrl = null;

        $callback = function (int $progress, int $size, string $resumeUrl) use ($video, $request, &$oldResumeUrl): void {
            $this->progressRepository->setProgress($video->id, $progress / $size * 100.0);

            if ($resumeUrl !== $oldResumeUrl) {
                $video = $video->uploading(new UploadState($request, $resumeUrl));
                $this->queueManager->save($video);
                $oldResumeUrl = $resumeUrl;
            }
        };

        $videoId = $this->youtubeApi->uploadVideo(
            $token,
            $video->downloadedPath,
            $request,
            $callback,
            $resumeUrl ?? null,
        );

        unlink($video->downloadedPath);
        $this->queueManager->saveAndUnlock($video->uploaded($videoId));

        return Command::SUCCESS;
    }
}
