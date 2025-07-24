# Database Schema Reference

This document provides a comprehensive reference for Laravel Bookings database structure, relationships, and indexing strategies.

## Schema Overview

Laravel Bookings uses five core tables to manage booking functionality:

1. **bookable_resources** - Defines bookable entities
2. **bookings** - Stores booking records
3. **booked_periods** - Individual time slots within bookings
4. **bookable_plannings** - Availability rules and constraints
5. **bookable_relations** - Relationships between resources

## Core Tables

### bookable_resources

Represents any entity that can be booked through polymorphic relationships.

```sql
CREATE TABLE `bookable_resources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_type` varchar(255) NOT NULL,
  `resource_id` bigint unsigned NOT NULL,
  `max` int NOT NULL DEFAULT 1,
  `size` int NOT NULL DEFAULT 1,
  `is_bookable` tinyint(1) NOT NULL DEFAULT 1,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookable_resources_resource_type_resource_id_index` (`resource_type`, `resource_id`),
  KEY `bookable_resources_is_bookable_index` (`is_bookable`),
  KEY `bookable_resources_is_visible_index` (`is_visible`)
);
```

#### Column Descriptions

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint unsigned | Primary key |
| `resource_type` | varchar(255) | Polymorphic type (model class name) |
| `resource_id` | bigint unsigned | Polymorphic ID (model instance ID) |
| `max` | int | Maximum concurrent bookings allowed |
| `size` | int | Resource capacity/size |
| `is_bookable` | boolean | Whether resource accepts new bookings |
| `is_visible` | boolean | Whether resource is visible to users |

#### Indexes

```sql
-- Essential indexes for performance
KEY `bookable_resources_resource_type_resource_id_index` (`resource_type`, `resource_id`);
KEY `bookable_resources_is_bookable_index` (`is_bookable`);
KEY `bookable_resources_is_visible_index` (`is_visible`);

-- Composite index for filtered queries
KEY `bookable_resources_status_composite` (`is_bookable`, `is_visible`, `resource_type`);
```

### bookings

Stores booking records with polymorphic booker relationships.

```sql
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `booker_type` varchar(255) NOT NULL,
  `booker_id` bigint unsigned NOT NULL,
  `creator_id` bigint unsigned NULL,
  `relatable_type` varchar(255) NULL,
  `relatable_id` bigint unsigned NULL,
  `label` varchar(255) NULL,
  `note` text NULL,
  `meta` json NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_code_unique` (`code`),
  KEY `bookings_booker_type_booker_id_index` (`booker_type`, `booker_id`),
  KEY `bookings_creator_id_index` (`creator_id`),
  KEY `bookings_relatable_type_relatable_id_index` (`relatable_type`, `relatable_id`),
  KEY `bookings_created_at_index` (`created_at`)
);
```

#### Column Descriptions

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint unsigned | Primary key |
| `code` | varchar(255) | Unique booking identifier |
| `booker_type` | varchar(255) | Polymorphic type (who made the booking) |
| `booker_id` | bigint unsigned | Polymorphic ID (booker instance ID) |
| `creator_id` | bigint unsigned | Optional - who created the booking (if different from booker) |
| `relatable_type` | varchar(255) | Optional polymorphic relation |
| `relatable_id` | bigint unsigned | Optional polymorphic relation ID |
| `label` | varchar(255) | Human-readable booking title |
| `note` | text | Additional booking notes |
| `meta` | json | Flexible metadata storage |

#### JSON Meta Field Structure

The `meta` field supports flexible data storage:

```json
{
  "attendees": 5,
  "equipment": ["projector", "whiteboard"],
  "catering": true,
  "special_requests": "Wheelchair accessible",
  "billing_info": {
    "department": "Marketing",
    "cost_center": "MC001"
  },
  "contact": {
    "phone": "+1234567890",
    "email": "organizer@example.com"
  }
}
```

#### Indexes

```sql
-- Performance indexes
UNIQUE KEY `bookings_code_unique` (`code`);
KEY `bookings_booker_type_booker_id_index` (`booker_type`, `booker_id`);
KEY `bookings_creator_id_index` (`creator_id`);
KEY `bookings_relatable_type_relatable_id_index` (`relatable_type`, `relatable_id`);
KEY `bookings_created_at_index` (`created_at`);

-- JSON search indexes (MySQL 8.0+)
CREATE INDEX `bookings_meta_attendees` ON `bookings` ((CAST(`meta`->'$.attendees' AS UNSIGNED)));
CREATE INDEX `bookings_meta_department` ON `bookings` ((CAST(`meta`->'$.billing_info.department' AS CHAR(100))));
```

