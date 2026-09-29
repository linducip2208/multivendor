<?php

declare(strict_types=1);

$stubs = [
    ['Admin', 'AiController', ['index' => 'View', 'generate' => 'RedirectResponse', 'usage' => 'View', 'prompts' => 'View', 'updatePrompts' => 'RedirectResponse']],
    ['Admin', 'AnalyticsController', [
        'index' => 'View', 'sales' => 'View', 'customers' => 'View', 'vendors' => 'View', 'products' => 'View',
        'marketing' => 'View', 'finance' => 'View', 'stock' => 'View', 'vendorSales' => 'View',
        'exportProducts' => 'StreamedResponse', 'exportOrders' => 'StreamedResponse',
        'exportCustomers' => 'StreamedResponse', 'exportTransactions' => 'StreamedResponse',
    ]],
    ['Admin', 'CmsController', [
        'offlinePayment' => 'View', 'updateOfflinePayment' => 'RedirectResponse', 'emailTemplates' => 'View',
        'updateEmailTemplates' => 'RedirectResponse', 'pages' => 'View', 'updatePages' => 'RedirectResponse',
        'menus' => 'View', 'updateMenus' => 'RedirectResponse', 'contacts' => 'View',
        'updateContacts' => 'RedirectResponse', 'helpTopics' => 'View', 'updateHelpTopics' => 'RedirectResponse',
        'language' => 'View', 'updateLanguage' => 'RedirectResponse', 'currency' => 'View',
        'updateCurrency' => 'RedirectResponse', 'translation' => 'View', 'updateTranslation' => 'RedirectResponse',
        'inhouseShop' => 'View', 'updateInhouseShop' => 'RedirectResponse', 'vendorSettings' => 'View',
        'updateVendorSettings' => 'RedirectResponse', 'smsGateway' => 'View', 'updateSmsGateway' => 'RedirectResponse',
        'thirdParty' => 'View', 'updateThirdParty' => 'RedirectResponse', 'roles' => 'View',
        'updateRoles' => 'RedirectResponse', 'permissions' => 'View', 'updatePermissions' => 'RedirectResponse',
    ]],
    ['Admin', 'CrmController', [
        'reviews' => 'View', 'moderateReview' => 'RedirectResponse', 'deleteReview' => 'RedirectResponse',
        'segments' => 'View', 'storeSegment' => 'RedirectResponse', 'syncSegment' => 'RedirectResponse',
        'destroySegment' => 'RedirectResponse', 'loyalty' => 'View', 'show' => 'View', 'activity' => 'View',
        'wishlists' => 'View', 'conversations' => 'View', 'conversation' => 'View', 'replyConversation' => 'RedirectResponse',
    ]],
    ['Admin', 'DeveloperController', [
        'index' => 'View', 'apiKeys' => 'View', 'storeApiKey' => 'RedirectResponse', 'destroyApiKey' => 'RedirectResponse',
        'webhooks' => 'View', 'storeWebhook' => 'RedirectResponse', 'updateWebhook' => 'RedirectResponse',
        'destroyWebhook' => 'RedirectResponse', 'webhookDeliveries' => 'View', 'replayDelivery' => 'RedirectResponse',
        'events' => 'View', 'logs' => 'View',
    ]],
    ['Admin', 'HomepageController', ['index' => 'View', 'update' => 'RedirectResponse', 'preview' => 'View']],
    ['Admin', 'InventoryController', [
        'index' => 'View', 'warehouses' => 'View', 'storeWarehouse' => 'RedirectResponse', 'updateWarehouse' => 'RedirectResponse',
        'movements' => 'View', 'adjust' => 'RedirectResponse', 'opname' => 'RedirectResponse',
        'storeTransfer' => 'RedirectResponse', 'receiveTransfer' => 'RedirectResponse',
    ]],
    ['Admin', 'MarketingController', [
        'index' => 'View', 'create' => 'View', 'store' => 'RedirectResponse', 'edit' => 'View', 'update' => 'RedirectResponse',
        'destroy' => 'RedirectResponse', 'show' => 'View', 'toggle' => 'RedirectResponse', 'abandonedCarts' => 'View',
        'sendAbandonedReminder' => 'RedirectResponse', 'referrals' => 'View', 'affiliates' => 'View',
        'update' => 'RedirectResponse', 'notifications' => 'View',
    ]],
    ['Admin', 'PaymentController', [
        'index' => 'View', 'show' => 'View', 'ledger' => 'View', 'ledgerAccounts' => 'View', 'refunds' => 'View',
        'refundShow' => 'View', 'reconciliation' => 'View', 'runReconciliation' => 'RedirectResponse',
        'refundOrder' => 'RedirectResponse',
    ]],
    ['Admin', 'SaasController', [
        'index' => 'View', 'store' => 'RedirectResponse', 'show' => 'View', 'update' => 'RedirectResponse',
        'destroy' => 'RedirectResponse', 'plans' => 'View', 'storePlan' => 'RedirectResponse',
        'updatePlan' => 'RedirectResponse', 'destroyPlan' => 'RedirectResponse', 'subscriptions' => 'View',
        'updateSubscriptionStatus' => 'RedirectResponse', 'usage' => 'View', 'themes' => 'View',
    ]],
    ['Admin', 'SeoController', [
        'index' => 'View', 'update' => 'RedirectResponse', 'sitemaps' => 'View', 'redirects' => 'View',
        'storeRedirect' => 'RedirectResponse', 'destroyRedirect' => 'RedirectResponse', 'pseo' => 'View',
        'updatePseo' => 'RedirectResponse', 'generatePseo' => 'RedirectResponse', 'reviewPseoPage' => 'RedirectResponse',
        'publishPseoPage' => 'RedirectResponse', 'destroyPseoPage' => 'RedirectResponse',
    ]],
    ['Admin', 'ShippingController', [
        'index' => 'View', 'show' => 'View', 'couriers' => 'View', 'courierShow' => 'View', 'track' => 'View',
        'returns' => 'View', 'decideReturn' => 'RedirectResponse',
    ]],
    ['Admin', 'SystemHealthController', [
        'index' => 'View', 'logs' => 'View', 'clearLogs' => 'RedirectResponse', 'queue' => 'View',
        'maintenance' => 'View', 'toggleMaintenance' => 'RedirectResponse', 'clearCache' => 'RedirectResponse',
    ]],
    ['Vendor', 'AiController', ['index' => 'View', 'generate' => 'RedirectResponse']],
    ['Vendor', 'CustomerController', ['index' => 'View', 'show' => 'View']],
    ['Vendor', 'DashboardController', ['index' => 'View']],
    ['Vendor', 'InventoryController', ['index' => 'View', 'movements' => 'View', 'adjust' => 'RedirectResponse']],
    ['Vendor', 'PromotionController', ['index' => 'View', 'store' => 'RedirectResponse', 'destroy' => 'RedirectResponse']],
    ['Vendor', 'SettingsController', [
        'index' => 'View', 'update' => 'RedirectResponse', 'shipping' => 'View', 'updateShipping' => 'RedirectResponse',
        'notifications' => 'View', 'updateNotifications' => 'RedirectResponse', 'security' => 'View',
        'updateSecurity' => 'RedirectResponse',
    ]],
    ['Vendor', 'StaffController', ['index' => 'View', 'store' => 'RedirectResponse', 'destroy' => 'RedirectResponse']],
    ['Vendor', 'SubscriptionController', ['index' => 'View', 'subscribe' => 'RedirectResponse', 'cancel' => 'RedirectResponse']],
    ['Vendor', 'TicketController', ['index' => 'View', 'show' => 'View', 'store' => 'RedirectResponse', 'reply' => 'RedirectResponse']],
];

