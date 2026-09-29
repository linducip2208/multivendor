<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor Operating System support tables.
 *
 * Owns everything the vendor back-office needs that the pre-existing schema
 * could not express: shop staff, coupon campaigns, vendor support tickets,
 * notification preferences, and the vendor application pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createShopStaff();
        $this->createVendorSupportTickets();
        $this->createShopNotificationPreferences();
        $this->createShopSecuritySettings();
        $this->createVendorApplications();
        $this->createVendorApplicationDocuments();
        $this->scopeCampaignsToShops();
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_application_documents');
        Schema::dropIfExists('vendor_applications');
        Schema::dropIfExists('shop_security_settings');
        Schema::dropIfExists('shop_notification_preferences');
        Schema::dropIfExists('shop_staff');
    }

    private function createShopStaff(): void
    {
        if (Schema::hasTable('shop_staff')) {
            return;
        }

        Schema::create('shop_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->enum('role', ['manager', 'staff', 'finance', 'warehouse', 'support'])->default('staff');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'email']);
            $table->index(['shop_id', 'is_active']);
        });
    }

    private function createVendorSupportTickets(): void
    {
        $this->addColumns('support_tickets', [
            ['shop_id', 'integer', ['unsigned' => true], ['nullable' => true]],
            ['vendor_id', 'integer', ['unsigned' => true], ['nullable' => true]],
            ['reference', 'string', ['length' => 40], ['nullable' => true]],
            ['order_id', 'integer', ['unsigned' => true], ['nullable' => true]],
            ['first_response_at', 'timestamp', [], ['nullable' => true]],
            ['closed_at', 'timestamp', [], ['nullable' => true]],
        ]);

        // NOTE: support_ticket_replies is created once by
        // 2026_06_09_000014_create_support_tickets_table.php — do not
        // duplicate it here (previously a hasTable-guarded duplicate).

        $this->addIndex('support_tickets', ['shop_id', 'status'], 'support_tickets_shop_status_idx');
    }

    private function addColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as [$name, $type, $args, $modifiers]) {
            if (Schema::hasColumn($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($name, $type, $args, $modifiers) {
                $t->addColumn($type, $name, $args + $modifiers);
            });
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        } catch (\Throwable) {
        }
    }

    private function createShopNotificationPreferences(): void
    {
        if (Schema::hasTable('shop_notification_preferences')) {
            return;
        }

        Schema::create('shop_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('category', 60);
            $table->enum('channel', ['email', 'sms', 'whatsapp', 'in_app', 'push'])->default('email');
            $table->boolean('enabled')->default(true);
            $table->string('destination', 190)->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'category', 'channel']);
        });
    }

    private function createShopSecuritySettings(): void
    {
        if (Schema::hasTable('shop_security_settings')) {
            return;
        }

        Schema::create('shop_security_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret', 190)->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('login_alert')->default(true);
            $table->unsignedSmallInteger('login_alert_after')->default(1);
            $table->unsignedInteger('session_timeout_minutes')->default(120);
            $table->boolean('password_rotation_enabled')->default(false);
            $table->unsignedSmallInteger('password_rotation_days')->default(90);
            $table->unsignedTinyInteger('max_failed_attempts')->default(5);
            $table->unsignedInteger('lockout_minutes')->default(15);
            $table->json('ip_allowlist')->nullable();
            $table->timestamp('last_password_change_at')->nullable();
            $table->timestamps();
        });
    }

    private function createVendorApplications(): void
    {
        if (Schema::hasTable('vendor_applications')) {
            return;
        }

        Schema::create('vendor_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('shop_name');
            $table->string('slug', 120)->unique();
            $table->string('owner_name');
            $table->string('email')->index();
            $table->string('phone', 32)->nullable();
            $table->string('password');
            $table->text('description')->nullable();
            $table->string('category', 80)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->text('address')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 64)->nullable();
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'resubmitted'])->default('pending');
            $table->decimal('commission_value', 8, 2)->default(0);
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->string('commission_tier', 60)->nullable();
            $table->foreignId('subscription_plan_id')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    private function createVendorApplicationDocuments(): void
    {
        if (Schema::hasTable('vendor_application_documents')) {
            return;
        }

        Schema::create('vendor_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_application_id')->constrained('vendor_applications')->cascadeOnDelete();
            $table->enum('kind', ['identity', 'business_license', 'tax_document', 'bank_letter', 'selfie', 'other'])->default('identity');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_application_id', 'kind']);
        });
    }

    private function scopeCampaignsToShops(): void
    {
        $this->addColumns('campaigns', [
            ['shop_id', 'integer', ['unsigned' => true], ['nullable' => true]],
            ['code', 'string', ['length' => 60], ['nullable' => true]],
            ['banner', 'string', ['length' => 255], ['nullable' => true]],
        ]);

        $this->addIndex('campaigns', ['shop_id', 'status'], 'campaigns_shop_status_idx');
    }
};
