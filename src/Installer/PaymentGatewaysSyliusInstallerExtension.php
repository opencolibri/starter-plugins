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
 * Asks which payment gateways the app should have when a Sylius service is added with
 * "castor docker:service:install sylius", then brings the scaffolded app to them once Sylius is ready.
 *
 * Sylius-Standard 2.3+ already requires the gateways (Stripe, Mollie, PayPal…): the ones not picked are removed with
 * sylius:remove, and only the picked ones it lacks are added with sylius:add.
 */
final readonly class PaymentGatewaysSyliusInstallerExtension implements SyliusInstallerExtensionInterface
{
    public function getInputs(): array
    {
        $gateways = PluginTasks::group(GroupedPluginInstallerInterface::PAYMENT);

        if ([] === $gateways) {
            return [];
        }

        // Every gateway by default, the way Sylius-Standard ships them.
        return [
            new Input('payment_gateways', 'Payment gateways', InputType::Choice, $gateways, $gateways, true),
        ];
    }

    public function afterScaffold(App $app, array $answers): void
    {
        $wanted = $answers['payment_gateways'] ?? null;

        // No answer (a non-interactive install without the option): keep what Sylius-Standard ships.
        if (!\is_array($wanted)) {
            return;
        }

        [$toAdd, $toRemove] = self::changes(
            array_map(static fn(mixed $gateway): string => (string) $gateway, array_values($wanted)),
            PluginTasks::group(GroupedPluginInstallerInterface::PAYMENT),
            PluginTasks::installed($app->directory()),
        );

        if ([] !== $toRemove) {
            run('castor sylius:remove --no-interaction ' . implode(' ', array_map(escapeshellarg(...), $toRemove)));
        }

        if ([] !== $toAdd) {
            run('castor sylius:add --no-interaction ' . implode(' ', array_map(escapeshellarg(...), $toAdd)));
        }
    }

    /**
     * @param list<string> $wanted    the gateways picked
     * @param list<string> $gateways  every payment gateway the starter can add
     * @param list<string> $installed the plugins the app already requires
     *
     * @return array{0: list<string>, 1: list<string>} the gateways to add, and the installed ones to remove
     */
    public static function changes(array $wanted, array $gateways, array $installed): array
    {
        $installedGateways = array_values(array_intersect($gateways, $installed));

        return [
            array_values(array_diff(array_intersect($wanted, $gateways), $installedGateways)),
            array_values(array_diff($installedGateways, $wanted)),
        ];
    }
}
