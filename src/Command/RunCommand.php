<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\Video;
use xorik\YtUpload\Model\VideoState;
use xorik\YtUpload\Service\ProgressRepository;
use xorik\YtUpload\Service\QueueManager;
use xorik\YtUpload\Service\UI\CliRenderer;

#[AsCommand(name: 'yt:run')]
class RunCommand extends Command
{
    private const MIN_FREE_DISK_SPACE = 30 * 1024 * 1024 * 1024; // 30 GB
    private const MAX_DOWNLOADED_VIDEOS = 4;

    /** @var Process[][] */
    private array $processes = [
        'queued' => [],
        'downloaded' => [],
        'uploaded' => [],
        'prepared' => [],
    ];

    /** @var Video[] */
    private array $pendingVideos = [];

    private CliRenderer $cliRenderer;

    public function __construct(
        readonly private QueueManager $queueManager,
        readonly private ProgressRepository $progressRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->cliRenderer = new CliRenderer($input, $output);

        $videos = $this->queueManager->list();

        $this->cliRenderer->init($videos);

        foreach ($videos as $video) {
            if (!\in_array($video->state, [VideoState::DOWNLOADING, VideoState::UPLOADING], true)) {
                $this->pendingVideos[(string) $video->id] = $video;
                continue;
            }

            // Resume started processes
            $command = match ($video->state) {
                VideoState::DOWNLOADING => 'yt:download',
                VideoState::UPLOADING => 'yt:upload',
            };

            $state = match ($video->state) {
                VideoState::DOWNLOADING => VideoState::QUEUED,
                VideoState::UPLOADING => VideoState::DOWNLOADED,
            };

            $this->startProcess($video->id, $state, $command);
        }

        while (true) {
            $this->loop();
            sleep(10);
        }
    }

    private function loop(): void
    {
        // Check background tasks
        foreach ($this->processes as $state => $list) {
            foreach ($list as $id => $process) {
                if ($process->isRunning()) {
                    if ($state === VideoState::QUEUED->value || $state === VideoState::DOWNLOADED->value) {
                        $videoId = Uuid::fromString($id);
                        $progress = $this->progressRepository->getProgress($videoId);

                        if ($progress !== null) {
                            $this->cliRenderer->updateProgress(
                                $videoId,
                                $progress
                            );
                        }
                    }

                    continue;
                }

                $this->handleClosedProcess(VideoState::from($state), Uuid::fromString($id), $process);
            }
        }

        // Check if any pending process can be started
        foreach ($this->pendingVideos as $id => $video) {
            $canStartPendingVideo = $this->canStartPendingVideo($video->state);
            if (!$canStartPendingVideo) {
                continue;
            }

            // All steps are finished
            if ($video->state === VideoState::PUBLISHED) {
                unset($this->pendingVideos[$id]);
                continue;
            }

            $command = match ($video->state) {
                VideoState::QUEUED => 'yt:download',
                VideoState::DOWNLOADED => 'yt:upload',
                VideoState::UPLOADED => 'yt:publish',
                default => throw new \LogicException('Unexpected state: ' . $video->state->value),
            };

            $this->startProcess($video->id, $video->state, $command);

            // Remove from pending list
            unset($this->pendingVideos[$id]);
        }
    }

    private function handleClosedProcess(VideoState $state, Uuid $id, Process $process): void
    {
        unset($this->processes[$state->value][(string) $id]);
        $video = $this->queueManager->get($id);

        // TODO: try to recover
        if ($process->getExitCode() > 0) {
//            $this->io->error([
//                'Process has finished with error',
//                'Name: ' . $video->videoDetails->title,
//                'State: ' . $state->name, $process->getOutput(),
//            ]);

            return;
        }

        // Move video to pending state
        $this->pendingVideos[(string) $id] = $video;
    }

    private function canStartPendingVideo(VideoState $state): bool
    {
        if ($state === VideoState::QUEUED) {
            if (\count($this->processes[VideoState::QUEUED->value]) > 0) {
                return false;
            }

            $downloadedVideosCount = $this->countDownloadedVideos();
            if ($downloadedVideosCount >= self::MAX_DOWNLOADED_VIDEOS) {
                return false;
            }

            return $downloadedVideosCount === 0
                || disk_free_space(__DIR__) > self::MIN_FREE_DISK_SPACE;
        }

        // Make sure only one process is uploading
        if ($state === VideoState::DOWNLOADED) {
            return \count($this->processes[VideoState::DOWNLOADED->value]) === 0;
        }

        // Max 2 publish processes, to save API quota
        if ($state === VideoState::UPLOADED) {
            return \count($this->processes[VideoState::UPLOADED->value]) < 2;
        }

        return true;
    }

    private function startProcess(Uuid $id, VideoState $state, string $command): void
    {
        $process = new Process(['php', 'index.php', $command, $id, '-vvv']);
        $process->setTimeout(null);
        $process->start();

        $this->processes[$state->value][(string) $id] = $process;
    }

    // TODO: store in a variable, and update when start/finish a process
    private function countDownloadedVideos(): int
    {
        // Check current downloading and uploading tasks
        $count = \count($this->processes[VideoState::QUEUED->value]) + \count($this->processes[VideoState::DOWNLOADED->value]);

        // Plus pending downloaded videos
        foreach ($this->pendingVideos as $video) {
            if ($video->state === VideoState::DOWNLOADED) {
                ++$count;
            }
        }

        return $count;
    }
}
