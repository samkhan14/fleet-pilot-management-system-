<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('allow_overlapping_assignments')->default(false);
            $table->boolean('require_pod_photo')->default(false);
            $table->boolean('require_pod_signature')->default(false);
            $table->boolean('require_recipient_name')->default(true);
            $table->unsignedInteger('tracking_token_ttl_hours')->default(720);
            $table->boolean('public_tracking_enabled')->default(true);
            $table->boolean('notify_on_assign')->default(true);
            $table->boolean('notify_on_delay')->default(true);
            $table->boolean('notify_on_failed')->default(true);
            $table->boolean('notify_on_delivered')->default(true);
            $table->json('document_types');
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->timestamps();
        });

        Schema::create('permission_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->string('permission', 80);
            $table->unique(['organization_id', 'role', 'permission']);
            $table->index(['organization_id', 'role']);
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_line1')->nullable();
            $table->string('billing_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_region')->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_country')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'name']);
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('role_title')->nullable();
            $table->boolean('authorized')->default(true);
            $table->timestamps();
            $table->index('customer_id');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('kind', 20);
            $table->string('availability', 20)->default('available');
            $table->string('status', 20)->default('active');
            $table->string('license_number')->nullable();
            $table->date('license_expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'availability']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('fleet_number');
            $table->string('registration')->nullable();
            $table->string('vehicle_type', 30);
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->string('availability', 20)->default('available');
            $table->string('status', 20)->default('active');
            $table->string('maintenance_status', 30)->default('ok');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'fleet_number']);
            $table->index(['organization_id', 'status', 'availability']);
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['organization_id', 'role']);
        });

        Schema::create('shipment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('order_number');
            $table->string('customer_reference')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'order_number']);
            $table->index(['organization_id', 'status', 'created_at']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('shipment_orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('shipment_number');
            $table->string('customer_reference')->nullable();
            $table->string('service_type', 30);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 40)->default('draft');
            $table->string('description', 2000);
            $table->string('handling_instructions', 2000)->nullable();
            $table->unsignedInteger('declared_value_cents')->nullable();
            $table->string('currency', 3);
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->timestamp('pickup_window_start');
            $table->timestamp('pickup_window_end');
            $table->timestamp('delivery_window_start');
            $table->timestamp('delivery_window_end');
            $table->timestamp('planned_pickup_at')->nullable();
            $table->timestamp('planned_delivery_at')->nullable();
            $table->timestamp('actual_pickup_at')->nullable();
            $table->timestamp('actual_delivery_at')->nullable();
            $table->string('service_area')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('current_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('current_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->unsignedBigInteger('current_trip_id')->nullable();
            $table->unsignedBigInteger('current_assignment_id')->nullable();
            $table->unsignedBigInteger('current_exception_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'shipment_number']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['organization_id', 'planned_delivery_at']);
            $table->index(['organization_id', 'planned_pickup_at']);
            $table->index(['current_driver_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['organization_id', 'service_area', 'status']);
        });

        Schema::create('shipment_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('instructions')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'kind']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->index('shipment_id');
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('trip_number');
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('planned');
            $table->timestamp('planned_departure')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'trip_number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreign('current_trip_id')->references('id')->on('trips')->nullOnDelete();
        });

        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('stop_type', 20);
            $table->integer('sequence');
            $table->string('status', 20)->default('pending');
            $table->timestamp('planned_arrival')->nullable();
            $table->timestamp('actual_arrival')->nullable();
            $table->timestamp('actual_departure')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'sequence']);
            $table->index(['trip_id', 'sequence']);
            $table->index('shipment_id');
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'status', 'window_start']);
            $table->index(['vehicle_id', 'status', 'window_start']);
            $table->index(['organization_id', 'status']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('active_shipment_key')->nullable();
            $table->unsignedBigInteger('active_trip_key')->nullable();
            $table->unique('active_shipment_key');
            $table->unique('active_trip_key');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE assignments
                ADD CONSTRAINT assignments_target_check CHECK ((shipment_id IS NULL) <> (trip_id IS NULL)),
                ADD CONSTRAINT assignments_window_check CHECK (window_end > window_start)
        SQL);

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreign('current_assignment_id')->references('id')->on('assignments')->nullOnDelete();
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 40)->nullable();
            $table->string('message', 500);
            $table->string('reason', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['shipment_id', 'occurred_at', 'id']);
            $table->index(['organization_id', 'occurred_at']);
        });

        Schema::create('delivery_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('recipient_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('amends_id')->nullable()->constrained('delivery_evidences')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shipment_id', 'type']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('doc_type', 60);
            $table->string('visibility', 30);
            $table->string('title');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'doc_type']);
            $table->index('shipment_id');
        });

        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('kind', 30)->default('attachment');
            $table->foreignId('evidence_id')->nullable()->constrained('delivery_evidences')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('organization_id');
        });

        Schema::create('shipment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->text('description');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['shipment_id', 'status']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreign('current_exception_id')->references('id')->on('shipment_exceptions')->nullOnDelete();
        });

        Schema::create('notification_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dedupe_key');
            $table->string('template', 40);
            $table->string('channel', 20);
            $table->string('recipient');
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'dedupe_key']);
            $table->index(['status', 'attempts']);
        });

        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->string('kind', 20);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('billing_status', 20)->default('unbilled');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('invoice_reference')->nullable();
            $table->string('external_accounting_ref')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'billing_status', 'created_at']);
            $table->index(['customer_id', 'payment_status']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('tracking_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shipment_id', 'expires_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('request_hash', 64);
            $table->json('response');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('tracking_tokens');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('charges');
        Schema::dropIfExists('notification_jobs');
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['current_exception_id']);
        });
        Schema::dropIfExists('shipment_exceptions');
        Schema::dropIfExists('stored_files');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('delivery_evidences');
        Schema::dropIfExists('tracking_events');
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['current_assignment_id']);
        });
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('trip_stops');
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['current_trip_id']);
        });
        Schema::dropIfExists('trips');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipment_addresses');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('shipment_orders');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('permission_grants');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organizations');
    }
};
