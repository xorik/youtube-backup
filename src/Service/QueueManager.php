<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;
use xorik\YtUpload\Model\Video;
use xorik\YtUpload\Model\VideoDetails;
use xorik\YtUpload\Model\VideoRange;

class QueueManager
{
    public function __construct(
        readonly private Serializer $serializer,
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

    public function get(Uuid $id): Video
    {
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
}
