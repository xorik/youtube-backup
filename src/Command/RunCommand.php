<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\Video;
use xorik\YtUpload\Model\VideoState;
use xorik\YtUpload\Service\QueueManager;

#[AsCommand(name: 'yt:run')]
class RunCommand extends Command
{
    /** @var Process[][] */
    private array $processes = [];

    /** @var Video[] */
    private array $pendingVideos = [];

    private SymfonyStyle $io;

    public function __construct(
        readonly private QueueManager $queueManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $videos = $this->queueManager->list();

        foreach ($videos as $video) {
            $id = (string) $video->id;

            // Resume started processes
            if (\in_array($video->state, [VideoState::DOWNLOADING, VideoState::UPLOADING, VideoState::UPLOADED], true)) {
                $command = match ($video->state) {
                    VideoState::DOWNLOADING => 'yt:download',
                    VideoState::UPLOADING => 'yt:upload',
                    VideoState::UPLOADED => 'yt:publish',
                };

                $this->io->info(sprintf('Resuming process % for video "%s"', $command, $video->videoDetails->title));
                $this->startProcess($video, $command);
                continue;
            }

            $this->pendingVideos[$id] = $video;
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
                    continue;
                }

                $this->handleClosedProcess(VideoState::from($state), Uuid::fromString($id), $process);
            }
        }

        // Check if any pending process can be started
        foreach ($this->pendingVideos as $id => $video) {
            if (!$this->canStartPendingVideo($video->state)) {
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

            $this->io->info(sprintf('Starting process %s for video "%s"', $command, $video->videoDetails->title));
            $this->startProcess($video, $command);

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
            $this->io->error([
                'Process has finished with error',
                'Name: ' . $video->videoDetails->title,
                'State: ' . $state->name, $process->getOutput(),
            ]);

            return;
        }

        $this->io->info(sprintf('Video "%s" has changed state from %s to %s', $video->videoDetails->title, $state->name, $video->state->name));

        // Move video to pending state
        $this->pendingVideos[(string) $id] = $video;
    }

    private function canStartPendingVideo(VideoState $state): bool
    {
        return true;
    }

    private function startProcess(Video $video, string $command): void
    {
        $id = (string) $video->id;
        $process = new Process(['php', 'index.php', $command, $id]);
        $process->setTimeout(null);
        $process->start();

        $this->processes[$video->state->value][$id] = $process;
    }
}
