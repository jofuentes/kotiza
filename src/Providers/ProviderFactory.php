<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Crypto;
use App\Providers\Http\CallLogger;
use App\Providers\Http\DbCallLogger;
use App\Providers\Http\DbTokenStore;
use App\Providers\Http\HttpClientFactory;
use App\Providers\Http\TokenStore;

/** Crea instancias de conectores listas para usar */
final class ProviderFactory
{
    public function __construct(
        private TokenStore $tokens,
        private CallLogger $logger,
        private ?\Closure $handler = null,
        private ?\App\Providers\Catalog\LocalCatalog $catalog = null,
    ) {
    }

    public static function default(): self
    {
        return new self(new DbTokenStore(Crypto::fromEnv()), new DbCallLogger());
    }

    public function make(ProviderAccount $account): ProviderInterface
    {
        $class = ProviderRegistry::get($account->providerCode)
            ?? throw new ProviderException("El conector «{$account->providerCode}» no está instalado.");
        $http = HttpClientFactory::make($account->id, $this->logger, $this->handler);
        return new $class($account, $http, $this->tokens, $this->catalog ?? new \App\Providers\Catalog\DbLocalCatalog());
    }
}
