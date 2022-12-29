<?php

declare(strict_types=1);

namespace xorik\YtUpload\Command;

use GuzzleHttp\Psr7\Request;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Serializer;
use xorik\YtUpload\Model\PrivacyStatus;
use xorik\YtUpload\Model\VideoDetails;
use xorik\YtUpload\Service\TokenStorage;
use xorik\YtUpload\Service\YoutubeApi;

#[AsCommand(name: 'yt:upload')]
class UploadToYoutubeCommand extends Command
{
    public function __construct(
        private YoutubeApi $youtubeApi,
        private TokenStorage $tokenStorage,
        private Serializer $serializer,
        private CacheItemPoolInterface $cache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $token = $this->tokenStorage->getToken();

        $item = $this->cache->getItem('progress');
        if ($item->isHit()) {
            $request = $this->serializer->denormalize($item->get()['request'], Request::class);
            $requestAsArray = $item->get()['request'];
            $resumeUrl = $item->get()['url'];

            $io->info(sprintf('Resuming from url: %s', $resumeUrl));
        } else {
            $details = new VideoDetails(
                'test title',
                'test descr',
                ['first', 'second'],
                20,
                PrivacyStatus::PRIVATE,
            );

            $request = $this->youtubeApi->insertVideo(
                $token,
                $details,
            );

            $requestAsArray = $this->serializer->normalize($request);
        }

        $callback = function (int $progress, int $size, string $url) use ($io, $item, $requestAsArray) {
            $io->writeln(sprintf('%d%% ready', $progress / $size * 100));
            $item->set([
                'request' => $requestAsArray,
                'url' => $url,
            ]);
            $this->cache->save($item);
        };

        $this->youtubeApi->uploadVideo(
            $token,
            '/tmp/test-upload.mp4',
            $request,
            $callback,
            $resumeUrl ?? null,
        );

        $this->cache->deleteItem('progress');

        return Command::SUCCESS;
    }
}
