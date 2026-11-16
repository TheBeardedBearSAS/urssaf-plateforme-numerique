<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\Payload;

/**
 * Maps the HTTP answer of an API call to an {@see ApiResponse} or to the gateway exceptions (ADR 0002 and 0003).
 *
 * @internal
 */
final readonly class ResponseInterpreter
{
    public function __construct(private StreamFactoryInterface $streamFactory, private ErrorPayloadParser $errorParser) {}

    /**
     * @throws UrssafRequestRejected      HTTP 400
     * @throws UrssafAuthenticationFailed HTTP 401, 403
     * @throws UrssafResourceNotFound     HTTP 404 (single-resource reads and downloads)
     * @throws UrssafUnavailable          HTTP 429, 5xx (reads)
     * @throws OutcomeUnknown             upload: 5xx or unreadable 2xx answer
     * @throws UnexpectedUrssafResponse   any other status or unreadable body (reads)
     */
    public function interpret(ApiRequest $request, ResponseInterface $response, string $requestId): ApiResponse
    {
        $operation = $request->operation();
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return $this->success($request, $response, $requestId);
        }

        $errors = $this->errorParser->parseBody((string) $response->getBody());

        if ($status === 400) {
            throw UrssafRequestRejected::withErrors($operation, $status, $errors);
        }

        if ($status === 401 || $status === 403) {
            throw UrssafAuthenticationFailed::withStatus($status);
        }

        if ($status === 404 && self::isSingleResourceRead($operation)) {
            throw UrssafResourceNotFound::withErrors($operation, $errors);
        }

        if ($status === 429 || ($status >= 500 && !$operation->isUpload())) {
            throw UrssafUnavailable::withStatus($operation, $status, RetryAfter::seconds($response), $errors);
        }

        throw $status >= 500
            ? OutcomeUnknown::afterStatus($operation, $status)
            : UnexpectedUrssafResponse::unexpectedStatus($operation, $status);
    }

    private function success(ApiRequest $request, ResponseInterface $response, string $requestId): ApiResponse
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }

        if ($request->isDownload()) {
            return ApiResponse::binary(
                new PsrBinaryStream($response->getBody(), $this->streamFactory),
                $headers,
                $requestId,
            );
        }

        return ApiResponse::json($this->decode($request, (string) $response->getBody()), $headers, $requestId);
    }

    private static function isSingleResourceRead(Operation $operation): bool
    {
        return \in_array($operation, [
            Operation::GetIdentification,
            Operation::GetDeclaration,
            Operation::DownloadIdentificationFile,
            Operation::DownloadDeclarationFile,
        ], true);
    }

    private function decode(ApiRequest $request, string $body): Payload
    {
        try {
            return JsonDecoder::decode($body);
        } catch (UnexpectedUrssafResponse $exception) {
            throw $request->operation()->isUpload()
                ? OutcomeUnknown::unreadableAnswer($request->operation(), 'the body is not a JSON object or array')
                : $exception;
        }
    }
}
