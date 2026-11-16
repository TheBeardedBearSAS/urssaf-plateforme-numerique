<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Standalone;

use Closure;
use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Clock;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\EventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IdGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Outbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\PlatformResolver;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\RateLimiter;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\SubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Urssaf\PlatformGateway;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemorySubmissionRepository;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Wires the package without a framework. Every "with" method returns a new builder.
 *
 * Defaults: in-memory repository and token store (tests and prototypes: give your own persistence and a shared token
 * store in production), the UTC system clock, random UUIDs, no event publisher, no outbox, the default workflow policy.
 *
 * One property and one "with" method per collaborator, hence the counts.
 *
 * @mago-expect lint:too-many-properties
 * @mago-expect lint:too-many-methods
 */
final class UrssafPlateformeNumeriqueBuilder
{
    private ?PlatformGateway $gateway = null;
    private TokenStore $tokenStore;
    private Clock $clock;
    private IdGenerator $ids;
    private EventPublisher $events;
    private ?LoggerInterface $logger = null;
    private ?RateLimiter $rateLimiter = null;
    private WorkflowPolicy $policy;
    private SubmissionRepository $submissions;
    private TransactionManager $transactions;
    private WorkflowStatistics $statistics;
    private Outbox $outbox;
    private ?IntegrationEventSink $sink = null;

    private function __construct(
        private readonly HttpTransport $transport,
        private readonly CredentialsProvider $credentials,
        private readonly PlatformResolver $platforms,
        private readonly ApiConfiguration $configuration,
    ) {
        $this->tokenStore = new InMemoryTokenStore();
        $this->clock = new UtcSystemClock();
        $this->ids = new RandomUuidGenerator(new SecureRandomBytes());
        $this->events = new NullEventPublisher();
        $this->policy = new WorkflowPolicy();
        $submissions = new InMemorySubmissionRepository();
        $this->submissions = $submissions;
        $this->transactions = new ImmediateTransactionManager();
        $this->statistics = new InMemoryWorkflowStatistics($submissions);
        $this->outbox = new IgnoredOutbox();
    }

    public static function create(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        PlatformResolver $platforms,
        ApiConfiguration $configuration,
    ): self {
        return new self($transport, $credentials, $platforms, $configuration);
    }

    /**
     * A token store shared by every process that calls URSSAF (PSR-6 / PSR-16 store).
     */
    public function withTokenStore(TokenStore $tokenStore): self
    {
        return $this->with(static function (self $builder) use ($tokenStore): void {
            $builder->tokenStore = $tokenStore;
        });
    }

    public function withClock(Clock $clock): self
    {
        return $this->with(static function (self $builder) use ($clock): void {
            $builder->clock = $clock;
        });
    }

    public function withIdGenerator(IdGenerator $ids): self
    {
        return $this->with(static function (self $builder) use ($ids): void {
            $builder->ids = $ids;
        });
    }

    /**
     * Where the domain events are published after each commit (for instance a Psr14EventPublisher).
     */
    public function withEventPublisher(EventPublisher $events): self
    {
        return $this->with(static function (self $builder) use ($events): void {
            $builder->events = $events;
        });
    }

    /**
     * The logger of the HTTP gateway and of the event publication failures.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(static function (self $builder) use ($logger): void {
            $builder->logger = $logger;
        });
    }

    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return $this->with(static function (self $builder) use ($rateLimiter): void {
            $builder->rateLimiter = $rateLimiter;
        });
    }

    public function withPolicy(WorkflowPolicy $policy): self
    {
        return $this->with(static function (self $builder) use ($policy): void {
            $builder->policy = $policy;
        });
    }

    /**
     * Your own persistence: the repository, its statistics and the transaction manager that makes the repository and
     * the outbox atomic.
     */
    public function withRepository(
        SubmissionRepository $submissions,
        WorkflowStatistics $statistics,
        TransactionManager $transactions,
    ): self {
        return $this->with(static function (self $builder) use ($submissions, $statistics, $transactions): void {
            $builder->submissions = $submissions;
            $builder->statistics = $statistics;
            $builder->transactions = $transactions;
        });
    }

    /**
     * Writes the integration events to the outbox (in the transaction of the repository) and relays them to the sink.
     */
    public function withOutbox(Outbox $outbox, IntegrationEventSink $sink): self
    {
        return $this->with(static function (self $builder) use ($outbox, $sink): void {
            $builder->outbox = $outbox;
            $builder->sink = $sink;
        });
    }

    /**
     * Replaces the HTTP gateway (for instance with the Testing\SimulatedPlatformGateway in tests).
     */
    public function withGateway(PlatformGateway $gateway): self
    {
        return $this->with(static function (self $builder) use ($gateway): void {
            $builder->gateway = $gateway;
        });
    }

    public function build(): UrssafPlateformeNumerique
    {
        return new UrssafPlateformeNumerique(
            $this->gateway(),
            $this->platforms,
            $this->submissions,
            $this->unitOfWork(),
            $this->clock,
            $this->ids,
            $this->policy,
            $this->statistics,
            $this->outbox,
            $this->sink,
        );
    }

    /**
     * @param Closure(self): void $change applied to a copy of the builder
     */
    private function with(Closure $change): self
    {
        $builder = clone $this;
        $change($builder);

        return $builder;
    }

    private function gateway(): PlatformGateway
    {
        return $this->gateway ?? UrssafGatewayFactory::createWith($this->transport, $this->credentials, $this->tokenStore, $this->configuration, $this->gatewayOptions());
    }

    private function unitOfWork(): UnitOfWork
    {
        $failures = $this->logger instanceof LoggerInterface ? new PsrLoggerFailureReporter($this->logger) : new IgnoredFailures();

        return new UnitOfWork($this->transactions, $this->events, $this->ids, $failures, $this->outbox);
    }

    private function gatewayOptions(): GatewayOptions
    {
        $options = GatewayOptions::defaults()->withClock($this->clock)->withIdGenerator($this->ids);
        if ($this->rateLimiter instanceof RateLimiter) {
            $options = $options->withRateLimiter($this->rateLimiter);
        }

        return $this->logger instanceof LoggerInterface ? $options->withLogger($this->logger) : $options;
    }
}
