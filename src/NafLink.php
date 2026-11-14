<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

/**
 * Un code correspondant dans la table de passage officielle, avec la part d'activité qu'il reprend.
 */
final class NafLink
{
    /**
     * @param list<CodeApe> $otherSources
     */
    public function __construct(
        private readonly CodeApe $code,
        private readonly string $intitule,
        private readonly string $commonContent,
        private readonly array $otherSources,
    ) {}

    /**
     * Code correspondant, dans la nomenclature d'arrivée.
     */
    public function code(): CodeApe
    {
        return $this->code;
    }

    /**
     * Intitulé complet du code correspondant.
     */
    public function intitule(): string
    {
        return $this->intitule;
    }

    /**
     * « Contenu commun des sous-classes en correspondance », tel que l'INSEE l'écrit : l'activité du code
     * de départ qui passe dans ce code (« tout le contenu de la sous-classe NAF rév. 2 », ou une liste
     * d'activités). C'est ce texte qui permet de choisir le bon code en cas d'éclatement.
     */
    public function commonContent(): string
    {
        return $this->commonContent;
    }

    /**
     * Autres codes de la nomenclature de départ qui aboutissent aussi à ce code (regroupement),
     * dans l'ordre de la table. Liste vide si le code n'est atteint que depuis le code de départ.
     *
     * @return list<CodeApe>
     */
    public function otherSources(): array
    {
        return $this->otherSources;
    }
}
