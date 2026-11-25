<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use Castor\Docker\Installer\Input;
use Castor\Docker\Installer\InputType;
use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Installer\SyliusInstallerExtensionInterface;
use OpenColibri\Starter\Plugins\Tasks\PluginTasks;

use function Castor\run;

/**
 * Asks which payment gateways to add when a Sylius service is added with "castor docker:service:install sylius",
 * then adds them with sylius:add once Sylius is ready. A fresh app has none to remove.
 */
final readonly class PaymentGatewaysSyliusInstallerExtension implements SyliusInstallerExtensionInterface
{
    public function getInputs(): array
    {
        $gateways = PluginTasks::group(GroupedPluginInstallerInterface::PAYMENT);

        if ([] === $gateways) {
            return [];
        }

        return [
            new Input('payment_gateways', 'Payment gateways', InputType::Choice, [], $gateways, true),
        ];
    }

    public function afterScaffold(App $app, array $answers): void
    {
        $gateways = $answers['payment_gateways'] ?? [];

        if (!\is_array($gateways) || [] === $gateways) {
            return;
        }

        run('castor sylius:add --no-interaction ' . implode(' ', array_map(static fn(mixed $gateway): string => escapeshellarg((string) $gateway), $gateways)));
    }
}
