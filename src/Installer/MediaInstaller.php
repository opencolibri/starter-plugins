<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Core\Util\Yaml;

use function Castor\fs;
use function Castor\io;

final readonly class MediaInstaller implements GroupedPluginInstallerInterface
{
    /** The image tables the media column is added to. */
    private const array TABLES = ['sylius_avatar_image', 'sylius_product_image', 'sylius_taxon_image'];

    public function name(): string
    {
        return 'media';
    }

    public function description(): string
    {
        return 'Media management bundle for Symfony applications';
    }

    public function package(): string
    {
        return 'jolicode/media-bundle';
    }

    public function group(): string
    {
        return self::CATALOG;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Media plugin');

        // The mapped schema before the plugin, to generate the migration of the tables it changes.
        $schema = Database::mappedSchema($app);

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require jolicode/media-bundle');
        Symfony::addBundle($app, 'JoliCode\MediaBundle\Bridge\Sylius\JoliMediaSyliusBundle');

        Yaml::uncommentBlock(
            $app,
            'config/packages/joli_media.yaml',
            <<<'YAML'
                imports:
                    - { resource: '@JoliMediaSyliusBundle/config/app.php' }
                YAML,
        );

        Yaml::uncommentBlock(
            $app,
            'config/packages/joli_media.yaml',
            <<<'YAML'
                libraries:
                    default:
                        original:
                            flysystem: "filesystem.original.storage"
                            url_generator:
                                strategy: folder
                                path: /media/original/
                        cache:
                            flysystem: "filesystem.cache.storage"
                            must_store_when_generating_url: false
                            url_generator:
                                strategy: folder
                                path: /media/cache/
                YAML,
        );

        Yaml::uncommentBlock(
            $app,
            'config/routes/joli_media.yaml',
            <<<'YAML'
                _joli_media_sylius_admin:
                    resource: "@JoliMediaSyliusBundle/src/Admin/Controller/"
                    prefix: /admin/media
                YAML,
        );

        Yaml::appendToSection(
            $app,
            'config/services.yaml',
            'services',
            <<<'YAML'
                    # Filesystem Adapters for the Jolicode Media Bundle
                    filesystem.original.adapter:
                        class: League\Flysystem\Local\LocalFilesystemAdapter
                        arguments:
                            '$location': '%kernel.project_dir%/public/media/original'

                    filesystem.original.storage:
                        class: League\Flysystem\Filesystem
                        arguments:
                            '$adapter': '@filesystem.original.adapter'

                    filesystem.cache.adapter:
                        class: League\Flysystem\Local\LocalFilesystemAdapter
                        arguments:
                            '$location': '%kernel.project_dir%/public/media/cache'

                    filesystem.cache.storage:
                        class: League\Flysystem\Filesystem
                        arguments:
                            '$adapter': '@filesystem.cache.adapter'

                    # Form extensions to use the Jolicode media choice
                    JoliCode\MediaBundle\Bridge\Sylius\Admin\Form\Extension\AvatarImageTypeExtension: null
                    JoliCode\MediaBundle\Bridge\Sylius\Admin\Form\Extension\ProductImageTypeExtension: null
                    JoliCode\MediaBundle\Bridge\Sylius\Admin\Form\Extension\TaxonImageTypeExtension: null
                YAML,
        );

        $this->createMediaLibraryMenu($app);

        foreach (['Product/ProductImage', 'Taxonomy/TaxonImage', 'User/AvatarImage'] as $entity) {
            (new PhpFile($app->directory() . '/src/Entity/' . $entity . '.php'))
                ->addTrait(\JoliCode\MediaBundle\Bridge\Sylius\Doctrine\ORM\EntityWithMediaImageTrait::class) // @phpstan-ignore class.notFound
                ->save()
            ;
        }

        Symfony::cacheClear($app);
        // The media columns, in a migration generated once the others are run, which points them at the current images.
        Database::migrate($app);
        $migration = Database::generatePluginMigration($app, 'Add the media plugin', $schema);

        if (null !== $migration) {
            (new PhpFile($migration))
                ->appendToMethod('up', implode("\n", array_map(
                    static fn(string $table): string => \sprintf("\$this->addSql('UPDATE %s SET media = path');", $table),
                    self::TABLES,
                )))
                ->save()
            ;
        }

        Database::migrate($app);
        Assets::install($app);
    }

    private function createMediaLibraryMenu(App $app): void
    {
        $listener = $app->directory() . '/src/Menu/Admin/AdminMenuListener.php';

        fs()->dumpFile(
            $listener,
            <<<'PHP'
                <?php

                namespace App\Menu\Admin;

                use Knp\Menu\ItemInterface;
                use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
                use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

                #[AsEventListener(event: 'sylius.menu.admin.main')]
                final class AdminMenuListener
                {
                    public function __invoke(MenuBuilderEvent $event): void
                    {
                        $menu = $event->getMenu();

                        $this->addContentsSubMenu($menu);
                    }

                    private function addContentsSubMenu(ItemInterface $menu): void
                    {
                        $library = $menu
                            ->addChild('contents')
                            ->setLabel('Contents')
                            ->setLabelAttribute('icon', 'simple-icons:craftcms')
                            ->setExtra('always_open', true)
                        ;

                        $library->addChild('media_library', ['route' => 'joli_media_sylius_admin_explore'])
                            ->setLabel('media_library')
                            ->setExtra('translation_domain', 'JoliMediaSyliusBundle')
                        ;
                    }
                }

                PHP
        );
    }
}
