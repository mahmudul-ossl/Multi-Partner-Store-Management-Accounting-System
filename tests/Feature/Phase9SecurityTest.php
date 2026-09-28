<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Models\ApprovalRequest;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\ReportCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;

class Phase9SecurityTest extends FinanceTestCase
{
    public function test_partner_financial_statements_are_permission_driven(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner('Rahim Uddin');

        $this->actingAs($partnerUser)->get('/accounting/reports/profit-loss')->assertForbidden();
        $this->actingAs($partnerUser)->get('/accounting/reports/balance-sheet')->assertForbidden();
        $this->actingAs($partnerUser)->get('/accounting/reports/trial-balance')->assertForbidden();
        $this->actingAs($partnerUser)->get(route('reports.show', 'profit-loss'))->assertForbidden();
        $this->actingAs($partnerUser)->get(route('reports.show', 'balance-sheet'))->assertForbidden();
        $this->actingAs($partnerUser)->get(route('reports.show', 'trial-balance'))->assertForbidden();

        $visible = collect(ReportCatalog::visible($partnerUser))->pluck('key');
        $this->assertFalse($visible->contains('profit-loss'));
        $this->assertFalse($visible->contains('balance-sheet'));
        $this->assertFalse($visible->contains('trial-balance'));

        $this->actingAs($partnerUser)
            ->get(route('partners.dashboard', $partner))
            ->assertOk();
        $this->actingAs($partnerUser)
            ->get(route('partners.statement', $partner))
            ->assertOk();

        $partnerUser->givePermissionTo([
            PermissionName::ProfitLossView->value,
            PermissionName::BalanceSheetView->value,
            PermissionName::TrialBalanceView->value,
        ]);

        $this->actingAs($partnerUser->fresh())->get('/accounting/reports/profit-loss')->assertOk();
        $this->actingAs($partnerUser->fresh())->get('/accounting/reports/balance-sheet')->assertOk();
        $this->actingAs($partnerUser->fresh())->get('/accounting/reports/trial-balance')->assertOk();
    }

    public function test_every_non_public_route_requires_auth_and_an_authorization_check(): void
    {
        $public = ['login', 'login.store', 'home', 'api.login'];
        $selfService = ['logout', 'dashboard', 'notifications.read', 'notifications.read-all', 'api.logout'];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            $uri = $route->uri();
            $action = $route->getActionName();

            if (! str_starts_with($action, 'App\\')) {
                continue;
            }

            if ($uri === 'up' || in_array($name, $public, true) || str_starts_with((string) $name, 'sanctum.')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $authenticated = collect($middleware)->contains(
                fn (string $item): bool => $item === 'auth' || str_starts_with($item, 'auth:'),
            );
            $this->assertTrue($authenticated, 'Missing auth middleware on '.($name ?: $uri));

            if (in_array($name, $selfService, true)) {
                $this->assertStringContainsString('@', $action);

                continue;
            }

            $this->assertTrue($this->actionChecksAuthorization($action), 'Missing authorization check on '.($name ?: $uri));
            $checked++;
        }

        $this->assertGreaterThan(40, $checked);

        $this->assertNotNull(RateLimiter::limiter('login'));
        $this->assertNotNull(RateLimiter::limiter('api'));
        $this->assertNotNull(RateLimiter::limiter('approvals'));
        $this->assertContains('throttle:approvals', Route::getRoutes()->getByName('approvals.approve')->gatherMiddleware());
        $this->assertContains('throttle:login', Route::getRoutes()->getByName('api.login')->gatherMiddleware());
        $this->assertContains('auth:sanctum', Route::getRoutes()->getByName('api.partners.index')->gatherMiddleware());
        $this->assertContains('throttle:api', app('router')->getMiddlewareGroups()['api']);
    }

    public function test_models_use_fillable_and_do_not_open_mass_assignment(): void
    {
        foreach (File::allFiles(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.$file->getBasename('.php');
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $model = new $class;
            $this->assertNotSame([], $model->getFillable(), $class.' has no fillable attributes.');
            $this->assertNotSame([], $model->getGuarded(), $class.' is unguarded.');
        }

        $this->assertContains('password', (new User)->getHidden());
    }

    public function test_audit_actions_cover_approval_payment_and_stock_changes(): void
    {
        $values = array_map(fn (AuditAction $action): string => $action->value, AuditAction::cases());

        foreach (['approved', 'rejected', 'cancelled', 'reversed', 'payment', 'stock_adjusted'] as $action) {
            $this->assertContains($action, $values);
        }
    }

    public function test_seeded_demo_matches_the_spec_and_journals_balance(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(5, Partner::query()->count());
        $this->assertLessThanOrEqual(10, Partner::query()->count());
        $this->assertSame(20, Product::query()->count());
        $this->assertSame(5, Supplier::query()->count());
        $this->assertTrue(ApprovalRequest::query()->where('status', 'pending')->exists());
        $this->assertTrue(ApprovalRequest::query()->where('status', 'approved')->exists());
        $this->assertTrue(ApprovalRequest::query()->where('status', 'rejected')->exists());

        $unbalanced = 0;
        foreach (JournalEntry::query()->with('lines')->get() as $entry) {
            $debit = '0.00';
            $credit = '0.00';
            foreach ($entry->lines as $line) {
                $debit = bcadd($debit, (string) $line->debit, 2);
                $credit = bcadd($credit, (string) $line->credit, 2);
            }
            if ($debit !== $credit) {
                $unbalanced++;
            }
        }

        $this->assertSame(0, $unbalanced);
    }

    private function actionChecksAuthorization(string $action): bool
    {
        if (! str_contains($action, '@')) {
            return false;
        }

        [$class, $method] = explode('@', $action, 2);
        if (! method_exists($class, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($class, $method);
        $body = $this->methodSource($reflection);
        if (preg_match_all('/\$this->([A-Za-z0-9_]+)\s*\(/', $body, $calls) === 1 || isset($calls[1])) {
            foreach ($calls[1] as $called) {
                if (method_exists($class, $called)) {
                    $body .= $this->methodSource(new ReflectionMethod($class, $called));
                }
            }
        }

        if (preg_match('/authorize\s*\(|->can\s*\(|Gate::|abort_unless\s*\(|PermissionName::/', $body) === 1) {
            return true;
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();
            if (! is_subclass_of($name, FormRequest::class) || ! method_exists($name, 'authorize')) {
                continue;
            }

            $authorize = $this->methodSource(new ReflectionMethod($name, 'authorize'));
            if (preg_match('/can\s*\(|Gate::|authorize\s*\(/', $authorize) === 1) {
                return true;
            }
        }

        return false;
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $file = file($method->getFileName() ?: '');
        if ($file === false) {
            return '';
        }

        return implode('', array_slice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
