<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Tests\database\factories\BookableRelationFactory;
use Masterix21\Bookings\Tests\database\factories\BookableResourceFactory;

uses(RefreshDatabase::class);

it('has parentBookableResource belongsTo relationship', function () {
    $parentResource = BookableResourceFactory::new()->create();
    $childResource = BookableResourceFactory::new()->create();
    
    $relation = BookableRelationFactory::new()->create([
        'parent_bookable_resource_id' => $parentResource->id,
        'bookable_resource_id' => $childResource->id,
    ]);

    expect($relation->parentBookableResource())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->parentBookableResource)->toBeInstanceOf(BookableResource::class)
        ->and($relation->parentBookableResource->id)->toBe($parentResource->id);
});

it('has bookableResource belongsTo relationship', function () {
    $parentResource = BookableResourceFactory::new()->create();
    $childResource = BookableResourceFactory::new()->create();
    
    $relation = BookableRelationFactory::new()->create([
        'parent_bookable_resource_id' => $parentResource->id,
        'bookable_resource_id' => $childResource->id,
    ]);

    expect($relation->bookableResource())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->bookableResource)->toBeInstanceOf(BookableResource::class)
        ->and($relation->bookableResource->id)->toBe($childResource->id);
});

it('allows mass assignment for all attributes', function () {
    $attributes = [
        'parent_bookable_resource_id' => 1,
        'bookable_resource_id' => 2,
    ];

    $relation = new BookableRelation($attributes);

    expect($relation->parent_bookable_resource_id)->toBe(1)
        ->and($relation->bookable_resource_id)->toBe(2);
});

it('can create relations between bookable resources', function () {
    $parentResource = BookableResourceFactory::new()->create(['code' => 'PARENT-001']);
    $childResource = BookableResourceFactory::new()->create(['code' => 'CHILD-001']);
    
    $relation = BookableRelation::create([
        'parent_bookable_resource_id' => $parentResource->id,
        'bookable_resource_id' => $childResource->id,
    ]);

    expect($relation)->toBeInstanceOf(BookableRelation::class)
        ->and($relation->parent_bookable_resource_id)->toBe($parentResource->id)
        ->and($relation->bookable_resource_id)->toBe($childResource->id)
        ->and($relation->exists)->toBeTrue();
});

it('uses HasFactory trait', function () {
    expect(BookableRelation::factory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Factories\Factory::class);
});

it('persists data correctly', function () {
    $parentResource = BookableResourceFactory::new()->create();
    $childResource = BookableResourceFactory::new()->create();
    
    $relation = BookableRelation::create([
        'parent_bookable_resource_id' => $parentResource->id,
        'bookable_resource_id' => $childResource->id,
    ]);

    $retrieved = BookableRelation::find($relation->id);
    
    expect($retrieved)->not->toBeNull()
        ->and($retrieved->parent_bookable_resource_id)->toBe($parentResource->id)
        ->and($retrieved->bookable_resource_id)->toBe($childResource->id);
});

it('can query relations by parent resource', function () {
    $parentResource = BookableResourceFactory::new()->create();
    $childResource1 = BookableResourceFactory::new()->create();
    $childResource2 = BookableResourceFactory::new()->create();
    
    BookableRelation::create([
        'parent_bookable_resource_id' => $parentResource->id,
        'bookable_resource_id' => $childResource1->id,
    ]);
    
    BookableRelation::create([
        'parent_bookable_resource_id' => $parentResource->id,
        'bookable_resource_id' => $childResource2->id,
    ]);

    // Create relation with different parent
    $otherParent = BookableResourceFactory::new()->create();
    BookableRelation::create([
        'parent_bookable_resource_id' => $otherParent->id,
        'bookable_resource_id' => $childResource1->id,
    ]);

    $relations = BookableRelation::where('parent_bookable_resource_id', $parentResource->id)->get();
    
    expect($relations)->toHaveCount(2)
        ->and($relations->pluck('bookable_resource_id')->toArray())->toContain($childResource1->id, $childResource2->id);
});

it('has correct table name', function () {
    $relation = new BookableRelation();
    
    expect($relation->getTable())->toBe('bookable_relations');
});

it('has timestamps enabled by default', function () {
    $relation = new BookableRelation();
    
    expect($relation->timestamps)->toBeTrue();
});
