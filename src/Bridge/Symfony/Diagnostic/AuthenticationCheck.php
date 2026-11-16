<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafPlateformeNumerique\Bridge\Symfony\Diagnostic;

use Closure;
use Override;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\Operation;
use TheBeardedBear\UrssafPlateformeNumerique\Domain\Shared\TenantId;
use TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use Throwable;

/**
 * URSSAF grants an access token to every tenant: any cached token is dropped first, so that it never hides refused
 * credentials. The token provider given here must use a token store of its own (the bundle gives it an in-memory one):
 * the tokens of the application are left untouched.
 */
final readonly class AuthenticationCheck implements InstallationCheck
{
    private const NAME = 'authentication';

    /**
     * @param Closure(): OAuth2TokenProvider $tokenProvider builds the provider lazily, so that a failure of the
     *                                                      configuration is reported by the configuration check
     */
    public function __construct(private CredentialsProvider $credentials, private Closure $tokenProvider) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if (!$context->remoteCallsAllowed) {
            return [new CheckResult(self::NAME, '', CheckStatus::Skipped, 'Not checked (--skip-remote).')];
        }

        return array_map($this->check(...), $context->tenants);
    }

    /**
     * A diagnostic reports every failure as a line of its report (an invalid environment, for instance, only fails when
     * the token provider is built), whatever its type.
     */
    private function check(TenantId $tenantId): CheckResult
    {
        try {
            $tokens = ($this->tokenProvider)();
            $credentials = $this->credentials->forTenant($tenantId);
            $tokens->invalidate($credentials);
            $tokens->accessToken($credentials, Operation::GetIdentification);
        } catch (Throwable $failure) {
            return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Failure, $failure->getMessage());
        }

        return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Ok, 'Access token granted by URSSAF.');
    }
}
