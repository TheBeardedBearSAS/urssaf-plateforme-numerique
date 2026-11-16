<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RefreshSubmission;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RefreshSubmissionHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Dto\UploadOutcome;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFile;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\DownloadReportFileHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeedHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchDeclarations;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchDeclarationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchIdentifications;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchIdentificationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\FileReference;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Scenario;

/**
 * Every route of the scripted URSSAF platform answers the real gateway of the bundle (the support of the command tests).
 */
final class HttpScriptTest extends TestCase
{
    public function testTheUploadsAndTheReadsOfBothKinds(): void
    {
        $harness = Harness::boot();
        $tenant = TenantId::fromString(Scenario::TENANT);

        $identification = Scenario::uploadedIdentification($harness);
        $declaration = Scenario::uploadedDeclaration($harness, DeclarationType::Declaration, Scenario::TENANT, 'submission-2');
        $regularisation = Scenario::uploadedDeclaration($harness, DeclarationType::Regularisation, Scenario::TENANT, 'submission-3');
        $refresh = $harness->get(RefreshSubmissionHandler::class);

        static::assertSame(HttpScript::IDENTIFICATION_REFERENCE, $identification->urssafReference?->value());
        static::assertSame(HttpScript::DECLARATION_REFERENCE, $declaration->urssafReference?->value());
        static::assertSame(UploadOutcome::Uploaded, $regularisation->outcome);
        static::assertSame('accepted', $refresh(RefreshSubmission::of($tenant, $identification->submissionId))->state);
        static::assertSame('accepted', $refresh(RefreshSubmission::of($tenant, $declaration->submissionId))->state);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_UPLOAD_IDENTIFICATION));
        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_UPLOAD_DECLARATION));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_GET_IDENTIFICATION));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_GET_DECLARATION));
        static::assertSame('type=DECLARATION', $harness->urssaf->requests(HttpScript::ROUTE_UPLOAD_DECLARATION)[0]->getUri()->getQuery());
    }

    public function testTheSearchesTheDownloadsAndTheNewsFeed(): void
    {
        $harness = Harness::boot();
        $tenant = TenantId::fromString(Scenario::TENANT);
        $file = FileReference::of(HttpScript::GLOBAL_REPORT_FILE_ID, null);
        $download = $harness->get(DownloadReportFileHandler::class);

        $identifications = $harness->get(SearchIdentificationsHandler::class)(new SearchIdentifications($tenant, SubmissionSearch::all()));
        $declarations = $harness->get(SearchDeclarationsHandler::class)(new SearchDeclarations($tenant, SubmissionSearch::all()));
        $identificationReport = $download(DownloadReportFile::identification($tenant, UrssafReference::fromString(HttpScript::IDENTIFICATION_REFERENCE), $file));
        $declarationReport = $download(DownloadReportFile::declaration($tenant, UrssafReference::fromString(HttpScript::DECLARATION_REFERENCE), $file));
        $news = $harness->get(GetNewsFeedHandler::class)(new GetNewsFeed($tenant, PageRequest::firstPage()));

        static::assertCount(1, $identifications->items());
        static::assertCount(1, $declarations->items());
        static::assertSame(HttpScript::REPORT_CONTENT, $identificationReport->stream()->read(1024));
        static::assertSame('application/octet-stream', $declarationReport->contentType());
        static::assertSame(HttpScript::REPORT_CONTENT, $declarationReport->stream()->read(1024));
        static::assertCount(2, $news->items());
        foreach ([HttpScript::ROUTE_SEARCH_IDENTIFICATIONS, HttpScript::ROUTE_SEARCH_DECLARATIONS, HttpScript::ROUTE_DOWNLOAD_IDENTIFICATION_FILE, HttpScript::ROUTE_DOWNLOAD_DECLARATION_FILE, HttpScript::ROUTE_NEWS_FEED] as $route) {
            static::assertSame(1, $harness->urssaf->calls($route), $route);
        }
    }

    public function testARouteCanBeScriptedToFail(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_UPLOAD_DECLARATION, 400, '{"errors":[{"code":"PNDE_RT01","message":"Fictitious refusal."}]}');
        $harness->urssaf->failTransport(HttpScript::ROUTE_UPLOAD_IDENTIFICATION);

        static::assertSame(UploadOutcome::Rejected, Scenario::uploadedDeclaration($harness)->outcome);
        static::assertSame(UploadOutcome::OutcomeUnknown, Scenario::uploadedIdentification($harness, Scenario::TENANT, 'submission-2')->outcome);
    }

    public function testTheBodiesFollowTheScriptedStatuses(): void
    {
        static::assertStringContainsString('"status": "IN_PROCESS"', HttpScript::identificationBody('IN_PROCESS'));
        static::assertStringContainsString('"urssafRef": "' . HttpScript::IDENTIFICATION_REFERENCE . '"', HttpScript::identificationBody('ACCEPTED'));
        static::assertStringContainsString('"paymentStatus": "NOT_INITIATED"', HttpScript::declarationBody('ACCEPTED', 'NOT_INITIATED'));
        static::assertSame('{"urssafRef":"x"}', HttpScript::uploadBody('x'));
    }
}
