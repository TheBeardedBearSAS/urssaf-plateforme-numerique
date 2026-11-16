<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Query;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetDeclaration;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetIdentification;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetIdentificationHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeed;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetNewsFeedHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchDeclarations;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchDeclarationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchIdentifications;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\SearchIdentificationsHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\Page;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Pagination\PageInfo;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\PageRequest;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Search\SubmissionSearch;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\DeclarationType;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Submission\UrssafReference;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\DeclarationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Wire\IdentificationMapper;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Unit\Infrastructure\Urssaf\Wire\WireFixtures;

/**
 * The live queries resolve the platform of the tenant, call the gateway once and return its answer untouched.
 */
final class LiveQueriesTest extends TestCase
{
    private const TARGET = 'tenant-1|platform-1';

    public function testGetsAnIdentification(): void
    {
        $harness = new Harness();
        $identification = IdentificationMapper::map(WireFixtures::fixture('identification_response_handbuilt'), new DateTimeZone('UTC'));
        $harness->gateway->willGetIdentification($identification);
        $handler = new GetIdentificationHandler($harness->gateway, $harness->platforms);

        $result = $handler(new GetIdentification(Harness::tenant(), UrssafReference::fromString('urssaf-1')));

        static::assertSame($identification, $result);
        static::assertSame(['get_identification:urssaf-1'], $harness->gateway->recorded()->calls());
        static::assertSame([self::TARGET], $harness->gateway->recorded()->targets());
    }

    public function testGetsADeclarationOnThePlatformOfTheTenant(): void
    {
        $harness = new Harness();
        $declaration = DeclarationMapper::map(WireFixtures::fixture('declaration_response_handbuilt'), new DateTimeZone('UTC'));
        $harness->gateway->willGetDeclaration($declaration);
        $handler = new GetDeclarationHandler($harness->gateway, $harness->platforms);

        $result = $handler(new GetDeclaration(Harness::tenant(Harness::OTHER_TENANT), UrssafReference::fromString('urssaf-2')));

        static::assertSame($declaration, $result);
        static::assertSame(['get_declaration:urssaf-2'], $harness->gateway->recorded()->calls());
        static::assertSame(['tenant-2|platform-2'], $harness->gateway->recorded()->targets());
    }

    public function testSearchesIdentifications(): void
    {
        $harness = new Harness();
        $page = Page::of([IdentificationMapper::map(WireFixtures::fixture('identification_response_handbuilt'), new DateTimeZone('UTC'))], PageInfo::unknown());
        $harness->gateway->willSearchIdentifications($page);
        $search = SubmissionSearch::all();

        $result = (new SearchIdentificationsHandler($harness->gateway, $harness->platforms))(
            new SearchIdentifications(Harness::tenant(), $search),
        );

        static::assertSame($page, $result);
        static::assertSame(['search_identifications'], $harness->gateway->recorded()->calls());
        static::assertSame([$search], $harness->gateway->recorded()->searches());
        static::assertSame([self::TARGET], $harness->gateway->recorded()->targets());
    }

    public function testRefusesAnIdentificationSearchWithADeclarationType(): void
    {
        $harness = new Harness();
        $search = SubmissionSearch::of(PageRequest::firstPage(), null, null, null, DeclarationType::Declaration);
        $handler = new SearchIdentificationsHandler($harness->gateway, $harness->platforms);

        try {
            $handler(new SearchIdentifications(Harness::tenant(), $search));
            static::fail('The type must be refused.');
        } catch (InvalidValue $refused) {
            static::assertSame('submission_search.type_not_supported', $refused->violationCode());
        }
        static::assertSame([], $harness->gateway->recorded()->calls());
    }

    public function testSearchesDeclarationsWithTheirType(): void
    {
        $harness = new Harness();
        $page = Page::of([DeclarationMapper::map(WireFixtures::fixture('declaration_response_handbuilt'), new DateTimeZone('UTC'))], PageInfo::unknown());
        $harness->gateway->willSearchDeclarations($page);
        $search = SubmissionSearch::of(PageRequest::firstPage(), null, null, null, DeclarationType::Regularisation);

        $result = (new SearchDeclarationsHandler($harness->gateway, $harness->platforms))(
            new SearchDeclarations(Harness::tenant(), $search),
        );

        static::assertSame($page, $result);
        static::assertSame(['search_declarations'], $harness->gateway->recorded()->calls());
        static::assertSame([$search], $harness->gateway->recorded()->searches());
    }

    public function testATenantWithoutPlatformIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();
        $handler = new GetNewsFeedHandler($harness->gateway, $harness->platforms);

        try {
            $handler(new GetNewsFeed(Harness::tenant('tenant-9'), PageRequest::firstPage()));
            static::fail('The tenant has no platform.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('platform.not_found', $notFound->violationCode());
        }
        static::assertSame([], $harness->gateway->recorded()->calls());
    }
}
