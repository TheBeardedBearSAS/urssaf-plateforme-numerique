<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf;

use DateTimeZone;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;

/**
 * Immutable connection settings of the URSSAF gateway (ADR 0001, 0002 and 0006).
 */
final readonly class ApiConfiguration
{
    public const DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS = 60;
    public const DEFAULT_USER_AGENT = 'thebeardedbear-urssaf-plateforme-numerique';
    public const DEFAULT_ACCEPT_LANGUAGE = 'FR';
    public const DEFAULT_API_TIME_ZONE = 'Europe/Paris';

    private const ACCEPT_LANGUAGES = ['FR', 'EN'];
    private const HEADER_NAME_PATTERN = '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D';

    /**
     * @param string|null  $baseUriOverride          replaces the host of the target (e.g. a proxy); the base path and the
     *                                               scope still follow the target
     * @param string|null  $requestIdHeader          name of the optional request id header (omitted when null)
     * @param string       $acceptLanguage           value of the `Accept-Language` header, `FR` or `EN` (upper case)
     * @param int          $tokenSafetyMarginSeconds a cached token is considered expired this many seconds early
     * @param DateTimeZone $apiTimeZone              time zone of the zone-less timestamps written by URSSAF (ADR 0006)
     *
     * @throws InvalidValue "accept_language.invalid_format", "token_safety_margin.negative" or
     *                      "request_id_header.invalid_format"
     */
    public function __construct(
        private Environment $environment,
        private ?string $baseUriOverride,
        private ?string $requestIdHeader,
        private string $acceptLanguage = self::DEFAULT_ACCEPT_LANGUAGE,
        private int $tokenSafetyMarginSeconds = self::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS,
        private string $userAgent = self::DEFAULT_USER_AGENT,
        private DateTimeZone $apiTimeZone = new DateTimeZone(self::DEFAULT_API_TIME_ZONE),
    ) {
        if (!\in_array($acceptLanguage, self::ACCEPT_LANGUAGES, true)) {
            throw InvalidValue::invalidFormat('accept_language', 'FR or EN');
        }
        if ($tokenSafetyMarginSeconds < 0) {
            throw InvalidValue::negative('token_safety_margin');
        }
        if ($requestIdHeader !== null && preg_match(self::HEADER_NAME_PATTERN, $requestIdHeader) !== 1) {
            throw InvalidValue::invalidFormat('request_id_header', 'an HTTP header name');
        }
    }

    public static function forEnvironment(Environment $environment): self
    {
        return new self($environment, null, null);
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function baseUri(): string
    {
        return rtrim($this->baseUriOverride ?? $this->environment->baseUri(), '/');
    }

    public function tokenUri(): string
    {
        return $this->baseUri() . UrssafEndpoints::TOKEN_PATH;
    }

    /**
     * @param string $path resource path, starting with "/", relative to the base path of the target
     */
    public function apiUri(string $path): string
    {
        return $this->baseUri() . $this->environment->basePath() . $path;
    }

    public function scope(): string
    {
        return $this->environment->scope();
    }

    public function acceptLanguage(): string
    {
        return $this->acceptLanguage;
    }

    public function apiTimeZone(): DateTimeZone
    {
        return $this->apiTimeZone;
    }

    public function requestIdHeader(): ?string
    {
        return $this->requestIdHeader;
    }

    public function tokenSafetyMarginSeconds(): int
    {
        return $this->tokenSafetyMarginSeconds;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }
}
