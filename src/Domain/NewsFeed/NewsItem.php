<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\Instant;

/**
 * A notification of the news feed ("idNews", "newsText", "newsDate").
 */
final readonly class NewsItem
{
    private function __construct(
        private ?string $idNews,
        private ?string $newsText,
        private ?Instant $newsDate,
    ) {}

    public static function of(?string $idNews, ?string $newsText, ?Instant $newsDate): self
    {
        return new self($idNews, $newsText, $newsDate);
    }

    public function idNews(): ?string
    {
        return $this->idNews;
    }

    public function newsText(): ?string
    {
        return $this->newsText;
    }

    public function newsDate(): ?Instant
    {
        return $this->newsDate;
    }
}
