<?php
declare(strict_types=1);

namespace App\Providers;

/**
 * Descubre los conectores disponibles: cada carpeta src/Providers/{Nombre}/
 * con una clase {Nombre}Provider que implemente ProviderInterface.
 */
final class ProviderRegistry
{
    /** @var array<string, class-string<ProviderInterface>>|null */
    private static ?array $providers = null;

    /** @return array<string, class-string<ProviderInterface>> código => clase */
    public static function all(): array
    {
        if (self::$providers === null) {
            self::$providers = [];
            foreach (glob(__DIR__ . '/*/*Provider.php') ?: [] as $file) {
                $dir = basename(dirname($file));
                $class = __NAMESPACE__ . '\\' . $dir . '\\' . basename($file, '.php');
                if (class_exists($class) && is_subclass_of($class, ProviderInterface::class)
                    && !(new \ReflectionClass($class))->isAbstract()) {
                    self::$providers[$class::code()] = $class;
                }
            }
            ksort(self::$providers);
        }
        return self::$providers;
    }

    /** @return class-string<ProviderInterface>|null */
    public static function get(string $code): ?string
    {
        return self::all()[$code] ?? null;
    }

    /** Para pruebas */
    public static function register(string $class): void
    {
        self::all();
        self::$providers[$class::code()] = $class;
    }
}
