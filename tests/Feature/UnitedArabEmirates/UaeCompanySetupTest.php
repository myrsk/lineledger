<?php

use App\Actions\Companies\CreateCompany;
use App\Enums\Country;
use App\Enums\OrganizationType;
use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\TaxAgency;
use App\Models\TaxCode;
use App\Models\User;
use App\Services\Currency\Providers\FrankfurterProvider;
use App\Services\Posting\InvoicePoster;
use App\Services\Reporting\InvoicePdfRenderer;
use App\Support\Contacts\AddressLines;
use App\Support\Currency;
use App\Support\Defaults\EmiratiDefaults;
use App\Support\Tax\TaxAuthorityCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| United Arab Emirates company setup and documents
|--------------------------------------------------------------------------
|
| A UAE organization: AED, Dubai time, a calendar fiscal year, VAT wording,
| the Federal Tax Authority with 5% / zero-rated / exempt / out-of-scope
| codes, 15-digit TRN checks, day-first invoices, and AED exchange rates.
|
*/

function uaeOnboardingUser(): User
{
    $user = User::factory()->create();
    $user->companies()->detach();
    $user->forceFill(['current_company_id' => null])->save();

    test()->actingAs($user);

    return $user;
}

/*
| Country helpers
*/

it('answers every jurisdiction question for the UAE', function () {
    $ae = Country::UnitedArabEmirates;

    expect(Country::from('AE'))->toBe($ae)
        ->and($ae->label())->toBe('United Arab Emirates')
        ->and($ae->flag())->toBe('🇦🇪')
        ->and($ae->defaultCurrencyCode())->toBe('AED')
        ->and($ae->defaultTimezone())->toBe('Asia/Dubai')
        ->and($ae->defaultTimezone('DU'))->toBe('Asia/Dubai')
        ->and($ae->regionForTimezone('Asia/Dubai'))->toBeNull()
        ->and($ae->regionLabel())->toBe('Emirate')
        ->and($ae->postalCodeLabel())->toBe('P.O. Box')
        ->and($ae->taxLabel())->toBe('VAT')
        ->and($ae->usesVat())->toBeTrue()
        ->and($ae->taxNumberLabel())->toBe('Tax Registration Number (TRN)')
        ->and($ae->taxNumberDocumentLabel())->toBe('TRN')
        ->and($ae->inputTaxLabel())->toBe('Input VAT')
        ->and($ae->paidInputTaxLabel())->toBe('Paid (Input VAT)')
        ->and($ae->defaultFiscalYearStartMonth())->toBe(1)
        ->and($ae->taxAgencyNameForRegion('DU'))->toBeNull()
        ->and($ae->companyRegistrationNumberLabel())->toBe('Trade licence number')
        ->and($ae->usesCompanyRegistrationNumber(OrganizationType::Corporation))->toBeTrue()
        ->and($ae->usesCompanyRegistrationNumber(OrganizationType::Charity))->toBeFalse()
        ->and($ae->cheque())->toBe('Cheque')
        ->and($ae->chequeLabel('checkbook'))->toBe('Chequebook view')
        ->and($ae->defaults())->toBeInstanceOf(EmiratiDefaults::class)
        ->and(array_keys($ae->regions()))->toBe(['AZ', 'AJ', 'DU', 'FU', 'RK', 'SH', 'UQ']);

    expect(Country::Canada->usesVat())->toBeFalse()
        ->and(Country::UnitedStates->usesVat())->toBeFalse()
        ->and(Country::UnitedKingdom->usesVat())->toBeTrue();
});

it('resolves .ae hosts to the UAE', function () {
    expect(Country::fromHost('books.example.ae'))->toBe(Country::UnitedArabEmirates)
        ->and(Country::fromHost('example.com'))->toBe(Country::UnitedStates);
});

it('normalizes and validates UAE Tax Registration Numbers', function (string $typed, ?string $stored, bool $valid) {
    $ae = Country::UnitedArabEmirates;

    expect($ae->normalizeTaxNumber($typed))->toBe($stored)
        ->and($ae->isValidTaxNumber($typed))->toBe($valid);
})->with([
    'fifteen digits' => ['100123456789003', '100123456789003', true],
    'grouped with dashes' => ['100-1234-5678-9003', '100123456789003', true],
    'spaced' => [' 100 1234 5678 9003 ', '100123456789003', true],
    'blank' => ['   ', null, true],
    'too short' => ['10012345678900', '10012345678900', false],
    'too long' => ['1001234567890031', '1001234567890031', false],
    'letters' => ['TRN100123456789', 'TRN100123456789', false],
]);

