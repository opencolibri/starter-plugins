<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins;

use OpenColibri\Starter\Plugins\Installer\GroupedPluginInstallerInterface;
use OpenColibri\Starter\Plugins\Installer\PluginInstallerInterface;

/**
 * The categories of the plugins, for plugin pickers: the sections of docs/commands/plugins.md, in the same order. A
 * plugin gives its category with GroupedPluginInstallerInterface::group() (the "group" of #[AsPluginInstaller]); a
 * plugin of a category this enum does not know keeps it, labelled from its name.
 */
enum PluginCategory: string
{
    case Payment = GroupedPluginInstallerInterface::PAYMENT;
    case Security = GroupedPluginInstallerInterface::SECURITY;
    case Catalog = GroupedPluginInstallerInterface::CATALOG;
    case Orders = GroupedPluginInstallerInterface::ORDERS;
    case Developer = GroupedPluginInstallerInterface::DEVELOPER;

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Payment gateways',
            self::Security => 'Security and privacy',
            self::Catalog => 'Catalog and content',
            self::Orders => 'Orders and customers',
            self::Developer => 'Developer tools',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Payment => 'Take payments online: each gateway adds its payment method.',
            self::Security => 'Protect the accounts and the forms, and handle personal data.',
            self::Catalog => 'Pages, media and product bundles around the catalog.',
            self::Orders => 'What happens after the checkout, and what customers keep.',
            self::Developer => 'Tooling and monitoring for the people who build the shop.',
        };
    }

    /**
     * @return array{name: string, label: string, description: ?string}|null the category of the plugin, null without any
     */
    public static function describe(PluginInstallerInterface $installer): ?array
    {
        $group = $installer instanceof GroupedPluginInstallerInterface ? $installer->group() : null;

        if (null === $group || '' === $group) {
            return null;
        }

        $category = self::tryFrom($group);

        return null === $category
            ? ['name' => $group, 'label' => ucfirst(str_replace('_', ' ', $group)), 'description' => null]
            : ['name' => $category->value, 'label' => $category->label(), 'description' => $category->description()];
    }

    /**
     * @param list<string> $categories
     *
     * @return list<string> the categories in the order of this enum, then the unknown ones
     */
    public static function sort(array $categories): array
    {
        $known = array_values(array_intersect(array_column(self::cases(), 'value'), $categories));

        return [...$known, ...array_values(array_diff($categories, $known))];
    }
}
