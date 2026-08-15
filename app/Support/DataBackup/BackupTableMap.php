<?php

namespace App\Support\DataBackup;

/**
 * Single source of truth for disaster-recovery export/import table lists and order.
 */
class BackupTableMap
{
    public const MANIFEST_VERSION = 1;

    /** @var list<string> */
    public const EXCLUDED_COLUMNS = [
        'password',
        'remember_token',
    ];

    /**
     * Tenant-owned tables in foreign-key-safe import order.
     *
     * @var list<string>
     */
    public const TENANT_TABLES = [
        'users',
        'chambers',
        'doctors',
        'patients',
        'schedule_sessions',
        'slot_blocks',
        'lab_tests',
        'lab_collection_slots',
        'web_pages',
        'departments',
        'blog_posts',
        // bookings BEFORE live_sessions, and never the other way round:
        // `live_sessions.current_booking_id` is a foreign key to `bookings.id`,
        // so live_sessions is the child. This list is read forwards for import
        // (parents first) and reversed for delete (children first), so a single
        // wrong order breaks both directions — which is exactly what it did:
        // importing live_sessions first tripped the FK on MySQL, and deleting
        // bookings first tripped it again on any chamber with a live queue.
        'bookings',
        'booking_push_subscriptions',
        'chamber_cash_entries',
        'live_sessions',
        'booking_lab_test',
        'visit_records',
        'prescriptions',
        'prescription_items',
        'medicine_usages',
        'condition_usages',
        // A doctor's packs are hand-written and irreplaceable — nothing
        // regenerates them from the catalogue. Parent before child, as above.
        'prescription_templates',
        'prescription_template_items',
        'sms_messages',
    ];

    /**
     * Platform tables in foreign-key-safe import order.
     *
     * @var list<string>
     */
    public const PLATFORM_TABLES = [
        'users',
        'patient_accounts',
        'marketers',
        'discount_codes',
        'tenants',
        'domains',
        'billing_payments',
        'commissions',
    ];

    /** @return list<string> */
    public static function tenantTablesInDeleteOrder(): array
    {
        return array_reverse(self::TENANT_TABLES);
    }

    /** @return list<string> */
    public static function platformTablesInDeleteOrder(): array
    {
        return array_reverse(self::PLATFORM_TABLES);
    }

    public static function tableHasTenantColumn(string $table): bool
    {
        return in_array($table, [
            'users',
            'chambers',
            'doctors',
            'patients',
            'schedule_sessions',
            'slot_blocks',
            'lab_tests',
            'lab_collection_slots',
            'web_pages',
            'departments',
            'blog_posts',
            'live_sessions',
            'bookings',
            'booking_push_subscriptions',
            'chamber_cash_entries',
            'booking_lab_test',
            'visit_records',
            'prescriptions',
            'medicine_usages',
            'condition_usages',
            // Only the parent: template items hang off the template and reach
            // their tenant through it, like prescription_items.
            'prescription_templates',
            'sms_messages',
            'billing_payments',
            'commissions',
        ], true);
    }

    public static function primaryKeyColumn(string $table): string
    {
        return match ($table) {
            'domains' => 'id',
            default => 'id',
        };
    }
}
