<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Output;

final class NewsFeedCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:pn:news';

    public function testPrintsTheNewsFeed(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertSame([
            ['Id', 'Date', 'Text'],
            ['45eea586-2dac-4dbd-a173-aadfc7eda6d8', '2026-03-25T15:35:23Z', 'Sandbox PNID_2025-11_4309999_001.xml.gz: new reports available.'],
            ['9bc3d9fd-80a0-420e-85e4-f94e9668cdff', '2026-03-25T15:13:44Z', 'Sandbox PNID_2025-11_4309999_001.xml.gz: new reports available.'],
        ], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('2 news item(s).', $tester->getDisplay());
    }

    public function testSendsThePageAndTheSize(): void
    {
        $harness = Harness::boot();
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--page' => '2', '--size' => '10']));

        $query = $harness->urssaf->requests(HttpScript::ROUTE_NEWS_FEED)[0]->getUri()->getQuery();
        static::assertStringContainsString('page=2', $query);
        static::assertStringContainsString('size=10', $query);
    }

    public function testSendsNoPagingByDefault(): void
    {
        $harness = Harness::boot();

        $harness->command(self::COMMAND)->execute([]);

        static::assertSame('', $harness->urssaf->requests(HttpScript::ROUTE_NEWS_FEED)[0]->getUri()->getQuery());
    }

    public function testTheOptionsAreDescribed(): void
    {
        $definition = (new Application(Harness::boot()->kernel))->find(self::COMMAND)->getDefinition();

        static::assertSame('Page number, from 1', $definition->getOption('page')->getDescription());
        static::assertSame('Page size, from 1 to 500', $definition->getOption('size')->getDescription());
    }

    public function testAMissingFieldIsPrintedAsADash(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_NEWS_FEED, 200, '[{}]');
        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));

        static::assertSame([['Id', 'Date', 'Text'], ['-', '-', '-']], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('1 news item(s).', $tester->getDisplay());
    }

    public function testRefusesAnInvalidPage(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['--page' => '0']));
        static::assertStringContainsString('The --page option must be a positive integer.', $tester->getDisplay());
        static::assertSame(2, $tester->execute(['--size' => 'many']));
        static::assertStringContainsString('The --size option must be a positive integer.', $tester->getDisplay());
    }

    public function testRefusesASizeOutOfRange(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(2, $tester->execute(['--size' => '501']));
        static::assertStringContainsString('page_request.size_out_of_range', $tester->getDisplay());
        static::assertStringContainsString('Invalid input: 1 error(s).', $tester->getDisplay());
    }

    public function testTheNewsFeedIsNotAvailableOnTheTestApi(): void
    {
        $tester = Harness::boot(['environment' => 'test_api'])->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('The news feed (getPlatformNewsFeed) is not available on the test API.', $tester->getDisplay());
    }

    public function testTheTenantIsRequiredWhenSeveralAreConfigured(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $tester = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%', 'platform_id' => 'platform-2']]])->command(self::COMMAND);

        static::assertSame(2, $tester->execute([]));
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-2']));
    }
}
