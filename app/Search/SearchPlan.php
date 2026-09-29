<?php

declare(strict_types=1);

namespace App\Search;

final class SearchPlan
{
    public function __construct(
        public readonly ParsedQuery $parsed,
        public readonly array $variants = [],
        public readonly array $brandIds = [],
        public readonly array $shopIds = [],
        public readonly array $categoryIds = [],
        public readonly array $categoryPathNames = [],
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly ?float $minRating = null,
        public readonly ?float $maxRating = null,
        public readonly ?bool $inStock = null,
        public readonly array $attributeFilters = [],
        public readonly bool $typoEligible = false,
    ) {}

    public function hasTerm(): bool
    {
        return $this->variants !== [] || $this->parsed->phrases !== [];
    }

    public function tokens(): array
    {
        return array_map(static fn (array $group) => $group[0], $this->variants);
    }

    public function operatorsUsed(): array
    {
        $used = [];
        if ($this->parsed->brandTerms !== []) {
            $used[] = 'brand';
        }
        if ($this->parsed->shopTerms !== []) {
            $used[] = 'shop';
        }
        if ($this->parsed->categoryTerms !== []) {
            $used[] = 'category';
        }
        if ($this->parsed->minPrice !== null || $this->parsed->maxPrice !== null) {
            $used[] = 'price';
        }
        if ($this->parsed->minRating !== null) {
            $used[] = 'rating';
        }
        if ($this->parsed->inStock !== null) {
            $used[] = 'in';
        }
        if ($this->parsed->attributes !== []) {
            $used[] = 'attribute';
        }
        if ($this->parsed->mustNot !== []) {
            $used[] = 'exclude';
        }
        if ($this->parsed->phrases !== []) {
            $used[] = 'phrase';
        }

        return $used;
    }
}
