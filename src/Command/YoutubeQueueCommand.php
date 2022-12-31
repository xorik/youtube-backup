<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use xorik\YtUpload\Model\PrivacyStatus;
use xorik\YtUpload\Model\VideoDetails;
use xorik\YtUpload\Model\VideoRange;
use xorik\YtUpload\Model\VideoTimestamp;
use xorik\YtUpload\Model\YoutubeCategory;
use xorik\YtUpload\Service\QueueManager;

#[AsCommand(name: 'yt:queue')]
class YoutubeQueueCommand extends Command
{
    public function __construct(
        readonly private QueueManager $queueManager,
        readonly private CacheItemPoolInterface $cache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cacheItem = $this->cache->getItem('details');

        /** @var VideoDetails|null $oldDetails */
        $oldDetails = $cacheItem->get();

        $urlValidator = function (string $url): string {
            return str_starts_with($url, 'https://') ? $url : throw new \Exception('Invalid URL');
        };
        $sourceUrl = $io->ask('Source video URL', null, $urlValidator);

        $range = null;
        if ($io->confirm('Enter start and end time?')) {
            $range = $this->askRange($io);
        }

        $question = new Question('Video title', $oldDetails?->title);
        $question->setAutocompleterValues($oldDetails?->title !== null ? [$oldDetails?->title] : null);
        $title = $io->askQuestion($question);

        $question = new Question('Video description', "$oldDetails?->description");
        $question->setMultiline(true);
        $description = $io->askQuestion($question);

        $question = new Question('Tags', $oldDetails?->tags !== null ? implode(', ', $oldDetails?->tags) : null);
        $tags = explode(',', $io->askQuestion($question));
        $tags = array_map(fn (string $tag) => trim($tag), $tags);

        $category = $io->choice('Video category', YoutubeCategory::getStringValues(), $oldDetails?->category->toString());
        $category = YoutubeCategory::fromString($category);

        $privacy = $io->choice('Privacy', PrivacyStatus::values(), $oldDetails?->privacyStatus->value);
        $privacy = PrivacyStatus::from($privacy);

        $youtubeLicense = $io->confirm('Use Youtube license (y) or creative commons (n)', $oldDetails?->youtubeLicense ?? true);
        $thumbnailPath = $this->askThumbnailPath($io, $oldDetails?->thumbnailPath);
        $playlistId = $io->ask('Playlist ID (leave empty if not needed)', $oldDetails?->playlistId, fn (string $id) => !empty($id) ? $id : null);

        $details = new VideoDetails($title, $description, $tags, $category, $privacy, $youtubeLicense, $thumbnailPath, $playlistId);
        $this->cache->save($cacheItem->set($details));

        $this->queueManager->addToQueue($sourceUrl, $details, $range);

        $io->success(['Video was added to the queue!', 'Please run php index.php yt:run to start the process']);

        return Command::SUCCESS;
    }

    private function askRange(SymfonyStyle $io): VideoRange
    {
        $timeValidator = fn (string $time) => new VideoTimestamp($time);

        $start = $io->ask('Start time (H:MM:SS)', null, $timeValidator);
        $end = $io->ask('End time (H:MM:SS)', null, $timeValidator);

        return new VideoRange($start, $end);
    }

    private function askThumbnailPath(SymfonyStyle $io, ?string $oldValue): ?string
    {
        return $io->ask('Thumbnail path (leave empty for automatic)', $oldValue, function (string $path) {
            if ($path === '') {
                return null;
            }

            if (!file_exists($path)) {
                throw new \RuntimeException('File is not found');
            }

            return $path;
        });
    }
}
