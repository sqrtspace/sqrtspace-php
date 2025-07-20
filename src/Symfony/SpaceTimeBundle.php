<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Symfony bundle for SpaceTime integration
 */
class SpaceTimeBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
    
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Import services
        $container->import('../config/services.yaml');
        
        // Configure SpaceTime
        $container->parameters()
            ->set('spacetime.memory_limit', $config['memory_limit'] ?? '256M')
            ->set('spacetime.storage_path', $config['storage_path'] ?? '%kernel.project_dir%/var/spacetime')
            ->set('spacetime.chunk_strategy', $config['chunk_strategy'] ?? 'sqrt_n')
            ->set('spacetime.enable_checkpointing', $config['enable_checkpointing'] ?? true)
            ->set('spacetime.compression', $config['compression'] ?? true);
    }
    
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('memory_limit')
                    ->defaultValue('256M')
                    ->info('Maximum memory that SpaceTime operations can use')
                ->end()
                ->scalarNode('storage_path')
                    ->defaultValue('%kernel.project_dir%/var/spacetime')
                    ->info('Directory for temporary files')
                ->end()
                ->enumNode('chunk_strategy')
                    ->values(['sqrt_n', 'memory_based', 'fixed'])
                    ->defaultValue('sqrt_n')
                    ->info('Strategy for determining chunk sizes')
                ->end()
                ->booleanNode('enable_checkpointing')
                    ->defaultTrue()
                    ->info('Enable automatic checkpointing')
                ->end()
                ->booleanNode('compression')
                    ->defaultTrue()
                    ->info('Compress data in external storage')
                ->end()
                ->integerNode('compression_level')
                    ->defaultValue(6)
                    ->min(1)
                    ->max(9)
                    ->info('Compression level (1-9)')
                ->end()
            ->end();
    }
}