### booked_periods

Individual time periods within bookings, supporting multi-slot reservations.

```sql
CREATE TABLE `booked_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `bookable_resource_id` bigint unsigned NOT NULL,
  `relatable_type` varchar(255) NULL,
  `relatable_id` bigint unsigned NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booked_periods_booking_id_foreign` (`booking_id`),
  KEY `booked_periods_bookable_resource_id_foreign` (`bookable_resource_id`),
  KEY `booked_periods_relatable_type_relatable_id_index` (`relatable_type`, `relatable_id`),
  KEY `booked_periods_starts_at_index` (`starts_at`),
  KEY `booked_periods_ends_at_index` (`ends_at`),
  KEY `booked_periods_resource_dates_index` (`bookable_resource_id`, `starts_at`, `ends_at`),
  CONSTRAINT `booked_periods_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booked_periods_bookable_resource_id_foreign` FOREIGN KEY (`bookable_resource_id`) REFERENCES `bookable_resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booked_periods_dates_check` CHECK (`starts_at` < `ends_at`)
);
```

#### Column Descriptions

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint unsigned | Primary key |
| `booking_id` | bigint unsigned | Foreign key to bookings table |
| `bookable_resource_id` | bigint unsigned | Foreign key to bookable_resources |
| `relatable_type` | varchar(255) | Optional polymorphic relation |
| `relatable_id` | bigint unsigned | Optional polymorphic relation ID |
| `starts_at` | timestamp | Period start time (UTC) |
| `ends_at` | timestamp | Period end time (UTC) |

#### Constraints

```sql
-- Data integrity constraints
CONSTRAINT `booked_periods_dates_check` CHECK (`starts_at` < `ends_at`);
CONSTRAINT `booked_periods_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;
CONSTRAINT `booked_periods_bookable_resource_id_foreign` FOREIGN KEY (`bookable_resource_id`) REFERENCES `bookable_resources` (`id`) ON DELETE CASCADE;
```

#### Performance Indexes

```sql
-- Essential for overlap detection
KEY `booked_periods_resource_dates_index` (`bookable_resource_id`, `starts_at`, `ends_at`);

-- Date range queries
KEY `booked_periods_starts_at_index` (`starts_at`);
KEY `booked_periods_ends_at_index` (`ends_at`);

-- Covering index for common queries
KEY `booked_periods_covering_index` (`bookable_resource_id`, `starts_at`, `ends_at`, `booking_id`);
```

### bookable_plannings

Defines availability rules and scheduling constraints for resources.

```sql
CREATE TABLE `bookable_plannings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bookable_resource_id` bigint unsigned NOT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `available_from` time NULL,
  `available_to` time NULL,
  `available_on` json NULL,
  `unavailable_on` json NULL,
  `booking_starts_at` timestamp NULL,
  `booking_ends_at` timestamp NULL,
  `min_time_before_booking` int NULL,
  `max_time_before_booking` int NULL,
  `min_booking_duration` int NULL,
  `max_booking_duration` int NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookable_plannings_bookable_resource_id_foreign` (`bookable_resource_id`),
  KEY `bookable_plannings_starts_at_index` (`starts_at`),
  KEY `bookable_plannings_ends_at_index` (`ends_at`),
  KEY `bookable_plannings_booking_window_index` (`booking_starts_at`, `booking_ends_at`),
  CONSTRAINT `bookable_plannings_bookable_resource_id_foreign` FOREIGN KEY (`bookable_resource_id`) REFERENCES `bookable_resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookable_plannings_dates_check` CHECK (`starts_at` < `ends_at`),
  CONSTRAINT `bookable_plannings_booking_dates_check` CHECK (`booking_starts_at` IS NULL OR `booking_ends_at` IS NULL OR `booking_starts_at` < `booking_ends_at`)
);
```

