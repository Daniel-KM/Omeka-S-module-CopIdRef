<?php declare(strict_types=1);

namespace CopIdRef\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

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
            ])
        ;

        $this->getInputFilter()
            ->add([
                'name' => 'copidref_available_resources',
                'required' => false,
            ])
        ;
    }
}
