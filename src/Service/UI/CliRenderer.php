<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service\UI;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\Video;
use xorik\YtUpload\Model\VideoState;

class CliRenderer
{
    private const PROGRESS_OFFSET = 6;
    private readonly Cursor $cursor;
    private readonly ProgressRenderer $progressRenderer;
    private readonly int $terminalWidth;

    /** @var array<string,int> */
    private array $positions = [];

    public function __construct(
        InputInterface $input,
        readonly private OutputInterface $output
    ) {
        $this->cursor = new Cursor($output, $input);
        $this->terminalWidth = (new Terminal())->getWidth();

        $progressLength = $this->terminalWidth / 2 - self::PROGRESS_OFFSET;
        $this->progressRenderer = new ProgressRenderer($this->output, (int) $progressLength);
    }

    /**
     * @param Video[] $videos
     */
    public function init(array $videos): void
    {
        $this->cursor
            ->clearScreen()
            ->moveToPosition(0, 0)
        ;

        foreach ($videos as $i => $video) {
            $this->positions[(string) $video->id] = $i;
            $this->output->write($video->videoDetails->title);
            $this->updateVideoState($video->id, $video->state, $video->videoId);
        }
    }

    public function updateProgress(Uuid $videoId, float $progress): void
    {
        $position = $this->terminalWidth / 2 + self::PROGRESS_OFFSET;
        $this->cursor->moveToPosition((int) $position, $this->positions[(string) $videoId]);
        $this->progressRenderer->progress($progress);
    }

    public function printError(string $output, string $command, string $title): void
    {
        $this->cursor
            ->clearScreen()
            ->moveToPosition(0, 0)
        ;

        $this->output->writeln([
            '<error>Process has finished with error</>',
            'Name: ' . $title,
            'Command: ' . $command,
            $output,
        ]);
    }

    public function updateVideoState(Uuid $id, VideoState $state, ?string $videoId = null): void
    {
        $this->cursor
            ->moveToPosition((int) ($this->terminalWidth / 2), $this->positions[(string) $id])
            ->clearLineAfter()
        ;
        $this->writeState($state, $videoId);
        $this->output->writeln('');
    }

    private function writeState(VideoState $state, ?string $videoId): void
    {
        $text = match ($state) {
            VideoState::QUEUED => '<fg=gray;options=bold>QUEUE</> <fg=gray>Queued video</>',
            VideoState::DOWNLOADING => '<fg=blue;options=bold>DOWN</>',
            VideoState::DOWNLOADED => '<fg=magenta;options=bold>WAIT</>  <fg=magenta>Waiting for uploading</>',
            VideoState::UPLOADING => '<fg=yellow;options=bold>UPLD</>',
            VideoState::UPLOADED => '<fg=green;options=bold>PROC</>  <fg=green>Processing on YouTube</>',
            VideoState::PUBLISHED => '<fg=green;options=bold>DONE</>  <fg=green>Published:</> https://youtu.be/' . $videoId,
            VideoState::ERROR => '<fg=red;options=bold>ERR</>',
        };

        $this->output->write($text);
    }
}
