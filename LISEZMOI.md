IdRef (module pour Omeka S)
============================

> __Les nouvelles versions de ce module et l’assistance pour Omeka S version 3.0
> et supérieur sont disponibles sur [GitLab], qui semble mieux respecter les
> utilisateurs et la vie privée que le précédent entrepôt.__

See [English read me].

[IdRef] est un module pour [Omeka S] qui permet d’utiliser les référentiels [IdRef de l’Abes]
pour créer de nouveaux contenus à partir des données IdRef. C’est utile en
particulier lorsque l’on souhaite créer des items de type "auteur" pour ses
contenus. Si l’auteur n’est pas dans Omeka, il est possible de chercher dans la
base IdRef, de l’y créer si besoin, et de récupérer les données dans un nouvel
item, tout en le liant avec le document en cours d’édition.

Si vous avez seulement besoin de lier des références pour des propriétés, le
module [Value Suggest] doit être utilisé.

Ce module ne doit pas être confondu avec IdRef de BibLibre, dont les
fonctionnalités ont été intégrées dans [Value Suggest].


Installation
------------

Consulter la documentation générale pour [installer un module].

* À partir du zip

Télécharger la dernière livraison de [IdRef.zip] à partir de la liste des "releases",
décompressez les dans le dossier "modules", et renommer le dossier `IdRef`.

* À partir des sources et pour le développement

Si le module a été installé depuis les sources, renommer le dossier du module en `IdRef`.


Utilisation
-----------

Lors de la création ou de l’édition d’une notice, cliquez sur `Ressource Omeka`,
cherchez si la ressource n’existe pas déjà dans votre base, puis si besoin
lancer la recherche dans IdRef via le sélecteur.

Dans la fenêtre IdRef, cherchez votre notice ou créez en une nouvelle (un compte
peut être demandé à l’Abes) et cliquez sur le bouton "Lier la notice". Cette
dernière sera créée automatiquement dans Omeka et liée à la notice en cours.

Un alignement par défaut entre l’Unimarc et les ontologies Omeka est présent
dans le dossier `data/mappings`. Vous pouvez le modifier ou le compléter si
besoin. Seuls les auteurs et collectivités sont prédéfinis, outre une
correspondance générique.


TODO
----

- [ ] Moderniser le js (promise).
- [ ] Remplir une fiche nouvelle (cf. module Advanced Resource Template).
- [ ] Intégrer l’alignement simplifié du module Advanced Resource Template


Attention
---------

Utilisez-le à vos propres risques.

Il est toujours recommandé de sauvegarder vos fichiers et vos bases de données
et de vérifier vos archives régulièrement afin de pouvoir les reconstituer si
nécessaire.


Dépannage
---------

Voir les problèmes en ligne sur la page des [questions du module] du GitLab.


Licence
-------

Ce module est publié sous la licence [CeCILL v2.1], compatible avec [GNU/GPL] et
approuvée par la [FSF] et l’[OSI].

Ce logiciel est régi par la licence CeCILL de droit français et respecte les
règles de distribution des logiciels libres. Vous pouvez utiliser, modifier
et/ou redistribuer le logiciel selon les termes de la licence CeCILL telle que
diffusée par le CEA, le CNRS et l’INRIA à l’URL suivante "http://www.cecill.info".

En contrepartie de l’accès au code source et des droits de copie, de
modification et de redistribution accordée par la licence, les utilisateurs ne
bénéficient que d’une garantie limitée et l’auteur du logiciel, le détenteur des
droits patrimoniaux, et les concédants successifs n’ont qu’une responsabilité
limitée.

À cet égard, l’attention de l’utilisateur est attirée sur les risques liés au
chargement, à l’utilisation, à la modification et/ou au développement ou à la
reproduction du logiciel par l’utilisateur compte tenu de son statut spécifique
de logiciel libre, qui peut signifier qu’il est compliqué à manipuler, et qui
signifie donc aussi qu’il est réservé aux développeurs et aux professionnels
expérimentés ayant des connaissances informatiques approfondies. Les
utilisateurs sont donc encouragés à charger et à tester l’adéquation du logiciel
à leurs besoins dans des conditions permettant d’assurer la sécurité de leurs
systèmes et/ou de leurs données et, plus généralement, à l’utiliser et à
l’exploiter dans les mêmes conditions en matière de sécurité.

Le fait que vous lisez actuellement ce document signifie que vous avez pris
connaissance de la licence CeCILL et que vous en acceptez les termes.


Copyright
---------

* Copyright Daniel Berthereau, 2021 (voir [Daniel-KM] sur GitLab)
* Copyright Abes, (voir les fichiers présents dans [la présentation])

Ces fonctionnalités sont destinées à la future bibliothèque numérique [Manioc]
de l’Université des Antilles et Université de la Guyane, actuellement gérée avec
[Greenstone].


[IdRef]: https://gitlab.com/Daniel-KM/Omeka-S-module-IdRef
[English read me]: https://gitlab.com/Daniel-KM/Omeka-S-module-IdRef/blob/master/README.md
[Omeka S]: https://omeka.org/s
[IdRef de l’Abes]: https://www.idref.fr
[Value Suggest]: https://github.com/omeka-s-modules/ValueSuggest
[installer un module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[IdRef.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-IdRef/-/releases
[questions du module]: https://gitlab.com/Daniel-KM/Omeka-S-module-IdRef/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: http://http://opensource.org/licenses/MIT
[la présentation]: http://documentation.abes.fr/aideidrefdeveloppeur/index.html
[Manioc]: http://www.manioc.org
[Greenstone]: http://www.greenstone.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
