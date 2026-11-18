<?php

use App\Http\Controllers\Api\Category\Services\CategorySaveService;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->card = ['label' => 'Woody', 'title' => 'Oud Noir', 'description' => 'Smoked oud, amber and a trace of leather.', 'color' => '#c9915a'];
    $this->saveCards = fn (array $cards, ?int $id = null) => app(CategorySaveService::class)->handle(
        Request::create('/', 'POST', ['name' => 'Fragrance', 'active' => true, 'content_cards' => $cards]), $id,
    );
});

it('stores up to four ordered content cards and exposes them on active categories', function () {
    $cards = [$this->card, [...$this->card, 'title' => 'Padma'], [...$this->card, 'title' => 'Mango Leaf'], [...$this->card, 'title' => 'Monsoon']];
    $category = ($this->saveCards)($cards);
    expect($category->fresh()->content_cards)->toEqual($cards);
    expect(array_column($category->fresh()->content_cards, 'title'))->toBe(['Oud Noir', 'Padma', 'Mango Leaf', 'Monsoon']);
    $this->getJson('/api/storefront/categories')->assertOk()->assertJsonFragment(['content_cards' => $cards]);
});

it('rejects more than four cards', function () {
    expect(fn () => ($this->saveCards)(array_fill(0, 5, $this->card)))->toThrow(ValidationException::class);
});

it('rejects invalid colors and incomplete cards', function () {
    expect(fn () => ($this->saveCards)([[...$this->card, 'color' => 'red']]))->toThrow(ValidationException::class);
    expect(fn () => ($this->saveCards)([['title' => 'Incomplete']]))->toThrow(ValidationException::class);
});

it('clears cards without deleting the category and hides inactive category content', function () {
    $category = ($this->saveCards)([$this->card]);
    ($this->saveCards)([], $category->id);
    expect($category->fresh()->content_cards)->toBe([]);
    $category->update(['content_cards' => [$this->card], 'active' => false]);
    $this->getJson('/api/storefront/categories')->assertOk()->assertJsonMissing(['id' => $category->id]);
    expect(Category::find($category->id))->not->toBeNull();
});
