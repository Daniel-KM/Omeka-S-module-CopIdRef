<?php declare(strict_types=1);

namespace CopIdRef\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'copidref_available_resources',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'List of usable idref resources for the select', // @translate
                    'empty_options' => 'Ressource via IdRef', // @translate
                    'value_options' => [
                        'Nom de personne' => 'Nom de personne', // @translate
                        'Nom de collectivité' => 'Nom de collectivité', // @translate
                        'Nom commun' => 'Nom commun', // @translate
                        'Nom géographique' => 'Nom géographique', // @translate
                        'Famille' => 'Famille', // @translate
                        'Titre' => 'Titre', // @translate
                        'Auteur-Titre' => 'Auteur-Titre', // @translate
                        'Nom de marque' => 'Nom de marque', // @translate
                        'Ppn' => 'Ppn', // @translate
                        'Rcr' => 'Rcr', // @translate
                        'Tout' => 'Tout', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'copidref_available_resources',
                    'required' => false,
                ],
            ]);

        $this
            ->add([
                'name' => 'sync_records',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Sync records with IdRef', // @translate
                ],
                'attributes' => [
                    'id' => 'sync_records',
                    'class' => 'field-container',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ]);
        $fieldset = $this->get('sync_records');
        $fieldset
            ->add([
                'name' => 'mode',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Sync mode for properties', // @translate
                    'value_options' => [
                        'append' => 'Append new values', // @translate
                        'replace' => 'Replace all values', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'sync_mode',
                    // 'value' => 'empty',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'query',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Query to limit processed items', // @translate
                ],
                'attributes' => [
                    'id' => 'sync_query',
                ],
            ])
            ->add([
                'name' => 'properties',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'For properties', // @translate
                    'term_as_value' => true,
                    'prepend_value_options' => [
                        'all' => '[All properties]', // @translate
                    ],
                    'empty_option' => '',
                    'used_terms' => true,
                ],
                'attributes' => [
                    'id' => 'sync_properties',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties', // @translate
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'datatypes',
                // 'type' => BulkEditElement\DataTypeSelect::class,
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Datatypes used for mapping (configured in module Copie IdRef)', // @translate
                    'value_options' => [
                        'all' => '[All datatypes]', // @translate
                        'uri' => 'Uri', // @translate
                        'literal' => 'Literal', // @translate
                        'valuesuggest:idref:person' => 'IdRef Personnes', // @translate
                        'valuesuggest:idref:corporation' => 'IdRef Organisations', // @translate
                        'valuesuggest:geonames:geonames' => 'Geonames', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'sync_datatypes',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select datatypes…', // @translate
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'property_uri',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Property for url', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                    'used_terms' => true,
                ],
                'attributes' => [
                    'id' => 'sync_property_uri',
                    'class' => 'chosen-select',
                    'multiple' => false,
                    'data-placeholder' => 'Select property', // @translate
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'mapping_key',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Type à utiliser pour déterminer l’alignement quand le type de donnée n’est pas défini dans la notice', // @translate
                    'value_options' => [
                        'Personne' => 'Personne',
                        'Collectivité' => 'Collectivité',
                    ],
                ],
                'attributes' => [
                    'id' => 'sync_mapping_key',
                ],
            ])
            ->add([
                'name' => 'process',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Run in background', // @translate
                ],
                'attributes' => [
                    'id' => 'process',
                    'value' => 'Process', // @translate
                ],
            ])
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'copidref_available_resources',
                'required' => false,
            ])
        ;
        $inputFilter
            ->get('sync_records')
            ->add([
                'name' => 'mode',
                'required' => false,
            ])
            ->add([
                'name' => 'properties',
                'required' => false,
            ])
            ->add([
                'name' => 'datatypes',
                'required' => false,
            ])
            ->add([
                'name' => 'property_uri',
                'required' => false,
            ])
            ->add([
                'name' => 'mapping_key',
                'required' => false,
            ])
        ;
    }
}
