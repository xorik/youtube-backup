<?php

declare(strict_types=1);

namespace xorik\YtUpload\Service\UI;

use Symfony\Component\Console\Output\OutputInterface;

class ProgressRenderer
{
    private const PADDING = 8;

    public function __construct(
        readonly private OutputInterface $output,
        readonly private int $width,
    ) {
    }

    public function progress(float $value): void
    {
        // [=====>        ]  12.3%

        // Length of the "=====" part
        $progressLength = (int) round($value / 100 * ($this->width - self::PADDING)) - 1;

        $progressText = '';
        if ($progressLength > 0) {
            $progressText = str_repeat('=', $progressLength) . '>';
        } elseif ($progressLength === 0) {
            $progressText = '>';
        }

        // Add spaces inside the progress bar
        $progressText .= str_repeat(' ', $this->width - self::PADDING - \strlen($progressText));

        $this->output->write('[<fg=green>' . $progressText . '</>]' . sprintf('%6.1f', $value) . '%');
    }
}