$imports = [
    'View' => 'use Illuminate\View\View;',
    'RedirectResponse' => 'use Illuminate\Http\RedirectResponse;',
    'StreamedResponse' => 'use Symfony\Component\HttpFoundation\StreamedResponse;',
];

$created = 0;
$skipped = [];

foreach ($stubs as [$ns, $class, $methods]) {
    $path = __DIR__.'/../app/Http/Controllers/'.$ns.'/'.$class.'.php';
    $path = str_replace('\\', '/', $path);

    if (is_file($path)) {
        $skipped[] = "$ns\\$class";
        continue;
    }

    $used = array_values(array_unique(array_values($methods)));
    $useLines = '';
    foreach ($used as $type) {
        $useLines .= $imports[$type]."\n";
    }

    $body = '';
    foreach ($methods as $method => $type) {
        $body .= <<<PHP

            public function {$method}(Request \$request): {$type}
            {
                return view('admin.stub');
            }

        PHP;
    }

    $php = <<<PHP
    <?php

    declare(strict_types=1);

    namespace App\\Http\\Controllers\\{$ns};

    use App\\Http\\Controllers\\Controller;
    use Illuminate\\Http\\Request;
    {$useLines}
    class {$class} extends Controller
    {
    {$body}}

    PHP;

    $php = preg_replace('/^    /m', '', $php);
    $php = str_replace("    <?php", '<?php', $php);

    file_put_contents($path, $php);
    $created++;
}

echo "created: {$created}\n";
if ($skipped !== []) {
    echo 'skipped (already existed): '.implode(', ', $skipped)."\n";
}
