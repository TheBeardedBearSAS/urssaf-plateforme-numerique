<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Command\WorkflowStatusCommand;
use TheBeardedBear\UrssafPlateformeNumerique\Standalone\UrssafPlateformeNumerique;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafPlateformeNumerique\Tests\Functional\Symfony\Support\Harness;

/**
 * Every use case handler of the Application layer is a public service of the bundle, exactly the ones that the
 * standalone facade exposes.
 */
final class HandlersRegistrationTest extends TestCase
{
    public function testEveryHandlerIsAPublicServiceWithTheOutboxEnabled(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]);

        $handlers = self::handlersOfTheApplicationLayer();
        static::assertCount(18, $handlers);
        foreach ($handlers as $handler) {
            static::assertTrue($harness->has($handler), $handler);
            static::assertInstanceOf($handler, $harness->get($handler));
        }
    }

    public function testTheHandlersAreTheOnesOfTheStandaloneFacade(): void
    {
        $returned = [];
        foreach ((new ReflectionClass(UrssafPlateformeNumerique::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($method->isConstructor() || $method->getName() === 'gateway' || !$type instanceof ReflectionNamedType) {
                continue;
            }
            $returned[] = $type->getName();
        }
        $registered = self::handlersOfTheApplicationLayer();
        sort($returned);
        sort($registered);

        static::assertSame($registered, $returned);
    }

    public function testTheOutboxHandlersAreAbsentWithoutTheOutbox(): void
    {
        $harness = Harness::boot();

        static::assertFalse($harness->has(RelayOutboxHandler::class));
        static::assertFalse($harness->has(PurgeOutboxHandler::class));
        static::assertTrue($harness->has(GetWorkflowOverviewHandler::class));
    }

    public function testTheOverviewIsAbsentWithACustomDriverWithoutStatistics(): void
    {
        $container = (new TestKernel(['persistence' => ['driver' => 'custom', 'repository' => 'test.submissions']]))->definitions();

        static::assertFalse($container->has(GetWorkflowOverviewHandler::class));
        static::assertFalse($container->has(WorkflowStatusCommand::class));
    }

    public function testTheOverviewIsRegisteredWithACustomDriverWithStatistics(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'custom', 'repository' => 'test.submissions', 'statistics' => 'test.statistics']]);
        $container = (new TestKernel(['persistence' => ['driver' => 'custom', 'repository' => 'test.submissions', 'statistics' => 'test.statistics']]))->definitions();

        static::assertTrue($harness->has(GetWorkflowOverviewHandler::class));
        static::assertTrue($container->getDefinition(WorkflowStatusCommand::class)->hasTag('console.command'));
        static::assertTrue($container->getDefinition(WorkflowStatusCommand::class)->isAutowired());
    }

    /**
     * @return list<class-string>
     */
    private static function handlersOfTheApplicationLayer(): array
    {
        $handlers = [];
        foreach (['Command', 'Query'] as $directory) {
            $files = glob(\dirname(__DIR__, 3) . '/src/Application/' . $directory . '/*Handler.php');
            \assert(\is_array($files), 'The handlers directory exists.');
            foreach ($files as $file) {
                $class = 'TheBeardedBear\UrssafPlateformeNumerique\Application\\' . $directory . '\\' . basename($file, '.php');
                \assert(class_exists($class), 'Every handler file declares its class.');
                $handlers[] = $class;
            }
        }

        return $handlers;
    }
}
