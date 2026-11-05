<?php

namespace App\Support\Defaults;

use App\Enums\AccountSubtype;

/**
 * Default chart of accounts and lookups seeded on United Arab Emirates company
 * creation. Mirrors the shared starter set with UAE wording ("Current Account",
 * "VAT Payable"), the Federal Tax Authority as the VAT agency, and the UAE VAT
 * rates: standard 5%, zero-rated, exempt, and out of scope.
 *
 * Reverse-charge VAT (imports of goods and services) is not modelled; post it
 * with a journal entry.
 */
class EmiratiDefaults implements CompanyDefaults
{
    public function accounts(): array
    {
        return [
            // Assets
            ['code' => '1000', 'name' => 'Current Account', 'subtype' => AccountSubtype::Bank],
            ['code' => '1010', 'name' => 'Savings', 'subtype' => AccountSubtype::Bank],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'subtype' => AccountSubtype::AccountsReceivable, 'is_system' => true],
            ['code' => '1200', 'name' => 'Undeposited Funds', 'subtype' => AccountSubtype::UndepositedFunds, 'is_system' => true],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'subtype' => AccountSubtype::CurrentAsset],
            ['code' => '1400', 'name' => 'Inventory Asset', 'subtype' => AccountSubtype::Inventory, 'is_system' => true],
            ['code' => '1500', 'name' => 'Office Equipment', 'subtype' => AccountSubtype::FixedAsset],
            ['code' => '1510', 'name' => 'Accumulated Depreciation', 'subtype' => AccountSubtype::FixedAsset],

            // Liabilities
            ['code' => '2000', 'name' => 'Accounts Payable', 'subtype' => AccountSubtype::AccountsPayable, 'is_system' => true],
            ['code' => '2100', 'name' => 'Credit Card', 'subtype' => AccountSubtype::CreditCard],
            ['code' => '2200', 'name' => 'VAT Payable', 'subtype' => AccountSubtype::TaxPayable, 'is_system' => true],
            ['code' => '2300', 'name' => 'Employee Reimbursements Payable', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true],
            ['code' => '2700', 'name' => 'Bank Loan', 'subtype' => AccountSubtype::LongTermLiability, 'description' => 'Principal owing on bank loans. For loan payments, split principal to this account and interest to an interest expense account.'],

            // Equity
            ['code' => '3000', 'name' => 'Opening Balance Equity', 'subtype' => AccountSubtype::Equity],
            ['code' => '3100', 'name' => 'Owner Contributions', 'subtype' => AccountSubtype::Equity],
            ['code' => '3200', 'name' => 'Owner Draws', 'subtype' => AccountSubtype::Equity],
            ['code' => '3900', 'name' => 'Retained Earnings', 'subtype' => AccountSubtype::RetainedEarnings, 'is_system' => true],

            // Income
            ['code' => '4000', 'name' => 'Sales', 'subtype' => AccountSubtype::Income],
            ['code' => '4100', 'name' => 'Services', 'subtype' => AccountSubtype::Income],
            ['code' => '4900', 'name' => 'Other Income', 'subtype' => AccountSubtype::OtherIncome],

            // Expenses
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'subtype' => AccountSubtype::CostOfGoodsSold, 'is_system' => true],
            ['code' => '6000', 'name' => 'Advertising', 'subtype' => AccountSubtype::Expense],
            ['code' => '6010', 'name' => 'Bank Charges', 'subtype' => AccountSubtype::Expense],
            ['code' => '6020', 'name' => 'Insurance', 'subtype' => AccountSubtype::Expense],
            ['code' => '6030', 'name' => 'Meals & Entertainment', 'subtype' => AccountSubtype::Expense],
            ['code' => '6040', 'name' => 'Office Supplies', 'subtype' => AccountSubtype::Expense],
            ['code' => '6050', 'name' => 'Professional Fees', 'subtype' => AccountSubtype::Expense],
            ['code' => '6060', 'name' => 'Rent', 'subtype' => AccountSubtype::Expense],
            ['code' => '6070', 'name' => 'Software & Subscriptions', 'subtype' => AccountSubtype::Expense],
            ['code' => '6080', 'name' => 'Telephone & Internet', 'subtype' => AccountSubtype::Expense],
            ['code' => '6090', 'name' => 'Travel', 'subtype' => AccountSubtype::Expense],
            ['code' => '6100', 'name' => 'Utilities', 'subtype' => AccountSubtype::Expense],
            ['code' => '6110', 'name' => 'Licence & Government Fees', 'subtype' => AccountSubtype::Expense, 'description' => 'Trade licence renewals, visa and immigration fees, and other government charges.'],
            ['code' => '6900', 'name' => 'Miscellaneous Expense', 'subtype' => AccountSubtype::OtherExpense],
        ];
    }

    public function coreAccounts(): array
    {
        return [
            ['code' => '1000', 'name' => 'Current Account', 'subtype' => AccountSubtype::Bank],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'subtype' => AccountSubtype::AccountsReceivable, 'is_system' => true],
            ['code' => '1200', 'name' => 'Undeposited Funds', 'subtype' => AccountSubtype::UndepositedFunds, 'is_system' => true],
            ['code' => '1400', 'name' => 'Inventory Asset', 'subtype' => AccountSubtype::Inventory, 'is_system' => true],
            ['code' => '2000', 'name' => 'Accounts Payable', 'subtype' => AccountSubtype::AccountsPayable, 'is_system' => true],
            ['code' => '2200', 'name' => 'VAT Payable', 'subtype' => AccountSubtype::TaxPayable, 'is_system' => true],
            ['code' => '2300', 'name' => 'Employee Reimbursements Payable', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true],
            ['code' => '3000', 'name' => 'Opening Balance Equity', 'subtype' => AccountSubtype::Equity],
            ['code' => '3900', 'name' => 'Retained Earnings', 'subtype' => AccountSubtype::RetainedEarnings, 'is_system' => true],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'subtype' => AccountSubtype::CostOfGoodsSold, 'is_system' => true],
        ];
    }

    public function paymentMethods(): array
    {
        return [
            ['name' => 'Cash', 'is_cheque' => false],
            ['name' => 'Cheque', 'is_cheque' => true],
            ['name' => 'Bank Transfer', 'is_cheque' => false],
            ['name' => 'Wire', 'is_cheque' => false],
            ['name' => 'Credit card', 'is_cheque' => false, 'is_card' => true],
        ];
    }

    public function taxAgencies(): array
    {
        return [
            ['name' => 'Federal Tax Authority'],
        ];
    }

    public function taxCodes(): array
    {
        return [
            ['code' => 'SR', 'name' => 'Standard-rated VAT (5%)', 'rate_basis_points' => 500, 'recoverable' => true],
            ['code' => 'ZR', 'name' => 'Zero-rated (0%)', 'rate_basis_points' => 0, 'recoverable' => true],
            ['code' => 'EX', 'name' => 'Exempt', 'rate_basis_points' => 0, 'recoverable' => false],
            ['code' => 'OOS', 'name' => 'Out of scope', 'rate_basis_points' => 0, 'recoverable' => false],
        ];
    }
}