it('writes UAE dates day first', function () {
    expect(CarbonImmutable::create(2026, 3, 4)->format(Country::UnitedArabEmirates->shortDateFormat()))->toBe('04/03/2026');
});

it('prints the emirate name in a UAE address, unless it repeats the city', function () {
    expect(AddressLines::locality('Al Ain', 'AZ', null, 'AE', null))->toBe('Al Ain, Abu Dhabi')
        ->and(AddressLines::locality('Dubai', 'DU', null, 'AE', null))->toBe('Dubai')
        ->and(AddressLines::locality('Toronto', 'ON', 'M5V 1A1', 'CA', null))->toBe('Toronto, ON, M5V 1A1');
});

/*
| Currency and exchange rates
*/

it('offers AED as a two-decimal currency', function () {
    expect(Currency::isSelectable('AED'))->toBeTrue()
        ->and(Currency::symbol('AED'))->toBe('AED')
        ->and(Currency::name('AED'))->toBe('UAE Dirham')
        ->and(Currency::selectable())->toHaveKey('AED');
});

it('reads AED rates from the Frankfurter v2 API', function () {
    Http::fake([
        'api.frankfurter.dev/v2/rates*' => Http::response([
            ['date' => '2026-11-04', 'base' => 'AED', 'quote' => 'USD', 'rate' => 0.27229],
            ['date' => '2026-11-04', 'base' => 'AED', 'quote' => 'EUR', 'rate' => 0.25],
            ['date' => '2026-11-04', 'base' => 'AED', 'quote' => 'JPY', 'rate' => 41.6],
        ]),
    ]);

    $rates = (new FrankfurterProvider('https://api.frankfurter.dev/v2'))->rates('aed', ['usd', 'eur', 'jpy'], CarbonImmutable::create(2026, 11, 4));

    expect($rates)->toBe(['USD' => '0.27229', 'EUR' => '0.25', 'JPY' => '41.6']);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.frankfurter.dev/v2/rates?')
        && $request['base'] === 'AED'
        && $request['quotes'] === 'USD,EUR,JPY'
        && $request['date'] === '2026-11-04');
});

it('keeps speaking the v1 API when the base URL points at it', function () {
    Http::fake([
        'api.frankfurter.dev/v1/latest*' => Http::response(['base' => 'USD', 'date' => '2026-11-04', 'rates' => ['CAD' => 1.3712]]),
    ]);

    $rates = (new FrankfurterProvider('https://api.frankfurter.dev/v1'))->rates('USD', ['CAD']);

    expect($rates)->toBe(['CAD' => '1.3712']);

    Http::assertSent(fn (Request $request): bool => $request['symbols'] === 'CAD');
});

it('defaults the exchange-rate provider to the v2 API', function () {
    expect(config('services.exchange_rates.base_url'))->toBe('https://api.frankfurter.dev/v2');
});

/*
| Company creation
*/

it('creates a UAE company on AED, Dubai time and a calendar fiscal year', function () {
    $company = app(CreateCompany::class)->handle(User::factory()->create(), 'Creek Trading LLC', country: Country::UnitedArabEmirates, regionCode: 'DU');

    expect($company->currency_code)->toBe('AED')
        ->and($company->timezone)->toBe('Asia/Dubai')
        ->and($company->fiscal_year_start_month)->toBe(1)
        ->and($company->jurisdiction)->toBe(Country::UnitedArabEmirates);

    $agency = TaxAgency::withoutGlobalScopes()->where('company_id', $company->id)->sole();
    $codes = TaxCode::withoutGlobalScopes()->where('company_id', $company->id)->get()->keyBy('code');

    expect($agency->name)->toBe('Federal Tax Authority')
        ->and($agency->payableAccount->name)->toBe('VAT Payable')
        ->and($codes->keys()->sort()->values()->all())->toBe(['EX', 'OOS', 'SR', 'ZR'])
        ->and((int) $codes['SR']->rate_basis_points)->toBe(500)
        ->and($codes['SR']->is_recoverable)->toBeTrue()
        ->and($codes['ZR']->is_recoverable)->toBeTrue()
        ->and($codes['EX']->is_recoverable)->toBeFalse()
        ->and($codes['OOS']->is_recoverable)->toBeFalse()
        ->and($codes->pluck('agency_id')->unique()->all())->toBe([$agency->id]);
});

it('keeps Canada-, US- and UK-only features off for a UAE company', function () {
    $company = Company::factory()->forCountry(Country::UnitedArabEmirates, 'DU')->create();

    expect($company->usesGifi())->toBeFalse()
        ->and($company->filesT2125())->toBeFalse();
});