#### Column Descriptions

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint unsigned | Primary key |
| `bookable_resource_id` | bigint unsigned | Foreign key to bookable_resources |
| `starts_at` | timestamp | Planning period start |
| `ends_at` | timestamp | Planning period end |
| `available_from` | time | Daily availability start time |
| `available_to` | time | Daily availability end time |
| `available_on` | json | Days of week when available |
| `unavailable_on` | json | Specific unavailable dates |
| `booking_starts_at` | timestamp | When booking window opens |
| `booking_ends_at` | timestamp | When booking window closes |
| `min_time_before_booking` | int | Minimum advance booking time (minutes) |
| `max_time_before_booking` | int | Maximum advance booking time (minutes) |
| `min_booking_duration` | int | Minimum booking duration (minutes) |
| `max_booking_duration` | int | Maximum booking duration (minutes) |

#### JSON Field Examples

```json
// available_on - days of week (0 = Sunday, 6 = Saturday)
[1, 2, 3, 4, 5]  // Monday through Friday

// unavailable_on - specific dates
["2024-12-25", "2024-01-01", "2024-07-04"]
```

### bookable_relations

Manages relationships and dependencies between bookable resources.

```sql
CREATE TABLE `bookable_relations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned NOT NULL,
  `child_id` bigint unsigned NOT NULL,
  `relation_type` varchar(255) NOT NULL DEFAULT 'dependency',
  `metadata` json NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookable_relations_parent_id_foreign` (`parent_id`),
  KEY `bookable_relations_child_id_foreign` (`child_id`),
  KEY `bookable_relations_relation_type_index` (`relation_type`),
  UNIQUE KEY `bookable_relations_unique` (`parent_id`, `child_id`, `relation_type`),
  CONSTRAINT `bookable_relations_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `bookable_resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookable_relations_child_id_foreign` FOREIGN KEY (`child_id`) REFERENCES `bookable_resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookable_relations_no_self_reference` CHECK (`parent_id` != `child_id`)
);
```

#### Relation Types

- `dependency` - Child resource requires parent to be booked
- `exclusion` - Resources cannot be booked simultaneously
- `inclusion` - Booking parent automatically books child
- `substitution` - Child can substitute for parent

## Relationships Diagram

```
bookable_resources (1) ←→ (∞) bookable_relations
        ↓ (1)
        ↓
        ↓ (∞) booked_periods
        ↓         ↓ (∞)
        ↓         ↓
        ↓         ↓ (1) bookings
        ↓              ↓ (∞)
        ↓              ↓
        ↓ (∞) bookable_plannings
        
polymorphic relationships:
- bookable_resources → resource (any model)
- bookings → booker (User, Organization, etc.)
- bookings → relatable (optional relation)
- booked_periods → relatable (optional relation)
```

## Indexing Strategy

### Primary Indexes (Required)

These indexes are essential for basic functionality:

```sql
-- Polymorphic relationship lookups
CREATE INDEX idx_bookable_resources_morphs ON bookable_resources(resource_type, resource_id);
CREATE INDEX idx_bookings_booker_morphs ON bookings(booker_type, booker_id);

-- Period overlap detection (CRITICAL)
CREATE INDEX idx_booked_periods_overlaps ON booked_periods(bookable_resource_id, starts_at, ends_at);

-- Planning lookups
CREATE INDEX idx_plannings_resource_dates ON bookable_plannings(bookable_resource_id, starts_at, ends_at);
```

### Performance Indexes (Recommended)

Additional indexes for better performance:

```sql
-- Booking code lookups
CREATE UNIQUE INDEX idx_bookings_code ON bookings(code);

-- Date-based queries
CREATE INDEX idx_bookings_created_at ON bookings(created_at);
CREATE INDEX idx_booked_periods_starts_at ON booked_periods(starts_at);
CREATE INDEX idx_booked_periods_ends_at ON booked_periods(ends_at);

-- Status filtering
CREATE INDEX idx_bookable_resources_status ON bookable_resources(is_bookable, is_visible);

-- Covering indexes for common queries
CREATE INDEX idx_booked_periods_covering ON booked_periods(bookable_resource_id, starts_at, ends_at, booking_id);
```

### Query-Specific Indexes

Optimize for common query patterns:

```sql
-- Find available resources
CREATE INDEX idx_resources_availability ON bookable_resources(resource_type, is_bookable, is_visible);

-- User booking history
CREATE INDEX idx_bookings_user_history ON bookings(booker_type, booker_id, created_at);

