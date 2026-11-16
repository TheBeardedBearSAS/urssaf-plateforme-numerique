<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\OperationNotSupported;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\PlatformPaths;

/**
 * Live checks against the technical test API ("Bac a sable"). Environment: URSSAF_TEST_API_CLIENT_ID /
 * URSSAF_TEST_API_CLIENT_SECRET (required, otherwise skipped) and URSSAF_TEST_API_PLATFORM_ID.
 */
#[Group('contract')]
final class TestApiContractTest extends TestCase
{
    public function testObtainsAnAccessTokenOnTheTestApi(): void
    {
        $testApi = ContractEnvironment::testApi();

        $token = $testApi->tokenProvider->accessToken($testApi->credentials, Operation::SearchIdentifications);

        static::assertNotSame('', $token);
    }

    public function testTheGatewayRefusesTheNewsFeedLocally(): void
    {
        $testApi = ContractEnvironment::testApi();

        $this->expectException(OperationNotSupported::class);

        $testApi->gateway->newsFeed($testApi->tenantId, ContractEnvironment::platform($testApi), PageRequest::firstPage());
    }

    public function testTheTestApiDoesNotPublishTheNewsFeed(): void
    {
        $testApi = ContractEnvironment::testApi();

        try {
            $testApi->client->send(
                $testApi->tenantId,
                $testApi->credentials,
                ApiRequest::get(Operation::GetNewsFeed, PlatformPaths::newsFeed(ContractEnvironment::platform($testApi)), []),
            );
        } catch (UnexpectedUrssafResponse $unexpected) {
            static::assertSame(404, $unexpected->getCode());

            return;
        }

        static::fail('The test API must answer 404 on the news feed.');
    }
}