it('lists the Federal Tax Authority in the new-agency picker', function () {
    $company = Company::factory()->forCountry(Country::UnitedArabEmirates, 'DU')->create();

    expect(array_column(TaxAuthorityCatalog::forCompany($company), 'name'))->toBe(['Federal Tax Authority']);
});

/*
| Setup wizard
*/

it('suggests AED and a calendar fiscal year when the owner picks the UAE', function () {
    uaeOnboardingUser();

    Livewire::test('pages::welcome.setup-wizard')
        ->set('country', 'AE')
        ->assertSet('currency', 'AED')
        ->assertSet('fiscalYearStartMonth', 1);
});

it('prefills the UAE from Cloudflare geolocation', function () {
    uaeOnboardingUser();

    Livewire::withHeaders(['CF-IPCountry' => 'AE'])
        ->test('pages::welcome.setup-wizard')
        ->assertSet('country', 'AE')
        ->assertSet('currency', 'AED');
});

it('asks about VAT registration and checks the TRN in the wizard', function () {
    uaeOnboardingUser();

    Livewire::test('pages::welcome.setup-wizard')
        ->set('companyName', 'Bad TRN LLC')
        ->set('country', 'AE')
        ->set('region', 'DU')
        ->set('currency', 'AED')
        ->set('organizationType', 'corporation')
        ->set('orgCategory', 'corporation')
        ->set('chargesTax', true)
        ->set('step', 5)
        ->assertSee('Are you VAT-registered?')
        ->assertSee('Federal Tax Authority')
        ->assertSee('Tax Registration Number (TRN)')
        ->set('taxNumber', '12345')
        ->call('next')
        ->assertHasErrors(['taxNumber']);
});

it('creates a UAE company from the wizard with a normalized TRN', function () {
    $user = uaeOnboardingUser();

    Livewire::test('pages::welcome.setup-wizard')
        ->set('companyName', 'Marina Services LLC')
        ->set('country', 'AE')
        ->set('region', 'DU')
        ->set('currency', 'AED')
        ->set('organizationType', 'corporation')
        ->set('orgCategory', 'corporation')
        ->set('chargesTax', true)
        ->set('taxNumber', '100-1234-5678-9003')
        ->call('createCompany')
        ->assertHasNoErrors()
        ->assertRedirect();

    $company = Company::where('name', 'Marina Services LLC')->firstOrFail();

    expect($company->tax_number)->toBe('100123456789003')
        ->and($company->currency_code)->toBe('AED')
        ->and($company->jurisdiction)->toBe(Country::UnitedArabEmirates)
        ->and(TaxAgency::withoutGlobalScopes()->where('company_id', $company->id)->first()->name)->toBe('Federal Tax Authority')
        ->and($user->fresh()->current_company_id)->toBe($company->id);
});

/*
| Documents
*/

it('prints a UAE invoice in AED with day-first dates and the TRN', function () {
    $company = Company::factory()->forCountry(Country::UnitedArabEmirates, 'DU')->create([
        'currency_code' => 'AED',
        'tax_number' => '100123456789003',
    ]);
    app()->instance('current_company', $company);

    $customer = Contact::create(['display_name' => 'Customer FZE', 'is_customer' => true]);
    $vat = TaxCode::where('code', 'SR')->firstOrFail();

    $invoice = Invoice::create([
        'contact_id' => $customer->id,
        'invoice_no' => 'INV-AE-1',
        'invoice_date' => '2026-03-04',
        'due_date' => '2026-04-03',
    ]);
    $invoice->lines()->create([
        'account_id' => Account::where('code', '4000')->value('id'),
        'tax_code_id' => $vat->id,
        'description' => 'Consulting',
        'quantity' => '1',
        'unit_price_cents' => 100000,
        'line_subtotal_cents' => 100000,
        'line_tax_cents' => 5000,
        'line_total_cents' => 105000,
        'line_order' => 0,
    ]);
    app(InvoicePoster::class)->post($invoice);

    $renderer = app(InvoicePdfRenderer::class);
    $html = view('pdf.invoices.invoice', (new ReflectionMethod($renderer, 'data'))->invoke($renderer, $company, $invoice->fresh()))->render();

    expect($html)
        ->toContain('1,050.00')
        ->toContain('AED')
        ->toContain('04/03/2026')
        ->toContain('03/04/2026')
        ->toContain('TRN')
        ->toContain('100123456789003')
        ->not->toContain('$1,050.00')
        ->not->toContain('3/4/2026');

    app()->forgetInstance('current_company');
});
