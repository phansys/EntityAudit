<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SimpleThings\EntityAudit\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SimpleThingsEntityAuditExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('auditable.xml');

        $configurables = [
            'connection',
            'entity_manager',
            'audited_entities',
            'table_prefix',
            'table_suffix',
            'revision_field_name',
            'revision_type_field_name',
            'revision_table_name',
            'revision_id_field_type',
            'global_ignore_columns',
        ];

        foreach ($configurables as $key) {
            $container->setParameter('simplethings.entityaudit.'.$key, $config[$key]);
        }

        foreach ($config['service'] as $key => $service) {
            if (null !== $service) {
                $container->setAlias('simplethings_entityaudit.'.$key, $service);
            }
        }

        $this->fixParametersFromDoctrineEventSubscriberTag($container, [
            'simplethings_entityaudit.log_revisions_listener',
            'simplethings_entityaudit.create_schema_listener',
        ]);
    }

    private function fixParametersFromDoctrineEventSubscriberTag(ContainerBuilder $container, array $definitionNames): void
    {
        foreach ($definitionNames as $definitionName) {
            $definition = $container->getDefinition($definitionName);

            $tags = $definition->getTag('doctrine.event_subscriber');
            $definition->clearTag('doctrine.event_subscriber');

            foreach ($tags as $id => $attributes) {
                if (isset($attributes['connection'])) {
                    $connectionParameterName = trim($attributes['connection'], '%');
                    $attributes['connection'] = $container->hasParameter($connectionParameterName) ? $container->getParameter($connectionParameterName) : $attributes['connection'];
                }

                $definition->addTag('doctrine.event_subscriber', $attributes);
            }
        }
    }
}
