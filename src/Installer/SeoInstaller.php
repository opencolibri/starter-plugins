<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\PluginResourceCopier;

use function Castor\io;

final readonly class SeoInstaller implements GroupedPluginInstallerInterface
{
    public const string BUNDLE = 'Dedi\\SyliusSEOPlugin\\DediSyliusSEOPlugin';

    private const string ADAPTER = 'Dedi\\SyliusSEOPlugin\\SEO\\Adapter\\';

    private const string RICH_SNIPPET = 'Dedi\\SyliusSEOPlugin\\RichSnippet\\Adapter\\';

    private const string CREATE_CONTENT = <<<'PHP'
        protected function createReferenceableContent(): SEOContentInterface
        {
            return new SEOContent();
        }
        PHP;

    /**
     * The entities sylius-standard ships that get SEO contents or rich snippets: the interfaces and traits they get,
     * the classes their methods use, and these methods, all removed again by SeoRemover.
     *
     * @var array<string, array{interfaces: list<string>, traits: list<string>, imports: list<string>, methods: string}>
     */
    public const array ENTITIES = [
        'src/Entity/Product/Product.php' => [
            'interfaces' => [self::ADAPTER . 'ReferenceableInterface', self::RICH_SNIPPET . 'RichSnippetProductSubjectInterface'],
            'traits' => [self::ADAPTER . 'ReferenceableProductTrait', self::RICH_SNIPPET . 'RichSnippetProductSubjectTrait'],
            'imports' => ['Dedi\\SyliusSEOPlugin\\Entity\\SEOContent', 'Dedi\\SyliusSEOPlugin\\Entity\\SEOContentInterface', self::RICH_SNIPPET . 'RichSnippetSubjectInterface'],
            'methods' => <<<'PHP'
                public function getMetadataDescription(): ?string
                {
                    return $this->getReferenceableContent()->getMetadataDescription() ?: (null !== $this->getShortDescription() ? strip_tags($this->getShortDescription()) : null);
                }

                public function getOpenGraphMetadataImage(): ?string
                {
                    $image = $this->getImages()->first();

                    return $this->getReferenceableContent()->getOpenGraphMetadataImage() ?: (false !== $image ? $image->getPath() : null);
                }

                public function getRichSnippetSubjectParent(): ?RichSnippetSubjectInterface
                {
                    $taxon = $this->getMainTaxon();

                    return $taxon instanceof RichSnippetSubjectInterface ? $taxon : null;
                }

                public function getRichSnippetSubjectType(): string
                {
                    return 'product';
                }

                PHP . self::CREATE_CONTENT,
        ],
        'src/Entity/Product/ProductVariant.php' => [
            'interfaces' => [self::RICH_SNIPPET . 'RichSnippetProductVariantSubjectInterface'],
            'traits' => [self::RICH_SNIPPET . 'RichSnippetProductVariantSubjectTrait'],
            'imports' => [],
            'methods' => '',
        ],
        'src/Entity/Taxonomy/Taxon.php' => [
            'interfaces' => [self::ADAPTER . 'ReferenceableInterface', self::RICH_SNIPPET . 'RichSnippetSubjectInterface'],
            'traits' => [self::ADAPTER . 'ReferenceableTaxonTrait'],
            'imports' => ['Dedi\\SyliusSEOPlugin\\Entity\\SEOContent', 'Dedi\\SyliusSEOPlugin\\Entity\\SEOContentInterface'],
            'methods' => <<<'PHP'
                public function getMetadataDescription(): ?string
                {
                    return $this->getReferenceableContent()->getMetadataDescription() ?: (null !== $this->getDescription() ? strip_tags($this->getDescription()) : null);
                }

                public function getRichSnippetSubjectParent(): ?RichSnippetSubjectInterface
                {
                    $parent = $this->getParent();

                    return $parent instanceof RichSnippetSubjectInterface ? $parent : null;
                }

                public function getRichSnippetSubjectType(): string
                {
                    return 'taxon';
                }

                PHP . self::CREATE_CONTENT,
        ],
        'src/Entity/Channel/Channel.php' => [
            'interfaces' => [self::ADAPTER . 'ReferenceableInterface', self::ADAPTER . 'SeoAwareChannelInterface'],
            'traits' => [self::ADAPTER . 'ReferenceableChannelTrait', self::ADAPTER . 'SeoAwareChannelTrait'],
            'imports' => ['Dedi\\SyliusSEOPlugin\\Entity\\SEOContent', 'Dedi\\SyliusSEOPlugin\\Entity\\SEOContentInterface'],
            'methods' => self::CREATE_CONTENT,
        ],
    ];

    public function name(): string
    {
        return 'seo';
    }

    public function description(): string
    {
        return 'Dedi SEO: metadata, OpenGraph, noindex rules and rich snippets';
    }

    public function package(): string
    {
        return 'dedi/sylius-seo-plugin';
    }

    public function group(): string
    {
        return self::CATALOG;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding SEO plugin');

        // The mapped schema before the plugin, to generate the migration of the tables it changes.
        $schema = Database::mappedSchema($app);

        Composer::allowContribRecipes($app);
        // Without its config (copied below), the plugin bundle does not compile: clear the cache once it is there.
        Docker::run($app, 'composer require dedi/sylius-seo-plugin --no-scripts');
        Symfony::addBundle($app, self::BUNDLE);

        // The plugin config and routes, and the shop layout with its SEO title.
        PluginResourceCopier::copy($app, 'seo');

        foreach (self::ENTITIES as $entity => $wiring) {
            $file = new PhpFile($app->directory() . '/' . $entity);

            foreach ($wiring['interfaces'] as $interface) {
                $file->addInterface($interface);
            }

            foreach ($wiring['traits'] as $trait) {
                $file->addTrait($trait);
            }

            foreach ($wiring['imports'] as $import) {
                $file->addImport($import);
            }

            if ('' !== $wiring['methods']) {
                $file->addMethod($wiring['methods']);
            }

            $file->save();
        }

        Symfony::cacheClear($app);
        Docker::run($app, 'bin/console assets:install public');
        // The plugin only ships a 3.x to 4.x data migration: generate the one of its tables, once the others are run.
        Database::migrate($app);
        Database::generatePluginMigration($app, 'Add the SEO plugin', $schema);
        Database::migrate($app);
    }
}
