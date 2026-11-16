<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Declaration\DeclarationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Identification\IdentificationSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\NewsFeed\NewsItem;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;

/**
 * Live checks against the real-exchange sandbox "api-edi". Environment: URSSAF_SANDBOX_CLIENT_ID /
 * URSSAF_SANDBOX_CLIENT_SECRET (required), URSSAF_SANDBOX_PLATFORM_ID (tests that need a platform are skipped without
 * it) and URSSAF_SANDBOX_BASE_URI (optional host override).
 */
#[Group('contract')]
final class SandboxContractTest extends TestCase
{
    /** Well-formed (UUID v4) but fictitious reference, as every documented urssafRef is a UUID. */
    private const UNKNOWN_URSSAF_REF = '00000000-0000-4000-8000-000000000000';

    public function testObtainsAnAccessTokenOnTheSandboxHost(): void
    {
        $sandbox = ContractEnvironment::sandbox();

        $token = $sandbox->tokenProvider->accessToken($sandbox->credentials, Operation::SearchIdentifications);

        static::assertNotSame('', $token);
    }

    public function testSearchesIdentificationsAndAnswersAPageWithPaginationHeaders(): void
    {
        $sandbox = ContractEnvironment::sandbox();

        $page = $sandbox->gateway->searchIdentifications(
            $sandbox->tenantId,
            ContractEnvironment::platform($sandbox),
            SubmissionSearch::all(),
        );

        static::assertContainsOnlyInstancesOf(IdentificationSubmission::class, $page->items());
        self::assertPaginationFiguresAreKnown($page);
    }

    public function testSearchesDeclarationsAndAnswersAPageWithPaginationHeaders(): void
    {
        $sandbox = ContractEnvironment::sandbox();

        $page = $sandbox->gateway->searchDeclarations(
            $sandbox->tenantId,
            ContractEnvironment::platform($sandbox),
            SubmissionSearch::all(),
        );

        static::assertContainsOnlyInstancesOf(DeclarationSubmission::class, $page->items());
        self::assertPaginationFiguresAreKnown($page);
    }

    public function testGetOfAnUnknownIdentificationIsNotFound(): void
    {
        $sandbox = ContractEnvironment::sandbox();

        try {
            $sandbox->gateway->getIdentification(
                $sandbox->tenantId,
                ContractEnvironment::platform($sandbox),
                UrssafReference::fromString(self::UNKNOWN_URSSAF_REF),
            );
        } catch (UrssafResourceNotFound $notFound) {
            static::assertSame(Operation::GetIdentification, $notFound->operation());

            return;
        } catch (UrssafRequestRejected $rejected) {
            // 400 is only declared generically on every operation: report it, with its raw error codes, without failing.
            static::markTestIncomplete('Unknown urssafRef answered ' . $rejected->httpStatus() . ' instead of 404: ' . $rejected->getMessage());
        }

        static::fail('An unknown urssafRef must not be found.');
    }

    public function testGetOfAnUnknownDeclarationIsNotFound(): void
    {
        $sandbox = ContractEnvironment::sandbox();

        try {
            $sandbox->gateway->getDeclaration(
                $sandbox->tenantId,
                ContractEnvironment::platform($sandbox),
                UrssafReference::fromString(self::UNKNOWN_URSSAF_REF),
            );
        } catch (UrssafResourceNotFound $notFound) {
            static::assertSame(Operation::GetDeclaration, $notFound->operation());

            return;
        } catch (UrssafRequestRejected $rejected) {
            // 400 is only declared generically on every operation: report it, with its raw error codes, without failing.
            static::markTestIncomplete('Unknown urssafRef answered ' . $rejected->httpStatus() . ' instead of 404: ' . $rejected->getMessage());
        }

        static::fail('An unknown urssafRef must not be found.');
    }

    public function testNewsFeedAnswersAPageOnTheSandbox(): void
    {
        $sandbox = ContractEnvironment::sandbox();

        $page = $sandbox->gateway->newsFeed($sandbox->tenantId, ContractEnvironment::platform($sandbox), PageRequest::firstPage());

        static::assertContainsOnlyInstancesOf(NewsItem::class, $page->items());
        self::assertPaginationFiguresAreKnown($page);
    }

    /**
     * @param Page<covariant object> $page
     */
    private static function assertPaginationFiguresAreKnown(Page $page): void
    {
        $info = $page->info();

        static::assertNotNull($info->totalPages(), 'UCN-Total-Pages header missing.');
        static::assertNotNull($info->totalCount(), 'UCN-Total-Count header missing.');
        static::assertNotNull($info->currentPage(), 'UCN-Current-Page header missing.');
        static::assertNotNull($info->perPage(), 'UCN-Per-Page header missing.');
    }
}
