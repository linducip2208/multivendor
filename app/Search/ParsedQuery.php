<?php

declare(strict_types=1);

namespace App\Search;

final class ParsedQuery
{
    public function __construct(
        public readonly string $raw = '',
        public readonly string $text = '',
        public readonly array $phrases = [],
        public readonly array $rawTokens = [],
        public readonly array $mustNot = [],
        public readonly array $brandTerms = [],
        public readonly array $shopTerms = [],
        public readonly array $categoryTerms = [],
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly ?float $minRating = null,
        public readonly ?float $maxRating = null,
        public readonly ?bool $inStock = null,
        public readonly array $attributes = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->rawTokens === []
            && $this->phrases === []
            && $this->brandTerms === []
            && $this->shopTerms === []
            && $this->categoryTerms === [];
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'phrases' => $this->phrases,
            'tokens' => $this->rawTokens,
            'excluded' => $this->mustNot,
            'brand' => $this->brandTerms,
            'shop' => $this->shopTerms,
            'category' => $this->categoryTerms,
            'price' => [$this->minPrice, $this->maxPrice],
            'rating' => [$this->minRating, $this->maxRating],
            'in_stock' => $this->inStock,
            'attributes' => $this->attributes,
        ];
    }
}
