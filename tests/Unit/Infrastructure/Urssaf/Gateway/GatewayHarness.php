<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Gateway;

use TheBeardedBear\UrssafPlateformeNumerique\Domain\Platform\PlatformId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\PlatformExchange;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStack;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Http\HttpStacks;

/**
 * The gateway over the PSR-18 mock client of the HTTP test stack.
 */
final readonly class GatewayHarness
{
    public const BASE = 'https://api-edi.urssaf.fr/api-odspn-be-platforms/v1';

    public function __construct(public HttpStack $stack, public UrssafHttpGateway $gateway) {}

    public static function sandbox(): self
    {
        return self::over(HttpStacks::sandbox());
    }

    public static function on(Environment $environment): self
    {
        return self::over(HttpStacks::configured(ApiConfiguration::forEnvironment($environment)));
    }

    public static function over(HttpStack $stack): self
    {
        return new self($stack, new UrssafHttpGateway(new PlatformExchange($stack->credentials, $stack->jsonClient()), $stack->configuration));
    }

    public static function platform(): PlatformId
    {
        return PlatformId::fromString('platform-1');
    }

    public static function reference(): UrssafReference
    {
        return UrssafReference::fromString('unique-file-submission-id-12345');
    }

    /**
     * Queues the token answer, then the API answer.
     *
     * @param array<string, string> $headers
     */
    public function answer(int $status, string $body, array $headers = []): void
    {
        $this->stack->respondToken();
        $this->stack->respond($status, $body, $headers);
    }
}
