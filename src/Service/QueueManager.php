<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\Video;
use xorik\YtUpload\Model\VideoDetails;
use xorik\YtUpload\Model\VideoRange;

class QueueManager
{
    /** @var LockInterface[] */
    private array $locks = [];

    public function __construct(
        readonly private Serializer $serializer,
        readonly private LockFactory $lockFactory,
        readonly private string $queueDirectory,
    ) {
    }

    public function addToQueue(
        string $sourceUrl,
        VideoDetails $videoDetails,
        ?VideoRange $range,
    ): void {
        $video = new Video(Uuid::v6(), $sourceUrl, $videoDetails, $range);
        $this->save($video);
    }

    /**
     * @return Video[]
     */
    public function list(): array
    {
        $list = [];

        $files = scandir($this->queueDirectory);
        foreach ($files as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }

            $data = file_get_contents($this->queueDirectory . '/' . $file);
            $id = str_replace('.json', '', $file);
            $list[] = $this->serializer->deserialize($data, Video::class, 'json');
        }

        return $list;
    }

    public function getWithLock(Uuid $id): Video
    {
        $lock = $this->lockFactory->createLock('video_' . $id, null);
        $this->locks[(string) $id] = $lock; // Save locks to state, to prevent auto-releasing
        if (!$lock->acquire()) {
            throw new \RuntimeException(sprintf('Video %s is taken by another process', $id));
        }

        $path = sprintf('%s/%s.json', $this->queueDirectory, $id);

        if (!file_exists($path)) {
            throw new \RuntimeException('File does not exist: ' . $path);
        }

        return $this->serializer->deserialize(file_get_contents($path), Video::class, 'json');
    }

    public function save(Video $video): void
    {
        $path = sprintf('%s/%s.json', $this->queueDirectory, $video->id);

        $data = $this->serializer->serialize($video, 'json');
        file_put_contents($path, $data);
    }

    public function saveAndUnlock(Video $video): void
    {
        $this->save($video);

        $id = (string) $video->id;
        $this->locks[$id]->release();
        unset($this->locks[$id]);
    }
}