-- Resource utilization reports
CREATE INDEX idx_periods_utilization ON booked_periods(bookable_resource_id, starts_at, ends_at, created_at);
```

## Common Queries and Optimization

### 1. Find Available Resources

```sql
-- Optimized query to find available resources
SELECT br.* 
FROM bookable_resources br
WHERE br.resource_type = 'App\\Models\\Room'
  AND br.is_bookable = 1
  AND br.is_visible = 1
  AND NOT EXISTS (
    SELECT 1 FROM booked_periods bp
    WHERE bp.bookable_resource_id = br.id
      AND bp.starts_at < '2024-12-25 17:00:00'
      AND bp.ends_at > '2024-12-25 09:00:00'
  );
```

**Required indexes:**
- `idx_bookable_resources_morphs`
- `idx_booked_periods_overlaps`

### 2. Check Booking Overlaps

```sql
-- Find overlapping bookings for a resource
SELECT bp.* 
FROM booked_periods bp
JOIN bookings b ON bp.booking_id = b.id
WHERE bp.bookable_resource_id = ?
  AND bp.starts_at < ?  -- requested end time
  AND bp.ends_at > ?    -- requested start time
  AND bp.booking_id != ?; -- exclude current booking if updating
```

**Required indexes:**
- `idx_booked_periods_overlaps`

### 3. Resource Utilization

```sql
-- Calculate resource utilization
SELECT 
  br.id,
  br.resource_type,
  br.resource_id,
  COUNT(bp.id) as booking_count,
  SUM(TIMESTAMPDIFF(MINUTE, bp.starts_at, bp.ends_at)) as total_minutes
FROM bookable_resources br
LEFT JOIN booked_periods bp ON br.id = bp.bookable_resource_id
  AND bp.starts_at >= '2024-12-01'
  AND bp.ends_at <= '2024-12-31'
GROUP BY br.id;
```

**Required indexes:**
- `idx_periods_utilization`

## Migration Scripts

### Adding Indexes

```php
<?php
// database/migrations/add_performance_indexes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booked_periods', function (Blueprint $table) {
            $table->index(['bookable_resource_id', 'starts_at', 'ends_at'], 'idx_booked_periods_overlaps');
        });
        
        Schema::table('bookable_resources', function (Blueprint $table) {
            $table->index(['resource_type', 'resource_id'], 'idx_bookable_resources_morphs');
            $table->index(['is_bookable', 'is_visible'], 'idx_bookable_resources_status');
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['booker_type', 'booker_id'], 'idx_bookings_booker_morphs');
        });
    }
    
    public function down(): void
    {
        Schema::table('booked_periods', function (Blueprint $table) {
            $table->dropIndex('idx_booked_periods_overlaps');
        });
        
        Schema::table('bookable_resources', function (Blueprint $table) {
            $table->dropIndex('idx_bookable_resources_morphs');
            $table->dropIndex('idx_bookable_resources_status');
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_booker_morphs');
        });
    }
};
```

### Database Constraints

```php
<?php
// database/migrations/add_database_constraints.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure period dates are valid
        DB::statement('ALTER TABLE booked_periods ADD CONSTRAINT chk_period_dates CHECK (starts_at < ends_at)');
        
        // Ensure planning dates are valid
        DB::statement('ALTER TABLE bookable_plannings ADD CONSTRAINT chk_planning_dates CHECK (starts_at < ends_at)');
        
        // Prevent self-referencing relations
        DB::statement('ALTER TABLE bookable_relations ADD CONSTRAINT chk_no_self_reference CHECK (parent_id != child_id)');
        
        // Ensure resource capacity is positive
        DB::statement('ALTER TABLE bookable_resources ADD CONSTRAINT chk_positive_max CHECK (max > 0)');
        DB::statement('ALTER TABLE bookable_resources ADD CONSTRAINT chk_positive_size CHECK (size > 0)');
    }
    
    public function down(): void
    {
        DB::statement('ALTER TABLE booked_periods DROP CONSTRAINT chk_period_dates');
        DB::statement('ALTER TABLE bookable_plannings DROP CONSTRAINT chk_planning_dates');
        DB::statement('ALTER TABLE bookable_relations DROP CONSTRAINT chk_no_self_reference');
        DB::statement('ALTER TABLE bookable_resources DROP CONSTRAINT chk_positive_max');
        DB::statement('ALTER TABLE bookable_resources DROP CONSTRAINT chk_positive_size');
    }
};
```

This database schema provides a solid foundation for booking systems while maintaining flexibility for various use cases and ensuring optimal performance through proper indexing strategies.