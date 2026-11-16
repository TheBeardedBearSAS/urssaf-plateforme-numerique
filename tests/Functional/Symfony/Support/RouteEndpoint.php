<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

/**
 * One route of the scripted URSSAF platform: answers a response (JSON unless the headers say otherwise), or fails like
 * a broken network, and records the requests it received.
 */
final class RouteEndpoint
{
    /** @var list<RequestInterface> */
    private array $requests = [];

    private ResponseInterface|ClientExceptionInterface $answer;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $status, string $body, array $headers = [])
    {
        $this->respond($status, $body, $headers);
    }

    /**
     * @param array<string, string> $headers added to (or replacing) the JSON content type
     */
    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->answer = new Response($status, $headers + ['Content-Type' => 'application/json'], $body);
    }

    public function failTransport(): void
    {
        $this->answer = new TransportFailure('connection refused');
    }

    public function calls(): int
    {
        return \count($this->requests);
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * @throws ClientExceptionInterface the scripted network failure
     */
    public function __invoke(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->answer instanceof ClientExceptionInterface) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
